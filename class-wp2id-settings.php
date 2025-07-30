<?php
/**
 * WP2ID Settings Page
 *
 * Handles the plugin settings page in WordPress admin
 *
 * @since      1.0.0
 * @package    WP2ID
 * @subpackage WP2ID/admin
 */

if (!defined('WPINC')) {
    die;
}

/**
 * The settings page class.
 */
class WP2ID_Settings
{
    /**
     * The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     */
    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Register the settings page
     */
    public function register_settings_page()
    {
        add_submenu_page(
            'wp2id-dashboard',
            __('WP2ID Settings', 'wp2id'),
            __('Role Settings', 'wp2id'),
            'manage_options',
            'wp2id-settings',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Register settings and fields
     */
    public function register_settings()
    {
        // Register settings
        register_setting(
            'wp2id_settings',
            'wp2id_role_permissions',
            array(
                'type' => 'array',
                'sanitize_callback' => array($this, 'sanitize_permissions')
            )
        );

        // Add settings section
        add_settings_section(
            'wp2id_permissions_section',
            __('User Permissions', 'wp2id'),
            array($this, 'permissions_section_callback'),
            'wp2id_settings'
        );

        // Add permissions field
        add_settings_field(
            'wp2id_role_permissions',
            __('Role Permissions', 'wp2id'),
            array($this, 'permissions_field_callback'),
            'wp2id_settings',
            'wp2id_permissions_section'
        );
    }

    /**
     * Sanitize permissions data
     */
    public function sanitize_permissions($input)
    {
        $sanitized = array();
        $available_permissions = WP2ID_Permission_Manager::get_available_permissions();
        $wordpress_roles = WP2ID_Permission_Manager::get_wordpress_roles();

        if (is_array($input)) {
            foreach ($input as $role => $permissions) {
                if (array_key_exists($role, $wordpress_roles) && is_array($permissions)) {
                    $sanitized[$role] = array_values(
                        array_intersect($permissions, array_keys($available_permissions))
                    );
                }
            }
        }

        return $sanitized;
    }

    /**
     * Permissions section description
     */
    public function permissions_section_callback()
    {
        echo '<p>' . __('Configure which user roles can access different features of the WP2ID plugin.', 'wp2id') . '</p>';
    }

    /**
     * Render permissions field
     */
    public function permissions_field_callback()
    {
        $role_permissions = get_option('wp2id_role_permissions', []);
        $available_permissions = WP2ID_Permission_Manager::get_available_permissions();
        $wordpress_roles = WP2ID_Permission_Manager::get_wordpress_roles();

        echo '<table class="wp2id-permissions-table widefat">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . __('Role', 'wp2id') . '</th>';
        foreach ($available_permissions as $perm_key => $perm_label) {
            echo '<th>' . esc_html($perm_label) . '</th>';
        }
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($wordpress_roles as $role_key => $role_name) {
            echo '<tr>';
            echo '<td><strong>' . esc_html($role_name) . '</strong></td>';
            
            $current_permissions = isset($role_permissions[$role_key]) ? $role_permissions[$role_key] : [];
            
            foreach ($available_permissions as $perm_key => $perm_label) {
                $checked = in_array($perm_key, $current_permissions) ? 'checked' : '';
                $field_name = "wp2id_role_permissions[{$role_key}][]";
                
                echo '<td>';
                echo '<input type="checkbox" ';
                echo 'name="' . esc_attr($field_name) . '" ';
                echo 'value="' . esc_attr($perm_key) . '" ';
                echo $checked . ' />';
                echo '</td>';
            }
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';

        echo '<p class="description">' . 
             __('Note: Super Administrators always have all permissions regardless of these settings.', 'wp2id') . 
             '</p>';

        // Add reset button
        echo '<p>';
        echo '<button type="button" class="button button-secondary" id="wp2id-reset-permissions">';
        echo __('Reset to Defaults', 'wp2id');
        echo '</button>';
        echo '</p>';
    }

    /**
     * Render the settings page
     */
    public function render_settings_page()
    {
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        // Handle reset action
        if (isset($_POST['reset_permissions']) && 
            wp_verify_nonce($_POST['wp2id_settings_nonce'], 'wp2id_settings_action')) {
            
            $permission_manager = new WP2ID_Permission_Manager();
            $permission_manager->reset_to_defaults();
            
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p>' . __('Permissions have been reset to default values.', 'wp2id') . '</p>';
            echo '</div>';
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('wp2id_settings');
                do_settings_sections('wp2id_settings');
                submit_button(__('Save Settings', 'wp2id'));
                ?>
            </form>

            <!-- Reset Form -->
            <form method="post" action="" style="margin-top: 20px;">
                <?php wp_nonce_field('wp2id_settings_action', 'wp2id_settings_nonce'); ?>
                <input type="hidden" name="reset_permissions" value="1">
                <p>
                    <input type="submit" 
                           class="button button-secondary" 
                           value="<?php esc_attr_e('Reset All Permissions to Defaults', 'wp2id'); ?>" 
                           onclick="return confirm('<?php esc_attr_e('Are you sure you want to reset all permissions to default values?', 'wp2id'); ?>');">
                </p>
            </form>
        </div>

        <style>
        .wp2id-permissions-table {
            margin-top: 10px;
        }
        .wp2id-permissions-table th,
        .wp2id-permissions-table td {
            text-align: center;
            padding: 8px;
        }
        .wp2id-permissions-table th:first-child,
        .wp2id-permissions-table td:first-child {
            text-align: left;
        }
        .wp2id-permissions-table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        </style>

        <script>
        jQuery(document).ready(function($) {
            $('#wp2id-reset-permissions').on('click', function() {
                if (confirm('<?php echo esc_js(__('Are you sure you want to reset all permissions to default values?', 'wp2id')); ?>')) {
                    $('input[name="reset_permissions"]').closest('form').submit();
                }
            });
        });
        </script>
        <?php
    }

    /**
     * Enqueue admin styles for settings page
     */
    public function enqueue_admin_styles($hook)
    {
        if ($hook !== 'wp2id_page_wp2id-settings') {
            return;
        }

        wp_enqueue_style(
            $this->plugin_name . '-settings',
            plugin_dir_url(__FILE__) . '../assets/css/wp2id-settings.css',
            array(),
            $this->version
        );
    }
}
