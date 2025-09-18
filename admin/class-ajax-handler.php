<?php
/**
 * Ajax Handler Class
 * 
 * Handles AJAX requests for the WPML Migration Fixer plugin
 * 
 * @package WPML_Migration_Fixer
 * @since 1.0.3
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
        add_action('wp_ajax_wpml_fixer_ajax_fix_english', [$this, 'handle_fix_english']);
        add_action('wp_ajax_wpml_fixer_ajax_fix_pll_prefix', [$this, 'handle_fix_pll_prefix']);
        add_action('wp_ajax_wpml_fixer_ajax_fix_woo_attributes', [$this, 'handle_fix_woo_attributes']);
        add_action('wp_ajax_wpml_fixer_ajax_reset_session', [$this, 'handle_reset_session']);
        
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
     * Handle main process request - COMPLETE IMPLEMENTATION
     */
    public function handle_process() {
        $this->verify_request();
        
        try {
            $type = sanitize_text_field($_POST['type'] ?? '');
            $offset = intval($_POST['offset'] ?? 0);
            $batch_size = intval($_POST['batch_size'] ?? 20);
            $total_fixed = intval($_POST['total_fixed'] ?? 0);
            
            @set_time_limit(120);
            @ini_set('memory_limit', '512M');
            
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
                    wp_send_json_error('Invalid type: ' . $type);
            }
            
            wp_send_json_success($result);
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->log_error("Process failed", $e);
            }
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Process posts and pages
     */
    private function process_posts($offset, $batch_size) {
        global $wpdb;
        
        if (!$this->db_helper) {
            throw new Exception('Database helper not available');
        }
        
        // Check for WPML data
        $wpml_exists = $this->db_helper->wpml_tables_exist();
        $default_lang = pll_default_language();
        
        // Get post types
        $post_types = get_post_types(['public' => true], 'names');
        $excluded = $this->db_helper->get_excluded_post_types();
        $post_types = array_diff($post_types, $excluded);
        
        // Count total
        $total = 0;
        foreach ($post_types as $pt) {
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} 
                WHERE post_type = %s AND post_status IN ('publish', 'draft', 'private')",
                $pt
            ));
            $total += intval($count);
        }
        
        // Get posts to process
        $post_types_str = "'" . implode("','", esc_sql($post_types)) . "'";
        $posts = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, post_type FROM {$wpdb->posts}
            WHERE post_type IN ($post_types_str)
            AND post_status IN ('publish', 'draft', 'private')
            ORDER BY ID
            LIMIT %d, %d",
            $offset, $batch_size
        ));
        
        $fixed = 0;
        $debug_info = [];
        
        foreach ($posts as $post) {
            $current_lang = pll_get_post_language($post->ID);
            $needs_fix = false;
            $new_lang = null;
            
            if ($wpml_exists) {
                // Get WPML language
                $wpml_lang = $this->db_helper->get_wpml_post_language($post->ID, $post->post_type);
                
                if ($wpml_lang) {
                    $new_lang = $this->language_converter->convert_language($wpml_lang);
                    
                    if (!$current_lang || $current_lang !== $new_lang) {
                        $needs_fix = true;
                    }
                } elseif (!$current_lang && $default_lang) {
                    $new_lang = $default_lang;
                    $needs_fix = true;
                }
            } elseif (!$current_lang && $default_lang) {
                $new_lang = $default_lang;
                $needs_fix = true;
            }
            
            if ($needs_fix && $new_lang) {
                pll_set_post_language($post->ID, $new_lang);
                $fixed++;
                
                if (count($debug_info) < 5) {
                    $debug_info[] = "Post {$post->ID}: {$current_lang} -> {$new_lang}";
                }
            }
        }
        
        $processed = $offset + count($posts);
        
        if ($this->logger) {
            $this->logger->log("Posts batch processed: {$processed}/{$total}, fixed: {$fixed}", 'info');
        }
        
        return [
            'total' => $total,
            'processed' => $processed,
            'fixed' => $fixed,
            'continue' => ($processed < $total),
            'next_offset' => $offset + $batch_size,
            'debug' => implode(' | ', $debug_info)
        ];
    }
    
    /**
     * Process taxonomies - FIXED TOTAL COUNT CALCULATION
     */
    private function process_taxonomies($offset, $batch_size) {
        global $wpdb;
        
        if (!$this->db_helper) {
            throw new Exception('Database helper not available');
        }
        
        $wpml_exists = $this->db_helper->wpml_tables_exist();
        $default_lang = pll_default_language();
        
        // Get taxonomies
        $taxonomies = get_taxonomies(['public' => true], 'names');
        $excluded = $this->db_helper->get_excluded_taxonomies();
        $taxonomies = array_diff($taxonomies, $excluded);
        
        // FIXED: Count total terms properly using SQL instead of wp_count_terms
        $taxonomies_str = "'" . implode("','", esc_sql($taxonomies)) . "'";
        $total = $wpdb->get_var("
            SELECT COUNT(DISTINCT t.term_id)
            FROM {$wpdb->terms} t
            JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
            WHERE tt.taxonomy IN ($taxonomies_str)
        ");
        
        $total = intval($total);
        
        // Get terms to process
        $terms = $wpdb->get_results($wpdb->prepare("
            SELECT t.term_id, tt.taxonomy, tt.term_taxonomy_id
            FROM {$wpdb->terms} t
            JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
            WHERE tt.taxonomy IN ($taxonomies_str)
            ORDER BY t.term_id
            LIMIT %d, %d
        ", $offset, $batch_size));
        
        $fixed = 0;
        $debug_info = [];
        
        foreach ($terms as $term) {
            $current_lang = pll_get_term_language($term->term_id);
            $needs_fix = false;
            $new_lang = null;
            
            if ($wpml_exists && $term->term_taxonomy_id) {
                $wpml_lang = $this->db_helper->get_wpml_term_language($term->term_taxonomy_id, $term->taxonomy);
                
                if ($wpml_lang) {
                    $new_lang = $this->language_converter->convert_language($wpml_lang);
                    
                    if (!$current_lang || $current_lang !== $new_lang) {
                        $needs_fix = true;
                    }
                } elseif (!$current_lang && $default_lang) {
                    $new_lang = $default_lang;
                    $needs_fix = true;
                }
            } elseif (!$current_lang && $default_lang) {
                $new_lang = $default_lang;
                $needs_fix = true;
            }
            
            if ($needs_fix && $new_lang) {
                pll_set_term_language($term->term_id, $new_lang);
                $fixed++;
                
                if (count($debug_info) < 5) {
                    $debug_info[] = "Term {$term->term_id} ({$term->taxonomy}): {$current_lang} -> {$new_lang}";
                }
            }
        }
        
        $processed = $offset + count($terms);
        
        if ($this->logger) {
            $this->logger->log("Taxonomies batch processed: {$processed}/{$total}, fixed: {$fixed}", 'info');
        }
        
        return [
            'total' => $total,
            'processed' => $processed,
            'fixed' => $fixed,
            'continue' => ($processed < $total),
            'next_offset' => $offset + $batch_size,
            'debug' => implode(' | ', $debug_info)
        ];
    }
    
    /**
     * Process BetterDocs
     */
    private function process_betterdocs($offset, $batch_size) {
        if (!post_type_exists('docs')) {
            throw new Exception('BetterDocs not installed');
        }
        
        global $wpdb;
        
        if (!$this->db_helper->wpml_tables_exist()) {
            throw new Exception('WPML data not found');
        }
        
        $default_lang = pll_default_language();
        
        // Count docs and terms
        $docs_count = $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->posts}
            WHERE post_type = 'docs'
            AND post_status IN ('publish', 'draft', 'private')
        ");
        
        $bd_taxonomies = ['doc_category', 'doc_tag', 'knowledge_base'];
        $terms_count = 0;
        
        foreach ($bd_taxonomies as $tax) {
            if (taxonomy_exists($tax)) {
                $count = wp_count_terms($tax, ['hide_empty' => false]);
                if (!is_wp_error($count)) {
                    $terms_count += intval($count);
                }
            }
        }
        
        $total = intval($docs_count) + intval($terms_count);
        $fixed = 0;
        $processed = 0;
        
        // Process docs first
        if ($offset < $docs_count) {
            $docs = $wpdb->get_results($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts}
                WHERE post_type = 'docs'
                AND post_status IN ('publish', 'draft', 'private')
                ORDER BY ID
                LIMIT %d, %d",
                $offset, min($batch_size, $docs_count - $offset)
            ));
            
            foreach ($docs as $doc) {
                $current_lang = pll_get_post_language($doc->ID);
                $wpml_lang = $this->db_helper->get_wpml_post_language($doc->ID, 'docs');
                
                if ($wpml_lang) {
                    $new_lang = $this->language_converter->convert_language($wpml_lang);
                    
                    if (!$current_lang || $current_lang !== $new_lang) {
                        pll_set_post_language($doc->ID, $new_lang);
                        $fixed++;
                    }
                } elseif (!$current_lang && $default_lang) {
                    pll_set_post_language($doc->ID, $default_lang);
                    $fixed++;
                }
                $processed++;
            }
        }
        
        // Process BetterDocs taxonomies
        if ($processed < $batch_size && $offset + $processed >= $docs_count) {
            $tax_offset = max(0, $offset - $docs_count);
            $remaining = $batch_size - $processed;
            
            foreach ($bd_taxonomies as $tax) {
                if (!taxonomy_exists($tax) || $remaining <= 0) continue;
                
                $terms = get_terms([
                    'taxonomy' => $tax,
                    'hide_empty' => false,
                    'fields' => 'ids',
                    'number' => $remaining,
                    'offset' => $tax_offset
                ]);
                
                if (!is_wp_error($terms)) {
                    foreach ($terms as $tid) {
                        $current_lang = pll_get_term_language($tid);
                        
                        $tt_id = $wpdb->get_var($wpdb->prepare(
                            "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy}
                            WHERE term_id = %d AND taxonomy = %s",
                            $tid, $tax
                        ));
                        
                        if ($tt_id) {
                            $wpml_lang = $this->db_helper->get_wpml_term_language($tt_id, $tax);
                            
                            if ($wpml_lang) {
                                $new_lang = $this->language_converter->convert_language($wpml_lang);
                                
                                if (!$current_lang || $current_lang !== $new_lang) {
                                    pll_set_term_language($tid, $new_lang);
                                    $fixed++;
                                }
                            } elseif (!$current_lang && $default_lang) {
                                pll_set_term_language($tid, $default_lang);
                                $fixed++;
                            }
                        }
                        $processed++;
                        $remaining--;
                        if ($remaining <= 0) break;
                    }
                }
            }
        }
        
        if ($this->logger) {
            $this->logger->log("BetterDocs batch processed: {$processed} items, fixed: {$fixed}", 'info');
        }
        
        return [
            'total' => $total,
            'processed' => min($offset + $processed, $total),
            'fixed' => $fixed,
            'continue' => ($offset + $processed) < $total,
            'next_offset' => $offset + $batch_size
        ];
    }
    
    /**
     * Process WooCommerce
     */
    private function process_woocommerce($offset, $batch_size) {
        if (!class_exists('WooCommerce')) {
            throw new Exception('WooCommerce not active');
        }
        
        global $wpdb;
        
        $wpml_exists = $this->db_helper->wpml_tables_exist();
        $default_lang = pll_default_language();
        
        // WooCommerce taxonomies
        $woo_taxonomies = ['product_cat', 'product_tag', 'product_shipping_class'];
        
        // Add product attributes
        $attributes = wc_get_attribute_taxonomies();
        foreach ($attributes as $attribute) {
            $woo_taxonomies[] = 'pa_' . $attribute->attribute_name;
        }
        
        // Count total
        $total = 0;
        foreach ($woo_taxonomies as $tax) {
            if (taxonomy_exists($tax)) {
                $count = wp_count_terms($tax, ['hide_empty' => false]);
                if (!is_wp_error($count)) {
                    $total += intval($count);
                }
            }
        }
        
        // Add product variations
        $variations_count = $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->posts}
            WHERE post_type = 'product_variation'
            AND post_status IN ('publish', 'private')
        ");
        $total += intval($variations_count);
        
        $processed = 0;
        $fixed = 0;
        $debug_info = [];
        
        // Process WooCommerce taxonomies
        foreach ($woo_taxonomies as $tax) {
            if (!taxonomy_exists($tax) || $processed >= $batch_size) break;
            
            $terms = get_terms([
                'taxonomy' => $tax,
                'hide_empty' => false,
                'fields' => 'ids',
                'number' => min($batch_size - $processed, 50),
                'offset' => max(0, $offset - $processed)
            ]);
            
            if (!is_wp_error($terms)) {
                foreach ($terms as $tid) {
                    $current_lang = pll_get_term_language($tid);
                    
                    $tt_id = $wpdb->get_var($wpdb->prepare(
                        "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy}
                        WHERE term_id = %d AND taxonomy = %s",
                        $tid, $tax
                    ));
                    
                    if ($tt_id) {
                        $wpml_lang = $this->db_helper->get_wpml_term_language($tt_id, $tax);
                        
                        if ($wpml_lang) {
                            $new_lang = $this->language_converter->convert_language($wpml_lang);
                            
                            if (!$current_lang || $current_lang !== $new_lang) {
                                pll_set_term_language($tid, $new_lang);
                                $fixed++;
                                
                                if (count($debug_info) < 3) {
                                    $debug_info[] = "WC term {$tid} ({$tax}): {$current_lang} -> {$new_lang}";
                                }
                            }
                        } elseif (!$current_lang && $default_lang) {
                            pll_set_term_language($tid, $default_lang);
                            $fixed++;
                        }
                    }
                    $processed++;
                }
            }
        }
        
        // Process product variations
        if ($processed < $batch_size) {
            $var_offset = max(0, $offset - ($total - $variations_count));
            $variations = $wpdb->get_results($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts}
                WHERE post_type = 'product_variation'
                AND post_status IN ('publish', 'private')
                LIMIT %d, %d",
                $var_offset, $batch_size - $processed
            ));
            
            foreach ($variations as $variation) {
                $parent_id = wp_get_post_parent_id($variation->ID);
                if ($parent_id) {
                    $parent_lang = pll_get_post_language($parent_id);
                    if ($parent_lang) {
                        $current_lang = pll_get_post_language($variation->ID);
                        if (!$current_lang || $current_lang != $parent_lang) {
                            pll_set_post_language($variation->ID, $parent_lang);
                            $fixed++;
                        }
                    }
                }
                $processed++;
            }
        }
        
        if ($this->logger) {
            $this->logger->log("WooCommerce batch processed: {$processed} items, fixed: {$fixed}", 'info');
        }
        
        return [
            'total' => $total,
            'processed' => min($offset + $processed, $total),
            'fixed' => $fixed,
            'continue' => ($offset + $processed) < $total,
            'next_offset' => $offset + $batch_size,
            'debug' => implode(' | ', $debug_info)
        ];
    }
    
    /**
     * Process translation groups
     */
    private function process_translations($offset, $batch_size) {
        global $wpdb;
        
        if (!$this->db_helper->wpml_tables_exist()) {
            throw new Exception('WPML data not found');
        }
        
        // Get translation groups from database helper
        $post_groups = $this->db_helper->get_wpml_translation_groups('post', $batch_size, $offset);
        $term_groups = $this->db_helper->get_wpml_translation_groups('term', $batch_size, $offset);
        
        $total = count($post_groups) + count($term_groups);
        $fixed = 0;
        $processed = 0;
        
        // Process post translation groups
        foreach ($post_groups as $group) {
            $ids = explode(',', $group->ids);
            $langs = explode(',', $group->langs);
            
            $translations = [];
            for ($i = 0; $i < count($ids); $i++) {
                if (get_post_status($ids[$i])) {
                    $pll_lang = $this->language_converter->convert_language($langs[$i]);
                    if ($pll_lang) {
                        $translations[$pll_lang] = intval($ids[$i]);
                    }
                }
            }
            
            if (count($translations) > 1) {
                pll_save_post_translations($translations);
                $fixed++;
            }
            $processed++;
        }
        
        // Process term translation groups
        foreach ($term_groups as $group) {
            $tt_ids = explode(',', $group->ids);
            $langs = explode(',', $group->langs);
            
            $translations = [];
            for ($i = 0; $i < count($tt_ids); $i++) {
                $term_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT term_id FROM {$wpdb->term_taxonomy}
                    WHERE term_taxonomy_id = %d",
                    $tt_ids[$i]
                ));
                
                if ($term_id && term_exists($term_id)) {
                    $pll_lang = $this->language_converter->convert_language($langs[$i]);
                    if ($pll_lang) {
                        $translations[$pll_lang] = intval($term_id);
                    }
                }
            }
            
            if (count($translations) > 1) {
                pll_save_term_translations($translations);
                $fixed++;
            }
            $processed++;
        }
        
        if ($this->logger) {
            $this->logger->log("Translation groups processed: {$processed} groups, fixed: {$fixed}", 'info');
        }
        
        return [
            'total' => $total,
            'processed' => $processed,
            'fixed' => $fixed,
            'continue' => false, // Translation groups are processed in single batch
            'next_offset' => $offset + $batch_size
        ];
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
     * Handle diagnose request - COMPLETE IMPLEMENTATION FROM REFERENCE
     */
    public function handle_diagnose() {
        $this->verify_request();
        
        try {
            global $wpdb;
            
            ob_start();
            
            echo '<h3>Language Assignment Diagnosis</h3>';
            
            // Get all unique language codes assigned in the system
            $post_langs = $wpdb->get_col("
                SELECT DISTINCT t.slug
                FROM {$wpdb->terms} t
                JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
                WHERE tt.taxonomy = 'language'
            ");
            
            $term_langs = $wpdb->get_col("
                SELECT DISTINCT t.slug
                FROM {$wpdb->terms} t
                JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
                WHERE tt.taxonomy = 'term_language'
            ");
            
            $all_langs = array_unique(array_merge($post_langs, $term_langs));
            
            echo '<table class="diagnosis-table">';
            echo '<tr><th>Language Code</th><th>Posts</th><th>Terms</th><th>Status</th></tr>';
            
            $configured_langs = pll_languages_list(['fields' => 'slug']);
            $problematic = [];
            $wrong_codes = [];
            
            foreach ($all_langs as $lang) {
                // Count posts with this language
                $post_count = $wpdb->get_var($wpdb->prepare("
                    SELECT COUNT(DISTINCT tr.object_id)
                    FROM {$wpdb->term_relationships} tr
                    JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                    JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
                    WHERE tt.taxonomy = 'language' AND t.slug = %s
                ", $lang));
                
                // Count terms with this language
                $term_count = $wpdb->get_var($wpdb->prepare("
                    SELECT COUNT(DISTINCT tr.object_id)
                    FROM {$wpdb->term_relationships} tr
                    JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                    JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
                    WHERE tt.taxonomy = 'term_language' AND t.slug = %s
                ", $lang));
                
                $status = in_array($lang, $configured_langs) ? 
                    '<span class="badge badge-success">Configured</span>' : 
                    '<span class="badge badge-warning">Not Configured</span>';
                
                echo "<tr>";
                echo "<td><strong>{$lang}</strong></td>";
                echo "<td>{$post_count}</td>";
                echo "<td>{$term_count}</td>";
                echo "<td>{$status}</td>";
                echo "</tr>";
                
                // Track problematic codes
                if (strpos($lang, 'pll_') === 0) {
                    $problematic['pll_prefixed'][] = $lang;
                }
                
                if (!in_array($lang, $configured_langs)) {
                    $problematic['unconfigured'][] = $lang;
                    $wrong_codes[$lang] = [
                        'posts' => intval($post_count),
                        'terms' => intval($term_count)
                    ];
                }
            }
            
            echo '</table>';
            
            // Check for pll_ prefix corruption
            if (!empty($problematic['pll_prefixed'])) {
                echo '<div style="margin-top: 15px; padding: 15px; background: #ffebee; border-radius: 8px; border-left: 4px solid #f44336;">';
                echo '<strong>🚨 CRITICAL DATA CORRUPTION DETECTED!</strong><br>';
                echo 'Invalid pll_ prefixed codes found: ' . implode(', ', $problematic['pll_prefixed']) . '<br>';
                echo '<strong>Use the EMERGENCY FIX button immediately to restore your content!</strong>';
                echo '</div>';
            }
            
            // Check for English variants that aren't the main configured English
            $english_variants = [];
            $main_english = false;
            
            // Find main configured English
            foreach ($configured_langs as $lang) {
                if ($lang === 'en') {
                    $main_english = 'en';
                    break;
                } elseif (strpos($lang, 'en') === 0 && !$main_english) {
                    $main_english = $lang;
                }
            }
            
            // Find English variants that aren't the main one
            foreach ($all_langs as $lang) {
                if (strpos($lang, 'en') === 0 && $lang !== $main_english && !in_array($lang, $configured_langs)) {
                    $english_variants[] = $lang;
                }
            }
            
            if (!empty($english_variants)) {
                echo '<div style="margin-top: 15px; padding: 15px; background: #fff3cd; border-radius: 8px; border-left: 4px solid #ffc107;">';
                echo '<strong>⚠️ English Variants Detected:</strong> ' . implode(', ', $english_variants) . '<br>';
                echo 'Content is assigned to variants not configured in Polylang.<br>';
                echo '<strong>Use "Fix English Variants" to reassign to your main English: ' . $main_english . '</strong>';
                echo '</div>';
            }
            
            // Check specific taxonomies
            echo '<h4 style="margin-top: 20px;">Taxonomy Language Distribution</h4>';
            echo '<table class="diagnosis-table">';
            echo '<tr><th>Taxonomy</th><th>Total</th><th>With Language</th><th>Without Language</th></tr>';
            
            $taxonomies = ['category', 'post_tag', 'doc_category', 'doc_tag', 'product_cat', 'product_tag'];
            
            foreach ($taxonomies as $tax) {
                if (!taxonomy_exists($tax)) continue;
                
                $total = wp_count_terms($tax, ['hide_empty' => false]);
                if (is_wp_error($total)) continue;
                
                $with_lang = $wpdb->get_var($wpdb->prepare("
                    SELECT COUNT(DISTINCT t.term_id)
                    FROM {$wpdb->terms} t
                    JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
                    WHERE tt.taxonomy = %s
                    AND EXISTS (
                        SELECT 1 FROM {$wpdb->term_relationships} tr2
                        JOIN {$wpdb->term_taxonomy} tt2 ON tr2.term_taxonomy_id = tt2.term_taxonomy_id
                        WHERE tr2.object_id = t.term_id AND tt2.taxonomy = 'term_language'
                    )
                ", $tax));
                
                $without_lang = $total - $with_lang;
                
                echo "<tr>";
                echo "<td><strong>{$tax}</strong></td>";
                echo "<td>{$total}</td>";
                echo "<td>{$with_lang}</td>";
                echo "<td>" . ($without_lang > 0 ? "<span class='badge badge-warning'>{$without_lang}</span>" : "0") . "</td>";
                echo "</tr>";
            }
            
            echo '</table>';
            
            $html = ob_get_clean();
            
            if ($this->logger) {
                $problematic_count = count($problematic);
                $this->logger->log("Diagnosis completed - Found {$problematic_count} issue types", 'info');
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
     * Handle fix WooCommerce attributes - COMPLETE IMPLEMENTATION FROM REFERENCE
     */
    public function handle_fix_woo_attributes() {
        $this->verify_request();
        
        try {
            if (!class_exists('WooCommerce')) {
                wp_send_json_error('WooCommerce not active');
            }
            
            global $wpdb;
            
            $offset = intval($_POST['offset'] ?? 0);
            $batch_size = intval($_POST['batch_size'] ?? 100);
            
            @set_time_limit(120);
            @ini_set('memory_limit', '512M');
            
            // Get default language
            $default_lang = pll_default_language();
            
            // Get all product attributes
            $attributes = wc_get_attribute_taxonomies();
            $attribute_taxonomies = [];
            
            foreach ($attributes as $attribute) {
                $attribute_taxonomies[] = 'pa_' . $attribute->attribute_name;
            }
            
            if (empty($attribute_taxonomies)) {
                wp_send_json_success([
                    'total' => 0,
                    'processed' => 0,
                    'fixed' => 0,
                    'continue' => false
                ]);
            }
            
            // Get ALL terms without language assignment
            $taxonomies_list = "'" . implode("','", esc_sql($attribute_taxonomies)) . "'";
            
            // First, get the total count
            $total = $wpdb->get_var("
                SELECT COUNT(DISTINCT t.term_id)
                FROM {$wpdb->terms} t
                JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
                WHERE tt.taxonomy IN ($taxonomies_list)
                AND NOT EXISTS (
                    SELECT 1 FROM {$wpdb->term_relationships} tr2
                    JOIN {$wpdb->term_taxonomy} tt2 ON tr2.term_taxonomy_id = tt2.term_taxonomy_id
                    WHERE tr2.object_id = t.term_id AND tt2.taxonomy = 'term_language'
                )
            ");
            
            $total = intval($total);
            
            if ($total == 0) {
                wp_send_json_success([
                    'total' => 0,
                    'processed' => 0,
                    'fixed' => 0,
                    'continue' => false
                ]);
            }
            
            // Get the batch of terms to process
            $terms_to_process = $wpdb->get_results($wpdb->prepare("
                SELECT t.term_id, tt.taxonomy
                FROM {$wpdb->terms} t
                JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
                WHERE tt.taxonomy IN ($taxonomies_list)
                AND NOT EXISTS (
                    SELECT 1 FROM {$wpdb->term_relationships} tr2
                    JOIN {$wpdb->term_taxonomy} tt2 ON tr2.term_taxonomy_id = tt2.term_taxonomy_id
                    WHERE tr2.object_id = t.term_id AND tt2.taxonomy = 'term_language'
                )
                ORDER BY t.term_id
                LIMIT %d, %d
            ", $offset, $batch_size));
            
            $processed = 0;
            $fixed = 0;
            
            // Process each term
            foreach ($terms_to_process as $term) {
                // Double-check the language
                $current_lang = pll_get_term_language($term->term_id);
                
                if (!$current_lang) {
                    // Assign default language
                    pll_set_term_language($term->term_id, $default_lang);
                    $fixed++;
                }
                $processed++;
            }
            
            $total_processed = $offset + $processed;
            $continue = ($total_processed < $total);
            
            wp_send_json_success([
                'total' => $total,
                'processed' => $total_processed,
                'fixed' => $fixed,
                'continue' => $continue,
                'next_offset' => $offset + $batch_size,
                'debug' => "Found {$total} terms without language, processed batch of {$processed}, fixed {$fixed}"
            ]);
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->log_error("Fix WooCommerce attributes failed", $e);
            }
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Handle fix English variants - COMPLETE IMPLEMENTATION FROM REFERENCE
     */
    public function handle_fix_english() {
        $this->verify_request();
        
        try {
            global $wpdb;
            
            $offset = intval($_POST['offset'] ?? 0);
            $batch_size = intval($_POST['batch_size'] ?? 100);
            
            @set_time_limit(120);
            @ini_set('memory_limit', '512M');
            
            // Get main English language code from Polylang
            $configured_langs = pll_languages_list(['fields' => 'slug']);
            $main_english = false;
            
            // Find the main English language
            foreach ($configured_langs as $lang) {
                if ($lang === 'en') {
                    $main_english = 'en';
                    break;
                } elseif (strpos($lang, 'en') === 0 && !$main_english) {
                    $main_english = $lang;
                }
            }
            
            if (!$main_english) {
                wp_send_json_error('No English language configured in Polylang');
            }
            
            // English variants to check
            $english_variants = ['en-gb', 'en_gb', 'en-us', 'en_us', 'en-au', 'en_au', 'en-ca', 'en_ca', 'en-nz', 'en_nz', 'en-za', 'en_za'];
            
            // Remove the main English from variants list
            $english_variants = array_diff($english_variants, [$main_english]);
            
            // Build SQL for finding items with wrong English variants
            $variants_sql = "'" . implode("','", esc_sql($english_variants)) . "'";
            
            // Count total items with English variants
            $total_posts = $wpdb->get_var("
                SELECT COUNT(DISTINCT tr.object_id)
                FROM {$wpdb->term_relationships} tr
                JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
                WHERE tt.taxonomy = 'language' 
                AND t.slug IN ($variants_sql)
            ");
            
            $total_terms = $wpdb->get_var("
                SELECT COUNT(DISTINCT tr.object_id)
                FROM {$wpdb->term_relationships} tr
                JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
                WHERE tt.taxonomy = 'term_language' 
                AND t.slug IN ($variants_sql)
            ");
            
            $total_count = $total_posts + $total_terms;
            
            if ($total_count == 0) {
                wp_send_json_success([
                    'total' => 0,
                    'processed' => 0,
                    'fixed_posts' => 0,
                    'fixed_terms' => 0,
                    'continue' => false
                ]);
            }
            
            $fixed_posts = 0;
            $fixed_terms = 0;
            $processed = 0;
            
            // Process posts first
            if ($offset < $total_posts) {
                $posts_to_fix = $wpdb->get_results($wpdb->prepare("
                    SELECT DISTINCT tr.object_id as post_id, t.slug as current_lang
                    FROM {$wpdb->term_relationships} tr
                    JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                    JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
                    WHERE tt.taxonomy = 'language' 
                    AND t.slug IN ($variants_sql)
                    LIMIT %d, %d
                ", $offset, min($batch_size, $total_posts - $offset)));
                
                foreach ($posts_to_fix as $post) {
                    // Delete wrong assignment
                    $wpdb->query($wpdb->prepare("
                        DELETE tr FROM {$wpdb->term_relationships} tr
                        JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                        JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
                        WHERE tr.object_id = %d
                        AND tt.taxonomy = 'language'
                        AND t.slug = %s
                    ", $post->post_id, $post->current_lang));
                    
                    // Set correct language
                    pll_set_post_language($post->post_id, $main_english);
                    $fixed_posts++;
                    $processed++;
                }
            }
            
            // Process terms if needed
            $remaining = $batch_size - $processed;
            if ($remaining > 0 && $offset + $processed >= $total_posts) {
                $term_offset = max(0, $offset - $total_posts);
                
                $terms_to_fix = $wpdb->get_results($wpdb->prepare("
                    SELECT DISTINCT tr.object_id as term_id, t.slug as current_lang
                    FROM {$wpdb->term_relationships} tr
                    JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                    JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
                    WHERE tt.taxonomy = 'term_language' 
                    AND t.slug IN ($variants_sql)
                    LIMIT %d, %d
                ", $term_offset, $remaining));
                
                foreach ($terms_to_fix as $term) {
                    // Delete wrong assignment
                    $wpdb->query($wpdb->prepare("
                        DELETE tr FROM {$wpdb->term_relationships} tr
                        JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                        JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
                        WHERE tr.object_id = %d
                        AND tt.taxonomy = 'term_language'
                        AND t.slug = %s
                    ", $term->term_id, $term->current_lang));
                    
                    // Set correct language
                    pll_set_term_language($term->term_id, $main_english);
                    $fixed_terms++;
                    $processed++;
                }
            }
            
            $total_processed = $offset + $processed;
            $continue = ($total_processed < $total_count);
            
            wp_send_json_success([
                'total' => $total_count,
                'processed' => $total_processed,
                'fixed_posts' => $fixed_posts,
                'fixed_terms' => $fixed_terms,
                'continue' => $continue,
                'next_offset' => $offset + $batch_size
            ]);
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->log_error("Fix English failed", $e);
            }
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Handle fix pll prefix - COMPLETE IMPLEMENTATION FROM REFERENCE
     */
    public function handle_fix_pll_prefix() {
        $this->verify_request();
        
        try {
            global $wpdb;
            
            $offset = intval($_POST['offset'] ?? 0);
            $batch_size = intval($_POST['batch_size'] ?? 100);
            
            @set_time_limit(120);
            @ini_set('memory_limit', '512M');
            
            // Get configured languages
            $configured_langs = pll_languages_list(['fields' => 'slug']);
            
            // Check both language and term_language taxonomies
            $total_posts = $wpdb->get_var("
                SELECT COUNT(DISTINCT tr.object_id)
                FROM {$wpdb->term_relationships} tr
                JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
                WHERE tt.taxonomy = 'language'
                AND t.slug LIKE 'pll_%'
            ");
            
            $total_terms = $wpdb->get_var("
                SELECT COUNT(DISTINCT tr.object_id)
                FROM {$wpdb->term_relationships} tr
                JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
                WHERE tt.taxonomy = 'term_language'
                AND t.slug LIKE 'pll_%'
            ");
            
            $total_count = $total_posts + $total_terms;
            
            if ($total_count == 0) {
                wp_send_json_success([
                    'total' => 0,
                    'processed' => 0,
                    'fixed' => 0,
                    'continue' => false
                ]);
            }
            
            $fixed = 0;
            $processed = 0;
            
            // Enhanced mappings
            $pll_mappings = [
                'pll_en' => 'en',
                'pll_es' => 'es',
                'pll_de' => 'de',
                'pll_fr' => 'fr',
                'pll_ar' => 'ar',
                'pll_bg' => 'bg',
                'pll_cs' => 'cs',
                'pll_sk' => 'sk',
                'pll_da' => 'da',
                'pll_el' => 'el',
                'pll_et' => 'et',
                'pll_fi' => 'fi',
                'pll_ga' => 'ga',
                'pll_he' => 'he',
                'pll_hr' => 'hr',
                'pll_hu' => 'hu',
                'pll_id' => 'id',
                'pll_is' => 'is',
                'pll_it' => 'it',
                'pll_ja' => 'ja',
                'pll_ko' => 'ko',
                'pll_lv' => 'lv',
                'pll_lt' => 'lt',
                'pll_nl' => 'nl',
                'pll_no' => 'no',
                'pll_pl' => 'pl',
                'pll_pt' => 'pt',
                'pll_pt-pt' => 'pt-pt',
                'pll_pt_pt' => 'pt-pt',
                'pll_ru' => 'ru',
                'pll_sv' => 'sv',
                'pll_tr' => 'tr',
                'pll_uk' => 'uk',
                'pll_vi' => 'vi',
                'pll_zh' => 'zh',
            ];
            
            // Adjust mappings based on configured languages
            foreach ($configured_langs as $lang) {
                if (strpos($lang, 'en') === 0) {
                    $pll_mappings['pll_en'] = $lang;
                    break;
                }
            }
            
            // Process posts with pll_ prefix
            if ($offset < $total_posts) {
                $posts_to_fix = $wpdb->get_results($wpdb->prepare("
                    SELECT DISTINCT tr.object_id as post_id, t.slug as wrong_code
                    FROM {$wpdb->term_relationships} tr
                    JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                    JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
                    WHERE tt.taxonomy = 'language'
                    AND t.slug LIKE 'pll_%%'
                    LIMIT %d, %d
                ", $offset, min($batch_size, $total_posts - $offset)));
                
                foreach ($posts_to_fix as $post) {
                    $wrong_code = $post->wrong_code;
                    
                    // Find the correct language code
                    $correct_code = null;
                    if (isset($pll_mappings[$wrong_code])) {
                        $correct_code = $pll_mappings[$wrong_code];
                    } else {
                        // Try to extract language from pll_ prefix
                        $base_code = str_replace('pll_', '', $wrong_code);
                        if (in_array($base_code, $configured_langs)) {
                            $correct_code = $base_code;
                        }
                    }
                    
                    if ($correct_code && in_array($correct_code, $configured_langs)) {
                        // Delete the wrong assignment
                        $wpdb->query($wpdb->prepare("
                            DELETE tr FROM {$wpdb->term_relationships} tr
                            JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                            JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
                            WHERE tr.object_id = %d
                            AND tt.taxonomy = 'language'
                            AND t.slug = %s
                        ", $post->post_id, $wrong_code));
                        
                        // Set the correct language
                        pll_set_post_language($post->post_id, $correct_code);
                        $fixed++;
                    }
                    $processed++;
                }
            }
            
            // Process terms with pll_ prefix
            $remaining = $batch_size - $processed;
            if ($remaining > 0 && $offset + $processed >= $total_posts) {
                $term_offset = max(0, $offset - $total_posts);
                
                $terms_to_fix = $wpdb->get_results($wpdb->prepare("
                    SELECT DISTINCT tr.object_id as term_id, t.slug as wrong_code
                    FROM {$wpdb->term_relationships} tr
                    JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                    JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
                    WHERE tt.taxonomy = 'term_language'
                    AND t.slug LIKE 'pll_%%'
                    LIMIT %d, %d
                ", $term_offset, $remaining));
                
                foreach ($terms_to_fix as $term) {
                    $wrong_code = $term->wrong_code;
                    
                    // Find the correct language code
                    $correct_code = null;
                    if (isset($pll_mappings[$wrong_code])) {
                        $correct_code = $pll_mappings[$wrong_code];
                    } else {
                        // Try to extract language from pll_ prefix
                        $base_code = str_replace('pll_', '', $wrong_code);
                        if (in_array($base_code, $configured_langs)) {
                            $correct_code = $base_code;
                        }
                    }
                    
                    if ($correct_code && in_array($correct_code, $configured_langs)) {
                        // Delete the wrong assignment
                        $wpdb->query($wpdb->prepare("
                            DELETE tr FROM {$wpdb->term_relationships} tr
                            JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                            JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
                            WHERE tr.object_id = %d
                            AND tt.taxonomy = 'term_language'
                            AND t.slug = %s
                        ", $term->term_id, $wrong_code));
                        
                        // Set the correct language
                        pll_set_term_language($term->term_id, $correct_code);
                        $fixed++;
                    }
                    $processed++;
                }
            }
            
            $total_processed = $offset + $processed;
            $continue = ($total_processed < $total_count);
            
            // If this is the last batch, clean up orphaned pll_ terms
            if (!$continue) {
                $wpdb->query("
                    DELETE t, tt FROM {$wpdb->terms} t
                    JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
                    WHERE tt.taxonomy IN ('language', 'term_language')
                    AND t.slug LIKE 'pll_%'
                ");
            }
            
            wp_send_json_success([
                'total' => $total_count,
                'processed' => $total_processed,
                'fixed' => $fixed,
                'continue' => $continue,
                'next_offset' => $offset + $batch_size
            ]);
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->log_error("Fix PLL prefix failed", $e);
            }
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Handle reset session
     */
    public function handle_reset_session() {
        $this->verify_request();
        
        try {
            // Clear any transients or session data
            delete_transient('wpml_fixer_session_' . get_current_user_id());
            
            wp_send_json_success([
                'message' => 'Session reset successfully'
            ]);
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->log_error("Reset session failed", $e);
            }
            wp_send_json_error($e->getMessage());
        }
    }
}

} // End of class_exists check