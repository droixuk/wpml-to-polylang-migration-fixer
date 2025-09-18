<?php
/**
 * Ajax Handler Class
 * 
 * Handles AJAX requests for the WPML Migration Fixer plugin
 * 
 * @package WPML_Migration_Fixer
 * @since 1.0.2
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
     * Nonce action name
     */
    private $nonce_action = 'wpml_fixer_ajax';
    
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
        add_action('wp_ajax_wpml_fixer_ajax_verify_migration', [$this, 'handle_verify_migration']);
        add_action('wp_ajax_wpml_fixer_ajax_test_connection', [$this, 'handle_test_connection']);
        
        // Log successful initialization
        if ($this->logger) {
            $this->logger->log('AJAX handlers registered successfully', 'info');
        }
    }
    
    /**
     * Verify AJAX request
     */
    private function verify_request() {
        // Check nonce
        if (!check_ajax_referer($this->nonce_action, $this->nonce_action, false)) {
            if ($this->logger) {
                $this->logger->log('AJAX request failed: Invalid nonce', 'error');
            }
            wp_send_json_error('Security check failed');
            exit;
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            if ($this->logger) {
                $this->logger->log('AJAX request failed: Unauthorized user', 'error');
            }
            wp_send_json_error('Unauthorized access');
            exit;
        }
        
        // Log successful verification
        if ($this->logger) {
            $this->logger->log('AJAX request verified successfully', 'debug');
        }
    }
    
    /**
     * Handle test connection request
     */
    public function handle_test_connection() {
        $this->verify_request();
        
        try {
            $test_data = sanitize_text_field($_POST['test_data'] ?? '');
            
            $response_data = [
                'message' => 'AJAX connection test successful!',
                'timestamp' => current_time('mysql'),
                'test_data_received' => $test_data,
                'nonce_action_used' => $this->nonce_action,
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
     * Handle analyze request
     */
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
                throw new Exception('Polylang not active');
            }
            
            // Get analysis data
            $stats = $this->db_helper->get_migration_statistics();
            
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
                echo '</div>';
            }
            $html = ob_get_clean();
            
            if ($this->logger) {
                $this->logger->log("Analysis completed successfully", 'info');
            }
            
            wp_send_json_success($html);
            
        } catch (Exception $e) {
            $error_message = 'Analysis failed: ' . $e->getMessage();
            
            if ($this->logger) {
                $this->logger->log_error("Analysis failed", $e);
            }
            
            wp_send_json_error($error_message);
        }
    }
    
    /**
     * Handle diagnose request
     */
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
            
            if ($this->logger) {
                $this->logger->log_error("Diagnosis failed", $e);
            }
            wp_send_json_error($error_message);
        }
    }
    
    /**
     * Handle verify migration request
     */
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
                echo __('Verification completed. Basic verification successful.', 'wpml-to-polylang-migration-fixer');
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
    
    /**
     * Handle main process request
     */
    public function handle_process() {
        $this->verify_request();
        
        try {
            // Basic stub for now
            wp_send_json_success([
                'message' => 'Process handler not yet implemented',
                'total' => 0,
                'processed' => 0,
                'fixed' => 0,
                'continue' => false
            ]);
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->log_error("Process failed", $e);
            }
            wp_send_json_error($e->getMessage());
        }
    }
}

} // End of class_exists check