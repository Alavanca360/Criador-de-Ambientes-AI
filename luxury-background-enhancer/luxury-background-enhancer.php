<?php
/**
 * Plugin Name: Criador de Ambientes AI – Alavanca360
 * Description: Gera fundos de luxo para produtos usando a API PhotoRoom.
 * Version: 0.1.0
 * Author: Alavanca360
 * Text Domain: criador-de-ambientes-ai
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Define plugin paths.
define( 'LUXBG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'LUXBG_PLUGIN_FILE', __FILE__ );
define( 'LUXBG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Include required classes.
require_once LUXBG_PLUGIN_DIR . 'includes/class-image-processor.php';
require_once LUXBG_PLUGIN_DIR . 'includes/class-api-connector.php';
require_once LUXBG_PLUGIN_DIR . 'includes/class-admin-panel.php';
require_once LUXBG_PLUGIN_DIR . 'includes/class-status-tracker.php';

// Initialize the plugin.
function luxbg_init_plugin() {
    new LuxuryBg\Admin_Panel();
}
add_action( 'plugins_loaded', 'luxbg_init_plugin' );

