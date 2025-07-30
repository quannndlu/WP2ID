<?php
/**
 * The template-specific functionality of the plugin.
 *
 * @since      1.0.0
 * @package    WP2ID
 * @subpackage WP2ID/admin
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * The template-specific functionality of the plugin.
 *
 * Defines the custom post type for templates, including admin-only access,
 * title support, and metabox implementation.
 *
 * @package    WP2ID
 * @subpackage WP2ID/admin
 */
class WP2ID_Template {

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
    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        
        // Add filter to allow IDML file upload
        add_filter('upload_mimes', array($this, 'add_custom_mime_types'));
        
        // Add action to enqueue admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts_styles'));
    }
    
    /**
     * Enqueue scripts and styles for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_admin_scripts_styles($hook) {
        global $post;
        
        // Only enqueue on template edit page
        if ($hook == 'post.php' || $hook == 'post-new.php') {
            if (isset($post) && $post->post_type === 'wp2id-template') {
                // Enqueue admin CSS
                wp_enqueue_style(
                    $this->plugin_name, 
                    plugin_dir_url( dirname( __FILE__ ) ) . 'assets/css/wp2id-admin.css',
                    array(),
                    $this->version,
                    'all'
                );
                
                // Enqueue admin JS
                wp_enqueue_script(
                    $this->plugin_name,
                    plugin_dir_url( dirname( __FILE__ ) ) . 'assets/js/wp2id-admin.js',
                    array('jquery'),
                    $this->version,
                    false
                );
            }
        }
    }

    /**
     * Add custom MIME types to allowed file uploads
     *
     * @since    1.0.0
     * @param    array    $mimes    The current allowed MIME types
     * @return   array              Modified allowed MIME types
     */
    public function add_custom_mime_types($mimes) {
        // Add IDML file MIME type
        $mimes['idml'] = 'application/octet-stream';
        
        return $mimes;
    }

    /**
     * Register the custom post type for templates.
     *
     * @since    1.0.0
     */
    public function register_cpt_template() {
        $labels = array(
            'name'                  => _x( 'Templates', 'Post Type General Name', 'wp2id' ),
            'singular_name'         => _x( 'Template', 'Post Type Singular Name', 'wp2id' ),
            'menu_name'             => __( 'All Templates', 'wp2id' ),
            'name_admin_bar'        => __( 'Template', 'wp2id' ),
            'archives'              => __( 'Template Archives', 'wp2id' ),
            'attributes'            => __( 'Template Attributes', 'wp2id' ),
            'parent_item_colon'     => __( 'Parent Template:', 'wp2id' ),
            'all_items'             => __( 'All Templates', 'wp2id' ),
            'add_new_item'          => __( 'Add New Template', 'wp2id' ),
            'add_new'               => __( 'Add New', 'wp2id' ),
            'new_item'              => __( 'New Template', 'wp2id' ),
            'edit_item'             => __( 'Edit Template', 'wp2id' ),
            'update_item'           => __( 'Update Template', 'wp2id' ),
            'view_item'             => __( 'View Template', 'wp2id' ),
            'view_items'            => __( 'View Templates', 'wp2id' ),
            'search_items'          => __( 'Search Template', 'wp2id' ),
            'not_found'             => __( 'Not found', 'wp2id' ),
            'not_found_in_trash'    => __( 'Not found in Trash', 'wp2id' ),
            'featured_image'        => __( 'Featured Image', 'wp2id' ),
            'set_featured_image'    => __( 'Set featured image', 'wp2id' ),
            'remove_featured_image' => __( 'Remove featured image', 'wp2id' ),
            'use_featured_image'    => __( 'Use as featured image', 'wp2id' ),
            'insert_into_item'      => __( 'Insert into template', 'wp2id' ),
            'uploaded_to_this_item' => __( 'Uploaded to this template', 'wp2id' ),
            'items_list'            => __( 'Templates list', 'wp2id' ),
            'items_list_navigation' => __( 'Templates list navigation', 'wp2id' ),
            'filter_items_list'     => __( 'Filter templates list', 'wp2id' ),
        );
        
        $capabilities = array(
            'edit_post'             => 'manage_options',
            'read_post'             => 'manage_options',
            'delete_post'           => 'manage_options',
            'edit_posts'            => 'manage_options',
            'edit_others_posts'     => 'manage_options',
            'delete_posts'          => 'manage_options',
            'publish_posts'         => 'manage_options',
            'read_private_posts'    => 'manage_options',
        );
        
        $args = array(
            'label'                 => __( 'Template', 'wp2id' ),
            'description'           => __( 'Template for WP2ID', 'wp2id' ),
            'labels'                => $labels,
            'supports'              => array( 'title', 'custom-fields' ),
            'hierarchical'          => false,
            'public'                => false,
            'show_ui'               => true,
            'show_in_menu'          => 'wp2id-dashboard', // Show under WP2ID Dashboard menu
            'show_in_admin_bar'     => true,
            'show_in_nav_menus'     => false,
            'can_export'            => true,
            'has_archive'           => false,
            'exclude_from_search'   => true,
            'publicly_queryable'    => false,
            'capabilities'          => $capabilities,
            'show_in_rest'          => false,
        );
        
        register_post_type( 'wp2id-template', $args );
    }

    /**
     * Register meta boxes for the template custom post type.
     *
     * @since    1.0.0
     */
    public function register_template_metaboxes() {
        add_meta_box(
            'wp2id_template_data',
            __( 'Template Data', 'wp2id' ),
            array( $this, 'render_template_metabox' ),
            'wp2id-template',
            'normal',
            'high'
        );
        
        // Add a metabox to show extracted tags (only on edit screen, not on new template screen)
        global $pagenow;
        if ( $pagenow === 'post.php' ) {
            add_meta_box(
                'wp2id_template_tags',
                __( 'Extracted Tags', 'wp2id' ),
                array( $this, 'render_tags_metabox' ),
                'wp2id-template',
                'normal',
                'default'
            );
            
            // Add a metabox to show detailed tag information
            add_meta_box(
                'wp2id_template_tags_details',
                __( 'Tag Details & Content Analysis', 'wp2id' ),
                array( $this, 'render_tags_details_metabox' ),
                'wp2id-template',
                'normal',
                'default'
            );
        }
    }

    /**
     * Render the template metabox.
     *
     * @since    1.0.0
     * @param    WP_Post    $post    The post object.
     */
    public function render_template_metabox( $post ) {
        // Add nonce for security and authentication.
        wp_nonce_field( 'wp2id_template_nonce_action', 'wp2id_template_nonce' );

        // Retrieve existing values from the database.
        $idml_file_id = get_post_meta( $post->ID, '_wp2id_template_idml_file', true );
        $zip_file_id = get_post_meta( $post->ID, '_wp2id_template_zip_file', true );
        $tag_system = get_post_meta( $post->ID, '_wp2id_template_tag_system', true );
        
        // Set default value for tag system if not set
        if (empty($tag_system)) {
            $tag_system = 'tag-based'; // Default to tag-based system
        }
        
        // Get file URLs if IDs exist
        $idml_file_url = $idml_file_id ? wp_get_attachment_url( $idml_file_id ) : '';
        $zip_file_url = $zip_file_id ? wp_get_attachment_url( $zip_file_id ) : '';
        
        // Get file names if IDs exist
        $idml_file_name = $idml_file_id ? basename( get_attached_file( $idml_file_id ) ) : __( 'No file selected', 'wp2id' );
        $zip_file_name = $zip_file_id ? basename( get_attached_file( $zip_file_id ) ) : __( 'No file selected', 'wp2id' );
        
        // Include WordPress media scripts
        wp_enqueue_media();
        
        // Display the form, using the current values.
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="wp2id_template_idml_file"><?php _e( 'IDML File', 'wp2id' ); ?></label>
                </th>
                <td>
                    <div class="wp2id-file-upload">
                        <input type="hidden" name="wp2id_template_idml_file" id="wp2id_template_idml_file" value="<?php echo esc_attr( $idml_file_id ); ?>" />
                        <div class="file-info">
                            <span class="file-name"><?php echo esc_html( $idml_file_name ); ?></span>
                            <?php if ( $idml_file_url ) : ?>
                                <a href="<?php echo esc_url( $idml_file_url ); ?>" target="_blank" class="button button-small">
                                    <?php _e( 'Download', 'wp2id' ); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="button wp2id-upload-file" data-input-id="wp2id_template_idml_file" data-title="<?php _e( 'Select IDML File', 'wp2id' ); ?>" data-file-type="idml">
                            <?php _e( 'Select File', 'wp2id' ); ?>
                        </button>
                        <button type="button" class="button wp2id-remove-file" data-input-id="wp2id_template_idml_file" <?php echo empty( $idml_file_id ) ? 'style="display:none;"' : ''; ?>>
                            <?php _e( 'Remove File', 'wp2id' ); ?>
                        </button>
                        <p class="description"><?php _e( 'Select an IDML file from the media library.', 'wp2id' ); ?></p>
                    </div>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="wp2id_template_zip_file"><?php _e( 'ZIP File', 'wp2id' ); ?></label>
                </th>
                <td>
                    <div class="wp2id-file-upload">
                        <input type="hidden" name="wp2id_template_zip_file" id="wp2id_template_zip_file" value="<?php echo esc_attr( $zip_file_id ); ?>" />
                        <div class="file-info">
                            <span class="file-name"><?php echo esc_html( $zip_file_name ); ?></span>
                            <?php if ( $zip_file_url ) : ?>
                                <a href="<?php echo esc_url( $zip_file_url ); ?>" target="_blank" class="button button-small">
                                    <?php _e( 'Download', 'wp2id' ); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="button wp2id-upload-file" data-input-id="wp2id_template_zip_file" data-title="<?php _e( 'Select ZIP File', 'wp2id' ); ?>" data-file-type="zip">
                            <?php _e( 'Select File', 'wp2id' ); ?>
                        </button>
                        <button type="button" class="button wp2id-remove-file" data-input-id="wp2id_template_zip_file" <?php echo empty( $zip_file_id ) ? 'style="display:none;"' : ''; ?>>
                            <?php _e( 'Remove File', 'wp2id' ); ?>
                        </button>
                        <p class="description"><?php _e( 'Select a ZIP file from the media library.', 'wp2id' ); ?></p>
                    </div>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="wp2id_template_tag_system"><?php _e( 'Tag System', 'wp2id' ); ?></label>
                </th>
                <td>
                    <div class="wp2id-tag-system">
                        <select name="wp2id_template_tag_system" id="wp2id_template_tag_system">
                            <option value="tag-based" <?php selected( $tag_system, 'tag-based' ); ?>><?php _e( 'Tag-Based System', 'wp2id' ); ?></option>
                        </select>
                        <button type="button" id="wp2id_extract_tags" class="button button-secondary" style="margin-left: 10px;">
                            <?php _e( 'Extract Tags', 'wp2id' ); ?>
                        </button>
                        <div id="wp2id_extract_tags_result" style="margin-top: 10px;"></div>
                        <p class="description"><?php _e( 'Uses InDesign\'s native XML tag structure for content extraction from the IDML template.', 'wp2id' ); ?></p>
                    </div>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render the tags metabox.
     *
     * @since    1.0.0
     * @param    WP_Post    $post    The post object.
     */
    public function render_tags_metabox( $post ) {
        // Check if the post has an IDML file
        $idml_file_id = get_post_meta( $post->ID, '_wp2id_template_idml_file', true );
        if ( ! $idml_file_id ) {
            echo '<p>' . __( 'No IDML file has been uploaded for this template.', 'wp2id' ) . '</p>';
            return;
        }
        
        $idml_file_path = get_attached_file( $idml_file_id );
        if ( ! $idml_file_path || ! file_exists( $idml_file_path ) ) {
            echo '<p>' . __( 'IDML file not found. Please re-upload the file.', 'wp2id' ) . '</p>';
            return;
        }
        
        // Load the necessary classes
        if ( ! class_exists( 'WP2ID_IDML_Template' ) ) {
            require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp2id-idml-template.php';
        }
        
        // Initialize the template object
        $template = new WP2ID_IDML_Template();
        $template->set_template_id( $post->ID );
        
        // Get tags from database (or extract if empty)
        $tags = $template->get_template_tags( true );
        
        if ( empty( $tags ) && $template->get_last_error() ) {
            echo '<p class="error">' . __( 'Error extracting tags from the IDML file:', 'wp2id' ) . ' ' . esc_html( $template->get_last_error() ) . '</p>';
            return;
        }
        
        // Get the tag system
        $tag_system = get_post_meta( $post->ID, '_wp2id_template_tag_system', true );
        if ( empty( $tag_system ) ) {
            $tag_system = 'tag-based'; // Default to tag-based system
        }
        
        // Display the tag system and the tags
        ?>
        <div class="wp2id-tags-preview">
            <p>
                <strong><?php _e( 'Tag System:', 'wp2id' ); ?></strong> 
                <?php _e( 'Tag-Based System', 'wp2id' ); ?>
            </p>
            
            <?php if ( empty( $tags ) ) : ?>
                <p><?php _e( 'No tags found in the IDML file using the selected tag system.', 'wp2id' ); ?></p>
            <?php else : ?>
                <h4><?php _e( 'Found Tags:', 'wp2id' ); ?> <span class="tag-count">(<?php echo count( $tags ); ?>)</span></h4>
                <ul class="wp2id-tags-list">
                    <?php foreach ( $tags as $tag ) : ?>
                        <li><code><?php echo esc_html( $tag ); ?></code></li>
                    <?php endforeach; ?>
                </ul>
                <p class="description">
                    <?php _e( 'These tags are saved in the database and will be used when generating content from this template.', 'wp2id' ); ?>
                </p>
            <?php endif; ?>
            
            <p>
                <?php _e( 'Click "Extract Tags" button to re-extract tags from the IDML file and update the database.', 'wp2id' ); ?>
            </p>
            
            <p class="description">
                <?php _e( 'These tags were extracted from the IDML file based on your selected tag system. You can change the tag system in the Template Data section above.', 'wp2id' ); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Render the detailed tags metabox for content analysis.
     *
     * @since    1.0.0
     * @param    WP_Post    $post    The post object.
     */
    public function render_tags_details_metabox( $post ) {
        // Check if the post has an IDML file
        $idml_file_id = get_post_meta( $post->ID, '_wp2id_template_idml_file', true );
        if ( ! $idml_file_id ) {
            echo '<p>' . __( 'No IDML file has been uploaded for this template.', 'wp2id' ) . '</p>';
            return;
        }
        
        $idml_file_path = get_attached_file( $idml_file_id );
        if ( ! $idml_file_path || ! file_exists( $idml_file_path ) ) {
            echo '<p>' . __( 'IDML file not found. Please re-upload the file.', 'wp2id' ) . '</p>';
            return;
        }
        
        // Get detailed tags from database
        $detailed_tags = get_post_meta( $post->ID, '_wp2id_template_tags_details', true );
        
        if ( empty( $detailed_tags ) ) {
            echo '<div class="wp2id-tags-details-empty">';
            echo '<p>' . __( 'No detailed tag information available. This data is generated when tags are extracted from the IDML file.', 'wp2id' ) . '</p>';
            echo '<p>' . __( 'Click the "Extract Tags" button in the Template Data section above to generate detailed tag information.', 'wp2id' ) . '</p>';
            echo '</div>';
            return;
        }
        
        // Display the detailed tags information directly
        // error_log(print_r ($detailed_tags, true) );
        ?>
        <div class="wp2id-tags-details">
            <h4><?php _e( 'Detailed Tag Information:', 'wp2id' ); ?> <span class="tag-count">(<?php echo count( $detailed_tags ); ?> tags)</span></h4>
            
            <div class="wp2id-tags-details-table">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th class="tag-name-col"><?php _e( 'Tag Name', 'wp2id' ); ?></th>
                            <th class="tag-id-col"><?php _e( 'Source Story / Internal ID', 'wp2id' ); ?></th>
                            <th class="tag-type-col"><?php _e( 'Type', 'wp2id' ); ?></th>
                            <th class="tag-length-col"><?php _e( 'Length / Words', 'wp2id' ); ?></th>
                            <th class="tag-page-col"><?php _e( 'Page(s)', 'wp2id' ); ?></th>
                            <th class="tag-content-col"><?php _e( 'Content Preview', 'wp2id' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $detailed_tags as $tag_name => $details ) : ?>
                            <tr>
                                <td class="tag-name">
                                    <strong><?php echo esc_html( $tag_name ); ?></strong>
                                </td>
                                <td class="tag-id">
                                    <?php 
                                    // Display source story and internal ID if available
                                    $source_story = isset( $details['source_story'] ) ? $details['source_story'] : '-';
                                    $internal_id = isset( $details['internal_id'] ) && ! empty( $details['internal_id'] ) ? $details['internal_id'] : '';
                                    
                                    if ( $internal_id ) {
                                        echo '<code class="id-tag">' . esc_html( $source_story ) . '/' . esc_html( $internal_id ) . '</code>';
                                    } else {
                                        echo '<code class="id-tag">' . esc_html( $source_story ) . '</code>';
                                    }
                                    ?>
                                </td>
                                <td class="tag-type">
                                    <?php $tag_type = isset( $details['type'] ) ? $details['type'] : 'text'; ?>
                                    <span class="tag-type-badge tag-type-<?php echo esc_attr( strtolower( $tag_type ) ); ?>">
                                        <?php echo esc_html( ucfirst( $tag_type ) ); ?>
                                    </span>
                                </td>
                                <td class="tag-length">
                                    <?php 
                                    $char_count = isset( $details['length'] ) ? esc_html( $details['length'] ) : '0';
                                    $word_count = isset( $details['word_count'] ) ? esc_html( $details['word_count'] ) : '0';
                                    $tag_type = isset( $details['type'] ) ? $details['type'] : 'text';
                                    
                                    echo $char_count . ' chars';
                                    if ( $tag_type !== 'image' && intval( $word_count ) > 0 ) {
                                        echo '<br><small>' . $word_count . ' words</small>';
                                    }
                                    ?>
                                </td>
                                <td class="tag-page">
                                    <?php 
                                    if ( isset( $details['page_info'] ) && ! empty( $details['page_info']['page_numbers'] ) ) {
                                        $pages = array_unique( $details['page_info']['page_numbers'] );
                                        sort( $pages );
                                        if ( count( $pages ) > 3 ) {
                                            echo esc_html( $pages[0] ) . '-' . esc_html( end( $pages ) );
                                        } else {
                                            echo esc_html( implode( ', ', $pages ) );
                                        }
                                    } else {
                                        echo '<span class="no-page-info">-</span>';
                                    }
                                    ?>
                                </td>
                                <td class="tag-content">
                                    <?php 
                                    $content = isset( $details['content'] ) ? $details['content'] : '';
                                    $tag_type = isset( $details['type'] ) ? $details['type'] : 'text';
                                    
                                    if ( $tag_type === 'image' && empty( $content ) ) {
                                        echo '<span class="image-placeholder">🖼️ ' . __( 'Image placeholder', 'wp2id' ) . '</span>';
                                    } elseif ( !empty( $content ) ) {
                                        $preview = strlen( $content ) > 80 ? substr( $content, 0, 80 ) . '...' : $content;
                                        echo '<span class="content-preview">' . esc_html( $preview ) . '</span>';
                                    } else {
                                        echo '<span class="no-content">-</span>';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="wp2id-tags-details-footer">
                <p class="description">
                    <?php _e( 'This table shows all extracted tags from the IDML template. Each tag represents content that can be replaced when generating documents.', 'wp2id' ); ?>
                </p>
                <p class="description">
                    <?php _e( 'Tag Name: The identifier for the content. Source Story/ID: Shows the internal story reference. Type: "text" for paragraphs, "image" for image placeholders. Length/Words: Character count and word count for text content. Page(s): Shows which page(s) in the IDML document contain this tag.', 'wp2id' ); ?>
                </p>
            </div>
        </div>
        
        <style>
        .wp2id-tags-details-table {
            margin: 15px 0;
        }
        
        .wp2id-tags-details-table .wp-list-table {
            border-radius: 4px;
            overflow: hidden;
        }
        
        .wp2id-tags-details-table th {
            font-weight: 600;
            background: #f7f7f7;
        }
        
        .tag-name-col { width: 20%; }
        .tag-id-col { width: 15%; }
        .tag-type-col { width: 10%; }
        .tag-length-col { width: 12%; }
        .tag-page-col { width: 10%; }
        .tag-content-col { width: 33%; }
        
        .tag-name strong {
            color: #0073aa;
            font-weight: 600;
        }
        
        .id-tag {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 500;
        }
        
        .meaningful-tag {
            color: #999;
            font-style: italic;
        }
        
        .tag-type-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .tag-type-text {
            background: #e7f3ff;
            color: #0073aa;
        }
        
        .tag-type-image {
            background: #f0f6ff;
            color: #0a4b78;
        }
        
        .tag-length {
            font-weight: 500;
            color: #666;
        }
        
        .tag-length small {
            color: #999;
            font-weight: normal;
        }
        
        .tag-page {
            color: #666;
            font-weight: 500;
        }
        
        .no-page-info {
            color: #ccc;
            font-style: italic;
        }
        
        .content-preview {
            font-size: 12px;
            color: #333;
            line-height: 1.4;
        }
        
        .image-placeholder {
            color: #0073aa;
            font-size: 12px;
            font-weight: 500;
        }
        
        .no-content {
            color: #999;
            font-style: italic;
        }
        
        .wp2id-tags-details-empty {
            padding: 20px;
            text-align: center;
            background: #f9f9f9;
            border: 1px dashed #ddd;
            border-radius: 4px;
        }
        
        .tag-count {
            color: #0073aa;
            font-weight: normal;
        }
        </style>
        <?php
    }

    /**
     * Save the meta box data.
     *
     * @since    1.0.0
     * @param    int       $post_id    The post ID.
     */
    public function save_template_metabox( $post_id ) {
        error_log(__METHOD__ . ' called for post ID: ' . $post_id);
        // Check if nonce is valid.
        if ( ! isset( $_POST['wp2id_template_nonce'] ) || ! wp_verify_nonce( $_POST['wp2id_template_nonce'], 'wp2id_template_nonce_action' ) ) {
            return;
        }

        // Check if user has permissions to save data.
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Check if not an autosave.
        if ( wp_is_post_autosave( $post_id ) ) {
            return;
        }

        // Check if not a revision.
        if ( wp_is_post_revision( $post_id ) ) {
            return;
        }

        // Check post type
        if ( get_post_type( $post_id ) !== 'wp2id-template' ) {
            return;
        }

        // Save IDML file ID
        if ( isset( $_POST['wp2id_template_idml_file'] ) ) {
            $idml_file_id = absint( $_POST['wp2id_template_idml_file'] );
            update_post_meta( $post_id, '_wp2id_template_idml_file', $idml_file_id );
        }

        // Save ZIP file ID
        if ( isset( $_POST['wp2id_template_zip_file'] ) ) {
            $zip_file_id = absint( $_POST['wp2id_template_zip_file'] );
            update_post_meta( $post_id, '_wp2id_template_zip_file', $zip_file_id );
        }

        // Save tag system
        if ( isset( $_POST['wp2id_template_tag_system'] ) ) {
            $tag_system = sanitize_text_field( $_POST['wp2id_template_tag_system'] );
            $old_tag_system = get_post_meta( $post_id, '_wp2id_template_tag_system', true );
            update_post_meta( $post_id, '_wp2id_template_tag_system', $tag_system );
            
            // If tag system has changed or IDML file has changed, re-extract tags
            if ( $tag_system !== $old_tag_system || isset( $_POST['wp2id_template_idml_file'] ) ) {
                // Get the IDML file path
                $idml_file_id = get_post_meta( $post_id, '_wp2id_template_idml_file', true );
                $idml_file_path = get_attached_file( $idml_file_id );
                
                if ( $idml_file_path && file_exists( $idml_file_path ) ) {
                    // Load the necessary classes
                    if ( ! class_exists( 'WP2ID_IDML_Template' ) ) {
                        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp2id-idml-template.php';
                    }
                    
                    // Initialize the template object
                    $template = new WP2ID_IDML_Template();
                    $template->set_template_id( $post_id );
                    
                    // Extract and save tags
                    $tags = $template->extract_template_tags( true );
                    error_log( 'Tags re-extracted after tag system change. Found ' . count( $tags ) . ' tags.' );
                }
            }
        }
        error_log( 'Template metabox data saved for post ID: ' . $post_id );
    }

    /**
     * Add custom columns to the template list table.
     *
     * @since    1.0.0
     * @param    array    $columns    The current columns.
     * @return   array                Modified columns.
     */
    public function set_custom_template_columns( $columns ) {
        $date_column = $columns['date'];
        unset( $columns['date'] );
        
        // Add custom columns
        $columns['idml_file'] = __( 'IDML File', 'wp2id' );
        $columns['tag_system'] = __( 'Tag System', 'wp2id' );
        $columns['date'] = $date_column;
        
        return $columns;
    }

    /**
     * Display the custom column content for the template list table.
     *
     * @since    1.0.0
     * @param    string    $column    The column ID.
     * @param    int       $post_id    The post ID.
     */
    public function custom_template_column_content( $column, $post_id ) {
        switch ( $column ) {
            case 'idml_file' :
                $idml_file_id = get_post_meta( $post_id, '_wp2id_template_idml_file', true );
                $idml_file_url = $idml_file_id ? wp_get_attachment_url( $idml_file_id ) : '';
                $idml_file_name = $idml_file_id ? basename( get_attached_file( $idml_file_id ) ) : '';
                
                if ( $idml_file_url ) {
                    echo '<a href="' . esc_url( $idml_file_url ) . '" target="_blank">' . esc_html( $idml_file_name ) . '</a>';
                } else {
                    echo __( 'No file', 'wp2id' );
                }
                break;
            case 'tag_system' :
                $tag_system = get_post_meta( $post_id, '_wp2id_template_tag_system', true );
                echo __( 'Tag-Based System', 'wp2id' );
                break;
        }
    }

    /**
     * Disables WordPress file type check for IDML files.
     * 
     * This is necessary because WordPress might restrict IDML files due to security concerns,
     * but we need to allow them for our plugin functionality.
     *
     * @since    1.0.0
     * @param    array     $check          File data array containing 'ext', 'type', and 'proper_filename' keys
     * @param    string    $file           Full path to the file
     * @param    string    $filename       The name of the file
     * @param    array     $mimes          List of allowed mime types
     * @param    string    $real_mime      Real mime type of the uploaded file
     * @return   array                     Modified file data array
     */
    public function disable_file_type_check( $check, $file, $filename, $mimes, $real_mime = null ) {
        // Check if the file has an IDML extension
        if ( strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) ) === 'idml' ) {
            // Allow IDML files
            $check['ext'] = 'idml';
            $check['type'] = 'application/octet-stream';
        }
        
        return $check;
    }
}