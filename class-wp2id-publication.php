<?php

/**
 * The publication-specific functionality of the plugin.
 *
 * @since      1.0.0
 * @package    WP2ID
 * @subpackage WP2ID/admin
 */

if (! defined('WPINC')) {
    die;
}

// Include the utility classes
require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-wp2id-idml-utils.php';
require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-wp2id-zip-utils.php';
require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-wp2id-content-utils.php';
require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-wp2id-permission-manager.php';

/**
 * The publication-specific functionality of the plugin.
 *
 * Defines the custom post type for publications, including taxonomy, metaboxes,
 * and related functionality.
 *
 * @package    WP2ID
 * @subpackage WP2ID/admin
 */
class WP2ID_Publication
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
     * Temporary directory for IDML extraction during export process.
     * 
     * @since    1.0.0
     * @access   private
     * @var      string    $idml_extract_dir    Path to temporary IDML extraction directory.
     */
    private $idml_extract_dir;

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

        // Register actions and filters
        add_action('init', array($this, 'register_cpt_publication'));
        add_action('init', array($this, 'register_publication_taxonomy'));
        add_action('add_meta_boxes', array($this, 'register_publication_metaboxes'));
        add_action('save_post_wp2id_publication', array($this, 'save_publication_metaboxes'), 10, 3);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_wp2id_search_posts', array($this, 'ajax_search_posts'));
        add_action('wp_ajax_wp2id_get_post', array($this, 'ajax_get_post'));
        add_action('wp_ajax_wp2id_get_post_details', array($this, 'ajax_get_post_details'));
        add_action('wp_ajax_wp2id_get_template_tags', array($this, 'ajax_get_template_tags'));
        add_action('wp_ajax_wp2id_extract_direct_idml_tags', array($this, 'ajax_extract_direct_idml_tags')); // Add new AJAX handler for direct IDML tag extraction
        add_action('wp_ajax_wp2id_get_template_details_for_mapping', array($this, 'ajax_get_template_details_for_mapping')); // Add new AJAX handler for comprehensive template details
        add_action('wp_ajax_wp2id_export_idml', array($this, 'ajax_export_idml')); // Add new AJAX handler for IDML export
        add_action('wp_ajax_wp2id_preview_publication', array($this, 'ajax_preview_publication')); // Add new AJAX handler for publication preview

        // Add column for work status in admin list view
        add_filter('manage_wp2id-publication_posts_columns', array($this, 'add_work_status_column'));
        add_action('manage_wp2id-publication_posts_custom_column', array($this, 'display_work_status_column'), 10, 2);

        // Add filter dropdown for work status
        add_action('restrict_manage_posts', array($this, 'add_work_status_filter'));
        add_filter('parse_query', array($this, 'filter_publications_by_work_status'));

        add_filter('post_updated_messages', array($this, 'publication_updated_messages'));

        // Add filter for work status
        add_action('restrict_manage_posts', array($this, 'add_work_status_filter'));
        add_filter('pre_get_posts', array($this, 'filter_publications_by_work_status'));
    }

    /**
     * Register the custom post type for publications.
     *
     * @since    1.0.0
     */
    public function register_cpt_publication()
    {
        $labels = array(
            'name'                  => _x('Publications', 'Post Type General Name', 'wp2id'),
            'singular_name'         => _x('Publication', 'Post Type Singular Name', 'wp2id'),
            'menu_name'             => __('Publications', 'wp2id'),
            'name_admin_bar'        => __('Publication', 'wp2id'),
            'archives'              => __('Publication Archives', 'wp2id'),
            'attributes'            => __('Publication Attributes', 'wp2id'),
            'parent_item_colon'     => __('Parent Publication:', 'wp2id'),
            'all_items'             => __('All Publications', 'wp2id'),
            'add_new_item'          => __('Add New Publication', 'wp2id'),
            'add_new'               => __('Add New', 'wp2id'),
            'new_item'              => __('New Publication', 'wp2id'),
            'edit_item'             => __('Edit Publication', 'wp2id'),
            'update_item'           => __('Update Publication', 'wp2id'),
            'view_item'             => __('View Publication', 'wp2id'),
            'view_items'            => __('View Publications', 'wp2id'),
            'search_items'          => __('Search Publication', 'wp2id'),
            'not_found'             => __('Not found', 'wp2id'),
            'not_found_in_trash'    => __('Not found in Trash', 'wp2id'),
            'featured_image'        => __('Featured Image', 'wp2id'),
            'set_featured_image'    => __('Set featured image', 'wp2id'),
            'remove_featured_image' => __('Remove featured image', 'wp2id'),
            'use_featured_image'    => __('Use as featured image', 'wp2id'),
            'insert_into_item'      => __('Insert into publication', 'wp2id'),
            'uploaded_to_this_item' => __('Uploaded to this publication', 'wp2id'),
            'items_list'            => __('Publications list', 'wp2id'),
            'items_list_navigation' => __('Publications list navigation', 'wp2id'),
            'filter_items_list'     => __('Filter publications list', 'wp2id'),
        );

        $args = array(
            'label'                 => __('Publication', 'wp2id'),
            'description'           => __('Publication for WP2ID', 'wp2id'),
            'labels'                => $labels,
            'supports'              => array('title',),
            'hierarchical'          => false,
            'public'                => false,
            'show_ui'               => true,
            'show_in_menu'          => 'wp2id-dashboard', // Show under WP2ID Dashboard menu
            'menu_position'         => 5,
            'show_in_admin_bar'     => true,
            'show_in_nav_menus'     => true,
            'can_export'            => true,
            'has_archive'           => true,
            'exclude_from_search'   => false,
            'publicly_queryable'    => false,
            'capability_type' => 'post',
            'show_in_rest'          => true,
        );

        register_post_type('wp2id-publication', $args);

        // Register taxonomy for Publication categories
        $this->register_publication_taxonomy();
    }

    /**
     * Register taxonomy for Publication categories.
     *
     * @since    1.0.0
     */
    public function register_publication_taxonomy()
    {
        $labels = array(
            'name'                       => _x('Publication Categories', 'Taxonomy General Name', 'wp2id'),
            'singular_name'              => _x('Publication Category', 'Taxonomy Singular Name', 'wp2id'),
            'menu_name'                  => __('Categories', 'wp2id'),
            'all_items'                  => __('All Categories', 'wp2id'),
            'parent_item'                => __('Parent Category', 'wp2id'),
            'parent_item_colon'          => __('Parent Category:', 'wp2id'),
            'new_item_name'              => __('New Category Name', 'wp2id'),
            'add_new_item'               => __('Add New Category', 'wp2id'),
            'edit_item'                  => __('Edit Category', 'wp2id'),
            'update_item'                => __('Update Category', 'wp2id'),
            'view_item'                  => __('View Category', 'wp2id'),
            'separate_items_with_commas' => __('Separate categories with commas', 'wp2id'),
            'add_or_remove_items'        => __('Add or remove categories', 'wp2id'),
            'choose_from_most_used'      => __('Choose from the most used', 'wp2id'),
            'popular_items'              => __('Popular Categories', 'wp2id'),
            'search_items'               => __('Search Categories', 'wp2id'),
            'not_found'                  => __('Not Found', 'wp2id'),
            'no_terms'                   => __('No categories', 'wp2id'),
            'items_list'                 => __('Categories list', 'wp2id'),
            'items_list_navigation'      => __('Categories list navigation', 'wp2id'),
        );

        $args = array(
            'labels'                     => $labels,
            'hierarchical'               => true,
            'public'                     => true,
            'show_ui'                    => true,
            'show_admin_column'          => true,
            'show_in_nav_menus'          => true,
            'show_tagcloud'              => true,
            'show_in_rest'               => true,
        );

        register_taxonomy('publication-category', array('wp2id-publication'), $args);
    }

    /**
     * Register meta boxes for the publication custom post type.
     *
     * @since    1.0.0
     */
    public function register_publication_metaboxes()
    {
        // Metabox for template selection
        add_meta_box(
            'wp2id_publication_template',
            __('Template Selection', 'wp2id'),
            array($this, 'render_template_metabox'),
            'wp2id-publication',
            'side',
            'high'
        );

        // Metabox for post list
        add_meta_box(
            'wp2id_publication_posts',
            __('Associated Posts', 'wp2id'),
            array($this, 'render_posts_metabox'),
            'wp2id-publication',
            'normal',
            'high'
        );
    }

    /**
     * Render the template selection metabox.
     *
     * @since    1.0.0
     * @param    WP_Post    $post    The post object.
     */
    public function render_template_metabox($post)
    {
        wp_nonce_field('wp2id_publication_template_save', 'wp2id_publication_template_nonce');

        // Enqueue WordPress media library for direct IDML upload
        wp_enqueue_media();

        // Get saved template ID
        $template_id = get_post_meta($post->ID, '_wp2id_publication_template_id', true);

        // Get saved work status
        $work_status = get_post_meta($post->ID, '_wp2id_publication_work_status', true);
        if (empty($work_status)) {
            $work_status = 'initialization'; // Default value
        }

        // Query for available templates
        $templates = get_posts(array(
            'post_type' => 'wp2id-template',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ));

        // Include template select view
        include plugin_dir_path(__FILE__) . 'views/metabox-template-select.php';

        // Include work status view  
        include plugin_dir_path(__FILE__) . 'views/metabox-work-status.php';
    }

    /**
     * Render the posts metabox.
     *
     * @since    1.0.0
     * @param    WP_Post    $post    The post object.
     */
    public function render_posts_metabox($post)
    {
        wp_nonce_field('wp2id_publication_posts_save', 'wp2id_publication_posts_nonce');

        // Enqueue WordPress media library for any media functionality
        wp_enqueue_media();

        // Get saved post IDs
        $publication_posts = get_post_meta($post->ID, '_wp2id_publication_posts', true);
        if (!is_array($publication_posts)) {
            $publication_posts = array();
        }

        // Get saved position data
        $position_data = get_post_meta($post->ID, '_wp2id_publication_positions', true);
        if (!is_array($position_data)) {
            $position_data = array();
        }

        // Enqueue CSS for the metabox
        wp_enqueue_style(
            $this->plugin_name . '-metabox-publication',
            plugin_dir_url(__FILE__) . 'assets/css/metabox-publication.css',
            array(),
            $this->version
        );

        // Enqueue scripts for the metabox
        wp_enqueue_script(
            $this->plugin_name . '-publication',
            plugin_dir_url(__FILE__) . '../assets/js/wp2id-publication.js',
            array('jquery', 'jquery-ui-sortable', 'media-upload', 'media-views'),
            $this->version . '-' . time(), // Force cache refresh
            true
        );

        // Pass data to the script
        wp_localize_script(
            $this->plugin_name . '-publication',
            'wp2id_publication',
            array(
                'nonce' => wp_create_nonce('wp2id_publication_ajax_nonce'),
                'ajaxurl' => admin_url('admin-ajax.php'),
                'postId' => $post->ID,
                'i18n' => array(
                    'addToPub' => __('Add to Publication', 'wp2id'),
                    'remove' => __('Remove', 'wp2id'),
                    'loading' => __('Loading...', 'wp2id')
                )
            )
        );

        // Also add admin data for consistency
        wp_localize_script(
            $this->plugin_name . '-publication',
            'wp2id_publication_admin',
            array(
                'nonce' => wp_create_nonce('wp2id_publication_ajax_nonce'),
                'ajaxurl' => admin_url('admin-ajax.php'),
                'postId' => $post->ID
            )
        );

        // Get all categories for the filter
        $categories = get_categories(array('hide_empty' => false));

        // Include the view file
        include plugin_dir_path(__FILE__) . 'views/metabox-publication.php';
    }

    /**
     * Save the template metabox data.
     *
     * @since    1.0.0
     * @param    int       $post_id    The post ID.
     * @param    WP_Post   $post       The post object.
     */
    public function save_publication_metaboxes($post_id, $post)
    {
        // Only run for publications
        if ($post->post_type !== 'wp2id-publication') {
            return;
        }

        // Check template nonce
        if (
            !isset($_POST['wp2id_publication_template_nonce']) ||
            !wp_verify_nonce($_POST['wp2id_publication_template_nonce'], 'wp2id_publication_template_save')
        ) {
            return;
        }

        // Check posts nonce
        if (
            !isset($_POST['wp2id_publication_posts_nonce']) ||
            !wp_verify_nonce($_POST['wp2id_publication_posts_nonce'], 'wp2id_publication_posts_save')
        ) {
            return;
        }

        // Skip autosaves
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save template ID
        if (isset($_POST['wp2id_template_id'])) {
            $template_id = intval($_POST['wp2id_template_id']);
            update_post_meta($post_id, '_wp2id_publication_template_id', $template_id);
        }

        // Save work status
        if (isset($_POST['wp2id_work_status'])) {
            $work_status = sanitize_text_field($_POST['wp2id_work_status']);
            // Validate that it's one of our allowed values
            if (in_array($work_status, array('initialization', 'list_created', 'completed'))) {
                update_post_meta($post_id, '_wp2id_publication_work_status', $work_status);
            }
        }

        // Save associated posts
        if (isset($_POST['wp2id_publication_posts']) && is_array($_POST['wp2id_publication_posts'])) {
            // Get the previous posts before updating (for cleanup purposes)
            $previous_posts = get_post_meta($post_id, '_wp2id_publication_posts', true);
            if (!is_array($previous_posts)) {
                $previous_posts = array();
            }

            // Convert to integers and filter out invalid entries
            $post_ids = array_filter(array_map('intval', $_POST['wp2id_publication_posts']));
            update_post_meta($post_id, '_wp2id_publication_posts', $post_ids);

            // Save page numbers for posts
            if (isset($_POST['wp2id_page_numbers']) && is_array($_POST['wp2id_page_numbers'])) {
                $page_numbers = array();
                foreach ($_POST['wp2id_page_numbers'] as $individual_post_id => $page_number) {
                    $individual_post_id = intval($individual_post_id);
                    if (in_array($individual_post_id, $post_ids)) {
                        $page_numbers[$individual_post_id] = sanitize_text_field($page_number);
                    }
                }
                update_post_meta($post_id, '_wp2id_post_page_numbers', $page_numbers);
            } else {
                // Clear page numbers if no data provided
                update_post_meta($post_id, '_wp2id_post_page_numbers', array());
            }

            // Save position data for each post
            $position_data = array();
            if (isset($_POST['wp2id_positions']) && is_array($_POST['wp2id_positions'])) {
                foreach ($_POST['wp2id_positions'] as $post_id_key => $json_data) {
                    $post_id_int = intval($post_id_key);
                    if (in_array($post_id_int, $post_ids) && !empty($json_data)) {
                        // Sanitize and decode the JSON data
                        $sanitized_data = sanitize_text_field($json_data);
                        $decoded_data = json_decode($sanitized_data, true);
                        if (is_array($decoded_data)) {
                            $position_data[$post_id_int] = $decoded_data;
                        }
                    }
                }

                // Save all position data
                if (!empty($position_data)) {
                    update_post_meta($post_id, '_wp2id_publication_positions', $position_data);
                }
            }

            // Process central post mappings and save to publication meta
            if (isset($_POST['wp2id_post_mappings']) && !empty($_POST['wp2id_post_mappings'])) {
                $central_mappings_json = wp_unslash($_POST['wp2id_post_mappings']);
                $central_mappings = json_decode($central_mappings_json, true);

                if (is_array($central_mappings)) {
                    // Clean up mappings - only keep mappings for posts that are actually selected
                    $cleaned_mappings = array();
                    foreach ($central_mappings as $individual_post_id => $mapping_data) {
                        $individual_post_id = intval($individual_post_id);
                        // Only keep mappings for posts that are in our current post list
                        if (in_array($individual_post_id, $post_ids) && is_array($mapping_data)) {
                            $cleaned_mappings[$individual_post_id] = $mapping_data;
                        }
                    }

                    // Save the cleaned central mappings to the publication meta
                    update_post_meta($post_id, '_wp2id_post_mappings', $cleaned_mappings);

                    // Also save to individual post meta for backward compatibility and direct access
                    foreach ($cleaned_mappings as $individual_post_id => $mapping_data) {
                        $individual_post_id = intval($individual_post_id);

                        // Save the tag mappings to the individual post's meta
                        update_post_meta($individual_post_id, '_wp2id_individual_post_mappings', $mapping_data);

                        // Log the save operation
                        WP2ID_Debug::log("Saved tag mappings for post ID {$individual_post_id} from central mappings field", 'Publication');
                    }

                    // Log cleanup operation if any mappings were removed
                    $removed_count = count($central_mappings) - count($cleaned_mappings);
                    if ($removed_count > 0) {
                        WP2ID_Debug::log("Cleaned up {$removed_count} orphaned mapping entries during save", 'Publication');
                    }
                } else {
                    // Clear the central mappings if invalid data
                    update_post_meta($post_id, '_wp2id_post_mappings', array());
                }
            } else {
                // Clear the central mappings if no data provided
                update_post_meta($post_id, '_wp2id_post_mappings', array());
            }

            // Clean up orphaned individual post mapping data
            // Check posts that were previously in this publication but are no longer selected
            foreach ($previous_posts as $previous_post_id) {
                // If this post is no longer in the current selection, remove its individual mappings
                if (!in_array($previous_post_id, $post_ids)) {
                    delete_post_meta($previous_post_id, '_wp2id_individual_post_mappings');
                    WP2ID_Debug::log("Cleaned up individual post mappings for removed post ID {$previous_post_id}", 'Publication');
                }
            }
        } else {
            // Set empty array if no posts
            update_post_meta($post_id, '_wp2id_publication_posts', array());
            update_post_meta($post_id, '_wp2id_publication_positions', array());
            update_post_meta($post_id, '_wp2id_post_mappings', array());
        }

        // Save upload mode (template or direct_idml)
        if (isset($_POST['wp2id_upload_mode'])) {
            $upload_mode = sanitize_text_field($_POST['wp2id_upload_mode']);
            if (in_array($upload_mode, array('template', 'direct_idml'))) {
                update_post_meta($post_id, '_wp2id_publication_upload_mode', $upload_mode);
            }
        }

        // Save direct IDML file - be very careful to preserve existing file
        $current_file_id = get_post_meta($post_id, '_wp2id_publication_direct_idml_file', true);
        $preserve_file = false;

        if (isset($_POST['wp2id_direct_idml_file'])) {
            $submitted_file_id = sanitize_text_field($_POST['wp2id_direct_idml_file']);

            if ($submitted_file_id === 'preserve' || (empty($submitted_file_id) && $current_file_id)) {
                // Explicit preservation or empty submission with existing file - don't change anything
                $preserve_file = true;
            } elseif (is_numeric($submitted_file_id) && intval($submitted_file_id) > 0) {
                // New file ID provided
                $new_file_id = intval($submitted_file_id);

                // Verify the file exists before saving
                if (get_attached_file($new_file_id)) {
                    update_post_meta($post_id, '_wp2id_publication_direct_idml_file', $new_file_id);

                    // If file changed, clear old tags data
                    if ($current_file_id && $current_file_id != $new_file_id) {
                        delete_post_meta($post_id, '_wp2id_publication_direct_idml_tags');
                        delete_post_meta($post_id, '_wp2id_publication_direct_idml_tags_details');
                    }
                } else {
                    // File doesn't exist, preserve current state
                    $preserve_file = true;
                }
            } elseif ($submitted_file_id === '' || $submitted_file_id === '0') {
                // Explicit removal - only if there's a current file and this is intentional
                if ($current_file_id && isset($_POST['wp2id_remove_idml_file'])) {
                    delete_post_meta($post_id, '_wp2id_publication_direct_idml_file');
                    delete_post_meta($post_id, '_wp2id_publication_direct_idml_tags');
                    delete_post_meta($post_id, '_wp2id_publication_direct_idml_tags_details');
                } else {
                    // Preserve existing file
                    $preserve_file = true;
                }
            }
        } elseif ($current_file_id) {
            // No field submitted but file exists - preserve it
            $preserve_file = true;
        }

        // Debug logging (remove in production)
        if (WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('WP2ID Save: Post ID ' . $post_id . ', Current File: ' . $current_file_id . ', Submitted: ' . (isset($_POST['wp2id_direct_idml_file']) ? $_POST['wp2id_direct_idml_file'] : 'not set') . ', Preserve: ' . ($preserve_file ? 'yes' : 'no'));
        }
    }

    /**
     * AJAX handler for searching posts
     *
     * @since    1.0.0
     */
    public function ajax_search_posts()
    {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wp2id_publication_ajax_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'wp2id')));
        }

        // Check if user has permission
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'wp2id')));
        }

        // Check WP2ID specific permission for search results
        if (!WP2ID_Permission_Manager::current_user_can('access_search_results')) {
            wp_send_json_error(array('message' => __('You do not have permission to access search results.', 'wp2id')));
        }

        // Set default values for search parameters
        $args = array(
            'post_type' => 'post',
            'posts_per_page' => 10,
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC',
        );

        // Add filtering parameters
        if (!empty($_POST['category'])) {
            $args['cat'] = intval($_POST['category']);
        }

        if (!empty($_POST['date_filter'])) {
            $date = $_POST['date_filter'];
            $args['date_query'] = array();

            switch ($date) {
                case 'today':
                    $args['date_query'][] = array(
                        'year' => date('Y'),
                        'month' => date('m'),
                        'day' => date('d'),
                    );
                    break;
                case 'week':
                    $args['date_query'][] = array(
                        'after' => '1 week ago',
                    );
                    break;
                case 'month':
                    $args['date_query'][] = array(
                        'after' => '1 month ago',
                    );
                    break;
            }
        }

        if (!empty($_POST['author'])) {
            $args['author'] = intval($_POST['author']);
        }

        if (!empty($_POST['search'])) {
            $args['s'] = sanitize_text_field($_POST['search']);
        }

        // Handle pagination
        if (!empty($_POST['paged'])) {
            $args['paged'] = intval($_POST['paged']);
        }

        // Perform the query
        $query = new WP_Query($args);

        // Build response data
        $response = array(
            'posts' => array(),
            'total' => $query->found_posts,
            'pages' => $query->max_num_pages,
        );

        // Format post data
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();

                // Get post author name
                $author_id = get_post_field('post_author', $post_id);
                $author_name = get_the_author_meta('display_name', $author_id);

                // Get post categories
                $categories = get_the_category();
                $category_names = array();
                foreach ($categories as $category) {
                    $category_names[] = $category->name;
                }

                $response['posts'][] = array(
                    'id' => $post_id,
                    'title' => get_the_title(),
                    'date' => get_the_date(),
                    'author' => $author_name,
                    'categories' => implode(', ', $category_names),
                    'post_type' => get_post_type_object(get_post_type())->labels->singular_name,
                );
            }
        }

        wp_reset_postdata();

        wp_send_json_success($response);
    }

    /**
     * AJAX handler for getting a single post by ID
     *
     * @since    1.0.0
     */
    public function ajax_get_post()
    {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wp2id_publication_ajax_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'wp2id')));
        }

        // Check if user has permission
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'wp2id')));
        }

        // Check post ID
        if (empty($_POST['post_id'])) {
            wp_send_json_error(array('message' => __('No post ID provided.', 'wp2id')));
        }

        $post_id = intval($_POST['post_id']);
        $post = get_post($post_id);

        if (!$post) {
            wp_send_json_error(array('message' => __('Post not found.', 'wp2id')));
        }

        // Get post author name
        $author_name = get_the_author_meta('display_name', $post->post_author);

        // Get post type name
        $post_type_obj = get_post_type_object($post->post_type);
        $post_type_name = $post_type_obj ? $post_type_obj->labels->singular_name : $post->post_type;

        // Build response
        $response = array(
            'id' => $post->ID,
            'title' => $post->post_title,
            'post_type' => $post_type_name,
        );

        wp_send_json_success($response);
    }

    /**
     * AJAX handler to get comprehensive post details
     * 
     * @since 1.0.0
     */
    public function ajax_get_post_details()
    {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wp2id_publication_ajax_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'wp2id')));
        }

        // Check if user has permission
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'wp2id')));
        }

        // Check post ID
        if (empty($_POST['post_id'])) {
            wp_send_json_error(array('message' => __('No post ID provided.', 'wp2id')));
        }

        $post_id = intval($_POST['post_id']);
        $post = get_post($post_id);

        if (!$post) {
            wp_send_json_error(array('message' => __('Post not found.', 'wp2id')));
        }

        // Get post thumbnail - larger size for better preview in mapping dialog
        $thumbnail = get_the_post_thumbnail($post_id, 'medium'); // Use WordPress 'medium' size (typically 300x300)
        $thumbnail_info = array();

        if (has_post_thumbnail($post_id)) {
            $thumbnail_id = get_post_thumbnail_id($post_id);
            $image_data = wp_get_attachment_image_src($thumbnail_id, 'full');
            $image_meta = wp_get_attachment_metadata($thumbnail_id);

            if ($image_data && $image_meta) {
                $width = $image_meta['width'];
                $height = $image_meta['height'];
                $ratio = $width / $height;

                if ($ratio > 1.1) {
                    $orientation = 'landscape';
                } elseif ($ratio < 0.9) {
                    $orientation = 'portrait';
                } else {
                    $orientation = 'square';
                }

                $thumbnail_info = array(
                    'width' => $width,
                    'height' => $height,
                    'ratio' => round($ratio, 2),
                    'orientation' => $orientation,
                    'url' => $image_data[0]
                );
            }
        }

        if (empty($thumbnail)) {
            $thumbnail = '<div class="no-thumbnail">' . __('No Image', 'wp2id') . '</div>';
        }

        // Get full post content as text only (no trimming, no media)
        $raw_content = get_the_content(null, false, $post_id);
        $raw_content = apply_filters('the_content', $raw_content);

        // Strip all HTML tags and shortcodes to get plain text
        $content_preview = wp_strip_all_tags($raw_content, true);
        // Remove extra whitespace and line breaks
        $content_preview = preg_replace('/\s+/', ' ', $content_preview);
        $content_preview = trim($content_preview);

        // Get post excerpt (still trimmed for brevity in excerpt section)
        $excerpt = has_excerpt($post_id) ? get_the_excerpt($post_id) : wp_trim_words($content_preview, 20);

        // Get post author
        $author_id = get_post_field('post_author', $post_id);
        $author_name = get_the_author_meta('display_name', $author_id);

        // Get post date
        $post_date = get_the_date('', $post_id);

        // Get post categories
        $categories = get_the_category($post_id);
        $category_list = array();
        if (!empty($categories)) {
            foreach ($categories as $category) {
                $category_list[] = $category->name;
            }
        }
        $categories_str = !empty($category_list) ? implode(', ', $category_list) : __('Uncategorized', 'wp2id');

        // Build response data
        $response = array(
            'id' => $post_id,
            'title' => get_the_title($post_id),
            'thumbnail' => $thumbnail,
            'thumbnail_info' => $thumbnail_info,
            'content' => $content_preview, // Full text content without HTML/media
            'excerpt' => esc_html($excerpt),
            'author' => esc_html($author_name),
            'date' => $post_date,
            'categories' => esc_html($categories_str),
            'post_type' => $post->post_type
        );

        wp_send_json_success($response);
    }

    /**
     * AJAX handler to get template tags for a publication
     * 
     * @since 1.0.0
     */
    public function ajax_get_template_tags()
    {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wp2id_publication_ajax_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'wp2id')));
        }

        // Check if user has permission
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'wp2id')));
        }

        // Check required parameters
        if (empty($_POST['publication_id']) || empty($_POST['post_id'])) {
            wp_send_json_error(array('message' => __('Missing required parameters.', 'wp2id')));
        }

        $publication_id = intval($_POST['publication_id']);
        $post_id = intval($_POST['post_id']);

        // Get comprehensive template details using new method
        $template_details = $this->get_template_details_for_mapping($publication_id);

        if (!$template_details['success']) {
            wp_send_json_error(array('message' => __('Failed to get template details. Please check your template or IDML file.', 'wp2id')));
        }

        $template_tags = $template_details['template_tags'];
        $template_tags_details = $template_details['template_tags_details'];

        // Get all mappings from ALL posts in this publication
        $central_mappings = get_post_meta($publication_id, '_wp2id_post_mappings', true);
        $current_post_mappings = array();
        $used_tags = array(); // Track all used tags across all posts

        if (is_array($central_mappings)) {
            // Get mappings for current post
            if (isset($central_mappings[$post_id])) {
                $current_post_mappings = $central_mappings[$post_id];
            }

            // Collect ALL used tags from ALL posts
            foreach ($central_mappings as $mapped_post_id => $post_mappings) {
                if (is_array($post_mappings)) {
                    foreach ($post_mappings as $element => $tag_data) {
                        if (!empty($tag_data)) {
                            // Handle different mapping structures
                            if ($element === 'content' && is_array($tag_data)) {
                                // Content element can be array of position objects
                                foreach ($tag_data as $position) {
                                    if (is_array($position) && isset($position['tag']) && !empty($position['tag'])) {
                                        // Object format: {tag: "content1", start: "", end: ""}
                                        if (!in_array($position['tag'], $used_tags)) {
                                            $used_tags[] = $position['tag'];
                                            error_log("WP2ID: Found content position tag: " . $position['tag'] . " in post " . $mapped_post_id);
                                        }
                                    } elseif (is_string($position) && !empty($position)) {
                                        // Legacy string format in array
                                        if (!in_array($position, $used_tags)) {
                                            $used_tags[] = $position;
                                            error_log("WP2ID: Found legacy content tag: " . $position . " in post " . $mapped_post_id);
                                        }
                                    }
                                }
                            } elseif (is_string($tag_data) && !empty($tag_data)) {
                                // Standard string mapping for other elements
                                if (!in_array($tag_data, $used_tags)) {
                                    $used_tags[] = $tag_data;
                                    error_log("WP2ID: Found standard tag: " . $tag_data . " for element " . $element . " in post " . $mapped_post_id);
                                }
                            }
                        }
                    }
                }
            }

            error_log("WP2ID: Total used tags across publication " . $publication_id . ": " . implode(', ', $used_tags));
        }

        // Fallback: check the position data (legacy/backup location) if no central mappings
        if (empty($current_post_mappings)) {
            $position_data = get_post_meta($publication_id, '_wp2id_publication_positions', true);
            if (is_array($position_data) && isset($position_data[$post_id]) && isset($position_data[$post_id]['tagMappings'])) {
                $current_post_mappings = $position_data[$post_id]['tagMappings'];
            }
        }

        // Send response with comprehensive template details
        wp_send_json_success(array(
            'tags' => $template_tags,
            'mappings' => $current_post_mappings,
            'used_tags' => $used_tags, // All used tags across publication
            'tags_details' => $template_tags_details,
            // Additional template details
            'template_details' => array(
                'mode' => $template_details['mode'],
                'template_id' => $template_details['template_id'],
                'template_name' => $template_details['template_name'],
                'image_tags' => $template_details['image_tags'],
                'text_tags' => $template_details['text_tags'],
                'word_count_warnings' => $template_details['word_count_warnings'],
                'has_content_analysis' => $template_details['has_content_analysis'],
                'file_info' => $template_details['file_info'],
                'extraction_timestamp' => $template_details['extraction_timestamp'],
                'total_tags' => count($template_tags),
                'total_image_tags' => count($template_details['image_tags']),
                'total_text_tags' => count($template_details['text_tags'])
            )
        ));
    }

    /**
     * Process IDML export with given parameters
     * 
     * @param int $publication_id Publication ID
     * @param int $template_id Template ID
     * @param array $post_mappings Post mappings data
     * @return array Result array with success/error status and data
     */
    private function process_idml_export($publication_id, $template_id, $post_mappings)
    {
        // Determine upload mode
        $upload_mode = get_post_meta($publication_id, '_wp2id_publication_upload_mode', true);
        if (empty($upload_mode)) {
            $upload_mode = 'template'; // Default to template mode
        }

        $template_file_id = null;
        $template_tag_system = 'tag-based'; // Always use tag-based system for publications

        if ($upload_mode === 'direct_idml') {
            // Direct IDML mode - get file from publication meta
            $template_file_id = get_post_meta($publication_id, '_wp2id_publication_direct_idml_file', true);

            if (!$template_file_id) {
                return array('success' => false, 'message' => 'Direct IDML file not found.');
            }

            WP2ID_Debug::log('Using direct IDML mode with file ID: ' . $template_file_id, 'IDML_Export');
        } else {
            // Template mode - validate template and get file
            if (!$template_id) {
                return array('success' => false, 'message' => 'Template ID required for template mode.');
            }

            // Initialize IDML Template
            require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-wp2id-idml-template.php';
            $template = new WP2ID_IDML_Template($template_id);

            // Get template tag system setting (though we force tag-based for publications)
            $template_tag_system_setting = get_post_meta($template_id, '_wp2id_template_tag_system', true);
            if (!empty($template_tag_system_setting) && $template_tag_system_setting !== 'tag-based') {
                error_log('WARNING: Publication only supports tag-based system, forcing tag-based for template: ' . $template_id);
            }

            // Get template file ID
            $template_file_id = get_post_meta($template_id, '_wp2id_template_idml_file', true);

            if (!$template_file_id) {
                return array('success' => false, 'message' => 'Template IDML file not found.');
            }

            WP2ID_Debug::log('Using template mode with template ID: ' . $template_id . ', file ID: ' . $template_file_id, 'IDML_Export');
        }

        WP2ID_Debug::log('Upload mode: ' . $upload_mode . ', Template tag system: ' . $template_tag_system, 'IDML_Export');

        // Get template file path
        $template_file_path = get_attached_file($template_file_id);

        if (!$template_file_path || !file_exists($template_file_path)) {
            return array('success' => false, 'message' => 'Template IDML file could not be accessed.');
        }

        // Create a temporary directory for processing
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/wp2id-temp-' . uniqid();

        if (!wp_mkdir_p($temp_dir)) {
            return array('success' => false, 'message' => 'Failed to create temporary directory.');
        }

        // Copy template file to temp directory
        $temp_idml_path = $temp_dir . '/' . basename($template_file_path);
        copy($template_file_path, $temp_idml_path);

        // Extract IDML file (which is a ZIP archive)
        $idml_extract_dir = $temp_dir . '/idml-extract';
        if (!wp_mkdir_p($idml_extract_dir)) {
            WP2ID_IDML_Utils::cleanup_export_temp_files($temp_dir);
            return array('success' => false, 'message' => 'Failed to create IDML extraction directory.');
        }

        // Extract IDML using WP2ID_IDML_Reader
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-wp2id-idml-reader.php';
        $idml_reader = new WP2ID_IDML_Reader($temp_idml_path);
        $extraction_result = $idml_reader->extract($idml_extract_dir);

        if (!$extraction_result) {
            WP2ID_IDML_Utils::cleanup_export_temp_files($temp_dir);
            return array('success' => false, 'message' => 'Failed to extract IDML file: ' . $idml_reader->get_last_error());
        }

        // Store extract directory in class property for use in other methods
        $this->idml_extract_dir = $idml_extract_dir;

        // Get the designmap to find story files
        $designmap = $idml_reader->read_designmap();
        if (!$designmap || empty($designmap['stories'])) {
            WP2ID_IDML_Utils::cleanup_export_temp_files($temp_dir);
            return array('success' => false, 'message' => 'Failed to read story files from IDML template.');
        }

        // Create Links directory and Links.xml structure if it doesn't exist
        WP2ID_Debug::log('Creating Links directory structure if needed', 'IDML_Export');
        WP2ID_IDML_Utils::create_links_structure($idml_extract_dir);

        // Create images directory - clean up any existing images first
        $images_dir = $temp_dir . '/images';

        // Clean up existing images directory to prevent duplicates
        if (is_dir($images_dir)) {
            WP2ID_Debug::log("Cleaning up existing images directory to prevent duplicates: {$images_dir}", 'IDML_Export');
            WP2ID_IDML_Utils::cleanup_export_temp_files($images_dir);
        }

        // Create fresh images directory
        wp_mkdir_p($images_dir);
        WP2ID_Debug::log("Created fresh images directory: {$images_dir}", 'IDML_Export');

        // Process each story file and replace content based on mappings
        $media_files = array(); // Track media files to include in ZIP

        WP2ID_Debug::log('Total stories in designmap: ' . count($designmap['stories']), 'IDML_Export');

        // Create the folder name for the export file naming
        $export_folder_name = 'wp2id-export-' . sanitize_title(get_the_title($publication_id)) . '-' . date('Y-m-d');

        // Process stories using the extracted utility functions
        $story_processing_result = WP2ID_Content_Utils::process_stories_with_mappings($designmap, $post_mappings, $idml_extract_dir, $images_dir, $media_files, $template_tag_system, $export_folder_name);

        if (!$story_processing_result['success']) {
            WP2ID_IDML_Utils::cleanup_export_temp_files($temp_dir);
            return $story_processing_result;
        }

        // Process template image replacements in spread files
        // This handles cases where template images need to be replaced with downloaded/featured images
        WP2ID_Debug::log('Processing template image replacements in spread files', 'IDML_Export');
        WP2ID_Content_Utils::process_template_image_replacements($idml_extract_dir, $media_files, $export_folder_name, $post_mappings);

        // Update metadata.xml with manifest information for linked images
        WP2ID_Debug::log('Updating metadata.xml with image manifest', 'IDML_Export');
        WP2ID_IDML_Utils::update_metadata_manifest($idml_extract_dir);

        // Create output IDML file by zipping the modified files
        $output_idml_path = $temp_dir . '/export-' . sanitize_title(get_the_title($publication_id)) . '.idml';

        if (!WP2ID_ZIP_Utils::zip_directory($idml_extract_dir, $output_idml_path)) {
            WP2ID_IDML_Utils::cleanup_export_temp_files($temp_dir);
            return array('success' => false, 'message' => 'Failed to create output IDML file.');
        }

        // Create final ZIP package with IDML and images if any
        $output_zip_path = $upload_dir['path'] . '/wp2id-export-' . sanitize_title(get_the_title($publication_id)) . '-' . date('Y-m-d') . '.zip';
        $output_zip_url = $upload_dir['url'] . '/wp2id-export-' . sanitize_title(get_the_title($publication_id)) . '-' . date('Y-m-d') . '.zip';

        $zip = new ZipArchive();

        if ($zip->open($output_zip_path, ZipArchive::CREATE) !== true) {
            WP2ID_IDML_Utils::cleanup_export_temp_files($temp_dir);
            return array('success' => false, 'message' => 'Failed to create output ZIP file.');
        }

        // Add IDML file to the ZIP
        $zip->addFile($output_idml_path, basename($output_idml_path));

        // Add media files to the ZIP
        foreach ($media_files as $original_path => $export_path) {
            if (file_exists($export_path)) {
                $zip->addFile($export_path, 'images/' . basename($export_path));
            }
        }

        $zip->close();

        // Cleanup temporary files
        WP2ID_IDML_Utils::cleanup_export_temp_files($temp_dir);

        return array(
            'success' => true,
            'message' => 'Export completed successfully.',
            'download_url' => $output_zip_url
        );
    }

    // Deprecated wrapper functions removed - use utility classes directly

    /**
     * AJAX handler for exporting a publication to IDML format
     * 
     * Handles AJAX requests for IDML export by validating input parameters
     * and delegating the actual export processing to a separate method.
     *
     * @since    1.0.0
     * @access   public
     */
    public function ajax_export_idml()
    {
        // Verify nonce for security
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wp2id_publication_ajax_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }

        // Check WP2ID specific permission for export
        if (!WP2ID_Permission_Manager::current_user_can('export_publications')) {
            wp_send_json_error(array('message' => 'You do not have permission to export publications.'));
        }

        // Validate and get publication ID
        $publication_id = isset($_POST['publication_id']) ? intval($_POST['publication_id']) : 0;
        if (!$publication_id) {
            wp_send_json_error(array('message' => 'Invalid publication ID.'));
        }

        // Detect publication mode
        $upload_mode = get_post_meta($publication_id, '_wp2id_publication_upload_mode', true);
        if (empty($upload_mode)) {
            $upload_mode = 'template'; // Default to template mode
        }

        $template_id = null;

        if ($upload_mode === 'template') {
            // Template mode - validate template ID
            $template_id = get_post_meta($publication_id, '_wp2id_publication_template_id', true);
            if (!$template_id) {
                wp_send_json_error(array('message' => 'No template associated with this publication.'));
            }
        } else {
            // Direct IDML mode - validate direct IDML file
            $direct_idml_file = get_post_meta($publication_id, '_wp2id_publication_direct_idml_file', true);
            if (!$direct_idml_file) {
                wp_send_json_error(array('message' => 'No direct IDML file associated with this publication.'));
            }

            // Verify the file exists
            $file_path = get_attached_file($direct_idml_file);
            if (!$file_path || !file_exists($file_path)) {
                wp_send_json_error(array('message' => 'Direct IDML file could not be accessed.'));
            }
        }

        // Get and validate post mappings
        $post_mappings = get_post_meta($publication_id, '_wp2id_post_mappings', true);
        if (empty($post_mappings)) {
            wp_send_json_error(array('message' => 'No content mappings found.'));
        }

        // Process IDML export with mode-appropriate parameters
        $export_result = $this->process_idml_export($publication_id, $template_id, $post_mappings);

        // Return appropriate response
        if (!$export_result['success']) {
            wp_send_json_error(array('message' => $export_result['message']));
        }

        wp_send_json_success(array(
            'message' => 'Export completed successfully.',
            'download_url' => $export_result['download_url']
        ));
    }

    /**
     * AJAX handler for previewing a publication with HTML from ZIP template
     * 
     * Handles AJAX requests for publication preview by extracting HTML from ZIP templates
     * and mapping WordPress content to HTML placeholders.
     *
     * @since    1.0.0
     * @access   public
     */
    public function ajax_preview_publication()
    {
        // Verify nonce for security (WordPress AJAX uses 'security' parameter by convention)
        $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : (isset($_POST['security']) ? $_POST['security'] : '');
        if (!$nonce || !wp_verify_nonce($nonce, 'wp2id_publication_ajax_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }

        // Check if user has permission
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'You do not have permission to perform this action.'));
        }

        // Check required parameters
        if (empty($_POST['publication_id'])) {
            wp_send_json_error(array('message' => 'Missing publication ID parameter.'));
        }

        $publication_id = intval($_POST['publication_id']);

        // Check upload mode - disable preview for direct IDML
        $upload_mode = get_post_meta($publication_id, '_wp2id_publication_upload_mode', true);
        if ($upload_mode === 'direct_idml') {
            wp_send_json_error(array('message' => 'Preview is not available for direct IDML uploads.'));
        }

        // Check required parameters
        if (empty($_POST['template_id']) || empty($_POST['post_ids']) || empty($_POST['publication_id'])) {
            wp_send_json_error(array('message' => 'Missing required parameters.'));
        }

        $template_id = intval($_POST['template_id']);
        $post_ids = array_map('intval', $_POST['post_ids']);
        $publication_id = intval($_POST['publication_id']);

        // Get template ZIP file
        $zip_file_id = get_post_meta($template_id, '_wp2id_template_zip_file', true);
        if (!$zip_file_id) {
            wp_send_json_error(array('message' => 'No ZIP template file found for this template.'));
        }

        $zip_file_path = get_attached_file($zip_file_id);
        if (!$zip_file_path || !file_exists($zip_file_path)) {
            wp_send_json_error(array('message' => 'ZIP template file not found on server.'));
        }

        // Extract template files and create accessible preview
        $preview_result = WP2ID_ZIP_Utils::create_accessible_preview($zip_file_path, $template_id, $post_ids, $publication_id);
        if (!$preview_result['success']) {
            wp_send_json_error(array('message' => $preview_result['message']));
        }

        wp_send_json_success(array(
            'preview_url' => $preview_result['preview_url'],
            'message' => 'Preview generated successfully. Opening in new window...'
        ));
    }

    /**
     * Enqueue scripts and styles for admin pages
     *
     * @param string $hook Current admin page hook
     * @since 1.0.0
     */
    public function enqueue_scripts($hook)
    {
        // Only load on publication screens
        $screen = get_current_screen();
        if ($screen->post_type !== 'wp2id-publication') {
            return;
        }

        // Enqueue work status styles for all publication screens
        wp_enqueue_style(
            $this->plugin_name . '-work-status',
            plugin_dir_url(__FILE__) . 'assets/css/wp2id-work-status.css',
            array(),
            $this->version
        );

        // Enqueue work status JavaScript for all publication screens
        wp_enqueue_script(
            $this->plugin_name . '-work-status',
            plugin_dir_url(__FILE__) . 'assets/js/wp2id-work-status.js',
            array('jquery'),
            $this->version,
            true
        );

        // Pass translations to the work status script
        wp_localize_script(
            $this->plugin_name . '-work-status',
            'wp2id_work_status',
            array(
                'labels' => array(
                    'initialization' => __('Initialization', 'wp2id'),
                    'list_created' => __('List Created', 'wp2id'),
                    'completed' => __('Completed', 'wp2id')
                )
            )
        );

        // Check if we're on the edit screen
        if ($hook === 'post-new.php' || $hook === 'post.php') {
            // Enqueue WordPress media library for direct IDML upload
            wp_enqueue_media();

            // Enqueue jQuery UI Dialog and its dependencies
            wp_enqueue_script('jquery-ui-dialog');

            // Enqueue jQuery UI CSS
            wp_enqueue_style('wp-jquery-ui-dialog');

            // Only enqueue if not already enqueued by metabox
            if (!wp_script_is($this->plugin_name . '-publication', 'enqueued')) {
                // Enqueue publication scripts
                wp_enqueue_script(
                    $this->plugin_name . '-publication',
                    plugin_dir_url(__FILE__) . '../assets/js/wp2id-publication.js',
                    array('jquery', 'jquery-ui-sortable', 'jquery-ui-dialog', 'media-upload', 'media-views'),
                    $this->version . '-' . time(), // Force cache refresh
                    true
                );

                // Pass data to the script
                wp_localize_script(
                    $this->plugin_name . '-publication',
                    'wp2id_publication_admin',
                    array(
                        'nonce' => wp_create_nonce('wp2id_publication_ajax_nonce'),
                        'ajaxurl' => admin_url('admin-ajax.php')
                    )
                );
            }
        }

        // Không cần làm gì thêm cho trang danh sách, màu chỉ áp dụng cho badge
    }

    /**
     * Customize the update messages for the publication custom post type.
     *
     * @since    1.0.0
     * @param    array    $messages    Post update messages.
     * @return   array                 Modified post update messages.
     */
    public function publication_updated_messages($messages)
    {
        $post = get_post();
        $post_type = 'wp2id-publication';
        $post_type_object = get_post_type_object($post_type);

        $messages[$post_type] = array(
            0  => '', // Unused. Messages start at index 1.
            1  => __('Publication updated.', 'wp2id'),
            2  => __('Custom field updated.', 'wp2id'),
            3  => __('Custom field deleted.', 'wp2id'),
            4  => __('Publication updated.', 'wp2id'),
            5  => isset($_GET['revision']) ? sprintf(__('Publication restored to revision from %s', 'wp2id'), wp_post_revision_title((int) $_GET['revision'], false)) : false,
            6  => __('Publication published.', 'wp2id'),
            7  => __('Publication saved.', 'wp2id'),
            8  => __('Publication submitted.', 'wp2id'),
            9  => sprintf(
                __('Publication scheduled for: <strong>%1$s</strong>.', 'wp2id'),
                date_i18n(__('M j, Y @ G:i', 'wp2id'), strtotime($post->post_date))
            ),
            10 => __('Publication draft updated.', 'wp2id')
        );

        return $messages;
    }

    /**
     * Add a new column for Work Status in the publications list table.
     *
     * @since    1.0.0
     * @param    array    $columns    Existing columns.
     * @return   array                 Modified columns.
     */
    public function add_work_status_column($columns)
    {
        $new_columns = array();

        // Insert work status column after title
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'title') {
                $new_columns['work_status'] = __('Work Status', 'wp2id');
            }
        }

        return $new_columns;
    }

    /**
     * Display the Work Status column content.
     *
     * @since    1.0.0
     * @param    string    $column    The column name.
     * @param    int       $post_id    The post ID.
     */
    public function display_work_status_column($column, $post_id)
    {
        if ($column === 'work_status') {
            $work_status = get_post_meta($post_id, '_wp2id_publication_work_status', true);

            if (empty($work_status)) {
                $work_status = 'initialization'; // Default value
            }

            $status_labels = array(
                'initialization' => __('Initialization', 'wp2id'),
                'list_created' => __('List Created', 'wp2id'),
                'completed' => __('Completed', 'wp2id')
            );

            $status_classes = array(
                'initialization' => 'wp2id-status-initialization',
                'list_created' => 'wp2id-status-list-created',
                'completed' => 'wp2id-status-completed'
            );

            echo '<span class="wp2id-work-status-badge ' . esc_attr($status_classes[$work_status]) . '">' .
                esc_html($status_labels[$work_status]) .
                '</span>';
        }
    }

    /**
     * Add a filter dropdown for work status on the publication list page
     */
    public function add_work_status_filter()
    {
        $screen = get_current_screen();

        // Only add filter to publication list page
        if (!$screen || $screen->post_type !== 'wp2id-publication') {
            return;
        }

        $selected_status = isset($_GET['work_status']) ? sanitize_text_field($_GET['work_status']) : '';
?>
        <select name="work_status">
            <option value=""><?php _e('All Work Statuses', 'wp2id'); ?></option>
            <option value="initialization" <?php selected($selected_status, 'initialization'); ?>><?php _e('Initialization', 'wp2id'); ?></option>
            <option value="list_created" <?php selected($selected_status, 'list_created'); ?>><?php _e('List Created', 'wp2id'); ?></option>
            <option value="completed" <?php selected($selected_status, 'completed'); ?>><?php _e('Completed', 'wp2id'); ?></option>
        </select>
<?php
    }

    /**
     * Modify the query to filter by work status
     * 
     * @param WP_Query $query The WordPress query object
     * @return WP_Query Modified query
     */
    public function filter_publications_by_work_status($query)
    {
        global $pagenow;

        // Only modify in admin, on publications list, and if filter is set
        if (
            is_admin() && $pagenow === 'edit.php' &&
            isset($_GET['post_type']) && $_GET['post_type'] === 'wp2id-publication' &&
            isset($_GET['work_status']) && $_GET['work_status'] !== ''
        ) {

            $work_status = sanitize_text_field($_GET['work_status']);

            // Add meta query
            $meta_query = $query->get('meta_query');
            if (!is_array($meta_query)) {
                $meta_query = array();
            }

            $meta_query[] = array(
                'key' => '_wp2id_publication_work_status',
                'value' => $work_status,
                'compare' => '='
            );

            $query->set('meta_query', $meta_query);
        }

        return $query;
    }

    /**
     * AJAX handler for extracting tags from direct IDML upload
     *
     * @since    1.0.0
     */
    public function ajax_extract_direct_idml_tags()
    {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wp2id_publication_ajax_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'wp2id')));
        }

        // Check if user has permission
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'wp2id')));
        }

        // Check required parameters
        if (empty($_POST['publication_id']) || empty($_POST['idml_file_id'])) {
            wp_send_json_error(array('message' => __('Missing required parameters.', 'wp2id')));
        }

        $publication_id = intval($_POST['publication_id']);
        $idml_file_id = intval($_POST['idml_file_id']);

        // Use the new extraction method
        if (!$this->extract_and_save_direct_idml_tags($publication_id, $idml_file_id)) {
            wp_send_json_error(array('message' => __('Failed to extract tags from IDML file. Please check the file and try again.', 'wp2id')));
        }

        // Get the extracted tags
        $tags = get_post_meta($publication_id, '_wp2id_publication_direct_idml_tags', true);
        $detailed_tags = get_post_meta($publication_id, '_wp2id_publication_direct_idml_tags_details', true);

        wp_send_json_success(array(
            'message' => __('Tags extracted successfully', 'wp2id'),
            'tags_count' => count($tags),
            'tags' => $tags,
            'detailed_tags' => $detailed_tags
        ));
    }

    /**
     * Get comprehensive template details for mapping (supports both template and direct IDML modes)
     * 
     * @since 1.0.0
     * @param int $publication_id Publication ID
     * @return array Template details with tags, detailed information, and metadata
     */
    private function get_template_details_for_mapping($publication_id)
    {
        $template_details = array(
            'success' => false,
            'mode' => 'template',
            'template_id' => null,
            'template_name' => '',
            'template_tags' => array(),
            'template_tags_details' => array(),
            'word_count_warnings' => array(),
            'image_tags' => array(),
            'text_tags' => array(),
            'has_content_analysis' => false,
            'file_info' => array(),
            'extraction_timestamp' => null
        );

        // Check upload mode
        $upload_mode = get_post_meta($publication_id, '_wp2id_publication_upload_mode', true);
        if (empty($upload_mode)) {
            $upload_mode = 'template'; // Default to template mode
        }

        $template_details['mode'] = $upload_mode;

        if ($upload_mode === 'direct_idml') {
            // Direct IDML mode - get template details from publication meta
            $template_details['success'] = $this->get_direct_idml_template_details($publication_id, $template_details);
        } else {
            // Template mode - get template details from template
            $template_details['success'] = $this->get_template_mode_details($publication_id, $template_details);
        }

        return $template_details;
    }

    /**
     * Get template details for direct IDML mode
     * 
     * @since 1.0.0
     * @param int $publication_id Publication ID
     * @param array &$template_details Template details array to populate
     * @return bool Success status
     */
    private function get_direct_idml_template_details($publication_id, &$template_details)
    {
        // Get direct IDML file info
        $file_id = get_post_meta($publication_id, '_wp2id_publication_direct_idml_file', true);
        if (!$file_id) {
            return false;
        }

        $file_path = get_attached_file($file_id);
        if (!$file_path || !file_exists($file_path)) {
            return false;
        }

        // Get file information
        $template_details['file_info'] = array(
            'file_id' => $file_id,
            'file_path' => $file_path,
            'file_name' => basename($file_path),
            'file_size' => filesize($file_path),
            'file_url' => wp_get_attachment_url($file_id),
            'upload_date' => get_post_field('post_date', $file_id)
        );

        $template_details['template_name'] = 'Direct IDML: ' . basename($file_path);

        // Get extracted tags
        $template_tags = get_post_meta($publication_id, '_wp2id_publication_direct_idml_tags', true);
        $template_tags_details = get_post_meta($publication_id, '_wp2id_publication_direct_idml_tags_details', true);

        if (empty($template_tags)) {
            // Try to extract tags if not already done
            if (!$this->extract_and_save_direct_idml_tags($publication_id, $file_id)) {
                return false;
            }

            // Re-fetch after extraction
            $template_tags = get_post_meta($publication_id, '_wp2id_publication_direct_idml_tags', true);
            $template_tags_details = get_post_meta($publication_id, '_wp2id_publication_direct_idml_tags_details', true);
        }

        if (empty($template_tags)) {
            return false;
        }

        // Deserialize if needed
        if (is_serialized($template_tags)) {
            $template_tags = maybe_unserialize($template_tags);
        }

        // Process template tags
        $template_details['template_tags'] = $template_tags;
        $template_details['template_tags_details'] = is_array($template_tags_details) ? $template_tags_details : array();
        $template_details['has_content_analysis'] = !empty($template_tags_details);

        // Extract tag type information
        $this->analyze_template_tags($template_details);

        // Get extraction timestamp
        $template_details['extraction_timestamp'] = get_post_meta($publication_id, '_wp2id_direct_idml_extraction_timestamp', true);

        return true;
    }

    /**
     * Get template details for template mode
     * 
     * @since 1.0.0
     * @param int $publication_id Publication ID
     * @param array &$template_details Template details array to populate
     * @return bool Success status
     */
    private function get_template_mode_details($publication_id, &$template_details)
    {
        // Get template ID
        $template_id = get_post_meta($publication_id, '_wp2id_publication_template_id', true);
        if (!$template_id) {
            return false;
        }

        $template_details['template_id'] = $template_id;
        $template_details['template_name'] = get_the_title($template_id);

        // Get template tags
        $template_tags = get_post_meta($template_id, '_wp2id_template_tags', true);
        $template_tags_details = get_post_meta($template_id, '_wp2id_template_tags_details', true);

        if (empty($template_tags)) {
            return false;
        }

        // Deserialize if needed
        if (is_serialized($template_tags)) {
            $template_tags = maybe_unserialize($template_tags);
        }

        // Process template tags
        $template_details['template_tags'] = $template_tags;
        $template_details['template_tags_details'] = is_array($template_tags_details) ? $template_tags_details : array();
        $template_details['has_content_analysis'] = !empty($template_tags_details);

        // Get template file info
        $template_file_id = get_post_meta($template_id, '_wp2id_template_idml_file', true);
        if ($template_file_id) {
            $template_details['file_info'] = array(
                'file_id' => $template_file_id,
                'file_path' => get_attached_file($template_file_id),
                'file_name' => basename(get_attached_file($template_file_id)),
                'file_size' => filesize(get_attached_file($template_file_id)),
                'file_url' => wp_get_attachment_url($template_file_id),
                'upload_date' => get_post_field('post_date', $template_file_id)
            );
        }

        // Extract tag type information
        $this->analyze_template_tags($template_details);

        return true;
    }

    /**
     * Analyze template tags and categorize them
     * 
     * @since 1.0.0
     * @param array &$template_details Template details array to populate
     */
    private function analyze_template_tags(&$template_details)
    {
        $image_tags = array();
        $text_tags = array();
        $word_count_warnings = array();

        foreach ($template_details['template_tags'] as $tag_name) {
            $tag_details = isset($template_details['template_tags_details'][$tag_name])

                ? $template_details['template_tags_details'][$tag_name]
                : array();

            // Determine tag type
            $tag_type = 'text'; // Default
            if (!empty($tag_details['type'])) {
                $tag_type = $tag_details['type'];
            } else {
                // Fallback: determine by tag name patterns
                if (preg_match('/^(image|img|photo|picture|pic)/i', $tag_name)) {
                    $tag_type = 'image';
                }
            }

            // Categorize tags
            if ($tag_type === 'image') {
                $image_tags[] = array(
                    'name' => $tag_name,
                    'details' => $tag_details
                );
            } else {
                $text_tags[] = array(
                    'name' => $tag_name,
                    'details' => $tag_details
                );

                // Check for word count warnings
                if (!empty($tag_details['word_count']) && $tag_details['word_count'] > 0) {
                    $word_count_warnings[$tag_name] = array(
                        'current_word_count' => $tag_details['word_count'],
                        'current_length' => isset($tag_details['length']) ? $tag_details['length'] : 0,
                        'content_preview' => isset($tag_details['content']) ? wp_trim_words($tag_details['content'], 10) : ''
                    );
                }
            }
        }

        $template_details['image_tags'] = $image_tags;
        $template_details['text_tags'] = $text_tags;
        $template_details['word_count_warnings'] = $word_count_warnings;
    }

    /**
     * Extract and save tags from direct IDML file
     * 
     * @since 1.0.0
     * @param int $publication_id Publication ID
     * @param int $file_id IDML file ID
     * @return bool Success status
     */
    private function extract_and_save_direct_idml_tags($publication_id, $file_id)
    {
        $file_path = get_attached_file($file_id);
        if (!$file_path || !file_exists($file_path)) {
            return false;
        }

        try {
            // Extract tags using IDML Reader
            require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-wp2id-idml-reader.php';
            $reader = new WP2ID_IDML_Reader($file_path);

            // Extract the IDML file
            if (!$reader->extract()) {
                return false;
            }

            // Read all stories from the IDML document
            $stories = $reader->read_all_stories();
            if (!$stories) {
                $reader->cleanup();
                return false;
            }

            // Read all spreads (for image processing)
            $spreads = $reader->read_all_spreads();
            if (!$spreads) {
                $spreads = array(); // Continue without spreads if they can't be read
            }

            // Extract tags using tag-based system
            $tags = $reader->extract_custom_tags($stories, 'tag-based', $spreads);
            $detailed_tags = $reader->extract_custom_tags_detailed($stories, 'tag-based', $spreads);

            // Clean up temporary files
            $reader->cleanup();

            if (empty($tags)) {
                return false;
            }

            // Save extracted tags to publication meta
            update_post_meta($publication_id, '_wp2id_publication_direct_idml_tags', $tags);
            update_post_meta($publication_id, '_wp2id_publication_direct_idml_tags_details', $detailed_tags);
            update_post_meta($publication_id, '_wp2id_direct_idml_extraction_timestamp', current_time('timestamp'));

            return true;
        } catch (Exception $e) {
            WP2ID_Debug::log('Error extracting direct IDML tags: ' . $e->getMessage(), 'Publication');
            return false;
        }
    }

    /**
     * AJAX handler to get comprehensive template details for mapping
     * 
     * @since 1.0.0
     */
    public function ajax_get_template_details_for_mapping()
    {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wp2id_publication_ajax_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'wp2id')));
        }

        // Check if user has permission
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'wp2id')));
        }

        // Check required parameters
        if (empty($_POST['publication_id'])) {
            wp_send_json_error(array('message' => __('Missing publication ID parameter.', 'wp2id')));
        }

        $publication_id = intval($_POST['publication_id']);

        // Get comprehensive template details
        $template_details = $this->get_template_details_for_mapping($publication_id);

        if (!$template_details['success']) {
            wp_send_json_error(array('message' => __('Failed to get template details. Please check your template or IDML file.', 'wp2id')));
        }

        // Return comprehensive template information
        wp_send_json_success(array(
            'mode' => $template_details['mode'],
            'template_id' => $template_details['template_id'],
            'template_name' => $template_details['template_name'],
            'tags' => $template_details['template_tags'],
            'tags_details' => $template_details['template_tags_details'],
            'image_tags' => $template_details['image_tags'],
            'text_tags' => $template_details['text_tags'],
            'word_count_warnings' => $template_details['word_count_warnings'],
            'has_content_analysis' => $template_details['has_content_analysis'],
            'file_info' => $template_details['file_info'],
            'extraction_timestamp' => $template_details['extraction_timestamp'],
            'total_tags' => count($template_details['template_tags']),
            'total_image_tags' => count($template_details['image_tags']),
            'total_text_tags' => count($template_details['text_tags'])
        ));
    }
}
