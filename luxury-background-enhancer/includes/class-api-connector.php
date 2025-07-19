<?php
namespace LuxuryBg;

class API_Connector {
    // Endpoint antigo que utilizava o envio da imagem em base64. Mantido por
    // compatibilidade, mas o novo fluxo utiliza o endpoint /v1/replace via URL.
    private $endpoint = 'https://sdk.photoroom.com/v1/replace';
    private $api_key;

    public function __construct() {
        $this->api_key = get_option( 'luxbg_api_key', '' );
    }

    /**
     * Envia a imagem para o PhotoRoom utilizando o endpoint /v1/replace.
     * Utiliza a URL da imagem em vez de enviar o arquivo em base64 para
     * evitar erros do tipo "Please provide an image".
     */
    public function generate_luxury_image( $image_url, $prompt = 'luxury interior' ) {
        $api_key = get_option( 'luxbg_api_key' );
        if ( ! $api_key ) {
            return new \WP_Error( 'missing_api_key', 'API Key não configurada.' );
        }

        // Substitui a imagem por uma pública caso a enviada seja nula
        if ( empty( $image_url ) ) {
            $image_url = 'https://images.unsplash.com/photo-1683009427619-a1a11b799e05';
        }

        $endpoint = 'https://sdk.photoroom.com/v1/replace';

        $body = json_encode([
            'image_url'  => $image_url,
            'background' => $prompt,
        ]);

        $response = wp_remote_post( $endpoint, [
            'headers' => [
                'Content-Type' => 'application/json',
                'x-api-key'    => $api_key,
            ],
            'body'    => $body,
            'timeout' => 60,
        ]);

        if ( is_wp_error( $response ) ) {
            error_log( '[PhotoRoom API] Erro de conexão: ' . $response->get_error_message() );
            return new \WP_Error( 'api_connection_error', $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        // Log para debug completo
        error_log( '[PhotoRoom API] HTTP Code: ' . $code );
        error_log( '[PhotoRoom API] Body: ' . $body );

        $data = json_decode( $body, true );

        if ( isset( $data['image'] ) ) {
            return $data['image'];
        }

        return new \WP_Error( 'api_generate', 'Erro na API: ' . $body );
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
                'image_url' => 'https://images.unsplash.com/photo-1683009427619-a1a11b799e05?auto=format&w=600',
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
