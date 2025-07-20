<?php
namespace LuxuryBg;

class Admin_Panel {
    private $image_processor;
    private $api_connector;
    private $status_tracker;
    private $error_logger;

    public function __construct() {
        $this->image_processor = new Image_Processor();
        $this->api_connector   = new API_Connector();
        $this->status_tracker  = new Status_Tracker();
        $this->error_logger    = new Error_Logger();

        add_action( 'add_meta_boxes', [ $this, 'register_meta_box' ] );
        add_action( 'admin_post_luxbg_generate', [ $this, 'handle_generation' ] );
        add_action( 'admin_post_luxbg_fix_images', [ $this, 'handle_fix_images' ] );
        add_action( 'wp_ajax_luxbg_generate_ajax', [ $this, 'handle_generation_ajax' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_filter( 'manage_product_posts_columns', [ $this, 'add_status_column' ] );
        add_action( 'manage_product_posts_custom_column', [ $this, 'render_status_column' ], 10, 2 );
        add_action( 'admin_notices', [ $this, 'display_notices' ] );
        add_action( 'wp_ajax_luxbg_test_api', [ $this, 'ajax_test_api' ] );
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
                <input type="hidden" name="post_id" id="luxbg_post_id" value="<?php echo esc_attr( $post->ID ); ?>" />
                <button type="button" id="luxbg-generate-button" class="button button-primary" data-post="<?php echo esc_attr( $post->ID ); ?>">Gerar Fundo</button>
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
        if ( ! $thumbnail_id ) {
            $this->status_tracker->set_status( $product_id, 'Erro' );
            $this->error_logger->log( 'image', 'Imagem não encontrada para o produto ' . $product_id );
            $this->redirect_with_message( $product_id, 'image_not_found' );
        }

        $image_url  = wp_get_attachment_url( $thumbnail_id );
        $image_path = get_attached_file( $thumbnail_id );

        if ( ! $image_url || ! file_exists( $image_path ) ) {
            $this->status_tracker->set_status( $product_id, 'Erro' );
            $this->error_logger->log( 'image', 'Imagem não encontrada: ' . $thumbnail_id );
            $this->redirect_with_message( $product_id, 'image_not_found' );
        }

        $head = wp_remote_head( $image_url );
        if ( is_wp_error( $head ) ) {
            error_log( '[luxbg] HEAD request error: ' . $head->get_error_message() );
            error_log( '[luxbg] Warning: continuing despite failed HEAD request.' );
        } else {
            $code = wp_remote_retrieve_response_code( $head );
            error_log( '[luxbg] HEAD status: ' . $code );
            if ( $code >= 400 ) {
                error_log( '[luxbg] Warning: received HTTP ' . $code . ' for HEAD request, continuing anyway.' );
            }
        }

        if ( ! $this->image_processor->is_white_background( $image_path ) ) {
            $this->status_tracker->set_status( $product_id, 'Rejeitado - fundo não branco' );
            $this->error_logger->log( 'background_check', 'Imagem sem fundo branco: ' . $image_path );
            $this->redirect_with_message( $product_id, 'image_private' );
        }

        $generated = $this->api_connector->generate_luxury_image( $image_url, $prompt ?: $style );
        if ( is_wp_error( $generated ) ) {
            $this->status_tracker->set_status( $product_id, 'Erro' );
            $this->error_logger->log( 'api_generate', $generated->get_error_message() );
            $this->redirect_with_message( $product_id, $generated->get_error_code() );
        }

        $upload = wp_upload_bits( 'luxbg-' . uniqid() . '.jpg', null, $generated );
        if ( ! empty( $upload['error'] ) ) {
            $this->status_tracker->set_status( $product_id, 'Erro upload' );
            $this->error_logger->log( 'upload', $upload['error'] );
            $this->redirect_with_message( $product_id, 'upload_error' );
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

    public function handle_generation_ajax() {
        check_ajax_referer( 'luxbg_generate', '_ajax_nonce' );

        if ( ! current_user_can( 'edit_products' ) ) {
            wp_send_json_error( 'Sem permissões' );
        }

        $product_id = absint( $_POST['post_id'] ?? 0 );
        $style      = sanitize_text_field( $_POST['style'] ?? '' );
        $prompt     = sanitize_text_field( $_POST['prompt'] ?? '' );

        $this->status_tracker->set_status( $product_id, 'Processando' );

        $thumbnail_id = get_post_thumbnail_id( $product_id );
        if ( ! $thumbnail_id ) {
            $this->status_tracker->set_status( $product_id, 'Erro' );
            $this->error_logger->log( 'image', 'Imagem não encontrada para o produto ' . $product_id );
            wp_send_json_error( $this->friendly_message( 'image_not_found' ) );
        }

        $image_url  = wp_get_attachment_url( $thumbnail_id );
        $image_path = get_attached_file( $thumbnail_id );

        if ( ! $image_url || ! file_exists( $image_path ) ) {
            $this->status_tracker->set_status( $product_id, 'Erro' );
            $this->error_logger->log( 'image', 'Imagem não encontrada: ' . $thumbnail_id );
            wp_send_json_error( $this->friendly_message( 'image_not_found' ) );
        }

        $head = wp_remote_head( $image_url );
        if ( is_wp_error( $head ) ) {
            error_log( '[luxbg] HEAD request error: ' . $head->get_error_message() );
            error_log( '[luxbg] Warning: continuing despite failed HEAD request.' );
        } else {
            $code = wp_remote_retrieve_response_code( $head );
            error_log( '[luxbg] HEAD status: ' . $code );
            if ( $code >= 400 ) {
                error_log( '[luxbg] Warning: received HTTP ' . $code . ' for HEAD request, continuing anyway.' );
            }
        }

        if ( ! $this->image_processor->is_white_background( $image_path ) ) {
            $this->status_tracker->set_status( $product_id, 'Rejeitado - fundo não branco' );
            $this->error_logger->log( 'background_check', 'Imagem sem fundo branco: ' . $image_path );
            wp_send_json_error( $this->friendly_message( 'image_private' ) );
        }

        $generated = $this->api_connector->generate_luxury_image( $image_url, $prompt ?: $style );
        if ( is_wp_error( $generated ) ) {
            $this->status_tracker->set_status( $product_id, 'Erro' );
            $this->error_logger->log( 'api_generate', $generated->get_error_message() );
            wp_send_json_error( $this->friendly_message( $generated->get_error_code() ) );
        }

        $upload = wp_upload_bits( 'luxbg-' . uniqid() . '.jpg', null, $generated );
        if ( ! empty( $upload['error'] ) ) {
            $this->status_tracker->set_status( $product_id, 'Erro upload' );
            $this->error_logger->log( 'upload', $upload['error'] );
            wp_send_json_error( $this->friendly_message( 'upload_error' ) );
        }

        $attachment = [
            'post_mime_type' => 'image/jpeg',
            'post_title'     => 'LuxBG ' . $product_id,
            'post_content'   => '',
            'post_status'    => 'inherit',
        ];
        $attach_id = wp_insert_attachment( $attachment, $upload['file'], $product_id );
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attach_data = wp_generate_attachment_metadata( $attach_id, $upload['file'] );
        wp_update_attachment_metadata( $attach_id, $attach_data );

        $this->image_processor->resize_image( $attach_id );
        $this->status_tracker->save_generated_image( $product_id, $attach_id );

        add_post_meta( $product_id, '_product_image_gallery', $thumbnail_id );
        set_post_thumbnail( $product_id, $attach_id );

        $this->status_tracker->set_status( $product_id, 'Processado' );

        wp_send_json_success();
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

        $info = getimagesize( $path );
        if ( $info && $info[0] == 1024 && $info[1] == 423 ) {
            wp_redirect( get_edit_post_link( $product_id, 'url' ) );
            exit;
        }

        $upload_dir = wp_upload_dir();
        $filename   = wp_unique_filename( $upload_dir['path'], 'luxbg-fixed-' . uniqid() . '.jpg' );
        $new_path   = trailingslashit( $upload_dir['path'] ) . $filename;

        $this->image_processor->resize_image( $thumbnail_id, $new_path );
        if ( ! file_exists( $new_path ) ) {
            $this->error_logger->log( 'save_image', 'Failed to resize image' );
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
        wp_enqueue_style( 'luxbg-admin', LUXBG_PLUGIN_URL . 'assets/style.css' );

        wp_enqueue_script( 'luxbg-admin', LUXBG_PLUGIN_URL . 'assets/admin.js', [ 'jquery' ], null, true );

        wp_enqueue_script( 'luxbg-admin-ui', LUXBG_PLUGIN_URL . 'assets/admin_ui.js', [ 'jquery' ], null, true );

        wp_localize_script( 'luxbg-admin', 'luxbg_ajax', [
            'url'   => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'luxbg_generate' ),
        ] );

        wp_localize_script( 'luxbg-admin-ui', 'luxbg_admin_ui', [
            'ajax_url'   => admin_url( 'admin-ajax.php' ),
            'test_nonce' => wp_create_nonce( 'luxbg_test_api' ),
        ] );
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
        register_setting( 'luxbg_settings', 'luxbg_endpoint' );
    }

    public function render_settings_page() {
        $logs = $this->error_logger->get_logs();
        include LUXBG_PLUGIN_DIR . 'templates/admin-settings.php';
    }

    private function redirect_with_message( $product_id, $code ) {
        $url = add_query_arg( 'luxbg_msg', $code, get_edit_post_link( $product_id, 'url' ) );
        wp_safe_redirect( $url );
        exit;
    }

    private function friendly_message( $code ) {
        $map = [
            'image_not_found'      => 'Imagem não encontrada.',
            'image_private'        => 'A imagem destacada não é acessível.',
            'luxbg_request_error'  => 'Erro de comunicação com a API.',
            'luxbg_empty_response' => 'Resposta vazia da API.',
            'luxbg_http_403'       => 'Erro 403 - verifique sua chave de API.',
            'luxbg_invalid_format' => 'Formato inválido.',
            'luxbg_prompt_rejected'=> 'Prompt não aceito.',
            'luxbg_api_error'      => 'Erro ao gerar imagem.',
            'luxbg_no_image'       => 'Imagem não encontrada na resposta.',
            'luxbg_no_api_key'     => 'Chave da API não configurada.',
            'upload_error'         => 'Erro ao salvar imagem.',
        ];
        if ( isset( $map[ $code ] ) ) {
            return $map[ $code ];
        }

        if ( strpos( $code, 'luxbg_http_' ) === 0 ) {
            $status = str_replace( 'luxbg_http_', '', $code );
            return 'Erro ' . $status . ' ao se comunicar com a API.';
        }

        return 'Erro desconhecido';
    }

    public function display_notices() {
        if ( empty( $_GET['luxbg_msg'] ) ) {
            return;
        }
        $code = sanitize_text_field( wp_unslash( $_GET['luxbg_msg'] ) );
        $message = $this->friendly_message( $code );
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
    }

    public function ajax_test_api() {
        check_ajax_referer( 'luxbg_test_api', '_ajax_nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Sem permissões' );
        }

        $result = $this->api_connector->test_connection();

        if ( is_wp_error( $result ) ) {
            $this->error_logger->log( 'test_api', $result->get_error_message() );
            wp_send_json_error( $result->get_error_message() );
        }

        $this->error_logger->log( 'test_api', 'success' );
        wp_send_json_success( $result );
    }
}
