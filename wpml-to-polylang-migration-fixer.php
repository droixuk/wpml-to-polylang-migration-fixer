<?php
/**
 * Plugin Name: WPML to Polylang Migration Fixer
 * Plugin URI: https://your-website.com/wpml-to-polylang-migration-fixer
 * Description: Comprehensive tool to fix language assignments and translation groups after migrating from WPML to Polylang
 * Version: 1.0.1
 * Author: Your Name
 * Author URI: https://your-website.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wpml-to-polylang-migration-fixer
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Prevent multiple instantiation
if (defined('WPML_TO_POLYLANG_FIXER_VERSION')) {
    return;
}

// Define plugin constants
define('WPML_TO_POLYLANG_FIXER_VERSION', '1.0.1');
define('WPML_TO_POLYLANG_FIXER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPML_TO_POLYLANG_FIXER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WPML_TO_POLYLANG_FIXER_PLUGIN_FILE', __FILE__);
define('WPML_TO_POLYLANG_FIXER_DEBUG', false);

// Use consistent naming convention
define('WPML_FIXER_VERSION', WPML_TO_POLYLANG_FIXER_VERSION);
define('WPML_FIXER_PLUGIN_DIR', WPML_TO_POLYLANG_FIXER_PLUGIN_DIR);
define('WPML_FIXER_PLUGIN_URL', WPML_TO_POLYLANG_FIXER_PLUGIN_URL);

/**
 * Main plugin class
 */
class WPML_To_Polylang_Migration_Fixer {
    
    private static $instance = null;
    private $admin_handler = null;
    private $ajax_handler = null;
    private $language_converter = null;
    private $debug_logger = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Delay loading until plugins_loaded to ensure WordPress is fully initialized
        add_action('plugins_loaded', [$this, 'init'], 1);
    }
    
    public function init() {
        // Check if already initialized
        if ($this->debug_logger !== null) {
            return;
        }
        
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    private function load_dependencies() {
        // Only load files if they haven't been loaded yet
        $files = [
            'includes/class-debug-logger.php',
            'includes/class-language-converter.php',
            'includes/class-database-helper.php'
        ];
        
        foreach ($files as $file) {
            $path = WPML_TO_POLYLANG_FIXER_PLUGIN_DIR . $file;
            if (file_exists($path)) {
                require_once $path;
            }
        }
        
        if (is_admin()) {
            $admin_files = [
                'admin/class-admin-handler.php',
                'admin/class-ajax-handler.php',
                'admin/class-ui-renderer.php'
            ];
            
            foreach ($admin_files as $file) {
                $path = WPML_TO_POLYLANG_FIXER_PLUGIN_DIR . $file;
                if (file_exists($path)) {
                    require_once $path;
                }
            }
        }
    }
    
    private function init_hooks() {
        register_activation_hook(WPML_TO_POLYLANG_FIXER_PLUGIN_FILE, [$this, 'activate']);
        register_deactivation_hook(WPML_TO_POLYLANG_FIXER_PLUGIN_FILE, [$this, 'deactivate']);
        add_action('init', [$this, 'load_textdomain']);
        add_action('init', [$this, 'init_components'], 5);
    }
    
    public function init_components() {
        if (!function_exists('pll_languages_list')) {
            add_action('admin_notices', [$this, 'polylang_missing_notice']);
            return;
        }
        
        // Check class existence before instantiating
        if (class_exists('WPML_To_Polylang_Fixer_Debug_Logger')) {
            $this->debug_logger = new WPML_To_Polylang_Fixer_Debug_Logger();
        }
        
        if (class_exists('WPML_To_Polylang_Fixer_Language_Converter')) {
            $this->language_converter = new WPML_To_Polylang_Fixer_Language_Converter();
        }
        
        if (is_admin()) {
            // Use consistent class names
            if (class_exists('WPML_Fixer_Admin_Handler')) {
                $this->admin_handler = new WPML_Fixer_Admin_Handler();
            }
            
            if (class_exists('WPML_Fixer_Ajax_Handler')) {
                $this->ajax_handler = new WPML_Fixer_Ajax_Handler();
            }
        }
        
        if ($this->debug_logger) {
            $this->debug_logger->log('Plugin initialized', 'info');
        }
    }
    
    public function load_textdomain() {
        load_plugin_textdomain(
            'wpml-to-polylang-migration-fixer',
            false,
            dirname(plugin_basename(WPML_TO_POLYLANG_FIXER_PLUGIN_FILE)) . '/languages'
        );
    }
    
    public function activate() {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/wpml-to-polylang-fixer-logs';
        
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
            $htaccess = $log_dir . '/.htaccess';
            if (!file_exists($htaccess)) {
                file_put_contents($htaccess, 'Deny from all');
            }
        }
        
        add_option('wpml_to_polylang_fixer_debug_enabled', false);
        add_option('wpml_to_polylang_fixer_batch_size', 20);
        add_option('wpml_to_polylang_fixer_version', WPML_TO_POLYLANG_FIXER_VERSION);
    }
    
    public function deactivate() {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wpml_to_polylang_fixer_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_wpml_to_polylang_fixer_%'");
    }
    
    public function polylang_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p>
                <strong><?php esc_html_e('WPML to Polylang Migration Fixer:', 'wpml-to-polylang-migration-fixer'); ?></strong>
                <?php esc_html_e('This plugin requires Polylang to be installed and activated.', 'wpml-to-polylang-migration-fixer'); ?>
            </p>
        </div>
        <?php
    }
    
    public function get_component($component) {
        switch ($component) {
            case 'debug_logger':
                return $this->debug_logger;
            case 'language_converter':
                return $this->language_converter;
            case 'admin_handler':
                return $this->admin_handler;
            case 'ajax_handler':
                return $this->ajax_handler;
            default:
                return null;
        }
    }
}

// Initialize plugin only once
function wpml_to_polylang_migration_fixer() {
    return WPML_To_Polylang_Migration_Fixer::get_instance();
}

// Start the plugin
wpml_to_polylang_migration_fixer();