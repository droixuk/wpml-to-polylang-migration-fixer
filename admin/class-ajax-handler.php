<?php
/**
 * Ajax Handler Class
 * 
 * Enhanced with taxonomy fix implementation and translation groups repair
 * 
 * @package WPML_Migration_Fixer
 * @since 1.1.2
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
     * SQL runner helper
     */
    private $sql_runner;
    
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

        if (class_exists('WMF_SQL_Runner')) {
            $this->sql_runner = new WMF_SQL_Runner($this->logger);
        }

        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Register AJAX handlers
        add_action('wp_ajax_wpml_fixer_ajax_process', [$this, 'handle_process']);
        add_action('wp_ajax_wpml_fixer_ajax_test_connection', [$this, 'handle_test_connection']);

        // NEW: Comprehensive verification endpoint
        add_action('wp_ajax_wpml_fixer_ajax_comprehensive_verify', [$this, 'handle_comprehensive_verify']);
        add_action('wp_ajax_wmf_get_status', [$this, 'handle_get_status']);
        add_action('wp_ajax_wmf_sql_preview', [$this, 'handle_sql_preview']);
        add_action('wp_ajax_wmf_sql_execute', [$this, 'handle_sql_execute']);

        // Enhanced fix operations
        add_action('wp_ajax_wmf_ensure_buckets', [$this, 'handle_ensure_buckets']);
        add_action('wp_ajax_wmf_normalize_languages', [$this, 'handle_normalize_languages']);
        add_action('wp_ajax_wmf_fix_all_posts', [$this, 'handle_fix_all_posts']);
        add_action('wp_ajax_wmf_fix_all_terms', [$this, 'handle_fix_all_terms']);
        add_action('wp_ajax_wmf_fix_betterdocs', [$this, 'handle_fix_betterdocs']);
        add_action('wp_ajax_wmf_fix_woo_attributes', [$this, 'handle_fix_woo_attributes']);
        
        // Log successful initialization
        if ($this->logger) {
            $this->logger->log('Enhanced AJAX handlers registered (process/test/status/sql).', 'info');
        }
    }
    
    /**
     * Verify AJAX request
     */
    private function verify_request($require_polylang = true) {
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
        
        // Check Polylang if required
        if ($require_polylang && !function_exists('pll_languages_list')) {
            wp_send_json_error('Polylang is not active');
            exit;
        }
        
        // Log successful verification
        if ($this->logger) {
            $this->logger->log('AJAX request verified successfully', 'debug');
        }
    }
    
    /**
     * NEW: Handle process request - Main fix processing router
     */
    public function handle_process() {
        $this->verify_request();
        
        try {
            $type = sanitize_text_field($_POST['type'] ?? '');
            $offset = intval($_POST['offset'] ?? 0);
            $batch_size = intval($_POST['batch_size'] ?? 20);
            
            if (!$type) {
                throw new Exception('Process type not specified');
            }
            
            if ($this->logger) {
                $this->logger->log("Processing {$type} - offset: {$offset}, batch: {$batch_size}", 'info');
            }
            
            // Route to appropriate processor
            switch ($type) {
                case 'posts':
                    $result = $this->process_posts($offset, $batch_size);
                    break;
                case 'taxonomies':
                    $result = $this->process_taxonomies($offset, $batch_size);
                    break;
                case 'woocommerce':
                    $result = $this->process_woocommerce($offset, $batch_size);
                    break;
                case 'betterdocs':
                    $result = $this->process_betterdocs($offset, $batch_size);
                    break;
                case 'translations':
                    $result = $this->process_translations($offset, $batch_size);
                    break;
                default:
                    throw new Exception("Unknown process type: {$type}");
            }
            
            if ($this->logger) {
                $this->logger->log("Process {$type} completed - Fixed: {$result['fixed']}, Continue: " . ($result['continue'] ? 'yes' : 'no'), 'info');
            }
            
            wp_send_json_success($result);
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->log_error("Process failed", $e);
            }
            wp_send_json_error('Process failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Process taxonomies - Fix all terms without language assignment
     * Enhanced with robust fallbacks and cache clearing
     */
    private function process_taxonomies($offset = 0, $batch_size = 20) {
        global $wpdb;
        
        $start_time = microtime(true);
        $fixed = 0;
        $processed = 0;
        
        try {
            // Get excluded taxonomies
            $excluded_taxonomies = $this->db_helper->get_excluded_taxonomies();
            
            // Get all public taxonomies except excluded ones
            $public_taxonomies = get_taxonomies(['public' => true], 'names');
            $target_taxonomies = array_diff($public_taxonomies, $excluded_taxonomies);
            
            if (empty($target_taxonomies)) {
                return [
                    'total' => 0,
                    'processed' => 0,
                    'fixed' => 0,
                    'continue' => false,
                    'message' => 'No taxonomies to process'
                ];
            }
            
            // Escape and quote taxonomies for SQL (hardened approach)
            $taxonomies_sql = array_map('esc_sql', $target_taxonomies);
            $taxonomies_str = "'" . implode("','", $taxonomies_sql) . "'";
            
            // Get total count of orphaned terms
            $total_orphaned = $wpdb->get_var("
                SELECT COUNT(DISTINCT t.term_id)
                FROM {$wpdb->terms} t
                JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
                LEFT JOIN {$wpdb->term_relationships} tr ON t.term_id = tr.object_id
                LEFT JOIN {$wpdb->term_taxonomy} tl ON tr.term_taxonomy_id = tl.term_taxonomy_id AND tl.taxonomy = 'term_language'
                WHERE tt.taxonomy IN ({$taxonomies_str})
                AND tl.term_taxonomy_id IS NULL
            ");
            
            if ($total_orphaned == 0) {
                return [
                    'total' => 0,
                    'processed' => $offset,
                    'fixed' => 0,
                    'continue' => false,
                    'message' => 'All terms already have language assignments'
                ];
            }
            
            // Get batch of orphaned terms
            $orphaned_terms = $wpdb->get_results($wpdb->prepare("
                SELECT DISTINCT t.term_id, tt.taxonomy, tt.term_taxonomy_id
                FROM {$wpdb->terms} t
                JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
                LEFT JOIN {$wpdb->term_relationships} tr ON t.term_id = tr.object_id
                LEFT JOIN {$wpdb->term_taxonomy} tl ON tr.term_taxonomy_id = tl.term_taxonomy_id AND tl.taxonomy = 'term_language'
                WHERE tt.taxonomy IN ({$taxonomies_str})
                AND tl.term_taxonomy_id IS NULL
                LIMIT %d OFFSET %d
            ", $batch_size, $offset));
            
            // Enhanced default language fallback
            $default_lang = pll_default_language();
            if (empty($default_lang) && function_exists('pll_languages_list')) {
                $langs = pll_languages_list(['fields' => 'slug']);
                if (!empty($langs)) {
                    $default_lang = reset($langs);
                    if ($this->logger) {
                        $this->logger->log("Using first available language as fallback: {$default_lang}", 'info');
                    }
                }
            }
            
            if (empty($default_lang)) {
                throw new Exception('No default language available - Polylang may not be properly configured');
            }
            
            // Process each orphaned term
            foreach ($orphaned_terms as $term_data) {
                $processed++;
                
                try {
                    // Check if WPML data exists for this term
                    $wpml_language = null;
                    if ($this->db_helper->wpml_tables_exist()) {
                        $wpml_language = $this->db_helper->get_wpml_term_language(
                            $term_data->term_taxonomy_id, 
                            $term_data->taxonomy
                        );
                    }
                    
                    // Determine target language
                    if ($wpml_language) {
                        $target_language = $this->language_converter->convert_language($wpml_language);
                        if ($this->logger) {
                            $this->logger->log("Term {$term_data->term_id}: WPML {$wpml_language} -> PLL {$target_language}", 'debug');
                        }
                    } else {
                        $target_language = $default_lang;
                        if ($this->logger) {
                            $this->logger->log("Term {$term_data->term_id}: No WPML data, using default {$target_language}", 'debug');
                        }
                    }
                    
                    // Check if term already has this language (idempotency)
                    $current_language = pll_get_term_language($term_data->term_id);
                    if ($current_language === $target_language) {
                        if ($this->logger) {
                            $this->logger->log("Term {$term_data->term_id} already has language {$target_language}, skipping", 'debug');
                        }
                        continue;
                    }
                    
                    // Assign language using Polylang API
                    $result = pll_set_term_language($term_data->term_id, $target_language);
                    
                    if ($result !== false) {
                        $fixed++;
                        
                        // Clear term cache to prevent stale reads
                        clean_term_cache($term_data->term_id, $term_data->taxonomy);
                        
                        if ($this->logger) {
                            $this->logger->log("Fixed term {$term_data->term_id} ({$term_data->taxonomy}): set to {$target_language}", 'info');
                        }
                    } else {
                        if ($this->logger) {
                            $this->logger->log("Failed to set language for term {$term_data->term_id}", 'warning');
                        }
                    }
                    
                } catch (Exception $e) {
                    if ($this->logger) {
                        $this->logger->log_error("Error processing term {$term_data->term_id}", $e);
                    }
                    continue;
                }
            }
            
            $total_processed = $offset + $processed;
            $continue = ($total_processed < $total_orphaned);
            $next_offset = $offset + $batch_size;
            
            // Log performance
            $time_taken = microtime(true) - $start_time;
            if ($this->logger) {
                $this->logger->log_performance('process_taxonomies', $time_taken, $processed);
            }
            
            return [
                'total' => intval($total_orphaned),
                'processed' => $total_processed,
                'fixed' => $fixed,
                'continue' => $continue,
                'next_offset' => $next_offset,
                'message' => sprintf(
                    'Processed %d terms, fixed %d. %s',
                    $processed,
                    $fixed,
                    $continue ? "Continuing..." : "Complete!"
                )
            ];
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->log_error("Taxonomy processing failed", $e);
            }
            throw $e;
        }
    }
    
    /**
     * Process posts - Fix posts without language assignment
     */
    private function process_posts($offset = 0, $batch_size = 20) {
        global $wpdb;
        
        $start_time = microtime(true);
        $fixed = 0;
        $processed = 0;
        
        try {
            // Get excluded post types
            $excluded_post_types = $this->db_helper->get_excluded_post_types();
            
            // Get all public post types except excluded ones
            $public_post_types = get_post_types(['public' => true], 'names');
            $target_post_types = array_diff($public_post_types, $excluded_post_types);
            
            if (empty($target_post_types)) {
                return [
                    'total' => 0,
                    'processed' => 0,
                    'fixed' => 0,
                    'continue' => false,
                    'message' => 'No post types to process'
                ];
            }
            
            // Escape and quote post types for SQL
            $post_types_sql = array_map('esc_sql', $target_post_types);
            $post_types_str = "'" . implode("','", $post_types_sql) . "'";
            
            // Get total count of orphaned posts
            $total_orphaned = $wpdb->get_var("
                SELECT COUNT(*)
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
                LEFT JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = 'language'
                WHERE p.post_type IN ({$post_types_str})
                AND p.post_status IN ('publish', 'draft', 'private')
                AND tt.term_taxonomy_id IS NULL
            ");
            
            if ($total_orphaned == 0) {
                return [
                    'total' => 0,
                    'processed' => $offset,
                    'fixed' => 0,
                    'continue' => false,
                    'message' => 'All posts already have language assignments'
                ];
            }
            
            // Get batch of orphaned posts
            $orphaned_posts = $wpdb->get_results($wpdb->prepare("
                SELECT p.ID, p.post_type
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
                LEFT JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = 'language'
                WHERE p.post_type IN ({$post_types_str})
                AND p.post_status IN ('publish', 'draft', 'private')
                AND tt.term_taxonomy_id IS NULL
                LIMIT %d OFFSET %d
            ", $batch_size, $offset));
            
            // Enhanced default language fallback
            $default_lang = pll_default_language();
            if (empty($default_lang) && function_exists('pll_languages_list')) {
                $langs = pll_languages_list(['fields' => 'slug']);
                if (!empty($langs)) {
                    $default_lang = reset($langs);
                }
            }
            
            if (empty($default_lang)) {
                throw new Exception('No default language available - Polylang may not be properly configured');
            }
            
            // Process each orphaned post
            foreach ($orphaned_posts as $post_data) {
                $processed++;
                
                try {
                    // Check if WPML data exists for this post
                    $wpml_language = null;
                    if ($this->db_helper->wpml_tables_exist()) {
                        $wpml_language = $this->db_helper->get_wpml_post_language(
                            $post_data->ID, 
                            $post_data->post_type
                        );
                    }
                    
                    // Determine target language
                    if ($wpml_language) {
                        $target_language = $this->language_converter->convert_language($wpml_language);
                        if ($this->logger) {
                            $this->logger->log("Post {$post_data->ID}: WPML {$wpml_language} -> PLL {$target_language}", 'debug');
                        }
                    } else {
                        $target_language = $default_lang;
                        if ($this->logger) {
                            $this->logger->log("Post {$post_data->ID}: No WPML data, using default {$target_language}", 'debug');
                        }
                    }
                    
                    // Check if post already has this language (idempotency)
                    $current_language = pll_get_post_language($post_data->ID);
                    if ($current_language === $target_language) {
                        if ($this->logger) {
                            $this->logger->log("Post {$post_data->ID} already has language {$target_language}, skipping", 'debug');
                        }
                        continue;
                    }
                    
                    // Special handling for product variations
                    if ($post_data->post_type === 'product_variation') {
                        $parent_id = wp_get_post_parent_id($post_data->ID);
                        if (!$parent_id) {
                            $parent_id = get_post_meta($post_data->ID, '_parent_id', true);
                        }
                        
                        if ($parent_id) {
                            $parent_language = pll_get_post_language($parent_id);
                            if ($parent_language) {
                                $target_language = $parent_language;
                                if ($this->logger) {
                                    $this->logger->log("Variation {$post_data->ID}: inheriting parent language {$target_language}", 'debug');
                                }
                            }
                        }
                    }
                    
                    // Assign language using Polylang API
                    $result = pll_set_post_language($post_data->ID, $target_language);
                    
                    if ($result !== false) {
                        $fixed++;
                        if ($this->logger) {
                            $this->logger->log("Fixed post {$post_data->ID} ({$post_data->post_type}): set to {$target_language}", 'info');
                        }
                    } else {
                        if ($this->logger) {
                            $this->logger->log("Failed to set language for post {$post_data->ID}", 'warning');
                        }
                    }
                    
                } catch (Exception $e) {
                    if ($this->logger) {
                        $this->logger->log_error("Error processing post {$post_data->ID}", $e);
                    }
                    continue;
                }
            }
            
            $total_processed = $offset + $processed;
            $continue = ($total_processed < $total_orphaned);
            $next_offset = $offset + $batch_size;
            
            // Log performance
            $time_taken = microtime(true) - $start_time;
            if ($this->logger) {
                $this->logger->log_performance('process_posts', $time_taken, $processed);
            }
            
            return [
                'total' => intval($total_orphaned),
                'processed' => $total_processed,
                'fixed' => $fixed,
                'continue' => $continue,
                'next_offset' => $next_offset,
                'message' => sprintf(
                    'Processed %d posts, fixed %d. %s',
                    $processed,
                    $fixed,
                    $continue ? "Continuing..." : "Complete!"
                )
            ];
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->log_error("Post processing failed", $e);
            }
            throw $e;
        }
    }
    
    /**
     * Process BetterDocs - Fix docs and BetterDocs taxonomies
     */
    private function process_betterdocs($offset = 0, $batch_size = 20) {
        // Check if BetterDocs is active
        if (!post_type_exists('docs')) {
            return [
                'total' => 0,
                'processed' => 0,
                'fixed' => 0,
                'continue' => false,
                'message' => 'BetterDocs not active'
            ];
        }
        
        $start_time = microtime(true);
        $total_fixed = 0;
        
        // Process docs posts first (offset 0-999 for docs, 1000+ for terms)
        if ($offset < 1000) {
            $docs_result = $this->process_betterdocs_posts($offset, $batch_size);
            $total_fixed += $docs_result['fixed'];
            
            if ($docs_result['continue']) {
                return [
                    'total' => $docs_result['total'] + 1000, // Add buffer for terms
                    'processed' => $docs_result['processed'],
                    'fixed' => $total_fixed,
                    'continue' => true,
                    'next_offset' => $docs_result['next_offset'],
                    'message' => 'Processing docs: ' . $docs_result['message']
                ];
            } else {
                // Docs complete, start terms at offset 1000
                return [
                    'total' => $docs_result['total'] + 1000,
                    'processed' => 1000,
                    'fixed' => $total_fixed,
                    'continue' => true,
                    'next_offset' => 1000,
                    'message' => 'Docs complete, starting BetterDocs taxonomies...'
                ];
            }
        }
        
        // Process BetterDocs terms (offset >= 1000)
        $terms_offset = $offset - 1000;
        $terms_result = $this->process_betterdocs_terms($terms_offset, $batch_size);
        $total_fixed += $terms_result['fixed'];
        
        return [
            'total' => 1000 + $terms_result['total'],
            'processed' => 1000 + $terms_result['processed'],
            'fixed' => $total_fixed,
            'continue' => $terms_result['continue'],
            'next_offset' => $terms_result['continue'] ? (1000 + $terms_result['next_offset']) : null,
            'message' => 'Processing BetterDocs terms: ' . $terms_result['message']
        ];
    }
    
    /**
     * Process BetterDocs posts
     */
    private function process_betterdocs_posts($offset, $batch_size) {
        global $wpdb;
        
        // Enhanced default language fallback
        $default_lang = pll_default_language();
        if (empty($default_lang) && function_exists('pll_languages_list')) {
            $langs = pll_languages_list(['fields' => 'slug']);
            if (!empty($langs)) {
                $default_lang = reset($langs);
            }
        }
        
        if (empty($default_lang)) {
            throw new Exception('No default language available for BetterDocs processing');
        }
        
        // Get total docs without language
        $total_orphaned = $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
            LEFT JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = 'language'
            WHERE p.post_type = 'docs'
            AND p.post_status IN ('publish', 'draft', 'private')
            AND tt.term_taxonomy_id IS NULL
        ");
        
        if ($total_orphaned == 0) {
            return [
                'total' => 0,
                'processed' => $offset,
                'fixed' => 0,
                'continue' => false,
                'message' => 'All docs have language assignments'
            ];
        }
        
        // Get batch of docs
        $docs = $wpdb->get_results($wpdb->prepare("
            SELECT p.ID
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
            LEFT JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = 'language'
            WHERE p.post_type = 'docs'
            AND p.post_status IN ('publish', 'draft', 'private')
            AND tt.term_taxonomy_id IS NULL
            LIMIT %d OFFSET %d
        ", $batch_size, $offset));
        
        $fixed = 0;
        foreach ($docs as $doc) {
            // Check WPML language
            $wpml_language = null;
            if ($this->db_helper->wpml_tables_exist()) {
                $wpml_language = $this->db_helper->get_wpml_post_language($doc->ID, 'docs');
            }
            
            $target_language = $wpml_language 
                ? $this->language_converter->convert_language($wpml_language)
                : $default_lang;
            
            if (pll_set_post_language($doc->ID, $target_language)) {
                $fixed++;
            }
        }
        
        $total_processed = $offset + count($docs);
        $continue = ($total_processed < $total_orphaned);
        
        return [
            'total' => intval($total_orphaned),
            'processed' => $total_processed,
            'fixed' => $fixed,
            'continue' => $continue,
            'next_offset' => $offset + $batch_size,
            'message' => sprintf('Fixed %d/%d docs', $fixed, count($docs))
        ];
    }
    
    /**
     * Process BetterDocs terms
     */
    private function process_betterdocs_terms($offset, $batch_size) {
        global $wpdb;
        
        $bd_taxonomies = ['doc_category', 'doc_tag', 'knowledge_base'];
        $existing_taxonomies = array_filter($bd_taxonomies, 'taxonomy_exists');
        
        if (empty($existing_taxonomies)) {
            return [
                'total' => 0,
                'processed' => 0,
                'fixed' => 0,
                'continue' => false,
                'message' => 'No BetterDocs taxonomies found'
            ];
        }
        
        // Enhanced default language fallback
        $default_lang = pll_default_language();
        if (empty($default_lang) && function_exists('pll_languages_list')) {
            $langs = pll_languages_list(['fields' => 'slug']);
            if (!empty($langs)) {
                $default_lang = reset($langs);
            }
        }
        
        if (empty($default_lang)) {
            throw new Exception('No default language available for BetterDocs terms processing');
        }
        
        $taxonomies_str = "'" . implode("','", array_map('esc_sql', $existing_taxonomies)) . "'";
        
        // Get total terms without language
        $total_orphaned = $wpdb->get_var("
            SELECT COUNT(DISTINCT t.term_id)
            FROM {$wpdb->terms} t
            JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
            LEFT JOIN {$wpdb->term_relationships} tr ON t.term_id = tr.object_id
            LEFT JOIN {$wpdb->term_taxonomy} tl ON tr.term_taxonomy_id = tl.term_taxonomy_id AND tl.taxonomy = 'term_language'
            WHERE tt.taxonomy IN ({$taxonomies_str})
            AND tl.term_taxonomy_id IS NULL
        ");
        
        if ($total_orphaned == 0) {
            return [
                'total' => 0,
                'processed' => $offset,
                'fixed' => 0,
                'continue' => false,
                'message' => 'All BetterDocs terms have language assignments'
            ];
        }
        
        // Get batch of terms
        $terms = $wpdb->get_results($wpdb->prepare("
            SELECT DISTINCT t.term_id, tt.taxonomy, tt.term_taxonomy_id
            FROM {$wpdb->terms} t
            JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
            LEFT JOIN {$wpdb->term_relationships} tr ON t.term_id = tr.object_id
            LEFT JOIN {$wpdb->term_taxonomy} tl ON tr.term_taxonomy_id = tl.term_taxonomy_id AND tl.taxonomy = 'term_language'
            WHERE tt.taxonomy IN ({$taxonomies_str})
            AND tl.term_taxonomy_id IS NULL
            LIMIT %d OFFSET %d
        ", $batch_size, $offset));
        
        $fixed = 0;
        foreach ($terms as $term_data) {
            // Check WPML language
            $wpml_language = null;
            if ($this->db_helper->wpml_tables_exist()) {
                $wpml_language = $this->db_helper->get_wpml_term_language(
                    $term_data->term_taxonomy_id, 
                    $term_data->taxonomy
                );
            }
            
            $target_language = $wpml_language 
                ? $this->language_converter->convert_language($wpml_language)
                : $default_lang;
            
            if (pll_set_term_language($term_data->term_id, $target_language)) {
                $fixed++;
                // Clear term cache to prevent stale reads
                clean_term_cache($term_data->term_id, $term_data->taxonomy);
            }
        }
        
        $total_processed = $offset + count($terms);
        $continue = ($total_processed < $total_orphaned);
        
        return [
            'total' => intval($total_orphaned),
            'processed' => $total_processed,
            'fixed' => $fixed,
            'continue' => $continue,
            'next_offset' => $offset + $batch_size,
            'message' => sprintf('Fixed %d/%d BetterDocs terms', $fixed, count($terms))
        ];
    }
    
    /**
     * Process translation groups - Fix WPML translation groups not converted to Polylang
     */
    private function process_translations($offset = 0, $batch_size = 20) {
        global $wpdb;
        
        if (!$this->db_helper->wpml_tables_exist()) {
            return [
                'total' => 0,
                'processed' => 0,
                'fixed' => 0,
                'continue' => false,
                'message' => 'No WPML data found'
            ];
        }
        
        $start_time = microtime(true);
        $total_fixed = 0;
        
        // Process in phases: corrupted groups (0-499), posts (500-1499), terms (1500+)
        if ($offset < 500) {
            // Phase 1: Repair corrupted translation groups
            $corrupted_result = $this->repair_corrupted_translation_groups($offset, $batch_size);
            $total_fixed += $corrupted_result['fixed'];
            
            if ($corrupted_result['continue']) {
                return [
                    'total' => $corrupted_result['total'] + 2000, // Add buffer for posts + terms
                    'processed' => $corrupted_result['processed'],
                    'fixed' => $total_fixed,
                    'continue' => true,
                    'next_offset' => $corrupted_result['next_offset'],
                    'message' => 'Repairing corrupted groups: ' . $corrupted_result['message']
                ];
            } else {
                // Corrupted groups complete, start posts at offset 500
                return [
                    'total' => $corrupted_result['total'] + 2000,
                    'processed' => 500,
                    'fixed' => $total_fixed,
                    'continue' => true,
                    'next_offset' => 500,
                    'message' => 'Corrupted groups repaired, starting post groups...'
                ];
            }
        } elseif ($offset < 1500) {
            // Phase 2: Process missing post translation groups
            $posts_offset = $offset - 500;
            $posts_result = $this->process_post_translation_groups($posts_offset, $batch_size);
            $total_fixed += $posts_result['fixed'];
            
            if ($posts_result['continue']) {
                return [
                    'total' => 500 + $posts_result['total'] + 1000, // corrupted + posts + terms buffer
                    'processed' => 500 + $posts_result['processed'],
                    'fixed' => $total_fixed,
                    'continue' => true,
                    'next_offset' => 500 + $posts_result['next_offset'],
                    'message' => 'Processing post groups: ' . $posts_result['message']
                ];
            } else {
                // Posts complete, start terms at offset 1500
                return [
                    'total' => 500 + $posts_result['total'] + 1000,
                    'processed' => 1500,
                    'fixed' => $total_fixed,
                    'continue' => true,
                    'next_offset' => 1500,
                    'message' => 'Post groups complete, starting term groups...'
                ];
            }
        } else {
            // Phase 3: Process missing term translation groups
            $terms_offset = $offset - 1500;
            $terms_result = $this->process_term_translation_groups($terms_offset, $batch_size);
            $total_fixed += $terms_result['fixed'];
            
            return [
                'total' => 1500 + $terms_result['total'],
                'processed' => 1500 + $terms_result['processed'],
                'fixed' => $total_fixed,
                'continue' => $terms_result['continue'],
                'next_offset' => $terms_result['continue'] ? (1500 + $terms_result['next_offset']) : null,
                'message' => 'Processing term groups: ' . $terms_result['message']
            ];
        }
    }
    
    /**
     * Repair corrupted translation groups (invalid serialized data)
     */
    private function repair_corrupted_translation_groups($offset, $batch_size) {
        global $wpdb;
        
        // Get corrupted translation groups (both post and term translations)
        $corrupted_groups = $wpdb->get_results($wpdb->prepare("
            SELECT t.slug, tt.term_id, tt.term_taxonomy_id, tt.taxonomy, tt.description, tt.count
            FROM {$wpdb->terms} t
            JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
            WHERE tt.taxonomy IN ('post_translations', 'term_translations')
            AND t.slug LIKE 'pll_wpml_%%'
            AND tt.description != ''
            AND tt.description != 'b:0;'
            LIMIT %d OFFSET %d
        ", $batch_size, $offset));
        
        $total_corrupted = $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$wpdb->terms} t
            JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
            WHERE tt.taxonomy IN ('post_translations', 'term_translations')
            AND t.slug LIKE 'pll_wpml_%'
            AND tt.description != ''
            AND tt.description != 'b:0;'
        ");
        
        if ($total_corrupted == 0) {
            return [
                'total' => 0,
                'processed' => $offset,
                'fixed' => 0,
                'continue' => false,
                'message' => 'No corrupted translation groups found'
            ];
        }
        
        $fixed = 0;
        foreach ($corrupted_groups as $group) {
            try {
                // Try to unserialize the description
                $data = @unserialize($group->description);
                
                if ($data === false && $group->description !== 'b:0;') {
                    // Corrupted serialized data - attempt to repair
                    if ($this->repair_translation_group($group)) {
                        $fixed++;
                    }
                } elseif (is_array($data)) {
                    // Valid data but verify integrity
                    if ($this->verify_and_fix_translation_group($group, $data)) {
                        $fixed++;
                    }
                }
                
            } catch (Exception $e) {
                if ($this->logger) {
                    $this->logger->log_error("Failed to repair translation group {$group->slug}", $e);
                }
            }
        }
        
        $total_processed = $offset + count($corrupted_groups);
        $continue = ($total_processed < $total_corrupted);
        
        return [
            'total' => intval($total_corrupted),
            'processed' => $total_processed,
            'fixed' => $fixed,
            'continue' => $continue,
            'next_offset' => $offset + $batch_size,
            'message' => sprintf('Repaired %d/%d corrupted groups', $fixed, count($corrupted_groups))
        ];
    }
    
    /**
     * Process post translation groups
     */
    private function process_post_translation_groups($offset, $batch_size) {
        global $wpdb;
        
        $icl_table = $wpdb->prefix . 'icl_translations';
        
        // Get total WPML post translation groups that need processing
        $total_trids = $wpdb->get_var("
            SELECT COUNT(DISTINCT trid)
            FROM {$icl_table}
            WHERE element_type LIKE 'post_%'
            AND trid IN (
                SELECT trid FROM {$icl_table}
                WHERE element_type LIKE 'post_%'
                GROUP BY trid HAVING COUNT(*) > 1
            )
            AND trid NOT IN (
                SELECT CAST(REPLACE(t.slug, 'pll_wpml_', '') AS UNSIGNED)
                FROM {$wpdb->terms} t
                JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
                WHERE tt.taxonomy = 'post_translations'
                AND t.slug LIKE 'pll_wpml_%'
                AND t.slug REGEXP '^pll_wpml_[0-9]+$'
            )
        ");
        
        if ($total_trids == 0) {
            return [
                'total' => 0,
                'processed' => $offset,
                'fixed' => 0,
                'continue' => false,
                'message' => 'All post translation groups already converted'
            ];
        }
        
        // Get batch of orphaned trids
        $orphaned_trids = $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT trid
            FROM {$icl_table}
            WHERE element_type LIKE 'post_%'
            AND trid IN (
                SELECT trid FROM {$icl_table}
                WHERE element_type LIKE 'post_%'
                GROUP BY trid HAVING COUNT(*) > 1
            )
            AND trid NOT IN (
                SELECT CAST(REPLACE(t.slug, 'pll_wpml_', '') AS UNSIGNED)
                FROM {$wpdb->terms} t
                JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
                WHERE tt.taxonomy = 'post_translations'
                AND t.slug LIKE 'pll_wpml_%'
                AND t.slug REGEXP '^pll_wpml_[0-9]+$'
            )
            LIMIT %d OFFSET %d
        ", $batch_size, $offset));
        
        $fixed = 0;
        foreach ($orphaned_trids as $trid) {
            try {
                if ($this->create_post_translation_group($trid)) {
                    $fixed++;
                }
            } catch (Exception $e) {
                if ($this->logger) {
                    $this->logger->log_error("Failed to create post translation group for trid {$trid}", $e);
                }
            }
        }
        
        $total_processed = $offset + count($orphaned_trids);
        $continue = ($total_processed < $total_trids);
        
        return [
            'total' => intval($total_trids),
            'processed' => $total_processed,
            'fixed' => $fixed,
            'continue' => $continue,
            'next_offset' => $offset + $batch_size,
            'message' => sprintf('Fixed %d/%d post groups', $fixed, count($orphaned_trids))
        ];
    }
    
    /**
     * Process term translation groups
     */
    private function process_term_translation_groups($offset, $batch_size) {
        global $wpdb;
        
        $icl_table = $wpdb->prefix . 'icl_translations';
        
        // Get total WPML term translation groups that need processing
        $total_trids = $wpdb->get_var("
            SELECT COUNT(DISTINCT trid)
            FROM {$icl_table}
            WHERE element_type LIKE 'tax_%'
            AND trid IN (
                SELECT trid FROM {$icl_table}
                WHERE element_type LIKE 'tax_%'
                GROUP BY trid HAVING COUNT(*) > 1
            )
            AND trid NOT IN (
                SELECT CAST(REPLACE(t.slug, 'pll_wpml_', '') AS UNSIGNED)
                FROM {$wpdb->terms} t
                JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
                WHERE tt.taxonomy = 'term_translations'
                AND t.slug LIKE 'pll_wpml_%'
                AND t.slug REGEXP '^pll_wpml_[0-9]+$'
            )
        ");
        
        if ($total_trids == 0) {
            return [
                'total' => 0,
                'processed' => $offset,
                'fixed' => 0,
                'continue' => false,
                'message' => 'All term translation groups already converted'
            ];
        }
        
        // Get batch of orphaned trids - FIXED SQL query
        $orphaned_trids = $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT trid
            FROM {$icl_table}
            WHERE element_type LIKE 'tax_%%'
            AND trid IN (
                SELECT trid FROM {$icl_table}
                WHERE element_type LIKE 'tax_%%'
                GROUP BY trid HAVING COUNT(*) > 1
            )
            AND trid NOT IN (
                SELECT CAST(REPLACE(t.slug, 'pll_wpml_', '') AS UNSIGNED)
                FROM {$wpdb->terms} t
                JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
                WHERE tt.taxonomy = 'term_translations'
                AND t.slug LIKE 'pll_wpml_%%'
                AND t.slug REGEXP '^pll_wpml_[0-9]+$'
            )
            LIMIT %d OFFSET %d
        ", $batch_size, $offset));
        
        $fixed = 0;
        foreach ($orphaned_trids as $trid) {
            try {
                if ($this->create_term_translation_group($trid)) {
                    $fixed++;
                }
            } catch (Exception $e) {
                if ($this->logger) {
                    $this->logger->log_error("Failed to create term translation group for trid {$trid}", $e);
                }
            }
        }
        
        $total_processed = $offset + count($orphaned_trids);
        $continue = ($total_processed < $total_trids);
        
        return [
            'total' => intval($total_trids),
            'processed' => $total_processed,
            'fixed' => $fixed,
            'continue' => $continue,
            'next_offset' => $offset + $batch_size,
            'message' => sprintf('Fixed %d/%d term groups', $fixed, count($orphaned_trids))
        ];
    }
    
    /**
     * Helper methods for translation group creation and repair
     */
    private function create_post_translation_group($trid) {
        // Implementation continues in next method...
        return $this->create_translation_group_core($trid, 'post');
    }
    
    private function create_term_translation_group($trid) {
        return $this->create_translation_group_core($trid, 'term');
    }
    
    /**
     * Core translation group creation logic
     */
    private function create_translation_group_core($trid, $type) {
        global $wpdb;
        
        $icl_table = $wpdb->prefix . 'icl_translations';
        $element_pattern = ($type === 'post') ? 'post_%' : 'tax_%';
        $taxonomy = ($type === 'post') ? 'post_translations' : 'term_translations';
        
        try {
            $wpdb->query('START TRANSACTION');
            
            // Get all elements in this WPML translation group
            $translations = $wpdb->get_results($wpdb->prepare("
                SELECT element_id, language_code, element_type
                FROM {$icl_table}
                WHERE trid = %d AND element_type LIKE %s
                ORDER BY element_id
            ", $trid, $element_pattern));
            
            if (empty($translations)) {
                throw new Exception("No translations found for trid {$trid}");
            }
            
            // Build translation map and validate objects
            $translation_map = [];
            $valid_objects = [];
            
            foreach ($translations as $trans) {
                $object_id = intval($trans->element_id);
                
                // For terms, element_id is term_taxonomy_id, need to get term_id
                if ($type === 'term') {
                    $term_data = $wpdb->get_row($wpdb->prepare(
                        "SELECT term_id FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id = %d",
                        $object_id
                    ));
                    
                    if ($term_data) {
                        $object_id = intval($term_data->term_id);
                    } else {
                        continue; // Skip if term doesn't exist
                    }
                }
                
                // Check if object still exists
                $table = ($type === 'post') ? $wpdb->posts : $wpdb->terms;
                $id_field = ($type === 'post') ? 'ID' : 'term_id';
                
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT {$id_field} FROM {$table} WHERE {$id_field} = %d",
                    $object_id
                ));
                
                if ($exists) {
                    // Convert WPML language to Polylang language
                    $pll_language = $this->language_converter->convert_language($trans->language_code);
                    $translation_map[$pll_language] = $object_id;
                    $valid_objects[] = $object_id;
                    
                    // Ensure object has correct language assignment
                    if ($type === 'post') {
                        $current_lang = pll_get_post_language($object_id);
                        if ($current_lang !== $pll_language) {
                            pll_set_post_language($object_id, $pll_language);
                        }
                    } else {
                        $current_lang = pll_get_term_language($object_id);
                        if ($current_lang !== $pll_language) {
                            pll_set_term_language($object_id, $pll_language);
                        }
                    }
                }
            }
            
            if (count($translation_map) < 2) {
                $wpdb->query('ROLLBACK');
                if ($this->logger) {
                    $this->logger->log("Skipping trid {$trid}: less than 2 valid translations", 'debug');
                }
                return false;
            }
            
            // Create translation group term
            $term_slug = 'pll_wpml_' . $trid;
            
            // Check if term already exists
            $existing_term = $wpdb->get_var($wpdb->prepare(
                "SELECT term_id FROM {$wpdb->terms} WHERE slug = %s",
                $term_slug
            ));
            
            if ($existing_term) {
                $wpdb->query('ROLLBACK');
                return false;
            }
            
            // Insert term
            $result = $wpdb->insert(
                $wpdb->terms,
                [
                    'name' => $term_slug,
                    'slug' => $term_slug,
                    'term_group' => 0
                ],
                ['%s', '%s', '%d']
            );
            
            if ($result === false) {
                throw new Exception("Failed to insert translation group term");
            }
            
            $term_id = $wpdb->insert_id;
            
            // Insert term taxonomy
            $serialized_map = serialize($translation_map);
            $result = $wpdb->insert(
                $wpdb->term_taxonomy,
                [
                    'term_id' => $term_id,
                    'taxonomy' => $taxonomy,
                    'description' => $serialized_map,
                    'parent' => 0,
                    'count' => count($valid_objects)
                ],
                ['%d', '%s', '%s', '%d', '%d']
            );
            
            if ($result === false) {
                throw new Exception("Failed to insert translation group taxonomy");
            }
            
            $term_taxonomy_id = $wpdb->insert_id;
            
            // Link all objects to this translation group
            foreach ($valid_objects as $object_id) {
                $wpdb->query($wpdb->prepare(
                    "INSERT IGNORE INTO {$wpdb->term_relationships} (object_id, term_taxonomy_id, term_order)
                     VALUES (%d, %d, 0)",
                    $object_id,
                    $term_taxonomy_id
                ));
            }
            
            $wpdb->query('COMMIT');
            
            if ($this->logger) {
                $this->logger->log("Created {$type} translation group {$term_slug} with " . count($valid_objects) . " objects", 'info');
            }
            
            return true;
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            if ($this->logger) {
                $this->logger->log_error("Failed to create {$type} translation group for trid {$trid}", $e);
            }
            return false;
        }
    }
    
    /**
     * Repair a corrupted translation group
     */
    private function repair_translation_group($group) {
        global $wpdb;
        
        try {
            $wpdb->query('START TRANSACTION');
            
            // Extract trid from slug (pll_wpml_123 -> 123)
            if (!preg_match('/^pll_wpml_(\d+)$/', $group->slug, $matches)) {
                throw new Exception("Invalid translation group slug: {$group->slug}");
            }
            
            $trid = intval($matches[1]);
            $icl_table = $wpdb->prefix . 'icl_translations';
            
            // Get objects from this translation group using relationships
            $objects = $wpdb->get_results($wpdb->prepare("
                SELECT tr.object_id
                FROM {$wpdb->term_relationships} tr
                WHERE tr.term_taxonomy_id = %d
            ", $group->term_taxonomy_id));
            
            if (empty($objects)) {
                // No objects linked - delete the orphaned group
                $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id = %d", $group->term_taxonomy_id));
                $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->terms} WHERE term_id = %d", $group->term_id));
                
                $wpdb->query('COMMIT');
                if ($this->logger) {
                    $this->logger->log("Deleted orphaned translation group {$group->slug}", 'info');
                }
                return true;
            }
            
            // Rebuild description from linked objects
            $translation_map = [];
            
            foreach ($objects as $obj) {
                $object_id = intval($obj->object_id);
                
                // Get language for this object
                if ($group->taxonomy === 'post_translations') {
                    $language = pll_get_post_language($object_id);
                } else {
                    $language = pll_get_term_language($object_id);
                }
                
                if ($language) {
                    $translation_map[$language] = $object_id;
                }
            }
            
            if (count($translation_map) >= 2) {
                // Update with rebuilt description
                $serialized_map = serialize($translation_map);
                $wpdb->update(
                    $wpdb->term_taxonomy,
                    [
                        'description' => $serialized_map,
                        'count' => count($translation_map)
                    ],
                    ['term_taxonomy_id' => $group->term_taxonomy_id],
                    ['%s', '%d'],
                    ['%d']
                );
            } else {
                // Not enough valid translations - delete the group
                $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->term_relationships} WHERE term_taxonomy_id = %d", $group->term_taxonomy_id));
                $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id = %d", $group->term_taxonomy_id));
                $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->terms} WHERE term_id = %d", $group->term_id));
            }
            
            $wpdb->query('COMMIT');
            
            if ($this->logger) {
                $this->logger->log("Repaired corrupted translation group {$group->slug}", 'info');
            }
            
            return true;
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            if ($this->logger) {
                $this->logger->log_error("Failed to repair translation group {$group->slug}", $e);
            }
            return false;
        }
    }
    
    /**
     * Verify and fix a translation group with valid data
     */
    private function verify_and_fix_translation_group($group, $data) {
        global $wpdb;
        
        if (!is_array($data)) {
            return false;
        }
        
        try {
            $valid_translations = [];
            $needs_update = false;
            
            // Verify each translation in the map
            foreach ($data as $language => $object_id) {
                $object_id = intval($object_id);
                
                // Check if object still exists
                if ($group->taxonomy === 'post_translations') {
                    $exists = $wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE ID = %d", $object_id));
                } else {
                    $exists = $wpdb->get_var($wpdb->prepare("SELECT term_id FROM {$wpdb->terms} WHERE term_id = %d", $object_id));
                }
                
                if ($exists) {
                    $valid_translations[$language] = $object_id;
                } else {
                    $needs_update = true;
                    if ($this->logger) {
                        $this->logger->log("Removing missing object {$object_id} from translation group {$group->slug}", 'debug');
                    }
                }
            }
            
            // Check if count matches
            if ($group->count != count($valid_translations)) {
                $needs_update = true;
            }
            
            if ($needs_update && count($valid_translations) >= 2) {
                // Update the translation group
                $serialized_map = serialize($valid_translations);
                $wpdb->update(
                    $wpdb->term_taxonomy,
                    [
                        'description' => $serialized_map,
                        'count' => count($valid_translations)
                    ],
                    ['term_taxonomy_id' => $group->term_taxonomy_id],
                    ['%s', '%d'],
                    ['%d']
                );
                
                if ($this->logger) {
                    $this->logger->log("Updated translation group {$group->slug} with " . count($valid_translations) . " valid translations", 'info');
                }
                
                return true;
                
            } elseif (count($valid_translations) < 2) {
                // Not enough translations - delete the group
                $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->term_relationships} WHERE term_taxonomy_id = %d", $group->term_taxonomy_id));
                $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id = %d", $group->term_taxonomy_id));
                $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->terms} WHERE term_id = %d", $group->term_id));
                
                if ($this->logger) {
                    $this->logger->log("Deleted translation group {$group->slug} with insufficient valid translations", 'info');
                }
                
                return true;
            }
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->log_error("Failed to verify translation group {$group->slug}", $e);
            }
        }
        
        return false;
    }
    
    /**
     * Placeholder methods for future implementation
     */
    private function process_woocommerce($offset, $batch_size) {
        return [
            'total' => 0,
            'processed' => 0,
            'fixed' => 0,
            'continue' => false,
            'message' => 'WooCommerce processing - to be implemented'
        ];
    }
    
    /**
     * NEW: Handle comprehensive verification request with timeout prevention
     */
    public function handle_comprehensive_verify() {
        $this->verify_request(true);
        
        try {
            if (!$this->migration_verifier) {
                throw new Exception('Migration verifier not initialized');
            }
            
            // Increase time limit and memory for large sites
            @set_time_limit(300); // 5 minutes
            @ini_set('memory_limit', '512M');
            
            if ($this->logger) {
                $this->logger->log('Starting comprehensive verification with enhanced output buffering', 'info');
            }
            
            // Use output buffering with larger buffer
            if (ob_get_level()) {
                ob_end_clean();
            }
            ob_start();
            
            $results = $this->migration_verifier->verify_migration();
            
            // Generate HTML output with error handling
            try {
                // Extract variables for the view
                extract(array('results' => $results));
                
                // Use the view file instead of hardcoded method
                $view_file = WPML_TO_POLYLANG_FIXER_PLUGIN_DIR . 'admin/views/verification-results-comprehensive.php';
                if (file_exists($view_file)) {
                    include $view_file;
                } else {
                    echo '<div class="status-message status-error">';
                    echo __('Comprehensive verification results template not found.', 'wpml-to-polylang-migration-fixer');
                    echo '</div>';
                }
                $html = ob_get_clean();
                
                // Check if output was truncated
                if (empty($html) || strlen($html) < 100) {
                    throw new Exception('Verification output appears to be truncated');
                }
                
            } catch (Exception $render_error) {
                ob_end_clean();
                
                // Fallback to simple text output if HTML rendering fails
                $html = $this->render_simple_verification_results($results);
                
                if ($this->logger) {
                    $this->logger->log("HTML rendering failed, using simple output: " . $render_error->getMessage(), 'warning');
                }
            }
            
            if ($this->logger) {
                $status = $results['overall_status'];
                $issues = $results['total_critical_issues'];
                $html_length = strlen($html);
                $this->logger->log("Comprehensive verification completed - Status: {$status}, Issues: {$issues}, Output: {$html_length} chars", 'info');
            }
            
            wp_send_json_success($html);
            
        } catch (Exception $e) {
            // Clean any hanging output buffers
            while (ob_get_level()) {
                ob_end_clean();
            }
            
            $error_message = 'Comprehensive verification failed: ' . $e->getMessage();
            
            if ($this->logger) {
                $this->logger->log_error("Comprehensive verification failed", $e);
            }
            
            // Try to provide useful fallback information
            $fallback_html = '<div class="status-message status-error">';
            $fallback_html .= '<h4>Verification Error: ' . esc_html($e->getMessage()) . '</h4>';
            $fallback_html .= '<p>The comprehensive verification encountered an issue. You can try:</p>';
            $fallback_html .= '<ul>';
            $fallback_html .= '<li>Use the "Basic Verify" button instead</li>';
            $fallback_html .= '<li>Run individual analysis and diagnosis tools</li>';
            $fallback_html .= '<li>Check the debug console for more details</li>';
            $fallback_html .= '</ul>';
            $fallback_html .= '</div>';
            
            wp_send_json_success($fallback_html);
        }
    }
    
    /**
     * Render simple verification results as fallback for large datasets
     */
    private function render_simple_verification_results($results) {
        $html = '<h3>Migration Verification Results</h3>';
        
        $status = $results['overall_status'] === 'success' ? 'PASSED' : 'ISSUES FOUND';
        $status_class = $results['overall_status'] === 'success' ? 'status-success' : 'status-error';
        
        $html .= '<div class="verification-status ' . $status_class . '" style="padding: 15px; margin-bottom: 20px; border-radius: 8px;">';
        $html .= '<h4>Migration Verification: ' . $status . '</h4>';
        
        if ($results['overall_status'] !== 'success') {
            $html .= '<p><strong>' . intval($results['total_critical_issues']) . ' critical issues</strong> need attention.</p>';
        } else {
            $html .= '<p>All critical components have been successfully migrated.</p>';
        }
        
        $html .= '</div>';
        
        // Simple stats list
        $html .= '<div style="background: #f9f9f9; padding: 15px; border-radius: 8px;">';
        $html .= '<h4>Component Status:</h4>';
        $html .= '<ul>';
        
        if (isset($results['languages'])) {
            $html .= '<li><strong>Languages:</strong> ' . intval($results['languages']['pll_languages'] ?? 0) . '/' . intval($results['languages']['wpml_languages'] ?? 0) . '</li>';
        }
        
        if (isset($results['posts'])) {
            $html .= '<li><strong>Posts with Language:</strong> ' . intval($results['posts']['posts_with_language'] ?? 0) . '</li>';
            if (($results['posts']['posts_without_language'] ?? 0) > 0) {
                $html .= '<li><strong style="color: #d32f2f;">Posts Missing Language:</strong> ' . intval($results['posts']['posts_without_language']) . '</li>';
            }
        }
        
        if (isset($results['terms'])) {
            $html .= '<li><strong>Terms with Language:</strong> ' . intval($results['terms']['terms_with_language'] ?? 0) . '</li>';
            if (($results['terms']['terms_without_language'] ?? 0) > 0) {
                $html .= '<li><strong style="color: #d32f2f;">Terms Missing Language:</strong> ' . intval($results['terms']['terms_without_language']) . '</li>';
            }
        }
        
        if (isset($results['translation_groups'])) {
            $html .= '<li><strong>Translation Groups:</strong> ' . intval($results['translation_groups']['valid_groups'] ?? 0) . '/' . intval($results['translation_groups']['total_groups'] ?? 0) . '</li>';
        }
        
        if (isset($results['betterdocs']) && ($results['betterdocs']['betterdocs_active'] ?? false)) {
            $html .= '<li><strong>BetterDocs:</strong> ' . intval($results['betterdocs']['docs_with_language'] ?? 0) . '/' . intval($results['betterdocs']['total_docs'] ?? 0) . '</li>';
        }
        
        $html .= '</ul>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Handle test connection request (enhanced)
     */
    public function handle_test_connection() {
        $this->verify_request(true);
        
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
                'wpml_data' => $this->db_helper ? $this->db_helper->wpml_tables_exist() : false,
                'betterdocs_detected' => post_type_exists('docs')
            ];
            
            if ($this->logger) {
                $this->logger->log('Connection test successful with translation groups fixes', 'info');
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
     * Handle ensure term_language buckets
     */
    public function handle_ensure_buckets() {
        $this->verify_request(true);

        try {
            // DEBUG START
            $debug_enabled = !empty($_POST['debug']) && get_option('wpml_fixer_debug_mode', false);
            if ($debug_enabled) {
                require_once WPML_TO_POLYLANG_FIXER_PLUGIN_DIR . 'includes/class-debug-collector.php';
                $this->db_helper->init_debug_collector(true);
            }
            // DEBUG END

            $created = $this->db_helper->ensure_term_language_buckets();

            $response = [
                'message' => "Created {$created} missing term_language buckets",
                'created' => $created
            ];

            // DEBUG START
            if ($debug_enabled) {
                $debug_data = $this->db_helper->get_debug_data();
                if ($debug_data) {
                    $response['debug'] = $debug_data;
                }
            }
            // DEBUG END

            wp_send_json_success($response);
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->log_error("Failed to ensure buckets", $e);
            }
            wp_send_json_error('Failed to ensure buckets: ' . $e->getMessage());
        }
    }

    /**
     * Handle language normalization
     */
    public function handle_normalize_languages() {
        $this->verify_request(true);

        try {
            $offset = intval($_POST['offset'] ?? 0);
            $batch_size = intval($_POST['batch_size'] ?? 50);
            $preview = !empty($_POST['preview']);

            $pattern = isset($_POST['pattern']) ? sanitize_text_field(wp_unslash($_POST['pattern'])) : 'pll_%';
            $issues_snapshot = $this->db_helper->get_content_with_wrong_codes($pattern);
            $issues_total = (int)($issues_snapshot['posts'] ?? 0) + (int)($issues_snapshot['terms'] ?? 0);

            if ($preview) {
                wp_send_json_success([
                    'total' => $issues_total,
                    'processed' => 0,
                    'fixed' => 0,
                    'continue' => $issues_total > 0,
                    'issues_total' => $issues_total,
                    'issues_remaining' => $issues_total,
                    'message' => $issues_total > 0
                        ? __('Found language codes that need normalization.', 'wpml-migration-fixer')
                        : __('No language codes require normalization.', 'wpml-migration-fixer')
                ]);
                return;
            }

            // Process posts first
            if ($offset < 10000) {
                $posts_offset = $offset;
                $posts = $this->get_posts_batch($posts_offset, $batch_size);
                $results = $this->normalize_posts_languages($posts);
                $results['continue'] = !empty($posts);
                $results['next_offset'] = $offset + $batch_size;
            } else {
                // Then process terms
                $terms_offset = $offset - 10000;
                $terms = $this->get_terms_batch($terms_offset, $batch_size);
                $results = $this->normalize_terms_languages($terms);
                $results['continue'] = !empty($terms);
                $results['next_offset'] = $offset + $batch_size;
            }

            $issues_after = $this->db_helper->get_content_with_wrong_codes($pattern);
            $issues_remaining = (int)($issues_after['posts'] ?? 0) + (int)($issues_after['terms'] ?? 0);

            $results['total'] = $issues_total;
            $results['issues_total'] = $issues_total;
            $results['issues_remaining'] = $issues_remaining;

            wp_send_json_success($results);
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->log_error("Failed to normalize languages", $e);
            }
            wp_send_json_error('Failed to normalize: ' . $e->getMessage());
        }
    }

    /**
     * Handle fix all posts
     */
    public function handle_fix_all_posts() {
        $this->verify_request(true);

        try {
            $offset = intval($_POST['offset'] ?? 0);
            $batch_size = intval($_POST['batch_size'] ?? 50);

            global $wpdb;

            // Get public post types
            $post_types = get_post_types(['public' => true], 'names');
            $excluded = $this->db_helper->get_excluded_post_types();
            $post_types = array_diff($post_types, $excluded);

            if (empty($post_types)) {
                wp_send_json_success([
                    'processed' => 0,
                    'fixed' => 0,
                    'continue' => false,
                    'message' => 'No post types to process'
                ]);
                return;
            }

            $post_types_sql = "'" . implode("','", array_map('esc_sql', $post_types)) . "'";

            $preview = !empty($_POST['preview']);
            $issues_total_input = isset($_POST['issues_total']) ? max(0, intval($_POST['issues_total'])) : null;

            $issues_before = (int) $wpdb->get_var("
                SELECT COUNT(*)
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
                LEFT JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = 'language'
                WHERE p.post_type IN ({$post_types_sql})
                AND p.post_status IN ('publish', 'draft', 'private')
                AND tt.term_taxonomy_id IS NULL
            ");

            $total = (int) $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->posts}
                WHERE post_type IN ({$post_types_sql})
                AND post_status IN ('publish', 'draft', 'private')
            ");

            $issues_total = ($issues_total_input !== null) ? max($issues_total_input, $issues_before) : $issues_before;

            if ($preview) {
                wp_send_json_success([
                    'total' => $total,
                    'processed' => 0,
                    'fixed' => 0,
                    'continue' => $issues_total > 0,
                    'issues_total' => $issues_total,
                    'issues_remaining' => $issues_total,
                    'message' => $issues_total > 0
                        ? __('Found posts without Polylang languages.', 'wpml-migration-fixer')
                        : __('All posts already have language assignments.', 'wpml-migration-fixer')
                ]);
                return;
            }

            // Get batch of posts
            $posts = $wpdb->get_col($wpdb->prepare("
                SELECT ID FROM {$wpdb->posts}
                WHERE post_type IN ({$post_types_sql})
                AND post_status IN ('publish', 'draft', 'private')
                ORDER BY ID
                LIMIT %d OFFSET %d
            ", $batch_size, $offset));

            $batch_results = ['processed' => 0, 'fixed' => 0];
            if (!empty($posts)) {
                $batch_results = $this->db_helper->fix_posts_batch($posts);
            }

            $items_processed = !empty($posts) ? count($posts) : 0;
            if (isset($batch_results['processed']) && $batch_results['processed'] > 0) {
                $items_processed = (int) $batch_results['processed'];
            }

            $processed_total = $total > 0 ? min($offset + $items_processed, $total) : 0;
            $fixed_batch = isset($batch_results['fixed']) ? (int) $batch_results['fixed'] : 0;

            $issues_remaining = (int) $wpdb->get_var("
                SELECT COUNT(*)
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
                LEFT JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = 'language'
                WHERE p.post_type IN ({$post_types_sql})
                AND p.post_status IN ('publish', 'draft', 'private')
                AND tt.term_taxonomy_id IS NULL
            ");

            $issues_fixed = max($issues_total - $issues_remaining, 0);

            $response = [
                'total' => $total,
                'processed' => $processed_total,
                'fixed' => $fixed_batch,
                'continue' => ($processed_total < $total) && ($items_processed > 0),
                'issues_total' => $issues_total,
                'issues_remaining' => $issues_remaining,
                'issues_fixed' => $issues_fixed,
                'message' => sprintf(
                    __('Processed %1$d of %2$d posts (fixed %3$d this batch)', 'wpml-migration-fixer'),
                    $processed_total,
                    $total,
                    $fixed_batch
                )
            ];

            if ($response['continue']) {
                $response['next_offset'] = $offset + $items_processed;
            }

            wp_send_json_success($response);
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->log_error("Failed to fix posts", $e);
            }
            wp_send_json_error('Failed to fix posts: ' . $e->getMessage());
        }
    }

    /**
     * Handle fix all terms
     */
    public function handle_fix_all_terms() {
        $this->verify_request(true);

        try {
            $offset = intval($_POST['offset'] ?? 0);
            $batch_size = intval($_POST['batch_size'] ?? 50);

            global $wpdb;

            // Get public taxonomies
            $taxonomies = get_taxonomies(['public' => true], 'names');
            $excluded = $this->db_helper->get_excluded_taxonomies();
            $taxonomies = array_diff($taxonomies, $excluded);

            // Include WooCommerce attributes
            $attributes = $wpdb->get_col("
                SELECT DISTINCT taxonomy FROM {$wpdb->term_taxonomy}
                WHERE taxonomy LIKE 'pa_%'
            ");
            $taxonomies = array_merge($taxonomies, $attributes);

            if (empty($taxonomies)) {
                wp_send_json_success([
                    'processed' => 0,
                    'fixed' => 0,
                    'continue' => false,
                    'message' => 'No taxonomies to process'
                ]);
                return;
            }

            $taxonomies_sql = "'" . implode("','", array_map('esc_sql', $taxonomies)) . "'";
            $preview = !empty($_POST['preview']);

            // Get batch of terms
            $terms = $wpdb->get_results($wpdb->prepare("
                SELECT tt.term_taxonomy_id, tt.term_id, tt.taxonomy
                FROM {$wpdb->term_taxonomy} tt
                WHERE tt.taxonomy IN ({$taxonomies_sql})
                ORDER BY tt.term_taxonomy_id
                LIMIT %d OFFSET %d
            ", $batch_size, $offset));

            $total = (int) $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->term_taxonomy}
                WHERE taxonomy IN ({$taxonomies_sql})
            ");

            $issues_total_input = isset($_POST['issues_total']) ? max(0, intval($_POST['issues_total'])) : null;

            $issues_terms_before = (int) $wpdb->get_var("
                SELECT COUNT(DISTINCT t.term_id)
                FROM {$wpdb->terms} t
                JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
                WHERE tt.taxonomy IN ({$taxonomies_sql})
                AND NOT EXISTS (
                    SELECT 1 FROM {$wpdb->term_relationships} tr
                    JOIN {$wpdb->term_taxonomy} tt2 ON tr.term_taxonomy_id = tt2.term_taxonomy_id AND tt2.taxonomy = 'term_language'
                    WHERE tr.object_id = t.term_id
                )
            ");

            $issues_baseline = $issues_terms_before;
            $issues_total = ($issues_total_input !== null) ? max($issues_total_input, $issues_baseline) : $issues_baseline;

            if ($preview) {
                wp_send_json_success([
                    'total' => $total,
                    'processed' => 0,
                    'fixed' => 0,
                    'continue' => $issues_total > 0,
                    'issues_total' => $issues_total,
                    'issues_remaining' => $issues_total,
                    'message' => $issues_total > 0
                        ? __('Found terms without language assignments.', 'wpml-migration-fixer')
                        : __('All terms already have language assignments.', 'wpml-migration-fixer')
                ]);
                return;
            }

            $batch_results = ['processed' => 0, 'fixed' => 0];
            if (!empty($terms)) {
                $batch_results = $this->db_helper->fix_terms_batch($terms);
            }

            $items_processed = !empty($terms) ? count($terms) : 0;
            if (isset($batch_results['processed']) && $batch_results['processed'] > 0) {
                $items_processed = (int) $batch_results['processed'];
            }

            $processed_total = $total > 0 ? min($offset + $items_processed, $total) : 0;
            $fixed_batch = isset($batch_results['fixed']) ? (int) $batch_results['fixed'] : 0;

            $issues_terms_remaining = (int) $wpdb->get_var("
                SELECT COUNT(DISTINCT t.term_id)
                FROM {$wpdb->terms} t
                JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
                WHERE tt.taxonomy IN ({$taxonomies_sql})
                AND NOT EXISTS (
                    SELECT 1 FROM {$wpdb->term_relationships} tr
                    JOIN {$wpdb->term_taxonomy} tt2 ON tr.term_taxonomy_id = tt2.term_taxonomy_id AND tt2.taxonomy = 'term_language'
                    WHERE tr.object_id = t.term_id
                )
            ");

            $issues_remaining = $issues_terms_remaining;

            $issues_fixed = max($issues_total - $issues_remaining, 0);

            $response = [
                'total' => $total,
                'processed' => $processed_total,
                'fixed' => $fixed_batch,
                'continue' => ($processed_total < $total) && ($items_processed > 0),
                'issues_total' => $issues_total,
                'issues_remaining' => $issues_remaining,
                'issues_fixed' => $issues_fixed,
                'message' => sprintf(
                    __('Processed %1$d of %2$d terms (fixed %3$d this batch)', 'wpml-migration-fixer'),
                    $processed_total,
                    $total,
                    $fixed_batch
                )
            ];

            if ($response['continue']) {
                $response['next_offset'] = $offset + $items_processed;
            }

            wp_send_json_success($response);
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->log_error("Failed to fix terms", $e);
            }
            wp_send_json_error('Failed to fix terms: ' . $e->getMessage());
        }
    }

    /**
     * Handle fix BetterDocs
     */
    public function handle_fix_betterdocs() {
        $this->verify_request(true);

        try {
            if (!$this->db_helper || !$this->db_helper->has_betterdocs()) {
                wp_send_json_success([
                    'total' => 0,
                    'processed' => 0,
                    'fixed' => 0,
                    'continue' => false,
                    'message' => __('BetterDocs content not detected.', 'wpml-migration-fixer')
                ]);
            }

            $offset = max(0, intval($_POST['offset'] ?? 0));
            $batch_size = max(1, intval($_POST['batch_size'] ?? 50));
            $preview = !empty($_POST['preview']);

            global $wpdb;

            $post_types = ['docs', 'betterdocs_faq'];
            $post_types_sql = "'" . implode("','", array_map('esc_sql', $post_types)) . "'";
            $total_posts = (int) $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->posts}
                WHERE post_type IN ({$post_types_sql})
                AND post_status IN ('publish', 'draft', 'private')
            ");

            $taxonomies = ['doc_category', 'doc_tag', 'knowledge_base', 'betterdocs_faq_category'];
            $taxonomies_sql = "'" . implode("','", array_map('esc_sql', $taxonomies)) . "'";
            $total_terms = (int) $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->term_taxonomy}
                WHERE taxonomy IN ({$taxonomies_sql})
            ");

            $grand_total = $total_posts + $total_terms;

            if ($grand_total === 0) {
                wp_send_json_success([
                    'total' => 0,
                    'processed' => 0,
                    'fixed' => 0,
                    'continue' => false,
                    'message' => __('No BetterDocs posts or terms found.', 'wpml-migration-fixer')
                ]);
            }

            $issues_total_input = isset($_POST['issues_total']) ? max(0, intval($_POST['issues_total'])) : null;

            $issues_posts_before = (int) $wpdb->get_var("
                SELECT COUNT(*)
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
                LEFT JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = 'language'
                WHERE p.post_type IN ({$post_types_sql})
                AND p.post_status IN ('publish', 'draft', 'private')
                AND tt.term_taxonomy_id IS NULL
            ");

            $issues_terms_before = (int) $wpdb->get_var("
                SELECT COUNT(DISTINCT t.term_id)
                FROM {$wpdb->terms} t
                JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
                WHERE tt.taxonomy IN ({$taxonomies_sql})
                AND NOT EXISTS (
                    SELECT 1 FROM {$wpdb->term_relationships} tr
                    JOIN {$wpdb->term_taxonomy} tt2 ON tr.term_taxonomy_id = tt2.term_taxonomy_id AND tt2.taxonomy = 'term_language'
                    WHERE tr.object_id = t.term_id
                )
            ");

            $issues_baseline = $issues_posts_before + $issues_terms_before;
            $issues_total = ($issues_total_input !== null) ? max($issues_total_input, $issues_baseline) : $issues_baseline;

            if ($preview) {
                wp_send_json_success([
                    'total' => $grand_total,
                    'processed' => 0,
                    'fixed' => 0,
                    'continue' => $issues_total > 0,
                    'issues_total' => $issues_total,
                    'issues_remaining' => $issues_total,
                    'message' => $issues_total > 0
                        ? __('Found BetterDocs content without language assignments.', 'wpml-migration-fixer')
                        : __('BetterDocs content is already aligned.', 'wpml-migration-fixer')
                ]);
                return;
            }

            $stage = ($offset < $total_posts) ? 'posts' : 'terms';
            $items_processed = 0;
            $processed_total = 0;
            $fixed_batch = 0;
            $next_offset = null;
            $message = '';

            if ($stage === 'posts') {
                $batch_results = $this->db_helper->fix_betterdocs_batch('posts', $batch_size, $offset);
                $items_processed = isset($batch_results['processed']) ? (int) $batch_results['processed'] : 0;
                $fixed_batch = isset($batch_results['fixed']) ? (int) $batch_results['fixed'] : 0;

                $processed_total = $total_posts > 0 ? min($offset + $items_processed, $total_posts) : 0;

                if ($items_processed > 0) {
                    $next_offset = $offset + $items_processed;
                    $message = sprintf(
                        __('BetterDocs posts %1$d of %2$d processed (fixed %3$d this batch)', 'wpml-migration-fixer'),
                        $processed_total,
                        $total_posts,
                        $fixed_batch
                    );
                } else {
                    $processed_total = $total_posts;
                    if ($total_terms > 0) {
                        $next_offset = $total_posts;
                        $message = __('BetterDocs posts complete, continuing with taxonomies…', 'wpml-migration-fixer');
                    } else {
                        $message = __('BetterDocs posts are already aligned.', 'wpml-migration-fixer');
                    }
                }
            } else {
                $term_offset = max(0, $offset - $total_posts);
                $batch_results = $this->db_helper->fix_betterdocs_batch('terms', $batch_size, $term_offset);
                $items_processed = isset($batch_results['processed']) ? (int) $batch_results['processed'] : 0;
                $fixed_batch = isset($batch_results['fixed']) ? (int) $batch_results['fixed'] : 0;

                $processed_terms = $total_terms > 0 ? min($term_offset + $items_processed, $total_terms) : 0;
                $processed_total = $total_posts + $processed_terms;

                if ($items_processed > 0) {
                    $next_offset = $offset + $items_processed;
                    $message = sprintf(
                        __('BetterDocs taxonomies %1$d of %2$d processed (fixed %3$d this batch)', 'wpml-migration-fixer'),
                        $processed_terms,
                        $total_terms,
                        $fixed_batch
                    );
                } else {
                    $message = __('BetterDocs taxonomies are already aligned.', 'wpml-migration-fixer');
                }
            }

            $processed_total = min($processed_total, $grand_total);
            $continue = false;

            if ($stage === 'posts') {
                $continue = ($processed_total < $grand_total) && ($next_offset !== null);
            } else {
                $continue = ($processed_total < $grand_total) && ($items_processed > 0);
            }

            $issues_posts_after = (int) $wpdb->get_var("
                SELECT COUNT(*)
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
                LEFT JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = 'language'
                WHERE p.post_type IN ({$post_types_sql})
                AND p.post_status IN ('publish', 'draft', 'private')
                AND tt.term_taxonomy_id IS NULL
            ");

            $issues_terms_after = (int) $wpdb->get_var("
                SELECT COUNT(DISTINCT t.term_id)
                FROM {$wpdb->terms} t
                JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
                WHERE tt.taxonomy IN ({$taxonomies_sql})
                AND NOT EXISTS (
                    SELECT 1 FROM {$wpdb->term_relationships} tr
                    JOIN {$wpdb->term_taxonomy} tt2 ON tr.term_taxonomy_id = tt2.term_taxonomy_id AND tt2.taxonomy = 'term_language'
                    WHERE tr.object_id = t.term_id
                )
            ");

            $issues_remaining = $issues_posts_after + $issues_terms_after;
            $issues_fixed = max($issues_total - $issues_remaining, 0);

            $response = [
                'total' => $grand_total,
                'processed' => $processed_total,
                'fixed' => $fixed_batch,
                'continue' => $continue,
                'issues_total' => $issues_total,
                'issues_remaining' => $issues_remaining,
                'issues_fixed' => $issues_fixed,
                'stage' => $stage,
                'message' => $message
            ];

            if ($continue && $next_offset !== null) {
                $response['next_offset'] = $next_offset;
            }

            wp_send_json_success($response);
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->log_error("Failed to fix BetterDocs", $e);
            }
            wp_send_json_error('Failed to fix BetterDocs: ' . $e->getMessage());
        }
    }

    /**
     * Handle fix WooCommerce attributes
     */
    public function handle_fix_woo_attributes() {
        $this->verify_request(true);

        try {
            $offset = intval($_POST['offset'] ?? 0);
            $batch_size = intval($_POST['batch_size'] ?? 50);

            $preview = !empty($_POST['preview']);
            $results = $this->db_helper->fix_woocommerce_attributes_batch($batch_size, $offset);

            $total = isset($results['total']) ? (int) $results['total'] : 0;
            $items_processed = isset($results['processed']) ? (int) $results['processed'] : 0;
            $fixed_batch = isset($results['fixed']) ? (int) $results['fixed'] : 0;

            $issues_total_input = isset($_POST['issues_total']) ? max(0, intval($_POST['issues_total'])) : null;

            $attributes = !empty($results['attributes']) ? $results['attributes'] : $wpdb->get_col("
                SELECT DISTINCT taxonomy FROM {$wpdb->term_taxonomy}
                WHERE taxonomy LIKE 'pa_%'
            ");

            $issues_before = 0;
            if (!empty($attributes)) {
                $attributes_sql = "'" . implode("','", array_map('esc_sql', $attributes)) . "'";
                $issues_before = (int) $wpdb->get_var("
                    SELECT COUNT(DISTINCT t.term_id)
                    FROM {$wpdb->terms} t
                    JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
                    WHERE tt.taxonomy IN ({$attributes_sql})
                    AND NOT EXISTS (
                        SELECT 1 FROM {$wpdb->term_relationships} tr
                        JOIN {$wpdb->term_taxonomy} tt2 ON tr.term_taxonomy_id = tt2.term_taxonomy_id AND tt2.taxonomy = 'term_language'
                        WHERE tr.object_id = t.term_id
                    )
                ");
            }

            $issues_total = ($issues_total_input !== null) ? max($issues_total_input, $issues_before) : $issues_before;

            if ($preview) {
                wp_send_json_success([
                    'total' => $total,
                    'processed' => 0,
                    'fixed' => 0,
                    'continue' => $issues_total > 0,
                    'issues_total' => $issues_total,
                    'issues_remaining' => $issues_total,
                    'message' => $issues_total > 0
                        ? __('Found WooCommerce attributes without language assignments.', 'wpml-migration-fixer')
                        : __('All WooCommerce attributes are already translated.', 'wpml-migration-fixer')
                ]);
                return;
            }

            $processed_total = $total > 0 ? min($offset + $items_processed, $total) : 0;
            $continue = ($processed_total < $total) && ($items_processed > 0);

            $issues_remaining = $issues_before;
            if (!empty($attributes)) {
                $attributes_sql = "'" . implode("','", array_map('esc_sql', $attributes)) . "'";
                $issues_remaining = (int) $wpdb->get_var("
                    SELECT COUNT(DISTINCT t.term_id)
                    FROM {$wpdb->terms} t
                    JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
                    WHERE tt.taxonomy IN ({$attributes_sql})
                    AND NOT EXISTS (
                        SELECT 1 FROM {$wpdb->term_relationships} tr
                        JOIN {$wpdb->term_taxonomy} tt2 ON tr.term_taxonomy_id = tt2.term_taxonomy_id AND tt2.taxonomy = 'term_language'
                        WHERE tr.object_id = t.term_id
                    )
                ");
            }

            $issues_fixed = max($issues_total - $issues_remaining, 0);

            $response = [
                'total' => $total,
                'processed' => $processed_total,
                'fixed' => $fixed_batch,
                'continue' => $continue,
                'issues_total' => $issues_total,
                'issues_remaining' => $issues_remaining,
                'issues_fixed' => $issues_fixed,
                'message' => sprintf(
                    __('Processed %1$d of %2$d product attributes (fixed %3$d this batch)', 'wpml-migration-fixer'),
                    $processed_total,
                    $total,
                    $fixed_batch
                )
            ];

            if ($continue) {
                $response['next_offset'] = $offset + $items_processed;
            }

            wp_send_json_success($response);
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->log_error("Failed to fix WooCommerce attributes", $e);
            }
            wp_send_json_error('Failed to fix attributes: ' . $e->getMessage());
        }
    }

    /**
     * Helper: Get posts batch for normalization
     */
    private function get_posts_batch($offset, $batch_size) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare("
            SELECT ID, post_type FROM {$wpdb->posts}
            WHERE post_status IN ('publish', 'draft', 'private')
            ORDER BY ID
            LIMIT %d OFFSET %d
        ", $batch_size, $offset));
    }

    /**
     * Helper: Get terms batch for normalization
     */
    private function get_terms_batch($offset, $batch_size) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare("
            SELECT tt.term_taxonomy_id, tt.term_id, tt.taxonomy
            FROM {$wpdb->term_taxonomy} tt
            ORDER BY tt.term_taxonomy_id
            LIMIT %d OFFSET %d
        ", $batch_size, $offset));
    }

    /**
     * Helper: Normalize posts languages
     */
    private function normalize_posts_languages($posts) {
        $results = ['processed' => 0, 'fixed' => 0];

        foreach ($posts as $post) {
            $results['processed']++;
            $current = pll_get_post_language($post->ID);
            $normalized = $this->language_converter->canonicalize_slug($current);

            if ($normalized && $normalized !== $current) {
                pll_set_post_language($post->ID, $normalized);
                $results['fixed']++;
            }
        }

        return $results;
    }

    /**
     * Helper: Normalize terms languages
     */
    private function normalize_terms_languages($terms) {
        $results = ['processed' => 0, 'fixed' => 0];

        foreach ($terms as $term) {
            $results['processed']++;
            $current = pll_get_term_language($term->term_id);
            $normalized = $this->language_converter->canonicalize_slug($current);

            if ($normalized && $normalized !== $current) {
                pll_set_term_language($term->term_id, $normalized);
                $results['fixed']++;
            }
        }

        return $results;
    }

    /**
     * Handle status request for the Status tab.
     */
    public function handle_get_status() {
        $this->verify_request(false);

        $helper = $this->db_helper;

        $pll_active = $helper ? $helper->has_polylang() : (function_exists('pll_languages_list') && function_exists('pll_default_language'));
        $wpml_tables = $helper ? $helper->has_wpml_tables() : false;
        $woo_active = $helper ? $helper->has_woocommerce() : (class_exists('WooCommerce') || defined('WC_VERSION'));
        $betterdocs_active = $helper ? $helper->has_betterdocs() : (class_exists('BetterDocs') || post_type_exists('docs') || defined('BETTERDOCS_VERSION'));
        $acf_active = $helper ? $helper->has_acf() : (class_exists('ACF') || function_exists('acf') || defined('ACF_VERSION'));

        $response = [
            'pll_active' => (bool) $pll_active,
            'wpml_tables' => (bool) $wpml_tables,
            'woo' => [
                'active' => (bool) $woo_active,
                'version' => defined('WC_VERSION') ? WC_VERSION : ''
            ],
            'betterdocs' => [
                'active' => (bool) $betterdocs_active,
                'version' => defined('BETTERDOCS_VERSION') ? BETTERDOCS_VERSION : ''
            ],
            'acf' => [
                'active' => (bool) $acf_active,
                'version' => defined('ACF_VERSION') ? ACF_VERSION : ''
            ],
            'languages' => $helper ? $helper->get_pll_languages() : [
                'list' => [],
                'default' => null
            ]
        ];

        wp_send_json_success($response);
    }

    /**
     * Preview SQL statements safely (read-only).
     */
    public function handle_sql_preview() {
        $this->verify_request(false);

        if (!$this->sql_runner) {
            wp_send_json_error(__('SQL runner is not available.', 'wpml-migration-fixer'));
        }

        $sql = isset($_POST['sql']) ? wp_unslash($_POST['sql']) : '';
        if (!is_string($sql) || trim($sql) === '') {
            wp_send_json_error(__('Please provide an SQL statement.', 'wpml-migration-fixer'));
        }

        try {
            $result = $this->sql_runner->preview($sql);
            wp_send_json_success($result);
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->log_error('SQL preview failed', $e);
            }
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Execute SQL statements after administrator confirmation.
     */
    public function handle_sql_execute() {
        $this->verify_request(false);

        if (!$this->sql_runner) {
            wp_send_json_error(__('SQL runner is not available.', 'wpml-migration-fixer'));
        }

        $sql = isset($_POST['sql']) ? wp_unslash($_POST['sql']) : '';
        if (!is_string($sql) || trim($sql) === '') {
            wp_send_json_error(__('Please provide an SQL statement.', 'wpml-migration-fixer'));
        }

        $confirmed = !empty($_POST['confirm']);
        if (!$confirmed) {
            wp_send_json_error(__('Execution must be confirmed.', 'wpml-migration-fixer'));
        }

        try {
            $result = $this->sql_runner->execute($sql);
            wp_send_json_success($result);
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->log_error('SQL execute failed', $e);
            }
            wp_send_json_error($e->getMessage());
        }
    }

}

} // End of class_exists check
