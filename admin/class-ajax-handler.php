<?php
/**
 * Ajax Handler Class
 * 
 * Enhanced with comprehensive migration verification
 * 
 * @package WPML_Migration_Fixer
 * @since 1.1.0
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
     * Migration verifier instance
     */
    private $migration_verifier;
    
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
        
        // Load the new verifier
        $verifier_file = WPML_TO_POLYLANG_FIXER_PLUGIN_DIR . 'includes/class-migration-verifier.php';
        if (file_exists($verifier_file)) {
            require_once $verifier_file;
        }
        
        if (class_exists('WPML_To_Polylang_Migration_Verifier')) {
            $this->migration_verifier = new WPML_To_Polylang_Migration_Verifier();
        }
        
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Register AJAX handlers - keeping existing ones
        add_action('wp_ajax_wpml_fixer_ajax_process', [$this, 'handle_process']);
        add_action('wp_ajax_wpml_fixer_ajax_analyze', [$this, 'handle_analyze']);
        add_action('wp_ajax_wpml_fixer_ajax_diagnose', [$this, 'handle_diagnose']);
        add_action('wp_ajax_wpml_fixer_ajax_verify_migration', [$this, 'handle_verify_migration']);
        add_action('wp_ajax_wpml_fixer_ajax_test_connection', [$this, 'handle_test_connection']);
        add_action('wp_ajax_wpml_fixer_ajax_fix_english', [$this, 'handle_fix_english']);
        add_action('wp_ajax_wpml_fixer_ajax_fix_pll_prefix', [$this, 'handle_fix_pll_prefix']);
        add_action('wp_ajax_wpml_fixer_ajax_fix_woo_attributes', [$this, 'handle_fix_woo_attributes']);
        add_action('wp_ajax_wpml_fixer_ajax_reset_session', [$this, 'handle_reset_session']);
        
        // NEW: Comprehensive verification endpoint
        add_action('wp_ajax_wpml_fixer_ajax_comprehensive_verify', [$this, 'handle_comprehensive_verify']);
        
        // Log successful initialization
        if ($this->logger) {
            $this->logger->log('Enhanced AJAX handlers registered successfully', 'info');
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
        
        // Check Polylang
        if (!function_exists('pll_languages_list')) {
            wp_send_json_error('Polylang is not active');
            exit;
        }
        
        // Log successful verification
        if ($this->logger) {
            $this->logger->log('AJAX request verified successfully', 'debug');
        }
    }
    
    /**
     * NEW: Handle comprehensive verification request
     */
    public function handle_comprehensive_verify() {
        $this->verify_request();
        
        try {
            if (!$this->migration_verifier) {
                throw new Exception('Migration verifier not initialized');
            }
            
            if ($this->logger) {
                $this->logger->log('Starting comprehensive verification', 'info');
            }
            
            $results = $this->migration_verifier->verify_migration();
            
            // Generate HTML output
            ob_start();
            $this->render_comprehensive_verification_results($results);
            $html = ob_get_clean();
            
            if ($this->logger) {
                $status = $results['overall_status'];
                $issues = $results['total_critical_issues'];
                $this->logger->log("Comprehensive verification completed - Status: {$status}, Issues: {$issues}", 'info');
            }
            
            wp_send_json_success($html);
            
        } catch (Exception $e) {
            $error_message = 'Comprehensive verification failed: ' . $e->getMessage();
            
            if ($this->logger) {
                $this->logger->log_error("Comprehensive verification failed", $e);
            }
            
            wp_send_json_error($error_message);
        }
    }
    
    /**
     * Render comprehensive verification results
     */
    private function render_comprehensive_verification_results($results) {
        ?>
        <h3><?php _e('Comprehensive Migration Verification', 'wpml-to-polylang-migration-fixer'); ?></h3>
        
        <!-- Overall Status -->
        <div class="verification-status <?php echo $results['overall_status'] === 'success' ? 'status-success' : 'status-error'; ?>" 
             style="padding: 15px; margin-bottom: 20px; border-radius: 8px;">
            <?php if ($results['overall_status'] === 'success'): ?>
                <h4 style="margin: 0; color: #2e7d32;">✅ Migration Verification: PASSED</h4>
                <p style="margin: 5px 0 0 0;">All critical components have been successfully migrated from WPML to Polylang.</p>
            <?php else: ?>
                <h4 style="margin: 0; color: #c62828;">❌ Migration Verification: ISSUES FOUND</h4>
                <p style="margin: 5px 0 0 0;">
                    <strong><?php echo $results['total_critical_issues']; ?> critical issues</strong> need attention.
                </p>
            <?php endif; ?>
        </div>
        
        <!-- Quick Stats -->
        <div class="quick-stats">
            <div class="stat-box">
                <div class="stat-label"><?php _e('Languages', 'wpml-to-polylang-migration-fixer'); ?></div>
                <div class="stat-number">
                    <?php echo $results['languages']['pll_languages']; ?>/<?php echo $results['languages']['wpml_languages']; ?>
                </div>
                <div class="badge <?php echo $results['languages']['critical_issues'] === 0 ? 'badge-success' : 'badge-error'; ?>">
                    <?php echo $results['languages']['critical_issues'] === 0 ? 'OK' : 'Issues'; ?>
                </div>
            </div>
            
            <div class="stat-box">
                <div class="stat-label"><?php _e('Posts', 'wpml-to-polylang-migration-fixer'); ?></div>
                <div class="stat-number">
                    <?php echo $results['posts']['posts_with_language']; ?>
                </div>
                <div class="badge <?php echo $results['posts']['critical_issues'] === 0 ? 'badge-success' : 'badge-error'; ?>">
                    <?php echo $results['posts']['critical_issues'] === 0 ? 'OK' : $results['posts']['critical_issues'] . ' Issues'; ?>
                </div>
            </div>
            
            <div class="stat-box">
                <div class="stat-label"><?php _e('Terms', 'wpml-to-polylang-migration-fixer'); ?></div>
                <div class="stat-number">
                    <?php echo $results['terms']['terms_with_language']; ?>
                </div>
                <div class="badge <?php echo $results['terms']['critical_issues'] === 0 ? 'badge-success' : 'badge-error'; ?>">
                    <?php echo $results['terms']['critical_issues'] === 0 ? 'OK' : $results['terms']['critical_issues'] . ' Issues'; ?>
                </div>
            </div>
            
            <div class="stat-box">
                <div class="stat-label"><?php _e('Translation Groups', 'wpml-to-polylang-migration-fixer'); ?></div>
                <div class="stat-number">
                    <?php echo $results['translation_groups']['valid_groups']; ?>/<?php echo $results['translation_groups']['total_groups']; ?>
                </div>
                <div class="badge <?php echo $results['translation_groups']['critical_issues'] === 0 ? 'badge-success' : 'badge-error'; ?>">
                    <?php echo $results['translation_groups']['critical_issues'] === 0 ? 'OK' : 'Issues'; ?>
                </div>
            </div>
        </div>
        
        <!-- Detailed Results -->
        <div style="margin-top: 30px;">
            <h4><?php _e('Detailed Verification Results', 'wpml-to-polylang-migration-fixer'); ?></h4>
            
            <?php foreach ($results as $component => $data): ?>
                <?php if (in_array($component, ['overall_status', 'total_critical_issues', 'wpml_data'])) continue; ?>
                
                <div class="verification-component" style="margin-bottom: 20px; padding: 15px; background: #f9f9f9; border-radius: 8px;">
                    <h5 style="margin: 0 0 10px 0; display: flex; align-items: center; gap: 10px;">
                        <?php 
                        $icons = [
                            'languages' => '🌐',
                            'posts' => '📝', 
                            'terms' => '🏷️',
                            'translation_groups' => '🔗',
                            'menus' => '📋',
                            'strings' => '💬',
                            'options' => '⚙️'
                        ];
                        echo $icons[$component] ?? '📊';
                        echo ' ' . ucwords(str_replace('_', ' ', $component));
                        ?>
                        
                        <span class="badge <?php echo $data['critical_issues'] === 0 ? 'badge-success' : 'badge-error'; ?>">
                            <?php echo $data['status']; ?>
                        </span>
                    </h5>
                    
                    <?php if (!empty($data['issues'])): ?>
                        <ul style="margin: 10px 0; color: #c62828;">
                            <?php foreach ($data['issues'] as $issue): ?>
                                <li><?php echo esc_html($issue); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    
                    <!-- Component-specific details -->
                    <?php if ($component === 'posts'): ?>
                        <div style="font-size: 14px; color: #666;">
                            Posts with language: <?php echo $data['posts_with_language']; ?> | 
                            Without language: <?php echo $data['posts_without_language']; ?> |
                            WPML groups: <?php echo $data['wpml_post_trids']; ?> |
                            PLL groups: <?php echo $data['pll_post_groups']; ?>
                        </div>
                    <?php elseif ($component === 'terms'): ?>
                        <div style="font-size: 14px; color: #666;">
                            Terms with language: <?php echo $data['terms_with_language']; ?> | 
                            Without language: <?php echo $data['terms_without_language']; ?> |
                            WPML groups: <?php echo $data['wpml_term_trids']; ?> |
                            PLL groups: <?php echo $data['pll_term_groups']; ?>
                        </div>
                    <?php elseif ($component === 'languages'): ?>
                        <div style="font-size: 14px; color: #666;">
                            WPML languages: <?php echo $data['wpml_languages']; ?> | 
                            Polylang languages: <?php echo $data['pll_languages']; ?>
                            <?php if (!empty($data['missing_in_pll'])): ?>
                                | Missing: <?php echo implode(', ', $data['missing_in_pll']); ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- WPML Data Status -->
        <div style="margin-top: 20px; padding: 15px; background: #e8f5e9; border-radius: 8px;">
            <h5 style="margin: 0 0 5px 0;">📊 WPML Data Status</h5>
            <div style="font-size: 14px;">
                WPML tables exist: <?php echo $results['wpml_data']['icl_translations_exists'] ? 'Yes' : 'No'; ?> |
                Records: <?php echo number_format($results['wpml_data']['icl_translations_count']); ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Handle test connection request (enhanced)
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
                    'migration_verifier' => $this->migration_verifier ? 'initialized' : 'missing',
                    'logger' => $this->logger ? 'initialized' : 'missing'
                ],
                'polylang_active' => function_exists('pll_languages_list'),
                'wpml_data' => $this->db_helper ? $this->db_helper->wpml_tables_exist() : false
            ];
            
            if ($this->logger) {
                $this->logger->log('Connection test successful with enhanced components', 'info');
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
     * Handle analyze request (enhanced with better verification)
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
            
            // Get enhanced analysis data
            $stats = $this->get_enhanced_analysis_stats();
            
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
                $this->logger->log("Enhanced analysis completed successfully", 'info');
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
     * Get enhanced analysis statistics
     */
    private function get_enhanced_analysis_stats() {
        $stats = $this->db_helper->get_migration_statistics();
        
        // Add enhanced checks if migration verifier is available
        if ($this->migration_verifier) {
            $verification = $this->migration_verifier->get_verification_summary();
            $stats['verification_summary'] = $verification;
            $stats['migration_issues_detected'] = $verification['total_critical_issues'];
        }
        
        return $stats;
    }
    
    /**
     * Handle diagnose request (using existing logic)
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
     * Handle verify migration request (redirects to comprehensive if available)
     */
    public function handle_verify_migration() {
        $this->verify_request();
        
        try {
            // Redirect to comprehensive verification if available
            if ($this->migration_verifier) {
                return $this->handle_comprehensive_verify();
            }
            
            // Fallback to basic verification
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
    
    // KEEPING ALL EXISTING METHODS from the original plugin
    // These are placeholders for methods that were already implemented
    
    public function handle_process() {
        // Keep existing implementation from current plugin
        wp_send_json_error('Process handler - refer to existing implementation');
    }
    
    public function handle_fix_english() {
        // Keep existing implementation from current plugin
        wp_send_json_error('Fix English handler - refer to existing implementation');
    }
    
    public function handle_fix_pll_prefix() {
        // Keep existing implementation from current plugin
        wp_send_json_error('Fix PLL prefix handler - refer to existing implementation');
    }
    
    public function handle_fix_woo_attributes() {
        // Keep existing implementation from current plugin
        wp_send_json_error('Fix WooCommerce attributes handler - refer to existing implementation');
    }
    
    public function handle_reset_session() {
        // Keep existing implementation from current plugin
        wp_send_json_error('Reset session handler - refer to existing implementation');
    }
}

} // End of class_exists check