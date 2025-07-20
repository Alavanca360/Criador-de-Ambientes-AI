<?php
namespace LuxuryBg;

class API_Connector {
    // Endpoint antigo que utilizava o envio da imagem em base64. Mantido por compatibilidade
    // mas o novo fluxo utiliza o endpoint /v1/replace via URL.
    private $endpoint;
    private $api_key;

    public function __construct() {
        $this->api_key  = get_option( 'luxbg_api_key', '' );
        $this->endpoint = get_option( 'luxbg_endpoint', 'https://sdk.photoroom.com/v1/replace' );
    }

    /**
     * Envia a imagem para o PhotoRoom utilizando o endpoint /v1/replace.
     * Utiliza a URL da imagem em vez de enviar o arquivo em base64 para
     * evitar erros do tipo "Please provide an image".
     *
     * @param string $image_url URL da imagem original
     * @param string $prompt    Background prompt
     * @return string|\WP_Error  Dados binários da imagem ou erro
     */
    public function generate_luxury_image( $image_url, $prompt = 'luxury interior' ) {
        if ( empty( $this->api_key ) ) {
            return new \WP_Error( 'luxbg_no_api_key', 'Chave da API não configurada.' );
        }

        if ( empty( $image_url ) ) {
            $image_url = 'https://images.unsplash.com/photo-1683009427619-a1a11b799e05';
        }

        $body = wp_json_encode([
            'image_url'        => $image_url,
            'background_prompt' => $prompt,
        ]);

        $response = wp_remote_post( $this->endpoint, [
            'headers' => [
                'Content-Type' => 'application/json',
                'x-api-key'    => $this->api_key,
            ],
            'body'    => $body,
            'timeout' => 60,
        ]);

        if ( is_wp_error( $response ) ) {
            error_log( '[PhotoRoom API] Erro de conexão: ' . $response->get_error_message() );
            return new \WP_Error( 'luxbg_request_error', $response->get_error_message() );
        }

        $status   = wp_remote_retrieve_response_code( $response );
        $raw_body = wp_remote_retrieve_body( $response );

        error_log( '[PhotoRoom API] HTTP Code: ' . $status );
        error_log( '[PhotoRoom API] Body: ' . $raw_body );

        if ( $status >= 400 ) {
            return new \WP_Error( 'luxbg_http_' . $status, $raw_body ?: 'HTTP error ' . $status );
        }

        if ( empty( $raw_body ) ) {
            return new \WP_Error( 'luxbg_empty_response', 'Resposta vazia da API' );
        }

        $data = json_decode( $raw_body, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new \WP_Error( 'luxbg_invalid_format', 'Formato inválido' );
        }

        if ( ! empty( $data['image'] ) ) {
            $image_b64 = $data['image'];
        } elseif ( ! empty( $data['image_b64'] ) ) {
            $image_b64 = $data['image_b64'];
        } elseif ( ! empty( $data['image_base64'] ) ) {
            $image_b64 = $data['image_base64'];
        } elseif ( ! empty( $data['result_url'] ) ) {
            $start   = time();
            $timeout = 20; // segundos

            do {
                $get = wp_remote_get( $data['result_url'], [ 'timeout' => 60 ] );
                if ( is_wp_error( $get ) ) {
                    return new \WP_Error( 'luxbg_request_error', $get->get_error_message() );
                }

                $code  = wp_remote_retrieve_response_code( $get );
                $body  = wp_remote_retrieve_body( $get );

                if ( $code >= 400 ) {
                    return new \WP_Error( 'luxbg_http_' . $code, 'HTTP error ' . $code );
                }

                if ( ( $code === 201 || $code === 202 ) || empty( $body ) ) {
                    if ( time() - $start >= $timeout ) {
                        return new \WP_Error( 'luxbg_timeout', 'Tempo esgotado aguardando imagem' );
                    }
                    sleep( 2 );
                    continue;
                }

                return $body;
            } while ( true );
        } else {
            return new \WP_Error( 'luxbg_api_error', 'Imagem não encontrada na resposta.' );
        }

        $binary = base64_decode( $image_b64 );
        if ( false === $binary ) {
            return new \WP_Error( 'luxbg_api_error', 'Falha ao decodificar imagem' );
        }

        return $binary;
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
                'image_url'        => 'https://images.unsplash.com/photo-1683009427619-a1a11b799e05?auto=format&w=600',
                'background_prompt' => 'luxury interior',
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
