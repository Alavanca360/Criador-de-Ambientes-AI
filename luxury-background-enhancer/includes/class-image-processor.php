<?php
namespace LuxuryBg;

class Image_Processor {
    public function is_white_background( $image_path ) {
        // Previously the plugin only allowed product images with white
        // backgrounds. To enable generation on any background we simply
        // return true here.
        return true;
    }

    public function resize_image( $attachment_id, $dest_path = null ) {
        $src_path  = get_attached_file( $attachment_id );

        if ( ! file_exists( $src_path ) ) {
            return $attachment_id;
        }

        if ( empty( $dest_path ) ) {
            $dest_path = $src_path;
        }

        $data = file_get_contents( $src_path );
        if ( false === $data ) {
            return $attachment_id;
        }

        $src_img = imagecreatefromstring( $data );
        if ( ! $src_img ) {
            return $attachment_id;
        }

        $src_w = imagesx( $src_img );
        $src_h = imagesy( $src_img );

        $target_w = 1024;
        $target_h = 423;

        $ratio = min( $target_w / $src_w, $target_h / $src_h );
        $new_w  = (int) ( $src_w * $ratio );
        $new_h  = (int) ( $src_h * $ratio );

        $resized = imagecreatetruecolor( $new_w, $new_h );
        imagecopyresampled( $resized, $src_img, 0, 0, 0, 0, $new_w, $new_h, $src_w, $src_h );

        $canvas = imagecreatetruecolor( $target_w, $target_h );
        imagecopyresampled( $canvas, $src_img, 0, 0, 0, 0, $target_w, $target_h, $src_w, $src_h );

        for ( $i = 0; $i < 3; $i++ ) {
            imagefilter( $canvas, IMG_FILTER_GAUSSIAN_BLUR );
        }

        $dst_x = (int) ( ( $target_w - $new_w ) / 2 );
        $dst_y = (int) ( ( $target_h - $new_h ) / 2 );
        imagecopy( $canvas, $resized, $dst_x, $dst_y, 0, 0, $new_w, $new_h );

        imagejpeg( $canvas, $dest_path, 90 );

        imagedestroy( $src_img );
        imagedestroy( $resized );
        imagedestroy( $canvas );

        return $attachment_id;
    }
}
