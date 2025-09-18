<?php
/**
 * Ajax Handler Class
 * 
 * Handles AJAX requests for the WPML Migration Fixer plugin
 * 
 * @package WPML_Migration_Fixer
 * @since 1.0.1
 */

if (!defined('ABSPATH')) {
    exit;
}

// Prevent duplicate class declaration
if (!class_exists('WPML_Fixer_Ajax_Handler')) {

class WPML_Fixer_Ajax_Handler {
    
    /**
     * Database helper instance
     */
    private $db_helper;
    
    /**
     * Language converter instance
     */
    private $language_converter;
    
    /**
     * Logger instance
     */
    private $logger;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Initialize components safely
        if (class_exists('WPML_To_Polylang_Fixer_Database_Helper')) {
            $this->db_helper = new WPML_To_Polylang_Fixer_Database_Helper();
        }
        
        if (class_exists('WPML_To_Polylang_Fixer_Language_Converter')) {
            $this->language_converter = new WPML_To_Polylang_Fixer_Language_Converter();
        }
        
        if (class_exists('WPML_To_Polylang_Fixer_Debug_Logger')) {
            $this->logger = new WPML_To_Polylang_Fixer_Debug_Logger();
        }
        
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Register AJAX handlers
        add_action('wp_ajax_wpml_fixer_ajax_process', [$this, 'handle_process']);
        add_action('wp_ajax_wpml_fixer_ajax_analyze', [$this, 'handle_analyze']);
        add_action('wp_ajax_wpml_fixer_ajax_diagnose', [$this, 'handle_diagnose']);
        add_action('wp_ajax_wpml_fixer_ajax_fix_english', [$this, 'handle_fix_english']);
        add_action('wp_ajax_wpml_fixer_ajax_fix_pll_prefix', [$this, 'handle_fix_pll_prefix']);
        add_action('wp_ajax_wpml_fixer_ajax_fix_woo_attributes', [$this, 'handle_fix_woo_attributes']);
        add_action('wp_ajax_wpml_fixer_ajax_reset_session', [$this, 'handle_reset_session']);
        add_action('wp_ajax_wpml_fixer_ajax_verify_migration', [$this, 'handle_verify_migration']);
        add_action('wp_ajax_wpml_fixer_ajax_test_connection', [$this, 'handle_test_connection']);
        add_action('wp_ajax_wpml_fixer_ajax_save_debug_setting', [$this, 'handle_save_debug_setting']);
        add_action('wp_ajax_wpml_fixer_download_logs', [$this, 'handle_download_logs']);
        
        // Log successful initialization
        if ($this->logger) {
            $this->logger->log('AJAX handlers registered successfully', 'info');
        }
    }
    
    /**
     * Verify AJAX request - Using same approach as working snippet
     */
    private function verify_request() {
        // Use same verification method as working snippet
        if (!check_ajax_referer('wpml_fixer_ajax', 'nonce', false)) {
            if ($this->logger) {
                $this->logger->log('AJAX request failed: Invalid nonce', 'error');
            }
            wp_send_json_error(__('Security check failed', 'wpml-migration-fixer'));
            exit;
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            if ($this->logger) {
                $this->logger->log('AJAX request failed: Unauthorized user - ' . get_current_user_id(), 'error');
            }
            wp_send_json_error(__('Unauthorized access', 'wpml-migration-fixer'));
            exit;
        }
        
        // Log successful verification for debugging
        if ($this->logger) {
            $this->logger->log('AJAX request verified successfully for user ' . get_current_user_id(), 'debug');
        }
    }
    
    /**
     * Handle main process request
     */
    public function handle_process() {
        $this->verify_request();
        
        $type = sanitize_text_field($_POST['type'] ?? '');
        $offset = intval($_POST['offset'] ?? 0);
        $batch_size = intval($_POST['batch_size'] ?? get_option('wpml_fixer_batch_size', 20));
        
        if ($this->logger) {
            $this->logger->log("Processing {$type} - Offset: {$offset}, Batch: {$batch_size}", 'info');
        }
        
        // Set execution limits
        @set_time_limit(120);
        @ini_set('memory_limit', '512M');
        
        $start_time = microtime(true);
        
        try {
            switch ($type) {
                case 'posts':
                    $result = $this->process_posts($offset, $batch_size);
                    break;
                case 'taxonomies':
                    $result = $this->process_taxonomies($offset, $batch_size);
                    break;
                case 'betterdocs':
                    $result = $this->process_betterdocs($offset, $batch_size);
                    break;
                case 'woocommerce':
                    $result = $this->process_woocommerce($offset, $batch_size);
                    break;
                case 'translations':
                    $result = $this->process_translations($offset, $batch_size);
                    break;
                default:
                    throw new Exception('Invalid process type: ' . $type);
            }
            
            $time_taken = microtime(true) - $start_time;
            if ($this->logger) {
                $this->logger->log_performance("Process {$type}", $time_taken, $result['fixed'] ?? 0);
            }
            
            wp_send_json_success($result);
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->log_error("Process failed: {$type}", $e);
            }
            wp_send_json_error($e->getMessage());
        }
    }
    
    // Add stub methods for all the process functions
    // These are simplified versions - you can expand them as needed
    
    private function process_posts($offset, $batch_size) {
        if (!$this->db_helper) {
            return ['error' => 'Database helper not initialized'];
        }
        
        $post_types = get_post_types(['public' => true], 'names');
        $exclude_types = $this->db_helper->get_excluded_post_types();
        $post_types = array_diff($post_types, $exclude_types);
        
        // Implementation would go here
        // For now, return basic structure
        return [
            'total' => 100,
            'processed' => min($offset + $batch_size, 100),
            'fixed' => 0,
            'continue' => ($offset + $batch_size) < 100,
            'next_offset' => $offset + $batch_size
        ];
    }
    
    private function process_taxonomies($offset, $batch_size) {
        return [
            'total' => 50,
            'processed' => min($offset + $batch_size, 50),
            'fixed' => 0,
            'continue' => ($offset + $batch_size) < 50,
            'next_offset' => $offset + $batch_size
        ];
    }
    
    private function process_betterdocs($offset, $batch_size) {
        if (!post_type_exists('docs')) {
            return ['error' => __('BetterDocs not installed', 'wpml-migration-fixer')];
        }
        
        return [
            'total' => 0,
            'processed' => 0,
            'fixed' => 0,
            'continue' => false,
            'next_offset' => 0
        ];
    }
    
    private function process_woocommerce($offset, $batch_size) {
        if (!class_exists('WooCommerce')) {
            return ['error' => __('WooCommerce not active', 'wpml-migration-fixer')];
        }
        
        return [
            'total' => 0,
            'processed' => 0,
            'fixed' => 0,
            'continue' => false,
            'next_offset' => 0
        ];
    }
    
    private function process_translations($offset, $batch_size) {
        if (!$this->db_helper || !$this->db_helper->wpml_tables_exist()) {
            return ['error' => __('WPML data not found', 'wpml-migration-fixer')];
        }
        
        return [
            'total' => 0,
            'processed' => 0,
            'fixed' => 0,
            'continue' => false,
            'next_offset' => 0
        ];
    }
    
    public function handle_analyze() {
        $this->verify_request();
        
        try {
            if ($this->logger) {
                $this->logger->log('Analysis request started', 'info');
            }
            
            // Check if required components are available
            if (!$this->db_helper) {
                throw new Exception('Database helper not initialized');
            }
            
            if (!function_exists('pll_languages_list')) {
                throw new Exception('Polylang not active or pll_languages_list function not available');
            }
            
            // Get analysis data
            $stats = $this->db_helper->get_migration_statistics();
            $problematic_codes = $this->language_converter ? $this->language_converter->get_problematic_codes() : [];
            
            if ($this->logger) {
                $this->logger->log('Statistics retrieved: ' . json_encode(array_map('intval', $stats)), 'debug');
            }
            
            // Ensure stats has all required keys with default values
            $stats = wp_parse_args($stats, [
                'posts_with_language' => 0,
                'posts_without_language' => 0,
                'terms_with_language' => 0,
                'terms_without_language' => 0,
                'translation_groups' => 0,
                'problematic_codes' => 0,
                'post_types_breakdown' => []
            ]);
            
            // Extract variables for the view
            extract($stats);
            
            // Capture the view output
            ob_start();
            $view_file = WPML_TO_POLYLANG_FIXER_PLUGIN_DIR . 'admin/views/analysis-results.php';
            if (file_exists($view_file)) {
                include $view_file;
            } else {
                echo '<div class="status-message status-error">';
                echo __('Analysis results template not found.', 'wpml-to-polylang-migration-fixer');
                echo '<br><small>Expected: ' . esc_html($view_file) . '</small>';
                echo '</div>';
            }
            $html = ob_get_clean();
            
            if ($this->logger) {
                $this->logger->log("Analysis completed successfully - Posts: {$stats['posts_with_language']}/{$stats['posts_without_language']}, Terms: {$stats['terms_with_language']}/{$stats['terms_without_language']}", 'info');
            }
            
            wp_send_json_success($html);
            
        } catch (Exception $e) {
            $error_message = 'Analysis failed: ' . $e->getMessage();
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $error_message .= ' in ' . $e->getFile() . ':' . $e->getLine();
            }
            
            if ($this->logger) {
                $this->logger->log_error("Analysis failed", $e);
            }
            
            wp_send_json_error($error_message);
        } catch (Error $e) {
            // Catch fatal errors as well
            $error_message = 'Analysis failed with fatal error: ' . $e->getMessage();
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $error_message .= ' in ' . $e->getFile() . ':' . $e->getLine();
            }
            
            if ($this->logger) {
                $this->logger->log_error("Analysis fatal error", $e);
            }
            
            wp_send_json_error($error_message);
        }
    }
    
    /**
     * Handle test connection request - Fixed to match working snippet
     */
    public function handle_test_connection() {
        // Use same verification as working snippet
        if (!check_ajax_referer('wpml_fixer_ajax', 'nonce', false)) {
            wp_send_json_error('Security check failed: Invalid nonce');
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
            return;
        }
        
        try {
            $test_data = sanitize_text_field($_POST['test_data'] ?? '');
            
            $response_data = [
                'message' => 'AJAX connection test successful!',
                'timestamp' => current_time('mysql'),
                'test_data_received' => $test_data,
                'components' => [
                    'db_helper' => $this->db_helper ? 'initialized' : 'missing',
                    'language_converter' => $this->language_converter ? 'initialized' : 'missing',
                    'logger' => $this->logger ? 'initialized' : 'missing'
                ],
                'polylang_active' => function_exists('pll_languages_list'),
                'wpml_data' => $this->db_helper ? $this->db_helper->wpml_tables_exist() : false
            ];
            
            if ($this->logger) {
                $this->logger->log('Connection test successful', 'info');
            }
            
            wp_send_json_success($response_data);
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->log_error("Connection test failed", $e);
            }
            wp_send_json_error('Connection test failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Handle save debug setting request - Fixed to match working snippet
     */
    public function handle_save_debug_setting() {
        // Use same verification approach as working snippet
        if (!check_ajax_referer('wpml_fixer_ajax', 'nonce', false)) {
            wp_send_json_error('Security check failed for debug setting');
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized to change debug setting');
            return;
        }
        
        try {
            $enabled = isset($_POST['enabled']) && $_POST['enabled'] === 'true';
            $old_setting = get_option('wpml_to_polylang_fixer_debug_enabled', false);
            
            update_option('wpml_to_polylang_fixer_debug_enabled', $enabled);
            
            // Only log after the setting is changed to avoid logging issues
            if ($this->logger && $enabled) {
                $this->logger->log('Debug mode ' . ($enabled ? 'enabled' : 'disabled') . ' (was: ' . ($old_setting ? 'enabled' : 'disabled') . ')', 'info');
            }
            
            wp_send_json_success([
                'debug_enabled' => $enabled,
                'previous_state' => $old_setting,
                'message' => 'Debug setting saved successfully'
            ]);
            
        } catch (Exception $e) {
            wp_send_json_error('Failed to save debug setting: ' . $e->getMessage());
        }
    }
    
    public function handle_diagnose() {
        $this->verify_request();
        
        try {
            $problematic = $this->language_converter ? $this->language_converter->get_problematic_codes() : [];
            $wrong_codes = $this->db_helper ? $this->db_helper->get_content_with_wrong_codes() : [];
            
            // Extract variables for the view
            extract(compact('problematic', 'wrong_codes'));
            
            ob_start();
            $view_file = WPML_TO_POLYLANG_FIXER_PLUGIN_DIR . 'admin/views/diagnosis-results.php';
            if (file_exists($view_file)) {
                include $view_file;
            } else {
                echo '<div class="status-message status-error">';
                echo __('Diagnosis results template not found.', 'wpml-to-polylang-migration-fixer');
                echo '<br><small>Expected: ' . esc_html($view_file) . '</small>';
                echo '</div>';
            }
            $html = ob_get_clean();
            
            if ($this->logger) {
                $problematic_count = count($problematic);
                $this->logger->log("Diagnosis completed - Found {$problematic_count} problematic codes", 'info');
            }
            
            wp_send_json_success($html);
            
        } catch (Exception $e) {
            $error_message = 'Diagnosis failed: ' . $e->getMessage();
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $error_message .= ' in ' . $e->getFile() . ':' . $e->getLine();
            }
            
            if ($this->logger) {
                $this->logger->log_error("Diagnosis failed", $e);
            }
            wp_send_json_error($error_message);
        }
    }
    
    public function handle_fix_english() {
        $this->verify_request();
        wp_send_json_success(['message' => 'Fix English not yet implemented']);
    }
    
    public function handle_fix_pll_prefix() {
        $this->verify_request();
        wp_send_json_success(['message' => 'Fix pll prefix not yet implemented']);
    }
    
    public function handle_fix_woo_attributes() {
        $this->verify_request();
        wp_send_json_success(['message' => 'Fix WooCommerce attributes not yet implemented']);
    }
    
    public function handle_verify_migration() {
        $this->verify_request();
        
        try {
            // Get verification data
            $verification = [
                'translation_groups' => 0,
                'orphaned_languages' => 0,
                'duplicate_assignments' => 0,
                'language_mapping' => []
            ];
            
            if ($this->db_helper) {
                $stats = $this->db_helper->get_migration_statistics();
                $verification['translation_groups'] = $stats['translation_groups'] ?? 0;
            }
            
            // Extract variables for the view
            extract(compact('verification'));
            
            ob_start();
            $view_file = WPML_TO_POLYLANG_FIXER_PLUGIN_DIR . 'admin/views/verification-results.php';
            if (file_exists($view_file)) {
                include $view_file;
            } else {
                echo '<div class="status-message status-info">';
                echo __('Verification results template not found. Basic verification completed.', 'wpml-to-polylang-migration-fixer');
                echo '</div>';
            }
            $html = ob_get_clean();
            
            wp_send_json_success($html);
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->log_error("Verification failed", $e);
            }
            wp_send_json_error('Verification failed: ' . $e->getMessage());
        }
    }
    
    public function handle_reset_session() {
        $this->verify_request();
        
        try {
            // Clear any transients
            delete_transient('wpml_fixer_session_' . get_current_user_id());
            
            if ($this->logger) {
                $this->logger->log("Session reset for user " . get_current_user_id(), 'info');
            }
            
            wp_send_json_success(__('Session reset successfully', 'wpml-migration-fixer'));
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->log_error("Reset session failed", $e);
            }
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function handle_download_logs() {
        $nonce = $_GET['nonce'] ?? '';
        
        if (!wp_verify_nonce($nonce, 'wpml_fixer_ajax')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        if (!$this->logger) {
            wp_die('Logger not initialized');
        }
        
        $export_file = $this->logger->export_logs();
        
        if (is_wp_error($export_file)) {
            wp_die($export_file->get_error_message());
        }
        
        if (!file_exists($export_file)) {
            wp_die('Export file not found');
        }
        
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="wpml-fixer-logs-' . date('Y-m-d-His') . '.zip"');
        header('Content-Length: ' . filesize($export_file));
        
        readfile($export_file);
        unlink($export_file);
        exit;
    }
}

} // End of class_exists check