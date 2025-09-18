<?php
/**
 * Plugin Name: WPML to Polylang Migration Fixer
 * Plugin URI: https://your-website.com/wpml-to-polylang-migration-fixer
 * Description: Comprehensive tool to fix language assignments and translation groups after migrating from WPML to Polylang
 * Version: 1.1.0
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
define('WPML_TO_POLYLANG_FIXER_VERSION', '1.1.0');
define('WPML_TO_POLYLANG_FIXER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPML_TO_POLYLANG_FIXER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WPML_TO_POLYLANG_FIXER_PLUGIN_FILE', __FILE__);
define('WPML_TO_POLYLANG_FIXER_DEBUG', defined('WP_DEBUG') && WP_DEBUG);

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
    private $db_helper = null;
    private $migration_verifier = null;
    private $initialized = false;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Initialize immediately but delay component loading
        add_action('plugins_loaded', [$this, 'init'], 1);
        add_action('init', [$this, 'init_components'], 5);
        
        // Load dependencies early
        $this->load_dependencies();
    }
    
    public function init() {
        if ($this->initialized) {
            return;
        }
        
        $this->init_hooks();
        $this->initialized = true;
    }
    
    private function load_dependencies() {
        // Load core files first
        $core_files = [
            'includes/class-debug-logger.php',
            'includes/class-database-helper.php', 
            'includes/class-language-converter.php',
            'includes/class-migration-verifier.php'  // NEW: Enhanced verifier
        ];
        
        foreach ($core_files as $file) {
            $path = WPML_TO_POLYLANG_FIXER_PLUGIN_DIR . $file;
            if (file_exists($path)) {
                require_once $path;
            }
        }
        
        // Load admin files only if needed
        if (is_admin()) {
            $admin_files = [
                'admin/class-ui-renderer.php',
                'admin/class-admin-handler.php',
                'admin/class-ajax-handler.php'
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
    }
    
    public function init_components() {
        // Don't initialize twice
        if ($this->debug_logger !== null) {
            return;
        }
        
        // Initialize debug logger first
        if (class_exists('WPML_To_Polylang_Fixer_Debug_Logger')) {
            $this->debug_logger = new WPML_To_Polylang_Fixer_Debug_Logger();
            $this->debug_logger->log('Plugin components initialization started (v' . WPML_TO_POLYLANG_FIXER_VERSION . ')', 'info');
        }
        
        // Initialize database helper
        if (class_exists('WPML_To_Polylang_Fixer_Database_Helper')) {
            $this->db_helper = new WPML_To_Polylang_Fixer_Database_Helper();
            if ($this->debug_logger) {
                $this->debug_logger->log('Database helper initialized', 'info');
            }
        }
        
        // Initialize language converter
        if (class_exists('WPML_To_Polylang_Fixer_Language_Converter')) {
            $this->language_converter = new WPML_To_Polylang_Fixer_Language_Converter();
            if ($this->debug_logger) {
                $this->debug_logger->log('Language converter initialized', 'info');
            }
        }
        
        // NEW: Initialize migration verifier
        if (class_exists('WPML_To_Polylang_Migration_Verifier')) {
            $this->migration_verifier = new WPML_To_Polylang_Migration_Verifier();
            if ($this->debug_logger) {
                $this->debug_logger->log('Migration verifier initialized', 'info');
            }
        }
        
        // Check Polylang availability
        if (!function_exists('pll_languages_list')) {
            add_action('admin_notices', [$this, 'polylang_missing_notice']);
            if ($this->debug_logger) {
                $this->debug_logger->log('Polylang not available', 'warning');
            }
            return;
        }
        
        // Initialize admin components only if in admin
        if (is_admin()) {
            if (class_exists('WPML_Fixer_Admin_Handler')) {
                $this->admin_handler = new WPML_Fixer_Admin_Handler();
                if ($this->debug_logger) {
                    $this->debug_logger->log('Admin handler initialized', 'info');
                }
            }
            
            if (class_exists('WPML_Fixer_Ajax_Handler')) {
                $this->ajax_handler = new WPML_Fixer_Ajax_Handler();
                if ($this->debug_logger) {
                    $this->debug_logger->log('AJAX handler initialized', 'info');
                }
            }
        }
        
        if ($this->debug_logger) {
            $components = [
                'debug_logger' => $this->debug_logger ? 'OK' : 'FAIL',
                'db_helper' => $this->db_helper ? 'OK' : 'FAIL',
                'language_converter' => $this->language_converter ? 'OK' : 'FAIL',
                'migration_verifier' => $this->migration_verifier ? 'OK' : 'FAIL',
                'admin_handler' => $this->admin_handler ? 'OK' : 'N/A',
                'ajax_handler' => $this->ajax_handler ? 'OK' : 'N/A'
            ];
            $this->debug_logger->log('Component initialization completed: ' . json_encode($components), 'info');
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
        // Create log directory
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/wpml-to-polylang-fixer-logs';
        
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
            $htaccess = $log_dir . '/.htaccess';
            if (!file_exists($htaccess)) {
                file_put_contents($htaccess, 'Deny from all');
            }
        }
        
        // Set default options
        add_option('wpml_to_polylang_fixer_debug_enabled', false);
        add_option('wpml_to_polylang_fixer_batch_size', 20);
        add_option('wpml_to_polylang_fixer_version', WPML_TO_POLYLANG_FIXER_VERSION);
        
        // Log activation
        if (class_exists('WPML_To_Polylang_Fixer_Debug_Logger')) {
            $logger = new WPML_To_Polylang_Fixer_Debug_Logger();
            $logger->log('Plugin activated - Version: ' . WPML_TO_POLYLANG_FIXER_VERSION, 'info');
        }
    }
    
    public function deactivate() {
        global $wpdb;
        
        // Clean up transients
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wpml_to_polylang_fixer_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_wpml_to_polylang_fixer_%'");
        
        // Log deactivation
        if ($this->debug_logger) {
            $this->debug_logger->log('Plugin deactivated', 'info');
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
    
    /**
     * Get plugin component by name
     */
    public function get_component($component) {
        switch ($component) {
            case 'debug_logger':
                return $this->debug_logger;
            case 'language_converter':
                return $this->language_converter;
            case 'db_helper':
                return $this->db_helper;
            case 'migration_verifier':
                return $this->migration_verifier;
            case 'admin_handler':
                return $this->admin_handler;
            case 'ajax_handler':
                return $this->ajax_handler;
            default:
                return null;
        }
    }
    
    /**
     * Check if components are properly initialized
     */
    public function is_ready() {
        return $this->debug_logger !== null && 
               $this->db_helper !== null && 
               $this->language_converter !== null &&
               $this->migration_verifier !== null;
    }
    
    /**
     * Get initialization status
     */
    public function get_status() {
        return [
            'initialized' => $this->initialized,
            'ready' => $this->is_ready(),
            'polylang_active' => function_exists('pll_languages_list'),
            'version' => WPML_TO_POLYLANG_FIXER_VERSION,
            'components' => [
                'debug_logger' => $this->debug_logger !== null,
                'db_helper' => $this->db_helper !== null,
                'language_converter' => $this->language_converter !== null,
                'migration_verifier' => $this->migration_verifier !== null,
                'admin_handler' => $this->admin_handler !== null,
                'ajax_handler' => $this->ajax_handler !== null
            ]
        ];
    }
}

// Initialize plugin only once
function wpml_to_polylang_migration_fixer() {
    return WPML_To_Polylang_Migration_Fixer::get_instance();
}

// Start the plugin
wpml_to_polylang_migration_fixer();