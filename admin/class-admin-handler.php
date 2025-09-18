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
        $this->db_helper = new WPML_Fixer_Database_Helper();
        $this->logger = new WPML_Fixer_Debug_Logger();
        
        // Load UI Renderer
        require_once WPML_FIXER_PLUGIN_DIR . 'admin/class-ui-renderer.php';
        $this->ui_renderer = new WPML_Fixer_UI_Renderer();
        
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
        
        // Settings page
        add_action('admin_menu', [$this, 'add_settings_page'], 1000);
    }
    
    /**
     * Check plugin requirements
     */
    public function check_requirements() {
        $is_our_page = isset($_GET['page']) && strpos($_GET['page'], 'wpml-fixer') === 0;
        
        if (!$is_our_page) {
            return;
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
     * Add settings page
     */
    public function add_settings_page() {
        add_submenu_page(
            'tools.php',
            __('WPML Fixer Settings', 'wpml-migration-fixer'),
            __('WPML Fixer Settings', 'wpml-migration-fixer'),
            'manage_options',
            'wpml-fixer-settings',
            [$this, 'render_settings_page']
        );
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
        $this->ui_renderer->render_main_page($system_status);
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wpml-migration-fixer'));
        }
        
        // Handle form submission
        if (isset($_POST['wpml_fixer_settings_nonce'])) {
            $this->handle_settings_save();
        }
        
        // Get current settings
        $settings = $this->get_settings();
        
        ?>
        <div class="wrap">
            <h1><?php _e('WPML Fixer Settings', 'wpml-migration-fixer'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('wpml_fixer_settings', 'wpml_fixer_settings_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="debug_enabled"><?php _e('Enable Debug Logging', 'wpml-migration-fixer'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="debug_enabled" name="debug_enabled" value="1" 
                                   <?php checked($settings['debug_enabled']); ?>>
                            <p class="description">
                                <?php _e('Enable detailed logging for troubleshooting.', 'wpml-migration-fixer'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="batch_size"><?php _e('Batch Size', 'wpml-migration-fixer'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="batch_size" name="batch_size" 
                                   value="<?php echo esc_attr($settings['batch_size']); ?>" 
                                   min="5" max="100" step="5">
                            <p class="description">
                                <?php _e('Number of items to process in each batch. Lower values prevent timeouts.', 'wpml-migration-fixer'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label><?php _e('Excluded Post Types', 'wpml-migration-fixer'); ?></label>
                        </th>
                        <td>
                            <?php
                            $post_types = get_post_types(['public' => false], 'objects');
                            foreach ($post_types as $post_type) {
                                ?>
                                <label style="display: block; margin-bottom: 5px;">
                                    <input type="checkbox" name="exclude_post_types[]" 
                                           value="<?php echo esc_attr($post_type->name); ?>"
                                           <?php checked(in_array($post_type->name, $settings['exclude_post_types'])); ?>>
                                    <?php echo esc_html($post_type->label); ?> (<?php echo esc_html($post_type->name); ?>)
                                </label>
                                <?php
                            }
                            ?>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label><?php _e('Debug Logs', 'wpml-migration-fixer'); ?></label>
                        </th>
                        <td>
                            <a href="<?php echo admin_url('admin-ajax.php?action=wpml_fixer_download_logs&nonce=' . wp_create_nonce('wpml_fixer_logs')); ?>" 
                               class="button">
                                <?php _e('Download Debug Logs', 'wpml-migration-fixer'); ?>
                            </a>
                            <button type="button" class="button" onclick="if(confirm('<?php esc_attr_e('Clear all debug logs?', 'wpml-migration-fixer'); ?>')) { document.getElementById('clear_logs').value='1'; this.form.submit(); }">
                                <?php _e('Clear Logs', 'wpml-migration-fixer'); ?>
                            </button>
                            <input type="hidden" id="clear_logs" name="clear_logs" value="">
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
            
            <?php if ($settings['debug_enabled']): ?>
            <div style="margin-top: 30px;">
                <h2><?php _e('Recent Log Entries', 'wpml-migration-fixer'); ?></h2>
                <div style="background: #f5f5f5; padding: 10px; max-height: 400px; overflow-y: auto; font-family: monospace; font-size: 12px;">
                    <?php
                    $recent_logs = $this->logger->get_recent_logs(50);
                    foreach ($recent_logs as $log) {
                        echo esc_html($log) . '<br>';
                    }
                    ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Handle settings save
     */
    private function handle_settings_save() {
        if (!wp_verify_nonce($_POST['wpml_fixer_settings_nonce'], 'wpml_fixer_settings')) {
            return;
        }
        
        // Clear logs if requested
        if (!empty($_POST['clear_logs'])) {
            $upload_dir = wp_upload_dir();
            $log_dir = $upload_dir['basedir'] . '/wpml-fixer-logs';
            $files = glob($log_dir . '/*.log');
            foreach ($files as $file) {
                unlink($file);
            }
            add_settings_error('wpml_fixer_settings', 'logs_cleared', __('Logs cleared successfully.', 'wpml-migration-fixer'), 'success');
        }
        
        // Save settings
        update_option('wpml_fixer_debug_enabled', !empty($_POST['debug_enabled']));
        update_option('wpml_fixer_batch_size', intval($_POST['batch_size']));
        update_option('wpml_fixer_exclude_post_types', $_POST['exclude_post_types'] ?? []);
        
        add_settings_error('wpml_fixer_settings', 'settings_saved', __('Settings saved.', 'wpml-migration-fixer'), 'success');
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
            'wpml_data_exists' => $this->db_helper->wpml_tables_exist(),
            'polylang_active' => function_exists('pll_languages_list'),
            'languages' => [],
            'stats' => []
        ];
        
        if ($status['polylang_active']) {
            $status['languages'] = pll_languages_list(['fields' => 'slug']);
            $status['default_language'] = pll_default_language();
        }
        
        // Get migration statistics
        $status['stats'] = $this->db_helper->get_migration_statistics();
        
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
            WPML_FIXER_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            WPML_FIXER_VERSION,
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
            WPML_FIXER_PLUGIN_URL . 'assets/css/admin.css',
            [],
            WPML_FIXER_VERSION
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
        $exclude = $this->db_helper->get_excluded_taxonomies();
        
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
        $exclude = array_merge(['attachment'], $this->db_helper->get_excluded_post_types());
        
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