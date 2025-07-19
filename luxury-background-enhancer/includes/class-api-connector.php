<?php
namespace LuxuryBg;

class API_Connector {
    private $endpoint = 'https://sdk.photoroom.com/v1/segment';
    private $api_key;

    public function __construct() {
        $this->api_key = get_option( 'luxbg_api_key', '' );
    }

    public function generate_image( $image_path, $prompt ) {
        $args = [
            'headers' => [
                'x-api-key'    => $this->api_key,
                'Content-Type' => 'application/json',
            ],
            'body'    => wp_json_encode([
                'image_file_b64' => base64_encode( file_get_contents( $image_path ) ),
                'prompt'         => $prompt,
            ]),
            'timeout' => 60,
        ];

        $response = wp_remote_post( $this->endpoint, $args );
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $status = wp_remote_retrieve_response_code( $response );
        $body   = wp_remote_retrieve_body( $response );

        if ( $status >= 400 ) {
            return new \WP_Error( 'luxbg_http_' . $status, 'HTTP error ' . $status );
        }

        if ( empty( $body ) ) {
            return new \WP_Error( 'luxbg_empty_response', 'Resposta vazia da API' );
        }

        $data = json_decode( $body, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new \WP_Error( 'luxbg_invalid_format', 'Formato inválido' );
        }

        if ( isset( $data['error'] ) ) {
            if ( stripos( $data['error'], 'prompt' ) !== false ) {
                return new \WP_Error( 'luxbg_prompt_rejected', $data['error'] );
            }
            return new \WP_Error( 'luxbg_api_error', $data['error'] );
        }

        $b64 = $data['image_b64'] ?? $data['image_base64'] ?? '';
        if ( ! empty( $b64 ) ) {
            return base64_decode( $b64 );
        }

        if ( ! empty( $data['result_url'] ) ) {
            $img_response = wp_remote_get( esc_url_raw( $data['result_url'] ) );
            if ( is_wp_error( $img_response ) ) {
                return $img_response;
            }
            $img_status = wp_remote_retrieve_response_code( $img_response );
            if ( $img_status >= 400 ) {
                return new \WP_Error( 'luxbg_http_' . $img_status, 'HTTP error ' . $img_status );
            }
            $img_body = wp_remote_retrieve_body( $img_response );
            if ( empty( $img_body ) ) {
                return new \WP_Error( 'luxbg_empty_response', 'Resposta vazia da API' );
            }
            return $img_body;
        }

        return new \WP_Error( 'luxbg_no_image', 'No image returned from API' );
    }

    public function test_connection() {
        if ( empty( $this->api_key ) ) {
            return new \WP_Error( 'luxbg_no_api_key', 'Chave da API não configurada.' );
        }

        $args = [
            'headers' => [
                'x-api-key'    => $this->api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'image_url' => 'https://images.unsplash.com/photo-1581291519195-ef11498d1cf5?auto=format&w=600',
                'prompt'    => 'luxury interior',
            ]),
            'timeout' => 60,
        ];

        $response = wp_remote_post( $this->endpoint, $args );
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $status = wp_remote_retrieve_response_code( $response );
        $body   = wp_remote_retrieve_body( $response );

        if ( $status >= 400 ) {
            return new \WP_Error( 'luxbg_http_' . $status, $body ?: 'HTTP error ' . $status );
        }

        if ( empty( $body ) ) {
            return new \WP_Error( 'luxbg_empty_response', 'Resposta vazia da API' );
        }

        $data = json_decode( $body, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new \WP_Error( 'luxbg_invalid_format', 'Formato inválido' );
        }

        $has_image = ! empty( $data['image_b64'] ) || ! empty( $data['image_base64'] ) || ! empty( $data['result_url'] );
        if ( ! $has_image ) {
            return new \WP_Error( 'luxbg_no_image', 'Nenhuma imagem retornada' );
        }

        return 'Conexão bem-sucedida';
    }
}
