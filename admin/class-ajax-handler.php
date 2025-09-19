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
        // Register AJAX handlers
        add_action('wp_ajax_wpml_fixer_ajax_process', [$this, 'handle_process']);
        add_action('wp_ajax_wpml_fixer_ajax_test_connection', [$this, 'handle_test_connection']);
        
        // NEW: Comprehensive verification endpoint
        add_action('wp_ajax_wpml_fixer_ajax_comprehensive_verify', [$this, 'handle_comprehensive_verify']);
        
        // Log successful initialization
        if ($this->logger) {
            $this->logger->log('Enhanced AJAX handlers with taxonomy and translation group fixes registered successfully', 'info');
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
        $this->verify_request();
        
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
}

} // End of class_exists check