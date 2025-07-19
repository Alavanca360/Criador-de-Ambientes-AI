<?php
/**
 * Classe responsável por conectar com a API da PhotoRoom
 */

class LUXBG_API_Connector {
    public static function generate_image($image_url, $style_prompt) {
        $api_key = get_option('luxbg_api_key');
        if (!$api_key) return false;

        $endpoint = 'https://sdk.photoroom.com/v1/segment';

        $response = wp_remote_post($endpoint, array(
            'headers' => array(
                'Authorization' => 'Token ' . $api_key,
                'Content-Type'  => 'application/json',
            ),
            'body' => json_encode(array(
                'image_url' => $image_url,
                'background' => $style_prompt
            )),
            'timeout' => 60,
        ));

        if (is_wp_error($response)) {
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['result_url'])) {
            return esc_url_raw($body['result_url']);
        }

        return false;
    }
}
