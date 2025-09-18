<?php
/**
 * Plugin Name: WPML to Polylang Migration Fixer
 * Plugin URI: https://your-website.com/wpml-to-polylang-migration-fixer
 * Description: Comprehensive tool to fix language assignments and translation groups after migrating from WPML to Polylang
 * Version: 1.0.0
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

// Define plugin constants
define('WPML_TO_POLYLANG_FIXER_VERSION', '1.0.0');
define('WPML_TO_POLYLANG_FIXER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPML_TO_POLYLANG_FIXER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WPML_TO_POLYLANG_FIXER_PLUGIN_FILE', __FILE__);
define('WPML_TO_POLYLANG_FIXER_DEBUG', false);

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
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    private function load_dependencies() {
        require_once WPML_TO_POLYLANG_FIXER_PLUGIN_DIR . 'includes/class-debug-logger.php';
        require_once WPML_TO_POLYLANG_FIXER_PLUGIN_DIR . 'includes/class-language-converter.php';
        require_once WPML_TO_POLYLANG_FIXER_PLUGIN_DIR . 'includes/class-database-helper.php';
        
        if (is_admin()) {
            require_once WPML_TO_POLYLANG_FIXER_PLUGIN_DIR . 'admin/class-admin-handler.php';
            require_once WPML_TO_POLYLANG_FIXER_PLUGIN_DIR . 'admin/class-ajax-handler.php';
            require_once WPML_TO_POLYLANG_FIXER_PLUGIN_DIR . 'admin/class-ui-renderer.php';
        }
    }
    
    private function init_hooks() {
        register_activation_hook(WPML_TO_POLYLANG_FIXER_PLUGIN_FILE, [$this, 'activate']);
        register_deactivation_hook(WPML_TO_POLYLANG_FIXER_PLUGIN_FILE, [$this, 'deactivate']);
        add_action('plugins_loaded', [$this, 'init_components']);
        add_action('init', [$this, 'load_textdomain']);
    }
    
    public function init_components() {
        if (!function_exists('pll_languages_list')) {
            add_action('admin_notices', [$this, 'polylang_missing_notice']);
            return;
        }
        
        $this->debug_logger = new WPML_To_Polylang_Fixer_Debug_Logger();
        $this->language_converter = new WPML_To_Polylang_Fixer_Language_Converter();
        
        if (is_admin()) {
            $this->admin_handler = new WPML_To_Polylang_Fixer_Admin_Handler();
            $this->ajax_handler = new WPML_To_Polylang_Fixer_Ajax_Handler();
        }
        
        $this->debug_logger->log('Plugin initialized', 'info');
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
        
        if (class_exists('WPML_To_Polylang_Fixer_Debug_Logger')) {
            $logger = new WPML_To_Polylang_Fixer_Debug_Logger();
            $logger->log('Plugin activated', 'info');
        }
    }
    
    public function deactivate() {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wpml_to_polylang_fixer_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_wpml_to_polylang_fixer_%'");
        
        if (class_exists('WPML_To_Polylang_Fixer_Debug_Logger')) {
            $logger = new WPML_To_Polylang_Fixer_Debug_Logger();
            $logger->log('Plugin deactivated', 'info');
        }
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

function wpml_to_polylang_migration_fixer() {
    return WPML_To_Polylang_Migration_Fixer::get_instance();
}

add_action('plugins_loaded', 'wpml_to_polylang_migration_fixer', 0);
