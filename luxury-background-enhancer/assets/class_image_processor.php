<?php
/**
 * Classe responsável por adicionar o botão "Gerar Fundo" na edição do produto
 * e processar a imagem com fundo de luxo via AJAX.
 */

class LUXBG_Image_Processor {
    public function __construct() {
        add_action('add_meta_boxes', array($this, 'add_metabox'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_luxbg_generate_background', array($this, 'process_image'));
    }

    public function add_metabox() {
        add_meta_box(
            'luxbg_metabox',
            'Criador de Fundo Luxuoso',
            array($this, 'render_metabox'),
            'product',
            'side',
            'default'
        );
    }

    public function render_metabox($post) {
        echo '<p>Escolha um estilo de fundo luxuoso:</p>';
        echo '<select id="luxbg_style" style="width:100%; margin-bottom:10px;">
                <option value="luxury loft with natural light">Loft moderno</option>
                <option value="Parisian living room, elegant, sunlight">Sala parisiense</option>
                <option value="minimalist Scandinavian studio">Estúdio escandinavo</option>
                <option value="bright luxury bedroom with soft shadows">Quarto natural</option>
              </select>';
        echo '<button type="button" class="button button-primary" id="luxbg-generate">Gerar Fundo</button>';
        echo '<p id="luxbg-status" style="margin-top:10px;"></p>';
    }

    public function enqueue_scripts($hook) {
        if ($hook !== 'post.php') return;

        wp_enqueue_script('luxbg-admin', LUXBG_PLUGIN_URL . 'assets/admin-ui.js', array('jquery'), null, true);
        wp_localize_script('luxbg-admin', 'luxbg_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('luxbg_nonce')
        ));
    }

    public function process_image() {
        check_ajax_referer('luxbg_nonce', 'nonce');

        $product_id = absint($_POST['product_id']);
        $style = sanitize_text_field($_POST['style']);

        if (!current_user_can('edit_post', $product_id)) {
            wp_send_json_error('Permissão negada.');
        }

        $thumbnail_id = get_post_thumbnail_id($product_id);
        $image_url = wp_get_attachment_url($thumbnail_id);

        if (!$image_url) {
            wp_send_json_error('Produto sem imagem destacada.');
        }

        require_once LUXBG_PLUGIN_DIR . 'includes/class-api-connector.php';
        $new_image_url = LUXBG_API_Connector::generate_image($image_url, $style);

        if (!$new_image_url) {
            wp_send_json_error('Erro ao gerar nova imagem.');
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $tmp = download_url($new_image_url);
        if (is_wp_error($tmp)) {
            wp_send_json_error('Erro ao baixar a imagem gerada.');
        }

        $filename = 'luxbg-' . time() . '.jpg';
        $file_array = array(
            'name'     => $filename,
            'tmp_name' => $tmp
        );

        $attachment_id = media_handle_sideload($file_array, $product_id);

        if (is_wp_error($attachment_id)) {
            @unlink($tmp);
            wp_send_json_error('Erro ao salvar a imagem no WordPress.');
        }

        $editor = wp_get_image_editor(get_attached_file($attachment_id));
        if (!is_wp_error($editor)) {
            $editor->resize(1024, 423, true);
            $editor->save(get_attached_file($attachment_id));
        }

        $old_thumb_id = get_post_thumbnail_id($product_id);
        if ($old_thumb_id) {
            $gallery = get_post_meta($product_id, '_product_image_gallery', true);
            $gallery_ids = $gallery ? explode(',', $gallery) : [];
            if (!in_array($old_thumb_id, $gallery_ids)) {
                $gallery_ids[] = $old_thumb_id;
                update_post_meta($product_id, '_product_image_gallery', implode(',', $gallery_ids));
            }
        }

        set_post_thumbnail($product_id, $attachment_id);

        wp_send_json_success(array(
            'nova_imagem' => wp_get_attachment_url($attachment_id)
        ));
    }
}
