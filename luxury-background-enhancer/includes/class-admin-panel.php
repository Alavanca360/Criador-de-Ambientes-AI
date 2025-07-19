<?php
namespace LuxuryBg;

class Admin_Panel {
    private $image_processor;
    private $api_connector;
    private $status_tracker;

    public function __construct() {
        $this->image_processor = new Image_Processor();
        $this->api_connector   = new API_Connector();
        $this->status_tracker  = new Status_Tracker();

        add_action( 'add_meta_boxes', [ $this, 'register_meta_box' ] );
        add_action( 'admin_post_luxbg_generate', [ $this, 'handle_generation' ] );
        add_action( 'admin_post_luxbg_fix_images', [ $this, 'handle_fix_images' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_filter( 'manage_product_posts_columns', [ $this, 'add_status_column' ] );
        add_action( 'manage_product_posts_custom_column', [ $this, 'render_status_column' ], 10, 2 );
    }

    public function register_meta_box() {
        add_meta_box(
            'luxbg-meta-box',
            'Criador de Ambientes AI',
            [ $this, 'render_meta_box' ],
            'product',
            'side'
        );
    }

    public function render_meta_box( $post ) {
        $styles = [
            'Loft moderno',
            'Sala parisiense',
            'Estúdio escandinavo',
            'Quarto natural',
            'Cozinha dourada',
        ];
        $status = $this->status_tracker->get_status( $post->ID );
        ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <p>
                <label for="luxbg_style">Estilo:</label>
                <select name="luxbg_style" id="luxbg_style">
                    <?php foreach ( $styles as $style ) : ?>
                        <option value="<?php echo esc_attr( $style ); ?>"><?php echo esc_html( $style ); ?></option>
                    <?php endforeach; ?>
                </select>
            </p>
            <p>
                <label for="luxbg_prompt">Prompt personalizado:</label>
                <textarea name="luxbg_prompt" id="luxbg_prompt" rows="3"></textarea>
            </p>
            <p>
                <input type="hidden" name="action" value="luxbg_generate" />
                <input type="hidden" name="post_id" value="<?php echo esc_attr( $post->ID ); ?>" />
                <?php submit_button( 'Gerar Fundo', 'primary', 'submit', false ); ?>
            </p>
        </form>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="luxbg_fix_images" />
            <input type="hidden" name="post_id" value="<?php echo esc_attr( $post->ID ); ?>" />
            <?php submit_button( 'Fix Images', 'secondary', 'submit', false ); ?>
        </form>
        <p>Status atual: <strong><?php echo esc_html( $status ? $status : 'Aguardando' ); ?></strong></p>
        <?php
    }

    public function handle_generation() {
        if ( ! current_user_can( 'edit_products' ) ) {
            wp_die( 'Sem permissões' );
        }

        $product_id = absint( $_POST['post_id'] ?? 0 );
        $style      = sanitize_text_field( $_POST['luxbg_style'] ?? '' );
        $prompt     = sanitize_text_field( $_POST['luxbg_prompt'] ?? '' );

        $this->status_tracker->set_status( $product_id, 'Processando' );

        $thumbnail_id = get_post_thumbnail_id( $product_id );
        $image_path = get_attached_file( $thumbnail_id );

        if ( ! $this->image_processor->is_white_background( $image_path ) ) {
            $this->status_tracker->set_status( $product_id, 'Rejeitado - fundo não branco' );
            wp_redirect( get_edit_post_link( $product_id, 'url' ) );
            exit;
        }

        $generated = $this->api_connector->generate_image( $image_path, $prompt ?: $style );
        if ( is_wp_error( $generated ) ) {
            $this->status_tracker->set_status( $product_id, 'Erro' );
            wp_redirect( get_edit_post_link( $product_id, 'url' ) );
            exit;
        }

        $upload = wp_upload_bits( 'luxbg-' . uniqid() . '.jpg', null, $generated );
        if ( ! empty( $upload['error'] ) ) {
            $this->status_tracker->set_status( $product_id, 'Erro upload' );
            wp_redirect( get_edit_post_link( $product_id, 'url' ) );
            exit;
        }

        $attachment = [
            'post_mime_type' => 'image/jpeg',
            'post_title'     => 'LuxBG ' . $product_id,
            'post_content'   => '',
            'post_status'    => 'inherit',
        ];
        $attach_id = wp_insert_attachment( $attachment, $upload['file'], $product_id );
        require_once( ABSPATH . 'wp-admin/includes/image.php' );
        $attach_data = wp_generate_attachment_metadata( $attach_id, $upload['file'] );
        wp_update_attachment_metadata( $attach_id, $attach_data );

        $this->image_processor->resize_image( $attach_id );
        $this->status_tracker->save_generated_image( $product_id, $attach_id );

        add_post_meta( $product_id, '_product_image_gallery', $thumbnail_id );
        set_post_thumbnail( $product_id, $attach_id );

        $this->status_tracker->set_status( $product_id, 'Processado' );

        wp_redirect( get_edit_post_link( $product_id, 'url' ) );
        exit;
    }

    public function handle_fix_images() {
        if ( ! current_user_can( 'edit_products' ) ) {
            wp_die( 'Sem permissões' );
        }

        $product_id   = absint( $_POST['post_id'] ?? 0 );
        $thumbnail_id = get_post_thumbnail_id( $product_id );
        if ( ! $thumbnail_id ) {
            wp_redirect( get_edit_post_link( $product_id, 'url' ) );
            exit;
        }

        $path   = get_attached_file( $thumbnail_id );
        $editor = wp_get_image_editor( $path );
        if ( is_wp_error( $editor ) ) {
            wp_redirect( get_edit_post_link( $product_id, 'url' ) );
            exit;
        }

        $size = $editor->get_size();
        if ( $size['width'] == 1024 && $size['height'] == 423 ) {
            wp_redirect( get_edit_post_link( $product_id, 'url' ) );
            exit;
        }

        $upload_dir = wp_upload_dir();
        $filename   = wp_unique_filename( $upload_dir['path'], 'luxbg-fixed-' . uniqid() . '.jpg' );
        $new_path   = trailingslashit( $upload_dir['path'] ) . $filename;

        $editor->resize( 1024, 423, true );
        $saved = $editor->save( $new_path );
        if ( is_wp_error( $saved ) ) {
            wp_redirect( get_edit_post_link( $product_id, 'url' ) );
            exit;
        }

        $attachment = [
            'post_mime_type' => 'image/jpeg',
            'post_title'     => 'LuxBG Fixed ' . $product_id,
            'post_content'   => '',
            'post_status'    => 'inherit',
        ];
        $attach_id = wp_insert_attachment( $attachment, $new_path, $product_id );
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attach_data = wp_generate_attachment_metadata( $attach_id, $new_path );
        wp_update_attachment_metadata( $attach_id, $attach_data );

        add_post_meta( $product_id, '_product_image_gallery', $thumbnail_id );
        set_post_thumbnail( $product_id, $attach_id );

        wp_redirect( get_edit_post_link( $product_id, 'url' ) );
        exit;
    }

    public function enqueue_assets() {
        wp_enqueue_style( 'luxbg-admin', plugins_url( '../assets/style.css', __FILE__ ) );
    }

    public function add_status_column( $columns ) {
        $columns['luxbg_status'] = 'Status IA';
        return $columns;
    }

    public function render_status_column( $column, $post_id ) {
        if ( 'luxbg_status' === $column ) {
            $status = $this->status_tracker->get_status( $post_id );
            if ( ! $status ) {
                $status = 'Aguardando';
            }
            echo esc_html( $status );
        }
    }

    public function add_settings_page() {
        add_options_page(
            'Criador de Ambientes AI',
            'Criador de Ambientes AI',
            'manage_options',
            'luxbg-settings',
            [ $this, 'render_settings_page' ]
        );
    }

    public function register_settings() {
        register_setting( 'luxbg_settings', 'luxbg_api_key' );
    }

    public function render_settings_page() {
        include LUXBG_PLUGIN_DIR . 'templates/admin-settings.php';
    }
}
