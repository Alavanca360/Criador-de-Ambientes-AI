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

        // Aqui chamaremos futuramente o conector da API
        $new_image_url = apply_filters('luxbg_generate_image', $image_url, $style);

        if (!$new_image_url) {
            wp_send_json_error('Erro ao gerar nova imagem.');
        }

        // Baixar, redimensionar e substituir virá depois
        wp_send_json_success(array('nova_imagem' => $new_image_url));
    }
}
