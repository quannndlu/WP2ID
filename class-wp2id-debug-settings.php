<?php
/**
 * The debug settings functionality of the plugin.
 *
 * @since      1.0.0
 * @package    WP2ID
 * @subpackage WP2ID/admin
 */

if (!defined('WPINC')) {
    die;
}

/**
 * The debug settings functionality of the plugin.
 */
class WP2ID_Debug_Settings {

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
     * @param    string    $plugin_name    The name of this plugin.
     * @param    string    $version        The version of this plugin.
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        // Add action to register settings
        add_action('admin_init', array($this, 'register_settings'));
    }

    /**
     * Register plugin debug settings.
     *
     * @since    1.0.0
     */
    public function register_settings() {
        // Register a setting
        register_setting(
            'wp2id_debug_settings',        // Option group
            'wp2id_debug_settings',        // Option name
            array($this, 'sanitize_settings') // Sanitize callback
        );

        // Add a settings section
        add_settings_section(
            'wp2id_debug_section',         // ID
            __('Debug Settings', 'wp2id'), // Title
            array($this, 'debug_section_callback'), // Callback
            'wp2id-debug'                 // Page
        );

        // Add settings field for enabling error logs
        add_settings_field(
            'wp2id_enable_error_logs',     // ID
            __('Error Logging', 'wp2id'),  // Title
            array($this, 'enable_error_logs_callback'), // Callback
            'wp2id-debug',                // Page
            'wp2id_debug_section'         // Section
        );
    }

    /**
     * Sanitize each setting field as needed.
     *
     * @since    1.0.0
     * @param    array    $input    Contains all settings fields as array keys.
     * @return   array              Sanitized values.
     */
    public function sanitize_settings($input) {
        $sanitized_input = array();
        
        // Include security class if not already included
        if (!class_exists('WP2ID_Security')) {
            require_once WP2ID_PLUGIN_DIR . 'includes/class-wp2id-security.php';
        }

        // Sanitize enable_error_logs checkbox
        if (isset($input['enable_error_logs'])) {
            $sanitized_input['enable_error_logs'] = (bool) $input['enable_error_logs'];
        } else {
            $sanitized_input['enable_error_logs'] = false;
        }

        return $sanitized_input;
    }

    /**
     * Callback for the debug section.
     *
     * @since    1.0.0
     */
    public function debug_section_callback() {
        echo '<p>' . __('Configure debug options for WP2ID.', 'wp2id') . '</p>';
    }

    /**
     * Callback for the enable error logs field.
     *
     * @since    1.0.0
     */
    public function enable_error_logs_callback() {
        $options = get_option('wp2id_debug_settings');
        $checked = isset($options['enable_error_logs']) ? $options['enable_error_logs'] : false;
        ?>
        <label for="wp2id_enable_error_logs">
            <input type="checkbox" id="wp2id_enable_error_logs" name="wp2id_debug_settings[enable_error_logs]" value="1" <?php checked(1, $checked); ?> />
            <?php _e('Enable error logging in this plugin for debugging purposes', 'wp2id'); ?>
        </label>
        <p class="description">
            <?php _e('When checked, detailed error logs will be written to wp-content/debug.log. This may affect performance and should only be enabled for debugging.', 'wp2id'); ?>
        </p>
        <?php
    }

    /**
     * Check if error logging is enabled
     *
     * @since    1.0.0
     * @return   bool    True if error logging is enabled, false otherwise.
     */
    public static function is_error_logging_enabled() {
        $options = get_option('wp2id_debug_settings');
        return isset($options['enable_error_logs']) && $options['enable_error_logs'] === true;
    }
}
