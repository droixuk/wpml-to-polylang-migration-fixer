<?php
/**
 * Admin Handler Class
 * 
 * Handles admin interface for the WPML Migration Fixer plugin
 * 
 * @package WPML_Migration_Fixer
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Prevent duplicate class declaration
if (!class_exists('WPML_Fixer_Admin_Handler')) {

class WPML_Fixer_Admin_Handler {
    
    /**
     * UI Renderer instance
     */
    private $ui_renderer;
    
    /**
     * Database helper instance
     */
    private $db_helper;
    
    /**
     * Logger instance
     */
    private $logger;
    
    /**
     * Admin page hook suffix
     */
    private $page_hook;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Initialize components safely
        if (class_exists('WPML_To_Polylang_Fixer_Database_Helper')) {
            $this->db_helper = new WPML_To_Polylang_Fixer_Database_Helper();
        }
        
        if (class_exists('WPML_To_Polylang_Fixer_Debug_Logger')) {
            $this->logger = new WPML_To_Polylang_Fixer_Debug_Logger();
        }
        
        // Load UI Renderer if not already loaded
        if (!class_exists('WPML_Fixer_UI_Renderer')) {
            $ui_file = WPML_TO_POLYLANG_FIXER_PLUGIN_DIR . 'admin/class-ui-renderer.php';
            if (file_exists($ui_file)) {
                require_once $ui_file;
            }
        }
        
        if (class_exists('WPML_Fixer_UI_Renderer')) {
            $this->ui_renderer = new WPML_Fixer_UI_Renderer();
        }
        
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Only initialize on our page or during our AJAX calls
        add_action('admin_init', [$this, 'check_requirements']);
        add_action('admin_menu', [$this, 'add_admin_menu'], 999);
        
        // Register Polylang filters
        add_filter('pll_get_taxonomies', [$this, 'register_taxonomies'], 999, 2);
        add_filter('pll_get_post_types', [$this, 'register_post_types'], 999, 2);
    }
    
    /**
     * Check plugin requirements
     */
    public function check_requirements() {
        $is_our_page = isset($_GET['page']) && strpos($_GET['page'], 'wpml-fixer') === 0;
        
        if (!$is_our_page) {
            return true;
        }
        
        // Check Polylang
        if (!function_exists('pll_languages_list')) {
            add_action('admin_notices', [$this, 'polylang_missing_notice']);
            return false;
        }
        
        // Check PHP version
        if (version_compare(PHP_VERSION, '7.0', '<')) {
            add_action('admin_notices', [$this, 'php_version_notice']);
            return false;
        }
        
        return true;
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        $this->page_hook = add_submenu_page(
            'tools.php',
            __('WPML Fixer', 'wpml-migration-fixer'),
            __('WPML Fixer', 'wpml-migration-fixer'),
            'manage_options',
            'wpml-fixer-ajax',
            [$this, 'render_main_page']
        );
        
        // Add help tab
        add_action('load-' . $this->page_hook, [$this, 'add_help_tabs']);
        
        // Enqueue scripts for our page only
        add_action('admin_print_scripts-' . $this->page_hook, [$this, 'enqueue_scripts']);
        add_action('admin_print_styles-' . $this->page_hook, [$this, 'enqueue_styles']);
    }
    
    /**
     * Render main page
     */
    public function render_main_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wpml-migration-fixer'));
        }
        
        // Get system status
        $system_status = $this->get_system_status();
        
        // Render the page
        if ($this->ui_renderer) {
            $this->ui_renderer->render_main_page($system_status);
        } else {
            echo '<div class="error"><p>UI Renderer not loaded.</p></div>';
        }
    }
    
    /**
     * Get settings
     */
    private function get_settings() {
        return [
            'debug_enabled' => get_option('wpml_fixer_debug_enabled', false),
            'batch_size' => get_option('wpml_fixer_batch_size', 20),
            'exclude_post_types' => get_option('wpml_fixer_exclude_post_types', [])
        ];
    }
    
    /**
     * Get system status
     */
    private function get_system_status() {
        global $wpdb;
        
        $status = [
            'wpml_data_exists' => false,
            'polylang_active' => function_exists('pll_languages_list'),
            'languages' => [],
            'default_language' => '',
            'stats' => []
        ];
        
        // Check for WPML tables
        if ($this->db_helper && method_exists($this->db_helper, 'wpml_tables_exist')) {
            $status['wpml_data_exists'] = $this->db_helper->wpml_tables_exist();
        }
        
        if ($status['polylang_active']) {
            $status['languages'] = pll_languages_list(['fields' => 'slug']);
            $status['default_language'] = pll_default_language();
        }
        
        // Get migration statistics
        if ($this->db_helper && method_exists($this->db_helper, 'get_migration_statistics')) {
            $status['stats'] = $this->db_helper->get_migration_statistics();
        }
        
        return $status;
    }
    
    /**
     * Enqueue scripts
     */
    public function enqueue_scripts() {
        // Enqueue jQuery
        wp_enqueue_script('jquery');
        
        // Enqueue our script
        wp_enqueue_script(
            'wpml-fixer-admin',
            WPML_TO_POLYLANG_FIXER_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            defined('WPML_TO_POLYLANG_FIXER_VERSION') ? WPML_TO_POLYLANG_FIXER_VERSION : '1.0.0',
            true
        );
        
        // Localize script
        wp_localize_script('wpml-fixer-admin', 'wpmlFixerAjax', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpml_fixer_ajax'),
            'strings' => [
                'confirmReset' => __('Are you sure you want to reset the session?', 'wpml-migration-fixer'),
                'confirmFix' => __('This will process all items. Continue?', 'wpml-migration-fixer'),
                'processing' => __('Processing...', 'wpml-migration-fixer'),
                'complete' => __('Complete!', 'wpml-migration-fixer'),
                'error' => __('An error occurred. Please check the logs.', 'wpml-migration-fixer')
            ]
        ]);
    }
    
    /**
     * Enqueue styles
     */
    public function enqueue_styles() {
        wp_enqueue_style(
            'wpml-fixer-admin',
            WPML_TO_POLYLANG_FIXER_PLUGIN_URL . 'assets/css/admin.css',
            [],
            defined('WPML_TO_POLYLANG_FIXER_VERSION') ? WPML_TO_POLYLANG_FIXER_VERSION : '1.0.0'
        );
    }
    
    /**
     * Add help tabs
     */
    public function add_help_tabs() {
        $screen = get_current_screen();
        
        $screen->add_help_tab([
            'id' => 'wpml-fixer-overview',
            'title' => __('Overview', 'wpml-migration-fixer'),
            'content' => '<p>' . __('WPML Migration Fixer helps you fix language assignments and translation groups after migrating from WPML to Polylang.', 'wpml-migration-fixer') . '</p>'
        ]);
        
        $screen->add_help_tab([
            'id' => 'wpml-fixer-usage',
            'title' => __('How to Use', 'wpml-migration-fixer'),
            'content' => '<p>' . __('1. Run Analysis to check your content status<br>2. Run Diagnosis to identify issues<br>3. Use the appropriate fix buttons to correct problems<br>4. Verify the migration when complete', 'wpml-migration-fixer') . '</p>'
        ]);
    }
    
    /**
     * Register taxonomies with Polylang
     */
    public function register_taxonomies($taxonomies, $is_settings) {
        $all = get_taxonomies(['public' => true]);
        $exclude = $this->db_helper ? $this->db_helper->get_excluded_taxonomies() : [];
        
        foreach ($all as $tax) {
            if (!in_array($tax, $exclude)) {
                $taxonomies[$tax] = $tax;
            }
        }
        
        return $taxonomies;
    }
    
    /**
     * Register post types with Polylang
     */
    public function register_post_types($post_types, $is_settings) {
        $all = get_post_types(['public' => true]);
        $exclude = $this->db_helper ? array_merge(['attachment'], $this->db_helper->get_excluded_post_types()) : ['attachment'];
        
        foreach ($all as $pt) {
            if (!in_array($pt, $exclude)) {
                $post_types[$pt] = $pt;
            }
        }
        
        return $post_types;
    }
    
    /**
     * Polylang missing notice
     */
    public function polylang_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p>
                <strong><?php _e('WPML Migration Fixer:', 'wpml-migration-fixer'); ?></strong>
                <?php _e('Polylang must be installed and activated to use this tool.', 'wpml-migration-fixer'); ?>
            </p>
        </div>
        <?php
    }
    
    /**
     * PHP version notice
     */
    public function php_version_notice() {
        ?>
        <div class="notice notice-warning">
            <p>
                <strong><?php _e('WPML Migration Fixer:', 'wpml-migration-fixer'); ?></strong>
                <?php printf(__('This plugin requires PHP 7.0 or higher. You are running PHP %s.', 'wpml-migration-fixer'), PHP_VERSION); ?>
            </p>
        </div>
        <?php
    }
}

} // End of class_exists check