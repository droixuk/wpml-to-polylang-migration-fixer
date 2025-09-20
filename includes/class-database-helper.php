<?php
/**
 * Database Helper Class
 * 
 * Handles database operations for the WPML to Polylang Migration Fixer plugin
 * Enhanced with BetterDocs support
 * 
 * @package WPML_To_Polylang_Migration_Fixer
 * @since 1.1.2
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPML_To_Polylang_Fixer_Database_Helper {

    private $logger;
    private $icl_table;
    private $debug_collector = null;

    public function __construct() {
        global $wpdb;
        $this->icl_table = $wpdb->prefix . 'icl_translations';

        // Logger will be initialized if class exists
        if (class_exists('WPML_To_Polylang_Fixer_Debug_Logger')) {
            $this->logger = new WPML_To_Polylang_Fixer_Debug_Logger();
        }
    }

    /**
     * Initialize debug collector
     */
    public function init_debug_collector($enabled = false) {
        if (class_exists('WPML_Fixer_Debug_Collector')) {
            $this->debug_collector = new WPML_Fixer_Debug_Collector($enabled);
        }
    }

    /**
     * Get debug data from collector
     */
    public function get_debug_data() {
        return $this->debug_collector ? $this->debug_collector->get_debug_data() : null;
    }
    
    public function wpml_tables_exist() {
        global $wpdb;
        return $wpdb->get_var("SHOW TABLES LIKE '{$this->icl_table}'") == $this->icl_table;
    }
    
    public function get_wpml_post_language($post_id, $post_type) {
        if (!$this->wpml_tables_exist()) {
            return null;
        }
        
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT language_code FROM {$this->icl_table}
            WHERE element_id = %d AND element_type = %s",
            $post_id,
            'post_' . $post_type
        ));
    }
    
    public function get_wpml_term_language($term_taxonomy_id, $taxonomy) {
        if (!$this->wpml_tables_exist()) {
            return null;
        }
        
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT language_code FROM {$this->icl_table}
            WHERE element_id = %d AND element_type = %s",
            $term_taxonomy_id,
            'tax_' . $taxonomy
        ));
    }
    
    public function get_wpml_translation_groups($type = 'post', $limit = 100, $offset = 0) {
        if (!$this->wpml_tables_exist()) {
            return [];
        }
        
        global $wpdb;
        
        $element_type_pattern = $type === 'post' ? 'post_%' : 'tax_%';
        
        $query = $wpdb->prepare("
            SELECT trid,
                   GROUP_CONCAT(element_id) as ids,
                   GROUP_CONCAT(language_code) as langs,
                   element_type
            FROM {$this->icl_table}
            WHERE element_type LIKE %s
            GROUP BY trid
            HAVING COUNT(*) > 1
            LIMIT %d OFFSET %d
        ", $element_type_pattern, $limit, $offset);
        
        return $wpdb->get_results($query);
    }
    
    public function get_content_with_wrong_codes($code_pattern = 'pll_%') {
        global $wpdb;
        
        $results = [
            'posts' => 0,
            'terms' => 0,
            'details' => []
        ];
        
        try {
            $results['posts'] = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(DISTINCT tr.object_id)
                FROM {$wpdb->term_relationships} tr
                JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
                WHERE tt.taxonomy = 'language'
                AND t.slug LIKE %s
            ", $code_pattern));
            
            $results['terms'] = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(DISTINCT tr.object_id)
                FROM {$wpdb->term_relationships} tr
                JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
                WHERE tt.taxonomy = 'term_language'
                AND t.slug LIKE %s
            ", $code_pattern));
            
            $wrong_codes = $wpdb->get_col($wpdb->prepare("
                SELECT DISTINCT t.slug
                FROM {$wpdb->terms} t
                JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
                WHERE tt.taxonomy IN ('language', 'term_language')
                AND t.slug LIKE %s
                LIMIT 10
            ", $code_pattern));
            
            $results['details'] = $wrong_codes;
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->log_error("Error getting content with wrong codes", $e);
            }
        }
        
        return $results;
    }
    
    public function fix_wrong_language_code($object_id, $object_type, $wrong_code, $correct_code) {
        global $wpdb;
        
        try {
            $wpdb->query('START TRANSACTION');
            
            $taxonomy = ($object_type === 'post') ? 'language' : 'term_language';
            
            $deleted = $wpdb->query($wpdb->prepare("
                DELETE tr FROM {$wpdb->term_relationships} tr
                JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
                WHERE tr.object_id = %d
                AND tt.taxonomy = %s
                AND t.slug = %s
            ", $object_id, $taxonomy, $wrong_code));
            
            if ($deleted === false) {
                throw new Exception('Failed to delete wrong language assignment');
            }
            
            if ($object_type === 'post') {
                pll_set_post_language($object_id, $correct_code);
            } else {
                pll_set_term_language($object_id, $correct_code);
            }
            
            $wpdb->query('COMMIT');
            
            if ($this->logger) {
                $this->logger->log("Fixed language for {$object_type} {$object_id}: {$wrong_code} -> {$correct_code}", 'info');
            }
            return true;
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            if ($this->logger) {
                $this->logger->log_error("Failed to fix language for {$object_type} {$object_id}", $e);
            }
            return false;
        }
    }
    
    /**
     * Enhanced excluded post types with BetterDocs support
     */
    public function get_excluded_post_types() {
        $excluded = [
            'attachment', 'oembed_cache', 'wp_global_styles', 'wpcode', 
            'acf-field-group', 'acf-field', 'acf-ui-options-page', 
            'shop_order', 'shop_order_refund', 'shop_coupon', 
            'attribute_group', 'omapi', 'user_request', 'wp_block',
            'wp_template', 'wp_template_part', 'wp_navigation', 'revision',
            'nav_menu_item', 'custom_css', 'customize_changeset', 'wp_pattern'
        ];
        
        // Note: 'docs' is NOT excluded - we want to process BetterDocs
        // Note: 'product' and 'product_variation' are NOT excluded - we want to process WooCommerce
        
        return apply_filters('wpml_to_polylang_fixer_excluded_post_types', $excluded);
    }
    
    /**
     * Enhanced excluded taxonomies ensuring BetterDocs taxonomies are processed
     */
    public function get_excluded_taxonomies() {
        $excluded = [
            'language', 'post_translations', 'term_translations', 'post_format',
            'term_language', 'wp_theme', 'wpcode_type', 'wpcode_location', 
            'wpcode_tags', 'elementor_library_type', 'product_type', 
            'product_visibility', 'wp_template_part_area', 'nav_menu', 
            'link_category', 'wp_pattern_category'
        ];
        
        // IMPORTANT: BetterDocs taxonomies are NOT excluded:
        // - doc_category
        // - doc_tag  
        // - knowledge_base
        // These will be processed by the taxonomy fixer
        
        // IMPORTANT: WooCommerce taxonomies are NOT excluded:
        // - product_cat
        // - product_tag
        // - product_shipping_class
        // - pa_* (product attributes)
        // These will be processed by the taxonomy fixer
        
        return apply_filters('wpml_to_polylang_fixer_excluded_taxonomies', $excluded);
    }
    
    /**
     * Enhanced migration statistics with BetterDocs support
     */
    public function get_migration_statistics() {
        global $wpdb;
        
        $stats = [
            'posts_with_language' => 0,
            'posts_without_language' => 0,
            'terms_with_language' => 0,
            'terms_without_language' => 0,
            'translation_groups' => 0,
            'problematic_codes' => 0,
            'post_types_breakdown' => []
        ];
        
        try {
            // Get post types to check (including BetterDocs)
            $post_types = get_post_types(['public' => true], 'names');
            $post_types = array_diff($post_types, $this->get_excluded_post_types());
            
            // Ensure BetterDocs is included if it exists
            if (post_type_exists('docs') && !in_array('docs', $post_types)) {
                $post_types[] = 'docs';
            }
            
            if (!empty($post_types)) {
                // Escape and quote post types for SQL
                $post_types_sql = array_map('esc_sql', $post_types);
                $post_types_str = "'" . implode("','", $post_types_sql) . "'";
                
                $total_posts = $wpdb->get_var("
                    SELECT COUNT(*) FROM {$wpdb->posts}
                    WHERE post_type IN ({$post_types_str})
                    AND post_status IN ('publish', 'draft', 'private')
                ");
                
                $posts_with_lang = $wpdb->get_var("
                    SELECT COUNT(DISTINCT p.ID)
                    FROM {$wpdb->posts} p
                    JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
                    JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                    WHERE p.post_type IN ({$post_types_str})
                    AND p.post_status IN ('publish', 'draft', 'private')
                    AND tt.taxonomy = 'language'
                ");
                
                $stats['posts_with_language'] = intval($posts_with_lang);
                $stats['posts_without_language'] = intval($total_posts) - intval($posts_with_lang);
                
                // Get breakdown by post type with enhanced BetterDocs detection
                $breakdown_results = $wpdb->get_results("
                    SELECT 
                        p.post_type,
                        COUNT(*) as total,
                        COUNT(tr.object_id) as with_language
                    FROM {$wpdb->posts} p
                    LEFT JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
                    LEFT JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = 'language'
                    WHERE p.post_type IN ({$post_types_str})
                    AND p.post_status IN ('publish', 'draft', 'private')
                    GROUP BY p.post_type
                ");
                
                foreach ($breakdown_results as $result) {
                    $stats['post_types_breakdown'][$result->post_type] = [
                        'total' => intval($result->total),
                        'with_language' => intval($result->with_language),
                        'without_language' => intval($result->total) - intval($result->with_language)
                    ];
                }
            }
            
            // Get taxonomies to check (including BetterDocs)
            $taxonomies = get_taxonomies(['public' => true], 'names');
            $taxonomies = array_diff($taxonomies, $this->get_excluded_taxonomies());
            
            // Ensure BetterDocs taxonomies are included if they exist
            $betterdocs_taxonomies = ['doc_category', 'doc_tag', 'knowledge_base'];
            foreach ($betterdocs_taxonomies as $bd_tax) {
                if (taxonomy_exists($bd_tax) && !in_array($bd_tax, $taxonomies)) {
                    $taxonomies[] = $bd_tax;
                }
            }
            
            if (!empty($taxonomies)) {
                // Escape and quote taxonomies for SQL
                $taxonomies_sql = array_map('esc_sql', $taxonomies);
                $taxonomies_str = "'" . implode("','", $taxonomies_sql) . "'";
                
                $total_terms = $wpdb->get_var("
                    SELECT COUNT(DISTINCT t.term_id)
                    FROM {$wpdb->terms} t
                    JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
                    WHERE tt.taxonomy IN ({$taxonomies_str})
                ");
                
                $terms_with_lang = $wpdb->get_var("
                    SELECT COUNT(DISTINCT t.term_id)
                    FROM {$wpdb->terms} t
                    JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
                    WHERE tt.taxonomy IN ({$taxonomies_str})
                    AND EXISTS (
                        SELECT 1 FROM {$wpdb->term_relationships} tr2
                        JOIN {$wpdb->term_taxonomy} tt2 ON tr2.term_taxonomy_id = tt2.term_taxonomy_id
                        WHERE tr2.object_id = t.term_id AND tt2.taxonomy = 'term_language'
                    )
                ");
                
                $stats['terms_with_language'] = intval($terms_with_lang);
                $stats['terms_without_language'] = intval($total_terms) - intval($terms_with_lang);
            }
            
            // Check for translation groups
            if ($this->wpml_tables_exist()) {
                $translation_groups = $wpdb->get_var("
                    SELECT COUNT(DISTINCT trid) FROM {$this->icl_table}
                    WHERE trid IN (
                        SELECT trid FROM {$this->icl_table}
                        GROUP BY trid
                        HAVING COUNT(*) > 1
                    )
                ");
                $stats['translation_groups'] = intval($translation_groups);
            }
            
            // Check for problematic codes
            $problematic_codes = $wpdb->get_var("
                SELECT COUNT(DISTINCT t.slug)
                FROM {$wpdb->terms} t
                JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
                WHERE tt.taxonomy IN ('language', 'term_language')
                AND t.slug LIKE 'pll_%'
            ");
            $stats['problematic_codes'] = intval($problematic_codes);
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->log_error("Error getting migration statistics", $e);
            }
            
            // Return basic stats structure even on error
            return $stats;
        }
        
        return $stats;
    }
    
    public function cleanup_orphaned_language_terms() {
        global $wpdb;
        
        $cleaned = 0;
        
        try {
            $cleaned += $wpdb->query("
                DELETE t, tt FROM {$wpdb->terms} t
                JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
                WHERE tt.taxonomy IN ('language', 'term_language')
                AND t.slug LIKE 'pll_%'
            ");
            
            if ($this->logger) {
                $this->logger->log("Cleaned up {$cleaned} orphaned language terms", 'info');
            }
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->log_error("Failed to cleanup orphaned language terms", $e);
            }
        }
        
        return $cleaned;
    }
    
    /**
     * NEW: Get BetterDocs statistics
     */
    public function get_betterdocs_statistics() {
        global $wpdb;
        
        $stats = [
            'active' => post_type_exists('docs'),
            'docs_total' => 0,
            'docs_with_language' => 0,
            'docs_without_language' => 0,
            'categories_total' => 0,
            'categories_with_language' => 0,
            'categories_without_language' => 0,
            'tags_total' => 0,
            'tags_with_language' => 0,
            'tags_without_language' => 0
        ];
        
        if (!$stats['active']) {
            return $stats;
        }
        
        try {
            // Docs statistics
            $stats['docs_total'] = $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->posts}
                WHERE post_type = 'docs'
                AND post_status IN ('publish', 'draft', 'private')
            ");
            
            $stats['docs_with_language'] = $wpdb->get_var("
                SELECT COUNT(DISTINCT p.ID)
                FROM {$wpdb->posts} p
                JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
                JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                WHERE p.post_type = 'docs'
                AND p.post_status IN ('publish', 'draft', 'private')
                AND tt.taxonomy = 'language'
            ");
            
            $stats['docs_without_language'] = $stats['docs_total'] - $stats['docs_with_language'];
            
            // BetterDocs taxonomies
            $bd_taxonomies = [
                'doc_category' => 'categories',
                'doc_tag' => 'tags'
            ];
            
            foreach ($bd_taxonomies as $taxonomy => $prefix) {
                if (taxonomy_exists($taxonomy)) {
                    $total_key = $prefix . '_total';
                    $with_key = $prefix . '_with_language';
                    $without_key = $prefix . '_without_language';
                    
                    $stats[$total_key] = $wpdb->get_var($wpdb->prepare("
                        SELECT COUNT(DISTINCT t.term_id)
                        FROM {$wpdb->terms} t
                        JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
                        WHERE tt.taxonomy = %s
                    ", $taxonomy));
                    
                    $stats[$with_key] = $wpdb->get_var($wpdb->prepare("
                        SELECT COUNT(DISTINCT t.term_id)
                        FROM {$wpdb->terms} t
                        JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
                        WHERE tt.taxonomy = %s
                        AND EXISTS (
                            SELECT 1 FROM {$wpdb->term_relationships} tr2
                            JOIN {$wpdb->term_taxonomy} tt2 ON tr2.term_taxonomy_id = tt2.term_taxonomy_id
                            WHERE tr2.object_id = t.term_id AND tt2.taxonomy = 'term_language'
                        )
                    ", $taxonomy));
                    
                    $stats[$without_key] = $stats[$total_key] - $stats[$with_key];
                }
            }
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->log_error("Error getting BetterDocs statistics", $e);
            }
        }
        
        return $stats;
    }

    /**
     * Ensure term_language buckets exist for all configured languages
     * Polylang needs a twin bucket in term_language for every language term
     *
     * @return int Number of buckets created
     */
    public function ensure_term_language_buckets() {
        global $wpdb;

        // DEBUG START
        if ($this->debug_collector) {
            $this->debug_collector->push_context('Ensuring term language buckets');
        }

        // First, find missing buckets
        $find_query = "
            SELECT t.term_id, t.name
            FROM {$wpdb->terms} t
            JOIN {$wpdb->term_taxonomy} tl ON tl.term_id=t.term_id AND tl.taxonomy='language'
            LEFT JOIN {$wpdb->term_taxonomy} tl2 ON tl2.term_id=t.term_id AND tl2.taxonomy='term_language'
            WHERE tl2.term_id IS NULL
        ";

        $missing = $wpdb->get_results($find_query);

        if ($this->debug_collector) {
            $this->debug_collector->log_query($find_query, 'Finding terms without language buckets', $missing);
            $this->debug_collector->log_operation('scan_missing_buckets',
                sprintf('Found %d terms without language buckets', count($missing)),
                'info',
                ['term_ids' => wp_list_pluck($missing, 'term_id')]
            );
        }
        // DEBUG END

        $created = $wpdb->query("
            INSERT IGNORE INTO {$wpdb->term_taxonomy} (term_id, taxonomy, description, parent, count)
            SELECT t.term_id, 'term_language', '', 0, 0
            FROM {$wpdb->terms} t
            JOIN {$wpdb->term_taxonomy} tl ON tl.term_id=t.term_id AND tl.taxonomy='language'
            LEFT JOIN {$wpdb->term_taxonomy} tl2 ON tl2.term_id=t.term_id AND tl2.taxonomy='term_language'
            WHERE tl2.term_id IS NULL
        ");

        // DEBUG START
        if ($this->debug_collector) {
            $insert_query = $wpdb->last_query;
            $this->debug_collector->log_query($insert_query, 'Creating missing language buckets', $created);
            $this->debug_collector->log_operation('create_buckets',
                sprintf('Created %d language buckets', $created),
                $created > 0 ? 'success' : 'info'
            );
            $this->debug_collector->pop_context();
        }
        // DEBUG END

        if ($this->logger) {
            $this->logger->log("Created {$created} missing term_language buckets", 'info');
        }

        return $created;
    }

    /**
     * Fix posts batch with comprehensive language assignment
     * Uses WPML data as source, canonicalizes codes, ensures languages exist
     *
     * @param array $ids Post IDs to fix
     * @return array Results with processed and fixed counts
     */
    public function fix_posts_batch($ids) {
        global $wpdb;

        $results = [
            'processed' => 0,
            'fixed' => 0,
            'errors' => 0,
            'details' => []
        ];

        // Initialize language converter if not already done
        if (!isset($this->lang_converter)) {
            $this->lang_converter = new WPML_To_Polylang_Fixer_Language_Converter();
        }

        foreach ($ids as $id) {
            $results['processed']++;

            try {
                // Get current PLL language
                $cur = function_exists('pll_get_post_language') ? pll_get_post_language($id) : null;

                // Get WPML source language
                $type = get_post_type($id);
                $wpml = $wpdb->get_var($wpdb->prepare(
                    "SELECT language_code FROM {$this->icl_table}
                     WHERE element_id=%d AND element_type=%s LIMIT 1",
                    $id, 'post_'.$type
                ));

                // Canonicalize and ensure language exists
                $slug = $this->lang_converter->canonicalize_slug($wpml ?: $cur);
                $slug = $this->lang_converter->ensure_pll_language_exists($slug);

                if ($slug && $slug !== $cur) {
                    pll_set_post_language($id, $slug);
                    clean_post_cache($id);
                    $results['fixed']++;

                    if ($this->logger) {
                        $this->logger->log("Post {$id} → {$slug} (from {$wpml})", 'debug');
                    }
                }
            } catch (Exception $e) {
                $results['errors']++;
                $results['details'][] = "Error fixing post {$id}: " . $e->getMessage();

                if ($this->logger) {
                    $this->logger->log_error("Failed to fix post {$id}", $e);
                }
            }
        }

        return $results;
    }

    /**
     * Fix terms batch with comprehensive language assignment
     * Uses WPML data as source by term_taxonomy_id
     *
     * @param array $terms Array of term objects with term_id, taxonomy, term_taxonomy_id
     * @return array Results with processed and fixed counts
     */
    public function fix_terms_batch($terms) {
        global $wpdb;

        $results = [
            'processed' => 0,
            'fixed' => 0,
            'errors' => 0,
            'details' => []
        ];

        // Initialize language converter if not already done
        if (!isset($this->lang_converter)) {
            $this->lang_converter = new WPML_To_Polylang_Fixer_Language_Converter();
        }

        foreach ($terms as $row) {
            $term_id = (int)$row->term_id;
            $taxonomy = $row->taxonomy;
            $tt_id = (int)$row->term_taxonomy_id;

            // Skip non-translatable/system taxonomies
            if ($this->is_excluded_taxonomy($taxonomy)) {
                continue;
            }

            $results['processed']++;

            try {
                // Get current PLL language
                $cur = function_exists('pll_get_term_language') ? pll_get_term_language($term_id) : null;

                // Get WPML source language
                $wpml = $wpdb->get_var($wpdb->prepare(
                    "SELECT language_code FROM {$this->icl_table}
                     WHERE element_id=%d AND element_type=%s LIMIT 1",
                    $tt_id, 'tax_'.$taxonomy
                ));

                // Canonicalize and ensure language exists
                $slug = $this->lang_converter->canonicalize_slug($wpml ?: $cur ?: pll_default_language());
                $slug = $this->lang_converter->ensure_pll_language_exists($slug);

                if (!$slug) continue;

                if ($slug !== $cur) {
                    pll_set_term_language($term_id, $slug);
                    clean_term_cache($term_id, $taxonomy);
                    $results['fixed']++;

                    if ($this->logger) {
                        $this->logger->log("Term {$term_id} ({$taxonomy}) → {$slug}", 'debug');
                    }
                }
            } catch (Exception $e) {
                $results['errors']++;
                $results['details'][] = "Error fixing term {$term_id}: " . $e->getMessage();

                if ($this->logger) {
                    $this->logger->log_error("Failed to fix term {$term_id}", $e);
                }
            }
        }

        return $results;
    }

    /**
     * Check if taxonomy should be excluded from processing
     *
     * @param string $taxonomy Taxonomy name
     * @return bool True if should be excluded
     */
    private function is_excluded_taxonomy($taxonomy) {
        $excluded = $this->get_excluded_taxonomies();
        return in_array($taxonomy, $excluded, true);
    }

    /**
     * Check if Polylang is active.
     */
    public function has_polylang(): bool {
        return function_exists('pll_languages_list');
    }

    /**
     * Check if WPML database tables exist.
     */
    public function has_wpml_tables(): bool {
        global $wpdb;

        $table_like = $wpdb->esc_like($wpdb->prefix . 'icl_translations');
        $result = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_like));

        return !empty($result);
    }

    /**
     * Check if WooCommerce is active.
     */
    public function has_woocommerce(): bool {
        return class_exists('WooCommerce') || function_exists('wc') || post_type_exists('product');
    }

    /**
     * Check if BetterDocs is active.
     */
    public function has_betterdocs(): bool {
        return post_type_exists('docs') || taxonomy_exists('knowledge_base');
    }

    /**
     * Check if Advanced Custom Fields is active.
     */
    public function has_acf(): bool {
        return function_exists('acf_get_field_groups') || post_type_exists('acf-field-group') || post_type_exists('acf-field');
    }

    /**
     * Determine if ACF objects have translation data (WPML or Polylang).
     */
    public function acf_has_translation_data(): bool {
        global $wpdb;

        $acf_post_types = [];
        if (post_type_exists('acf-field')) {
            $acf_post_types[] = 'acf-field';
        }
        if (post_type_exists('acf-field-group')) {
            $acf_post_types[] = 'acf-field-group';
        }

        if (empty($acf_post_types)) {
            return false;
        }

        // Check WPML translation records
        if ($this->has_wpml_tables()) {
            $wpml_exists = $wpdb->get_var(
                "SELECT 1 FROM {$this->icl_table}
                 WHERE element_type IN ('post_acf-field','post_acf-field-group')
                 LIMIT 1"
            );

            if (!empty($wpml_exists)) {
                return true;
            }
        }

        // Check Polylang relationships
        if ($this->has_polylang()) {
            $post_types_sql = "'" . implode("','", array_map('esc_sql', $acf_post_types)) . "'";

            $pll_exists = $wpdb->get_var(
                "SELECT 1
                 FROM {$wpdb->posts} p
                 JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
                 JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                 WHERE p.post_type IN ({$post_types_sql})
                 AND p.post_status IN ('publish','draft','private')
                 AND tt.taxonomy = 'language'
                 LIMIT 1"
            );

            if (!empty($pll_exists)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get Polylang languages list and default language slug.
     */
    public function get_pll_languages(): array {
        $languages = [
            'list' => [],
            'default' => null
        ];

        if ($this->has_polylang()) {
            $languages['list'] = pll_languages_list(['fields' => 'slug']);
            $languages['default'] = pll_default_language();
        }

        return $languages;
    }

    /**
     * Fix BetterDocs content languages specifically
     *
     * @param string $type 'posts' or 'terms'
     * @param int $batch_size Batch size for processing
     * @param int $offset Offset for batching
     * @return array Results array
     */
    public function fix_betterdocs_batch($type = 'posts', $batch_size = 50, $offset = 0) {
        global $wpdb;

        $results = [
            'processed' => 0,
            'fixed' => 0,
            'total' => 0,
            'continue' => false,
            'next_offset' => 0
        ];

        if (!$this->has_betterdocs()) {
            return $results;
        }

        if ($type === 'posts') {
            // Fix BetterDocs posts (docs and betterdocs_faq)
            $post_types = ['docs', 'betterdocs_faq'];
            $post_types_sql = "'" . implode("','", array_map('esc_sql', $post_types)) . "'";

            // Get total count
            $results['total'] = $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->posts}
                WHERE post_type IN ({$post_types_sql})
                AND post_status IN ('publish', 'draft', 'private')
            ");

            // Get batch of posts
            $posts = $wpdb->get_col($wpdb->prepare("
                SELECT ID FROM {$wpdb->posts}
                WHERE post_type IN ({$post_types_sql})
                AND post_status IN ('publish', 'draft', 'private')
                ORDER BY ID
                LIMIT %d OFFSET %d
            ", $batch_size, $offset));

            if (!empty($posts)) {
                $batch_results = $this->fix_posts_batch($posts);
                $results['processed'] = $batch_results['processed'];
                $results['fixed'] = $batch_results['fixed'];
            }
        } else {
            // Fix BetterDocs terms
            $taxonomies = ['doc_category', 'doc_tag', 'knowledge_base', 'betterdocs_faq_category'];
            $taxonomies_sql = "'" . implode("','", array_map('esc_sql', $taxonomies)) . "'";

            // Get total count
            $results['total'] = $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->term_taxonomy}
                WHERE taxonomy IN ({$taxonomies_sql})
            ");

            // Get batch of terms
            $terms = $wpdb->get_results($wpdb->prepare("
                SELECT tt.term_taxonomy_id, tt.term_id, tt.taxonomy
                FROM {$wpdb->term_taxonomy} tt
                WHERE tt.taxonomy IN ({$taxonomies_sql})
                ORDER BY tt.term_taxonomy_id
                LIMIT %d OFFSET %d
            ", $batch_size, $offset));

            if (!empty($terms)) {
                $batch_results = $this->fix_terms_batch($terms);
                $results['processed'] = $batch_results['processed'];
                $results['fixed'] = $batch_results['fixed'];
            }
        }

        // Check if we should continue
        $results['continue'] = ($offset + $results['processed']) < $results['total'];
        $results['next_offset'] = $offset + $batch_size;

        return $results;
    }

    /**
     * Fix WooCommerce product attributes (pa_*) languages
     *
     * @param int $batch_size Batch size for processing
     * @param int $offset Offset for batching
     * @return array Results array
     */
    public function fix_woocommerce_attributes_batch($batch_size = 50, $offset = 0) {
        global $wpdb;

        $results = [
            'processed' => 0,
            'fixed' => 0,
            'total' => 0,
            'continue' => false,
            'next_offset' => 0,
            'attributes' => []
        ];

        if (!$this->has_woocommerce()) {
            return $results;
        }

        // Get all pa_* taxonomies
        $attributes = $wpdb->get_col("
            SELECT DISTINCT taxonomy FROM {$wpdb->term_taxonomy}
            WHERE taxonomy LIKE 'pa_%'
        ");

        if (empty($attributes)) {
            return $results;
        }

        $results['attributes'] = $attributes;
        $attributes_sql = "'" . implode("','", array_map('esc_sql', $attributes)) . "'";

        // Get total count
        $results['total'] = $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->term_taxonomy}
            WHERE taxonomy IN ({$attributes_sql})
        ");

        // Get batch of attribute terms
        $terms = $wpdb->get_results($wpdb->prepare("
            SELECT tt.term_taxonomy_id, tt.term_id, tt.taxonomy
            FROM {$wpdb->term_taxonomy} tt
            WHERE tt.taxonomy IN ({$attributes_sql})
            ORDER BY tt.term_taxonomy_id
            LIMIT %d OFFSET %d
        ", $batch_size, $offset));

        if (!empty($terms)) {
            $batch_results = $this->fix_terms_batch($terms);
            $results['processed'] = $batch_results['processed'];
            $results['fixed'] = $batch_results['fixed'];
        }

        // Check if we should continue
        $results['continue'] = ($offset + $results['processed']) < $results['total'];
        $results['next_offset'] = $offset + $batch_size;

        return $results;
    }

    /**
     * Get comprehensive verification results
     * Includes all the SQL queries from the playbook
     *
     * @return array Verification results
     */
    public function get_comprehensive_verification() {
        global $wpdb;

        $results = [
            'languages_configured' => [],
            'term_language_buckets' => 0,
            'posts_without_pll' => 0,
            'terms_without_pll' => 0,
            'betterdocs_issues' => [
                'faq_posts_without_pll' => 0,
                'faq_categories_without_pll' => 0
            ],
            'terms_per_language' => [],
            'recommendations' => []
        ];

        try {
            // Languages configured in Polylang
            $languages = $wpdb->get_results("
                SELECT t.term_id, t.slug, t.name
                FROM {$wpdb->terms} t
                JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
                WHERE tt.taxonomy='language'
                ORDER BY t.slug
            ");

            foreach ($languages as $lang) {
                $results['languages_configured'][] = [
                    'id' => $lang->term_id,
                    'slug' => $lang->slug,
                    'name' => $lang->name
                ];
            }

            // Count term_language buckets
            $results['term_language_buckets'] = $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->term_taxonomy}
                WHERE taxonomy='term_language'
            ");

            // Posts without Polylang language
            $results['posts_without_pll'] = $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->posts} p
                WHERE p.post_status IN ('publish','draft','private')
                AND NOT EXISTS (
                    SELECT 1
                    FROM {$wpdb->term_relationships} tr
                    JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id=tr.term_taxonomy_id AND tt.taxonomy='language'
                    WHERE tr.object_id=p.ID
                )
            ");

            // Terms without Polylang term language
            $results['terms_without_pll'] = $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->terms} t
                JOIN {$wpdb->term_taxonomy} tt ON tt.term_id=t.term_id
                WHERE NOT EXISTS (
                    SELECT 1
                    FROM {$wpdb->term_relationships} tr
                    JOIN {$wpdb->term_taxonomy} tl ON tl.term_taxonomy_id=tr.term_taxonomy_id AND tl.taxonomy='term_language'
                    WHERE tr.object_id=t.term_id
                )
            ");

            // BetterDocs specific checks
            if ($this->has_betterdocs()) {
                // BetterDocs FAQ posts without PLL
                $results['betterdocs_issues']['faq_posts_without_pll'] = $wpdb->get_var("
                    SELECT COUNT(*) FROM {$wpdb->posts} p
                    LEFT JOIN {$wpdb->term_relationships} tr ON tr.object_id=p.ID
                    LEFT JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id=tr.term_taxonomy_id AND tt.taxonomy='language'
                    WHERE p.post_type='betterdocs_faq' AND tt.term_taxonomy_id IS NULL
                ");

                // BetterDocs FAQ categories without term_language
                $results['betterdocs_issues']['faq_categories_without_pll'] = $wpdb->get_var("
                    SELECT COUNT(*) FROM {$wpdb->term_taxonomy} tt
                    WHERE tt.taxonomy='betterdocs_faq_category'
                    AND NOT EXISTS (
                        SELECT 1
                        FROM {$wpdb->term_relationships} tr
                        JOIN {$wpdb->term_taxonomy} tl ON tl.term_taxonomy_id=tr.term_taxonomy_id AND tl.taxonomy='term_language'
                        WHERE tr.object_id=tt.term_id
                    )
                ");
            }

            // Terms per language
            $terms_per_lang = $wpdb->get_results("
                SELECT lang.slug, COUNT(*) AS terms
                FROM {$wpdb->terms} t
                JOIN {$wpdb->term_relationships} tr ON tr.object_id=t.term_id
                JOIN {$wpdb->term_taxonomy} ttl ON ttl.term_taxonomy_id=tr.term_taxonomy_id AND ttl.taxonomy='term_language'
                JOIN {$wpdb->terms} lang ON lang.term_id=ttl.term_id
                GROUP BY lang.slug
                ORDER BY terms DESC
            ");

            foreach ($terms_per_lang as $tpl) {
                $results['terms_per_language'][$tpl->slug] = (int)$tpl->terms;
            }

            // Generate recommendations
            if ($results['posts_without_pll'] > 0) {
                $results['recommendations'][] = "Fix {$results['posts_without_pll']} posts without language assignments";
            }
            if ($results['terms_without_pll'] > 0) {
                $results['recommendations'][] = "Fix {$results['terms_without_pll']} terms without language assignments";
            }
            if ($results['betterdocs_issues']['faq_posts_without_pll'] > 0) {
                $results['recommendations'][] = "Fix {$results['betterdocs_issues']['faq_posts_without_pll']} BetterDocs FAQ posts";
            }
            if ($results['betterdocs_issues']['faq_categories_without_pll'] > 0) {
                $results['recommendations'][] = "Fix {$results['betterdocs_issues']['faq_categories_without_pll']} BetterDocs FAQ categories";
            }

        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->log_error("Error in comprehensive verification", $e);
            }
        }

        return $results;
    }
}
