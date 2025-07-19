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


 vx2iej-codex/add-comprehensive-error-handling-to-plugin
        }

        $status = wp_remote_retrieve_response_code( $response );
        $body   = wp_remote_retrieve_body( $response );

        error_log( '[luxbg] Response code: ' . $status );
        error_log( '[luxbg] Response body: ' . $body );

        if ( $status >= 400 ) {
            error_log( '[luxbg] HTTP error ' . $status );
            return new \WP_Error( 'luxbg_http_' . $status, 'HTTP error ' . $status );
        }

        if ( empty( $body ) ) {
            return new \WP_Error( 'luxbg_empty_response', 'Resposta vazia da API' );
        }


        }

        $status = wp_remote_retrieve_response_code( $response );
        $body   = wp_remote_retrieve_body( $response );

        error_log( '[luxbg] Response code: ' . $status );
        error_log( '[luxbg] Response body: ' . $body );

        if ( empty( $body ) ) {
            return new \WP_Error( 'luxbg_empty_response', 'Resposta vazia da API' );
        }

 main
        $data = json_decode( $body, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new \WP_Error( 'luxbg_invalid_format', 'Formato inválido' );
        }

        if ( $status === 403 ) {
            return new \WP_Error( 'luxbg_http_403', 'Erro 403' );
        }

        // Handle API errors first
        if ( isset( $data['error'] ) ) {
            if ( stripos( $data['error'], 'prompt' ) !== false ) {
                return new \WP_Error( 'luxbg_prompt_rejected', $data['error'] );
            }
            return new \WP_Error( 'luxbg_api_error', $data['error'] );
        }

        // The API can return the image in different fields
        $b64 = $data['image_b64'] ?? $data['image_base64'] ?? '';
        if ( ! empty( $b64 ) ) {
            return base64_decode( $b64 );
        }

        if ( ! empty( $data['result_url'] ) ) {
vx2iej-codex/add-comprehensive-error-handling-to-plugin
            $url          = esc_url_raw( $data['result_url'] );
            error_log( '[luxbg] Fetching result url: ' . $url );
            $img_response = wp_remote_get( $url );

            error_log( '[luxbg] Fetching result url: ' . $data['result_url'] );
            $img_response = wp_remote_get( esc_url_raw( $data['result_url'] ) );
 main
            if ( is_wp_error( $img_response ) ) {
                error_log( '[luxbg] Error fetching result url: ' . $img_response->get_error_message() );
                return $img_response;
            }
            $img_status = wp_remote_retrieve_response_code( $img_response );
            if ( $img_status >= 400 ) {
                error_log( '[luxbg] Image fetch HTTP error: ' . $img_status );
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
}
