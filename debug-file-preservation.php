<?php
/**
 * WP2ID File Preservation Debug Helper
 * 
 * This file contains debugging functions to help track
 * file preservation issues during publication saves.
 * 
 * @since 1.0.0
 * @package WP2ID
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Debug hook to track publication saves
 */
add_action('save_post_wp2id-publication', function($post_id, $post, $update) {
    if (!WP_DEBUG || !defined('WP_DEBUG_LOG') || !WP_DEBUG_LOG) {
        return;
    }
    
    $current_file = get_post_meta($post_id, '_wp2id_publication_direct_idml_file', true);
    $upload_mode = get_post_meta($post_id, '_wp2id_publication_upload_mode', true);
    
    $debug_info = array(
        'post_id' => $post_id,
        'action' => $update ? 'update' : 'create',
        'upload_mode' => $upload_mode,
        'current_file_id' => $current_file,
        'submitted_file' => isset($_POST['wp2id_direct_idml_file']) ? $_POST['wp2id_direct_idml_file'] : 'not set',
        'remove_flag' => isset($_POST['wp2id_remove_idml_file']) ? 'yes' : 'no',
        'is_autosave' => (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) ? 'yes' : 'no',
        'request_method' => $_SERVER['REQUEST_METHOD'],
        'is_ajax' => (defined('DOING_AJAX') && DOING_AJAX) ? 'yes' : 'no'
    );
    
    error_log('WP2ID File Debug: ' . json_encode($debug_info));
}, 5, 3); // High priority to run before the main save function

/**
 * Add admin notice for file preservation status
 */
add_action('admin_notices', function() {
    if (!current_user_can('edit_posts') || !isset($_GET['post']) || get_post_type($_GET['post']) !== 'wp2id-publication') {
        return;
    }
    
    $post_id = intval($_GET['post']);
    $file_id = get_post_meta($post_id, '_wp2id_publication_direct_idml_file', true);
    $upload_mode = get_post_meta($post_id, '_wp2id_publication_upload_mode', true);
    
    if ($upload_mode === 'direct_idml') {
        // if ($file_id) {
        //     $file_path = get_attached_file($file_id);
        //     $file_exists = $file_path && file_exists($file_path);
            
        //     if (!$file_exists) {
        //         echo '<div class="notice notice-error"><p><strong>WP2ID Debug:</strong> Direct IDML file ID saved but file missing on disk (ID: ' . $file_id . ')</p></div>';
        //     } else {
        //         $filename = basename($file_path);
        //         echo '<div class="notice notice-info"><p><strong>WP2ID Debug:</strong> Direct IDML file preserved: ' . esc_html($filename) . ' (ID: ' . $file_id . ')</p></div>';
        //     }
        // } else {
        //     echo '<div class="notice notice-warning"><p><strong>WP2ID Debug:</strong> Publication in direct IDML mode but no file ID saved</p></div>';
        // }
    }
});
