<?php
namespace LuxuryBg;

class Image_Processor {
    public function is_white_background( $image_path ) {
        // Previously the plugin only allowed product images with white
        // backgrounds. To enable generation on any background we simply
        // return true here.
        return true;
    }

    public function resize_image( $attachment_id ) {
        $path = get_attached_file( $attachment_id );
        $editor = wp_get_image_editor( $path );
        if ( is_wp_error( $editor ) ) {
            return $attachment_id;
        }
        $editor->resize( 1024, 423, true );
        $editor->save( $path );
        return $attachment_id;
    }
}
