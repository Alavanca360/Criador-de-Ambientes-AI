<?php
namespace LuxuryBg;

class Error_Logger {
    const OPTION_NAME = 'luxbg_error_logs';
    const MAX_ENTRIES = 20;

    public function log( $context, $message ) {
        $logs   = get_option( self::OPTION_NAME, [] );
        $logs[] = [
            'time'    => current_time( 'mysql' ),
            'context' => $context,
            'message' => $message,
        ];
        if ( count( $logs ) > self::MAX_ENTRIES ) {
            $logs = array_slice( $logs, - self::MAX_ENTRIES );
        }
        update_option( self::OPTION_NAME, $logs, false );
    }

    public function get_logs() {
        return get_option( self::OPTION_NAME, [] );
    }
}
