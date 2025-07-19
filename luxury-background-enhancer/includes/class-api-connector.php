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
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        if ( empty( $data['image_b64'] ) ) {
            return new \WP_Error( 'luxbg_no_image', 'No image returned from API' );
        }
        return base64_decode( $data['image_b64'] );
    }
}
