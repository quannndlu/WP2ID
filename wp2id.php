<?php
/**
 * Plugin Name: WP2ID
 * Plugin URI: mailto:tinhp.wk@gmail.com
 * Description: A WordPress plugin with standard architecture
 * Version: 1.0.2
 * Author: Hoàng Bách
 * Author URI: mailto:tinhp.wk@gmail.com
 * Text Domain: wp2id
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package WP2ID
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// Define plugin constants
define( 'WP2ID_VERSION', '1.0.0' );
define( 'WP2ID_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP2ID_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WP2ID_PLUGIN_FILE', __FILE__ );
define( 'WP2ID_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Load required files
require_once WP2ID_PLUGIN_DIR . 'includes/class-wp2id.php';

// Load debug helper if in debug mode
if (WP_DEBUG) {
    require_once WP2ID_PLUGIN_DIR . 'debug-file-preservation.php';
}

/**
 * Begins execution of the plugin.
 *
 * @since 1.0.0
 */
function run_wp2id() {
    $plugin = new WP2ID();
    $plugin->run();
}
run_wp2id();