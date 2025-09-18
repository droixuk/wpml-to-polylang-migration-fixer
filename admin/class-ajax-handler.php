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
            
            // Escape and quote taxonomies for SQL
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
                        $target_language = pll_default_language();
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
                        $target_language = pll_default_language();
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
                : pll_default_language();
            
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
                : pll_default_language();
            
            if (pll_set_term_language($term_data->term_id, $target_language)) {
                $fixed++;
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
     * NEW: Handle comprehensive verification request
     */
    public function handle_comprehensive_verify() {
        $this->verify_request();
        
        try {
            if (!$this->migration_verifier) {
                throw new Exception('Migration verifier not initialized');
            }
            
            if ($this->logger) {
                $this->logger->log('Starting comprehensive verification with BetterDocs', 'info');
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
     * Render comprehensive verification results with BetterDocs support
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
        
        <!-- Enhanced Quick Stats with BetterDocs -->
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
            
            <!-- NEW: BetterDocs Status Box -->
            <?php if ($results['betterdocs']['betterdocs_active']): ?>
            <div class="stat-box">
                <div class="stat-label"><?php _e('BetterDocs', 'wpml-to-polylang-migration-fixer'); ?></div>
                <div class="stat-number">
                    <?php echo $results['betterdocs']['docs_with_language']; ?>/<?php echo $results['betterdocs']['total_docs']; ?>
                </div>
                <div class="badge <?php echo $results['betterdocs']['critical_issues'] === 0 ? 'badge-success' : 'badge-error'; ?>">
                    <?php echo $results['betterdocs']['critical_issues'] === 0 ? 'OK' : $results['betterdocs']['critical_issues'] . ' Issues'; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Action Recommendations -->
        <?php if ($results['overall_status'] !== 'success'): ?>
        <div style="margin-top: 20px; padding: 15px; background: #fff3cd; border-radius: 8px; border-left: 4px solid #ffc107;">
            <h5 style="margin: 0 0 10px 0;">🔧 Recommended Actions (Priority Order)</h5>
            <ul style="margin: 0; padding-left: 20px;">
                <?php if ($results['terms']['terms_without_language'] > 0): ?>
                <li><strong>🏷️ Priority 1:</strong> Fix <?php echo $results['terms']['terms_without_language']; ?> terms without language using "Fix All Taxonomies"</li>
                <?php endif; ?>
                
                <?php if ($results['translation_groups']['invalid_groups'] > 0 || $results['posts']['orphaned_wpml_groups'] > 0 || $results['terms']['orphaned_wpml_term_groups'] > 0): ?>
                <li><strong>🔗 Priority 2:</strong> Fix translation groups (<?php echo $results['translation_groups']['invalid_groups']; ?> corrupted, <?php echo $results['posts']['orphaned_wpml_groups']; ?> missing post groups, <?php echo $results['terms']['orphaned_wpml_term_groups']; ?> missing term groups) using "Fix Translation Groups"</li>
                <?php endif; ?>
                
                <?php if ($results['posts']['posts_without_language'] > 0): ?>
                <li><strong>📝 Priority 3:</strong> Fix <?php echo $results['posts']['posts_without_language']; ?> posts without language using "Fix Posts & Pages"</li>
                <?php endif; ?>
                
                <?php if ($results['betterdocs']['betterdocs_active'] && $results['betterdocs']['critical_issues'] > 0): ?>
                <li><strong>📚 Priority 4:</strong> Fix <?php echo $results['betterdocs']['critical_issues']; ?> BetterDocs issues using "Fix BetterDocs"</li>
                <?php endif; ?>
            </ul>
            
            <?php if ($results['translation_groups']['invalid_groups'] > 0): ?>
            <div style="margin-top: 15px; padding: 10px; background: #ffebee; border-radius: 5px; border-left: 3px solid #f44336;">
                <strong>⚠️ Translation Groups Critical:</strong> 
                <?php echo $results['translation_groups']['invalid_groups']; ?> corrupted translation groups detected. 
                This affects content relationships and should be fixed immediately to restore proper multilingual functionality.
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
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
            
            // Add BetterDocs stats if available
            if (post_type_exists('docs')) {
                $betterdocs_verification = $this->migration_verifier->verify_migration()['betterdocs'];
                $stats['betterdocs_stats'] = [
                    'active' => $betterdocs_verification['betterdocs_active'],
                    'docs_with_language' => $betterdocs_verification['docs_with_language'],
                    'docs_without_language' => $betterdocs_verification['docs_without_language'],
                    'total_docs' => $betterdocs_verification['total_docs']
                ];
            }
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