<?php
namespace LuxuryBg;

class Status_Tracker {
    const STATUS_META = '_luxbg_status';
    const IMAGE_META  = '_luxbg_generated_image_id';
    const PROMPT_META = '_luxbg_prompt';
    const STYLE_META  = '_luxbg_style';

    public function set_status( $product_id, $status ) {
        update_post_meta( $product_id, self::STATUS_META, $status );
    }

    public function get_status( $product_id ) {
        return get_post_meta( $product_id, self::STATUS_META, true );
    }

    public function save_generated_image( $product_id, $attachment_id ) {
        update_post_meta( $product_id, self::IMAGE_META, $attachment_id );
    }
}
