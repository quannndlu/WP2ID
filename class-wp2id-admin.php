<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @since      1.0.0
 * @package    WP2ID
 * @subpackage WP2ID/admin
 */

if (! defined('WPINC')) {
    die;
}

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 */
class WP2ID_Admin
{

    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $plugin_name    The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param    string    $plugin_name       The name of this plugin.
     * @param    string    $version    The version of this plugin.
     */
    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        // Initialize admin settings and register hooks
        $this->load_dependencies();

        // Add AJAX handlers
        add_action('wp_ajax_wp2id_extract_tags', array($this, 'ajax_extract_tags'));
        
        // Add body class for admin styling
        add_filter('admin_body_class', array($this, 'add_admin_body_class'));
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_styles()
    {
        wp_enqueue_style(
            $this->plugin_name,
            plugin_dir_url(__FILE__) . '../assets/css/wp2id-admin.css',
            array(),
            $this->version,
            'all'
        );
        
        // Add dashboard styles
        wp_enqueue_style(
            $this->plugin_name . '-dashboard',
            plugin_dir_url(__FILE__) . '../assets/css/wp2id-dashboard.css',
            array(),
            $this->version,
            'all'
        );
        
        // Thêm CSS cho màu sắc trạng thái làm việc ở danh sách publication
        $screen = get_current_screen();
        if ($screen && $screen->post_type === 'wp2id-publication' && $screen->base === 'edit') {
            wp_enqueue_style(
                $this->plugin_name . '-work-status',
                plugin_dir_url(__FILE__) . 'assets/css/wp2id-work-status.css',
                array(),
                $this->version,
                'all'
            );
        }
        
        // Add publication styles
        wp_enqueue_style(
            $this->plugin_name . '-publication',
            plugin_dir_url(__FILE__) . '../assets/css/wp2id-publication.css',
            array(),
            $this->version,
            'all'
        );
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts()
    {
        // Security best practice: Use nonces for AJAX requests
        $nonce = wp_create_nonce('wp2id_admin_nonce');
        
        // Thêm JS cho màu sắc hàng trong danh sách publication
        $screen = get_current_screen();
        if ($screen && $screen->post_type === 'wp2id-publication' && $screen->base === 'edit') {
            wp_enqueue_script(
                $this->plugin_name . '-admin-list',
                plugin_dir_url(__FILE__) . 'assets/js/wp2id-admin-list.js',
                array('jquery'),
                $this->version,
                true
            );
        }

        wp_enqueue_script(
            $this->plugin_name,
            plugin_dir_url(__FILE__) . '../assets/js/wp2id-admin.js',
            array('jquery'),
            $this->version,
            false
        );

        // Add i18n strings used in admin.js
        $i18n = array(
            'saveToUpdatePreview' => __('Save the template to update the tag preview with the new tag system.', 'wp2id'),
            'selectIdmlFirst' => __('Please select an IDML file first.', 'wp2id'),
            'extracting' => __('Extracting...', 'wp2id'),
            'extractingWait' => __('Extracting tags, please wait...', 'wp2id'),
            'extractTags' => __('Extract Tags', 'wp2id'),
            'tagsExtractedSuccess' => __('Tags extracted and saved successfully:', 'wp2id'),
            'tagsLoadedSuccess' => __('Existing tags loaded from database:', 'wp2id'),
            'tagSystemChanged' => __('Tag system has been updated.', 'wp2id'),
            'noTagsFound' => __('No tags found in the IDML file.', 'wp2id'),
            'errorExtracting' => __('Error extracting tags:', 'wp2id'),
            'unknownError' => __('Unknown error', 'wp2id'),
            'errorConnecting' => __('Error connecting to server.', 'wp2id')
        );

        // Pass nonce and other variables to JS
        global $post;
        wp_localize_script(
            $this->plugin_name,
            'wp2id_admin',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => $nonce,
                'i18n' => $i18n,
                'postId' => isset($post->ID) ? $post->ID : 0,
                'extractTagsNonce' => wp_create_nonce('wp2id_extract_tags_nonce')
            )
        );
    }

    /**
     * Load the required dependencies for the admin area.
     *
     * @since    1.0.0
     * @access   private
     */
    private function load_dependencies()
    {
        /**
         * The class responsible for defining debug settings.
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-wp2id-debug-settings.php';

        // Initialize the debug settings
        $debug_settings = new WP2ID_Debug_Settings($this->plugin_name, $this->version);
        
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
    }

    /**
     * AJAX handler for extracting tags from IDML files
     *
     * @since    1.0.0
     */
    public function ajax_extract_tags()
    {
        error_log(__METHOD__ . ' called');
        // Check nonce for security
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wp2id_extract_tags_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'wp2id')));
        }

        // Check if user has permission
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'wp2id')));
        }

        // Get the post ID and tag system
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $tag_system = isset($_POST['tag_system']) ? sanitize_text_field($_POST['tag_system']) : 'tag-based';
        $force_extract = isset($_POST['force_extract']) ? (bool) $_POST['force_extract'] : true;

        if (!$post_id) {
            wp_send_json_error(array('message' => __('Invalid template ID.', 'wp2id')));
        }

        // Check if the template post exists and is of the correct type
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'wp2id-template') {
            wp_send_json_error(array('message' => __('Invalid template.', 'wp2id')));
        }

        // Load the necessary IDML template class
        if (!class_exists('WP2ID_IDML_Template')) {
            require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-wp2id-idml-template.php';
        }

        // Initialize the template object
        $template = new WP2ID_IDML_Template();
        $template->set_template_id($post_id);

        // Update the tag system if different from current
        $current_tag_system = get_post_meta($post_id, '_wp2id_template_tag_system', true);
        $system_changed = false;

        if ($current_tag_system !== $tag_system) {
            update_post_meta($post_id, '_wp2id_template_tag_system', $tag_system);
            $system_changed = true;
            error_log('Tag system changed from ' . $current_tag_system . ' to ' . $tag_system);
        }

        // Determine if we need to force extraction or can use existing tags
        $need_extraction = $force_extract || $system_changed;

        if ($need_extraction) {
            error_log('Extracting tags from IDML file');
            // Extract the tags and save to database
            $tags = $template->extract_template_tags(true); // true to save to database

            if ($tags === false) {
                wp_send_json_error(array(
                    'message' => $template->get_last_error(),
                    'system_changed' => $system_changed
                ));
            }
            $action = 'extracted';
        } else {
            error_log('Using existing tags from database');
            $tags = $template->get_template_tags(false); // false to not extract if empty

            if (empty($tags)) {
                // If no tags in database and we're not forcing extraction, extract now
                $tags = $template->extract_template_tags(true);
                $action = 'extracted';
            } else {
                $action = 'loaded';
            }
        }

        error_log('Tags ' . $action . ': ' . print_r($tags, true));

        // Return the tags and additional information
        wp_send_json_success(array(
            'tags' => $tags,
            'action' => $action,
            'system_changed' => $system_changed,
            'count' => count($tags)
        ));
    }

    /**
     * Add admin menu pages for WP2ID plugin
     *
     * @since    1.0.0
     */
    public function add_admin_menu() {
        // Add main menu page
        add_menu_page(
            __('WP2ID Dashboard', 'wp2id'),
            __('WP2ID', 'wp2id'),
            'manage_options',
            'wp2id-dashboard',
            array($this, 'render_dashboard_page'),
            'dashicons-media-document',
            30
        );
        
        // Add Dashboard submenu
        add_submenu_page(
            'wp2id-dashboard',
            __('Dashboard', 'wp2id'),
            __('Dashboard', 'wp2id'),
            'manage_options',
            'wp2id-dashboard',
            array($this, 'render_dashboard_page')
        );
        
        // Add Settings submenu
        add_submenu_page(
            'wp2id-dashboard',
            __('Settings', 'wp2id'),
            __('Settings', 'wp2id'),
            'manage_options',
            'wp2id-settings',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * Render the dashboard page content
     *
     * @since    1.0.0
     */
    public function render_dashboard_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <nav class="nav-tab-wrapper wp-clearfix">
                <a href="?page=wp2id-dashboard" class="nav-tab <?php echo isset($_GET['page']) && $_GET['page'] === 'wp2id-dashboard' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Dashboard', 'wp2id'); ?>
                </a>
                <a href="edit.php?post_type=wp2id-template" class="nav-tab">
                    <?php _e('Manage Templates', 'wp2id'); ?>
                </a>
                <a href="edit.php?post_type=wp2id-publication" class="nav-tab">
                    <?php _e('Publications', 'wp2id'); ?>
                </a>
                <a href="?page=wp2id-settings" class="nav-tab <?php echo isset($_GET['page']) && $_GET['page'] === 'wp2id-settings' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Settings', 'wp2id'); ?>
                </a>
            </nav>
            
            <div class="tab-content">
                <div class="wp2id-dashboard-content">
                    <h2><?php _e('Welcome to WP2ID', 'wp2id'); ?></h2>
                    <p><?php _e('This plugin allows you to import InDesign IDML files into WordPress.', 'wp2id'); ?></p>
                    
                    <div class="wp2id-dashboard-card">
                        <h3><?php _e('Getting Started', 'wp2id'); ?></h3>
                        <p><?php _e('To begin using WP2ID, follow these steps:', 'wp2id'); ?></p>
                        <ol>
                            <li><?php _e('Configure your settings in the Settings tab', 'wp2id'); ?></li>
                            <li><?php _e('Go to Templates and create a new template', 'wp2id'); ?></li>
                            <li><?php _e('Upload an IDML file to your template', 'wp2id'); ?></li>
                            <li><?php _e('Extract tags and map them to your WordPress content', 'wp2id'); ?></li>
                            <li><?php _e('Create Publications and associate them with templates', 'wp2id'); ?></li>
                        </ol>
                        
                        <div class="wp2id-actions" style="margin-top: 20px;">
                            <a href="post-new.php?post_type=wp2id-template" class="button button-primary"><?php _e('Add New Template', 'wp2id'); ?></a>
                            <a href="post-new.php?post_type=wp2id-publication" class="button button-primary"><?php _e('Add New Publication', 'wp2id'); ?></a>
                            <a href="edit.php?post_type=wp2id-template" class="button"><?php _e('View All Templates', 'wp2id'); ?></a>
                            <a href="edit.php?post_type=wp2id-publication" class="button"><?php _e('View All Publications', 'wp2id'); ?></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render the settings page content
     *
     * @since    1.0.0
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <nav class="nav-tab-wrapper wp-clearfix">
                <a href="?page=wp2id-dashboard" class="nav-tab <?php echo isset($_GET['page']) && $_GET['page'] === 'wp2id-dashboard' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Dashboard', 'wp2id'); ?>
                </a>
                <a href="edit.php?post_type=wp2id-template" class="nav-tab">
                    <?php _e('Manage Templates', 'wp2id'); ?>
                </a>
                <a href="edit.php?post_type=wp2id-publication" class="nav-tab">
                    <?php _e('Publications', 'wp2id'); ?>
                </a>
                <a href="?page=wp2id-settings" class="nav-tab <?php echo isset($_GET['page']) && $_GET['page'] === 'wp2id-settings' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Settings', 'wp2id'); ?>
                </a>
            </nav>
            
            <div class="tab-content">
                <form method="post" action="options.php">
                    <?php
                    settings_fields('wp2id_debug_settings');
                    do_settings_sections('wp2id-debug');
                    submit_button();
                    ?>
                </form>
            </div>
        </div>
        <?php
    }
    
    /**
     * Add body class based on current admin page
     * 
     * @since 1.0.0
     * @param string $classes Current body class string
     * @return string Modified body class string
     */
    public function add_admin_body_class($classes)
    {
        // Method kept for backward compatibility but no longer needed
        return $classes;
    }
}
