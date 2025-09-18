<?php
/**
 * Ajax Handler Class
 * 
 * Handles AJAX requests for the WPML Migration Fixer plugin
 * 
 * @package WPML_Migration_Fixer
 * @since 1.0.0
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
        add_action('wp_ajax_wpml_fixer_download_logs', [$this, 'handle_download_logs']);
    }
    
    /**
     * Verify AJAX request
     */
    private function verify_request() {
        if (!check_ajax_referer('wpml_fixer_ajax', 'nonce', false)) {
            wp_send_json_error(__('Security check failed', 'wpml-migration-fixer'));
            exit;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized access', 'wpml-migration-fixer'));
            exit;
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
            $stats = $this->db_helper ? $this->db_helper->get_migration_statistics() : [];
            $problematic_codes = $this->language_converter ? $this->language_converter->get_problematic_codes() : [];
            
            ob_start();
            $view_file = WPML_TO_POLYLANG_FIXER_PLUGIN_DIR . 'admin/views/analysis-results.php';
            if (file_exists($view_file)) {
                include $view_file;
            } else {
                echo '<p>Analysis results view not found.</p>';
            }
            $html = ob_get_clean();
            
            wp_send_json_success($html);
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->log_error("Analysis failed", $e);
            }
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function handle_diagnose() {
        $this->verify_request();
        
        try {
            $problematic = $this->language_converter ? $this->language_converter->get_problematic_codes() : [];
            $wrong_codes = $this->db_helper ? $this->db_helper->get_content_with_wrong_codes() : [];
            
            ob_start();
            $view_file = WPML_TO_POLYLANG_FIXER_PLUGIN_DIR . 'admin/views/diagnosis-results.php';
            if (file_exists($view_file)) {
                include $view_file;
            } else {
                echo '<p>Diagnosis results view not found.</p>';
            }
            $html = ob_get_clean();
            
            wp_send_json_success($html);
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->log_error("Diagnosis failed", $e);
            }
            wp_send_json_error($e->getMessage());
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
        wp_send_json_success(['message' => 'Verification not yet implemented']);
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
        if (!wp_verify_nonce($_GET['nonce'] ?? '', 'wpml_fixer_logs')) {
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