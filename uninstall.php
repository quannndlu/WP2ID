<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @since      1.0.0
 * @package    WP2ID
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Define constants for uninstallation process if not already defined
if ( ! defined( 'WP2ID_PLUGIN_DIR' ) ) {
    define( 'WP2ID_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

// Include security class for any helper methods
if ( file_exists( WP2ID_PLUGIN_DIR . 'includes/class-wp2id-security.php' ) ) {
    require_once WP2ID_PLUGIN_DIR . 'includes/class-wp2id-security.php';
}

/**
 * Clean up plugin data
 */
function wp2id_uninstall() {
    // Delete plugin options
    delete_option( 'wp2id_settings' );
    
    // Delete transients
    delete_transient( 'wp2id_transient_data' );
    
    // Example: Remove plugin custom post types data
    // $posts = get_posts( array( 'post_type' => 'wp2id_custom_post', 'numberposts' => -1 ) );
    // foreach ( $posts as $post ) {
    //     wp_delete_post( $post->ID, true );
    // }
    
    // Example: Remove plugin custom db tables
    // global $wpdb;
    // $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wp2id_custom_table" );
}

// Perform uninstallation
wp2id_uninstall();
