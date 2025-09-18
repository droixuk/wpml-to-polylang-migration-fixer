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
        $this->db_helper = new WPML_Fixer_Database_Helper();
        $this->language_converter = new WPML_Fixer_Language_Converter();
        $this->logger = new WPML_Fixer_Debug_Logger();
        
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
        
        $this->logger->log("Processing {$type} - Offset: {$offset}, Batch: {$batch_size}", 'info');
        
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
            $this->logger->log_performance("Process {$type}", $time_taken, $result['fixed'] ?? 0);
            
            wp_send_json_success($result);
            
        } catch (Exception $e) {
            $this->logger->log_error("Process failed: {$type}", $e);
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Process posts
     */
    private function process_posts($offset, $batch_size) {
        $post_types = get_post_types(['public' => true], 'names');
        unset($post_types['attachment']);
        
        $exclude_types = $this->db_helper->get_excluded_post_types();
        $post_types = array_diff($post_types, $exclude_types);
        
        // Get total count
        global $wpdb;
        $post_types_str = "'" . implode("','", esc_sql($post_types)) . "'";
        $total = $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->posts}
            WHERE post_type IN ($post_types_str)
            AND post_status IN ('publish', 'draft', 'private')
        ");
        
        // Get posts to process
        $posts = $wpdb->get_results($wpdb->prepare("
            SELECT ID, post_type FROM {$wpdb->posts}
            WHERE post_type IN ($post_types_str)
            AND post_status IN ('publish', 'draft', 'private')
            ORDER BY ID
            LIMIT %d, %d
        ", $offset, $batch_size));
        
        $fixed = 0;
        $default_lang = pll_default_language();
        
        foreach ($posts as $post) {
            $current_lang = pll_get_post_language($post->ID);
            $needs_fix = false;
            $new_lang = null;
            
            // Check WPML data if available
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
            
            if ($needs_fix && $new_lang) {
                pll_set_post_language($post->ID, $new_lang);
                $fixed++;
            }
        }
        
        $processed = $offset + count($posts);
        
        return [
            'total' => intval($total),
            'processed' => $processed,
            'fixed' => $fixed,
            'continue' => ($processed < $total),
            'next_offset' => $offset + $batch_size
        ];
    }
    
    /**
     * Process taxonomies
     */
    private function process_taxonomies($offset, $batch_size) {
        $taxonomies = get_taxonomies(['public' => true], 'names');
        $exclude = $this->db_helper->get_excluded_taxonomies();
        $taxonomies = array_diff($taxonomies, $exclude);
        
        // Get total count
        $total = 0;
        foreach ($taxonomies as $tax) {
            $count = wp_count_terms($tax, ['hide_empty' => false]);
            if (!is_wp_error($count)) {
                $total += intval($count);
            }
        }
        
        // Get terms to process
        global $wpdb;
        $taxonomies_str = "'" . implode("','", esc_sql($taxonomies)) . "'";
        $terms = $wpdb->get_results($wpdb->prepare("
            SELECT t.term_id, tt.taxonomy, tt.term_taxonomy_id
            FROM {$wpdb->terms} t
            JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
            WHERE tt.taxonomy IN ($taxonomies_str)
            ORDER BY t.term_id
            LIMIT %d, %d
        ", $offset, $batch_size));
        
        $fixed = 0;
        $default_lang = pll_default_language();
        
        foreach ($terms as $term) {
            $current_lang = pll_get_term_language($term->term_id);
            $needs_fix = false;
            $new_lang = null;
            
            // Check WPML data if available
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
            
            if ($needs_fix && $new_lang) {
                pll_set_term_language($term->term_id, $new_lang);
                $fixed++;
            }
        }
        
        $processed = $offset + count($terms);
        
        return [
            'total' => $total,
            'processed' => $processed,
            'fixed' => $fixed,
            'continue' => ($processed < $total),
            'next_offset' => $offset + $batch_size
        ];
    }
    
    /**
     * Process BetterDocs
     */
    private function process_betterdocs($offset, $batch_size) {
        if (!post_type_exists('docs')) {
            return ['error' => __('BetterDocs not installed', 'wpml-migration-fixer')];
        }
        
        global $wpdb;
        
        // Count total BetterDocs items
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
        $default_lang = pll_default_language();
        
        // Process docs
        if ($offset < $docs_count) {
            $docs = $wpdb->get_results($wpdb->prepare("
                SELECT ID FROM {$wpdb->posts}
                WHERE post_type = 'docs'
                AND post_status IN ('publish', 'draft', 'private')
                ORDER BY ID
                LIMIT %d, %d
            ", $offset, min($batch_size, $docs_count - $offset)));
            
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
            return ['error' => __('WooCommerce not active', 'wpml-migration-fixer')];
        }
        
        global $wpdb;
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
            $variations = $wpdb->get_results($wpdb->prepare("
                SELECT ID FROM {$wpdb->posts}
                WHERE post_type = 'product_variation'
                AND post_status IN ('publish', 'private')
                LIMIT %d, %d
            ", $var_offset, $batch_size - $processed));
            
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
        
        return [
            'total' => $total,
            'processed' => min($offset + $processed, $total),
            'fixed' => $fixed,
            'continue' => ($offset + $processed) < $total,
            'next_offset' => $offset + $batch_size
        ];
    }
    
    /**
     * Process translations
     */
    private function process_translations($offset, $batch_size) {
        if (!$this->db_helper->wpml_tables_exist()) {
            return ['error' => __('WPML data not found', 'wpml-migration-fixer')];
        }
        
        global $wpdb;
        $icl_table = $wpdb->prefix . 'icl_translations';
        
        // Count total translation groups
        $post_groups_count = $wpdb->get_var("
            SELECT COUNT(DISTINCT trid)
            FROM $icl_table
            WHERE element_type LIKE 'post_%'
            AND trid IN (
                SELECT trid FROM $icl_table
                GROUP BY trid
                HAVING COUNT(*) > 1
            )
        ");
        
        $term_groups_count = $wpdb->get_var("
            SELECT COUNT(DISTINCT trid)
            FROM $icl_table
            WHERE element_type LIKE 'tax_%'
            AND trid IN (
                SELECT trid FROM $icl_table
                GROUP BY trid
                HAVING COUNT(*) > 1
            )
        ");
        
        $total = intval($post_groups_count) + intval($term_groups_count);
        $fixed = 0;
        $processed = 0;
        
        // Process post translation groups
        if ($offset < $post_groups_count) {
            $post_groups = $this->db_helper->get_wpml_translation_groups('post', min($batch_size / 2, $post_groups_count - $offset), $offset);
            
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
        }
        
        // Process term translation groups
        if ($processed < $batch_size && $offset + $processed >= $post_groups_count) {
            $term_offset = max(0, $offset - $post_groups_count);
            $term_groups = $this->db_helper->get_wpml_translation_groups('tax', $batch_size - $processed, $term_offset);
            
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
     * Handle analysis request
     */
    public function handle_analyze() {
        $this->verify_request();
        
        try {
            $stats = $this->db_helper->get_migration_statistics();
            $problematic_codes = $this->language_converter->get_problematic_codes();
            
            ob_start();
            include WPML_FIXER_PLUGIN_DIR . 'admin/views/analysis-results.php';
            $html = ob_get_clean();
            
            wp_send_json_success($html);
            
        } catch (Exception $e) {
            $this->logger->log_error("Analysis failed", $e);
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Handle diagnosis request
     */
    public function handle_diagnose() {
        $this->verify_request();
        
        try {
            $problematic = $this->language_converter->get_problematic_codes();
            $wrong_codes = $this->db_helper->get_content_with_wrong_codes();
            
            ob_start();
            include WPML_FIXER_PLUGIN_DIR . 'admin/views/diagnosis-results.php';
            $html = ob_get_clean();
            
            wp_send_json_success($html);
            
        } catch (Exception $e) {
            $this->logger->log_error("Diagnosis failed", $e);
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Handle fix English variants
     */
    public function handle_fix_english() {
        $this->verify_request();
        
        $offset = intval($_POST['offset'] ?? 0);
        $batch_size = intval($_POST['batch_size'] ?? 100);
        
        try {
            $result = $this->fix_english_variants($offset, $batch_size);
            wp_send_json_success($result);
        } catch (Exception $e) {
            $this->logger->log_error("Fix English variants failed", $e);
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Fix English variants
     */
    private function fix_english_variants($offset, $batch_size) {
        global $wpdb;
        
        $main_english = $this->language_converter->get_main_english_code();
        
        if (!$main_english) {
            throw new Exception(__('No English language configured in Polylang', 'wpml-migration-fixer'));
        }
        
        $english_variants = ['en-gb', 'en_gb', 'en-us', 'en_us', 'en-au', 'en_au', 'en-ca', 'en_ca', 'en-nz', 'en_nz', 'en-za', 'en_za'];
        $english_variants = array_diff($english_variants, [$main_english]);
        
        $variants_sql = "'" . implode("','", esc_sql($english_variants)) . "'";
        
        // Count affected items
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
            return [
                'total' => 0,
                'processed' => 0,
                'fixed_posts' => 0,
                'fixed_terms' => 0,
                'continue' => false
            ];
        }
        
        $fixed_posts = 0;
        $fixed_terms = 0;
        $processed = 0;
        
        // Process posts
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
                if ($this->db_helper->fix_wrong_language_code($post->post_id, 'post', $post->current_lang, $main_english)) {
                    $fixed_posts++;
                }
                $processed++;
            }
        }
        
        // Process terms
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
                if ($this->db_helper->fix_wrong_language_code($term->term_id, 'term', $term->current_lang, $main_english)) {
                    $fixed_terms++;
                }
                $processed++;
            }
        }
        
        $total_processed = $offset + $processed;
        
        return [
            'total' => $total_count,
            'processed' => $total_processed,
            'fixed_posts' => $fixed_posts,
            'fixed_terms' => $fixed_terms,
            'continue' => ($total_processed < $total_count),
            'next_offset' => $offset + $batch_size
        ];
    }
    
    /**
     * Handle fix pll_ prefix
     */
    public function handle_fix_pll_prefix() {
        $this->verify_request();
        
        $offset = intval($_POST['offset'] ?? 0);
        $batch_size = intval($_POST['batch_size'] ?? 100);
        
        try {
            $result = $this->fix_pll_prefix($offset, $batch_size);
            wp_send_json_success($result);
        } catch (Exception $e) {
            $this->logger->log_error("Fix pll_ prefix failed", $e);
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Fix pll_ prefix corruption
     */
    private function fix_pll_prefix($offset, $batch_size) {
        $wrong_codes = $this->db_helper->get_content_with_wrong_codes('pll_%');
        
        $total_count = $wrong_codes['posts'] + $wrong_codes['terms'];
        
        if ($total_count == 0) {
            return [
                'total' => 0,
                'processed' => 0,
                'fixed' => 0,
                'continue' => false
            ];
        }
        
        global $wpdb;
        $fixed = 0;
        $processed = 0;
        
        // Process posts with pll_ prefix
        if ($offset < $wrong_codes['posts']) {
            $posts_to_fix = $wpdb->get_results($wpdb->prepare("
                SELECT DISTINCT tr.object_id as post_id, t.slug as wrong_code
                FROM {$wpdb->term_relationships} tr
                JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
                WHERE tt.taxonomy = 'language'
                AND t.slug LIKE 'pll_%%'
                LIMIT %d, %d
            ", $offset, min($batch_size, $wrong_codes['posts'] - $offset)));
            
            foreach ($posts_to_fix as $post) {
                $correct_code = str_replace('pll_', '', $post->wrong_code);
                $correct_code = $this->language_converter->convert_language($correct_code);
                
                if ($this->db_helper->fix_wrong_language_code($post->post_id, 'post', $post->wrong_code, $correct_code)) {
                    $fixed++;
                }
                $processed++;
            }
        }
        
        // Process terms with pll_ prefix
        $remaining = $batch_size - $processed;
        if ($remaining > 0 && $offset + $processed >= $wrong_codes['posts']) {
            $term_offset = max(0, $offset - $wrong_codes['posts']);
            
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
                $correct_code = str_replace('pll_', '', $term->wrong_code);
                $correct_code = $this->language_converter->convert_language($correct_code);
                
                if ($this->db_helper->fix_wrong_language_code($term->term_id, 'term', $term->wrong_code, $correct_code)) {
                    $fixed++;
                }
                $processed++;
            }
        }
        
        $total_processed = $offset + $processed;
        $continue = ($total_processed < $total_count);
        
        // Clean up orphaned terms on last batch
        if (!$continue) {
            $this->db_helper->cleanup_orphaned_language_terms();
        }
        
        return [
            'total' => $total_count,
            'processed' => $total_processed,
            'fixed' => $fixed,
            'continue' => $continue,
            'next_offset' => $offset + $batch_size
        ];
    }
    
    /**
     * Handle fix WooCommerce attributes
     */
    public function handle_fix_woo_attributes() {
        $this->verify_request();
        
        if (!class_exists('WooCommerce')) {
            wp_send_json_error(__('WooCommerce not active', 'wpml-migration-fixer'));
        }
        
        $offset = intval($_POST['offset'] ?? 0);
        $batch_size = intval($_POST['batch_size'] ?? 100);
        
        try {
            $result = $this->fix_woo_attributes($offset, $batch_size);
            wp_send_json_success($result);
        } catch (Exception $e) {
            $this->logger->log_error("Fix WooCommerce attributes failed", $e);
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Fix WooCommerce attributes
     */
    private function fix_woo_attributes($offset, $batch_size) {
        global $wpdb;
        
        $default_lang = pll_default_language();
        
        // Get all product attributes
        $attributes = wc_get_attribute_taxonomies();
        $attribute_taxonomies = [];
        
        foreach ($attributes as $attribute) {
            $attribute_taxonomies[] = 'pa_' . $attribute->attribute_name;
        }
        
        if (empty($attribute_taxonomies)) {
            return [
                'total' => 0,
                'processed' => 0,
                'fixed' => 0,
                'continue' => false
            ];
        }
        
        $taxonomies_list = "'" . implode("','", esc_sql($attribute_taxonomies)) . "'";
        
        // Get total count
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
            return [
                'total' => 0,
                'processed' => 0,
                'fixed' => 0,
                'continue' => false
            ];
        }
        
        // Get terms to process
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
        
        foreach ($terms_to_process as $term) {
            $current_lang = pll_get_term_language($term->term_id);
            
            if (!$current_lang) {
                pll_set_term_language($term->term_id, $default_lang);
                $fixed++;
            }
            $processed++;
        }
        
        $total_processed = $offset + $processed;
        
        return [
            'total' => $total,
            'processed' => $total_processed,
            'fixed' => $fixed,
            'continue' => ($total_processed < $total),
            'next_offset' => $offset + $batch_size
        ];
    }
    
    /**
     * Handle verify migration
     */
    public function handle_verify_migration() {
        $this->verify_request();
        
        try {
            $verification = $this->verify_migration();
            
            ob_start();
            include WPML_FIXER_PLUGIN_DIR . 'admin/views/verification-results.php';
            $html = ob_get_clean();
            
            wp_send_json_success($html);
            
        } catch (Exception $e) {
            $this->logger->log_error("Verification failed", $e);
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Verify migration
     */
    private function verify_migration() {
        global $wpdb;
        
        $results = [
            'translation_groups' => 0,
            'orphaned_languages' => 0,
            'duplicate_assignments' => 0,
            'language_mapping' => []
        ];
        
        // Check for pll_wpml_ translation groups
        $results['translation_groups'] = $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$wpdb->terms}
            WHERE slug LIKE 'pll_wpml_%'
        ");
        
        // Check for orphaned language terms
        $results['orphaned_languages'] = $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$wpdb->terms} t
            JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
            WHERE tt.taxonomy IN ('language', 'term_language')
            AND t.slug LIKE 'pll_%'
        ");
        
        // Check for duplicate language assignments
        $results['duplicate_assignments'] = $wpdb->get_var("
            SELECT COUNT(DISTINCT tr1.object_id)
            FROM {$wpdb->term_relationships} tr1
            JOIN {$wpdb->term_taxonomy} tt1 ON tr1.term_taxonomy_id = tt1.term_taxonomy_id
            JOIN {$wpdb->term_relationships} tr2 ON tr1.object_id = tr2.object_id
            JOIN {$wpdb->term_taxonomy} tt2 ON tr2.term_taxonomy_id = tt2.term_taxonomy_id
            WHERE tt1.taxonomy = 'language'
            AND tt2.taxonomy = 'language'
            AND tr1.term_taxonomy_id != tr2.term_taxonomy_id
        ");
        
        // Check language code mapping
        if ($this->db_helper->wpml_tables_exist()) {
            $icl_table = $wpdb->prefix . 'icl_translations';
            $wpml_langs = $wpdb->get_col("SELECT DISTINCT language_code FROM $icl_table");
            $pll_langs = pll_languages_list(['fields' => 'slug']);
            
            foreach ($wpml_langs as $wpml_code) {
                $pll_code = $this->language_converter->convert_language($wpml_code);
                $results['language_mapping'][] = [
                    'wpml' => $wpml_code,
                    'pll' => $pll_code,
                    'status' => in_array($pll_code, $pll_langs) ? 'mapped' : 'missing'
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * Handle reset session
     */
    public function handle_reset_session() {
        $this->verify_request();
        
        try {
            // Clear any transients
            delete_transient('wpml_fixer_session_' . get_current_user_id());
            
            $this->logger->log("Session reset for user " . get_current_user_id(), 'info');
            
            wp_send_json_success(__('Session reset successfully', 'wpml-migration-fixer'));
            
        } catch (Exception $e) {
            $this->logger->log_error("Reset session failed", $e);
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Handle download logs
     */
    public function handle_download_logs() {
        if (!wp_verify_nonce($_GET['nonce'] ?? '', 'wpml_fixer_logs')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
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