<?php
/**
 * Database Helper Class
 * 
 * Handles database operations for the WPML to Polylang Migration Fixer plugin
 * 
 * @package WPML_To_Polylang_Migration_Fixer
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPML_To_Polylang_Fixer_Database_Helper {
    
    private $logger;
    private $icl_table;
    
    public function __construct() {
        global $wpdb;
        $this->icl_table = $wpdb->prefix . 'icl_translations';
        
        // Logger will be initialized if class exists
        if (class_exists('WPML_To_Polylang_Fixer_Debug_Logger')) {
            $this->logger = new WPML_To_Polylang_Fixer_Debug_Logger();
        }
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
    
    public function get_excluded_post_types() {
        $excluded = [
            'attachment', 'oembed_cache', 'wp_global_styles', 'wpcode', 
            'acf-field-group', 'acf-field', 'acf-ui-options-page', 
            'shop_order', 'shop_order_refund', 'shop_coupon', 
            'attribute_group', 'omapi', 'user_request', 'wp_block',
            'wp_template', 'wp_template_part', 'wp_navigation', 'revision',
            'nav_menu_item', 'custom_css', 'customize_changeset', 'wp_pattern'
        ];
        
        return apply_filters('wpml_to_polylang_fixer_excluded_post_types', $excluded);
    }
    
    public function get_excluded_taxonomies() {
        $excluded = [
            'language', 'post_translations', 'term_translations', 'post_format',
            'term_language', 'wp_theme', 'wpcode_type', 'wpcode_location', 
            'wpcode_tags', 'elementor_library_type', 'product_type', 
            'product_visibility', 'wp_template_part_area', 'nav_menu', 
            'link_category', 'wp_pattern_category'
        ];
        
        return apply_filters('wpml_to_polylang_fixer_excluded_taxonomies', $excluded);
    }
    
    /**
     * Get basic migration statistics
     * Used by basic verification fallback
     */
    public function get_migration_statistics() {
        global $wpdb;
        
        $stats = [
            'posts_with_language' => 0,
            'posts_without_language' => 0,
            'terms_with_language' => 0,
            'terms_without_language' => 0,
            'translation_groups' => 0,
            'post_types_breakdown' => []
        ];
        
        try {
            // Get post types to check
            $post_types = get_post_types(['public' => true], 'names');
            $post_types = array_diff($post_types, $this->get_excluded_post_types());
            
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
            }
            
            // Get taxonomies to check
            $taxonomies = get_taxonomies(['public' => true], 'names');
            $taxonomies = array_diff($taxonomies, $this->get_excluded_taxonomies());
            
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
}