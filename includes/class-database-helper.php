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
        $this->logger = new WPML_To_Polylang_Fixer_Debug_Logger();
        $this->icl_table = $wpdb->prefix . 'icl_translations';
    }
    
    public function wpml_tables_exist() {
        global $wpdb;
        return $wpdb->get_var("SHOW TABLES LIKE '{$this->icl_table}'") == $this->icl_table;
    }
    
    public function get_posts_without_language($post_types = [], $limit = 100, $offset = 0) {
        global $wpdb;
        
        if (empty($post_types)) {
            $post_types = get_post_types(['public' => true], 'names');
            unset($post_types['attachment']);
        }
        
        $exclude_types = $this->get_excluded_post_types();
        $post_types = array_diff($post_types, $exclude_types);
        
        if (empty($post_types)) {
            return [];
        }
        
        $post_types_str = "'" . implode("','", esc_sql($post_types)) . "'";
        
        $query = $wpdb->prepare("
            SELECT p.ID, p.post_type, p.post_title
            FROM {$wpdb->posts} p
            WHERE p.post_type IN ($post_types_str)
            AND p.post_status IN ('publish', 'draft', 'private')
            AND NOT EXISTS (
                SELECT 1 FROM {$wpdb->term_relationships} tr
                JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                WHERE tr.object_id = p.ID AND tt.taxonomy = 'language'
            )
            ORDER BY p.ID
            LIMIT %d OFFSET %d
        ", $limit, $offset);
        
        return $wpdb->get_results($query);
    }
    
    public function get_terms_without_language($taxonomies = [], $limit = 100, $offset = 0) {
        global $wpdb;
        
        if (empty($taxonomies)) {
            $taxonomies = get_taxonomies(['public' => true], 'names');
        }
        
        $exclude_taxonomies = $this->get_excluded_taxonomies();
        $taxonomies = array_diff($taxonomies, $exclude_taxonomies);
        
        if (empty($taxonomies)) {
            return [];
        }
        
        $taxonomies_str = "'" . implode("','", esc_sql($taxonomies)) . "'";
        
        $query = $wpdb->prepare("
            SELECT t.term_id, tt.taxonomy, t.name
            FROM {$wpdb->terms} t
            JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
            WHERE tt.taxonomy IN ($taxonomies_str)
            AND NOT EXISTS (
                SELECT 1 FROM {$wpdb->term_relationships} tr2
                JOIN {$wpdb->term_taxonomy} tt2 ON tr2.term_taxonomy_id = tt2.term_taxonomy_id
                WHERE tr2.object_id = t.term_id AND tt2.taxonomy = 'term_language'
            )
            ORDER BY t.term_id
            LIMIT %d OFFSET %d
        ", $limit, $offset);
        
        return $wpdb->get_results($query);
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
            
            $this->logger->log("Fixed language for {$object_type} {$object_id}: {$wrong_code} -> {$correct_code}", 'info');
            return true;
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            $this->logger->log_error("Failed to fix language for {$object_type} {$object_id}", $e);
            return false;
        }
    }
    
    public function get_excluded_post_types() {
        $excluded = [
            'oembed_cache', 'wp_global_styles', 'wpcode', 'acf-field-group',
            'acf-field', 'acf-ui-options-page', 'shop_order', 'shop_order_refund',
            'shop_coupon', 'attribute_group', 'omapi', 'user_request', 'wp_block',
            'wp_template', 'wp_template_part', 'wp_navigation', 'revision',
            'nav_menu_item', 'custom_css', 'customize_changeset', 'wp_pattern'
        ];
        
        return apply_filters('wpml_to_polylang_fixer_excluded_post_types', $excluded);
    }
    
    public function get_excluded_taxonomies() {
        $excluded = [
            'language', 'post_translations', 'term_translations', 'post_format',
            'wp_theme', 'wpcode_type', 'wpcode_location', 'wpcode_tags',
            'elementor_library_type', 'product_type', 'product_visibility',
            'wp_template_part_area', 'nav_menu', 'link_category', 'wp_pattern_category'
        ];
        
        return apply_filters('wpml_to_polylang_fixer_excluded_taxonomies', $excluded);
    }
    
    public function get_migration_statistics() {
        global $wpdb;
        
        $stats = [
            'posts_with_language' => 0,
            'posts_without_language' => 0,
            'terms_with_language' => 0,
            'terms_without_language' => 0,
            'translation_groups' => 0,
            'problematic_codes' => 0
        ];
        
        $post_types = get_post_types(['public' => true], 'names');
        unset($post_types['attachment']);
        $post_types = array_diff($post_types, $this->get_excluded_post_types());
        
        if (!empty($post_types)) {
            $post_types_str = "'" . implode("','", esc_sql($post_types)) . "'";
            
            $total_posts = $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->posts}
                WHERE post_type IN ($post_types_str)
                AND post_status IN ('publish', 'draft', 'private')
            ");
            
            $posts_with_lang = $wpdb->get_var("
                SELECT COUNT(DISTINCT p.ID)
                FROM {$wpdb->posts} p
                JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
                JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                WHERE p.post_type IN ($post_types_str)
                AND p.post_status IN ('publish', 'draft', 'private')
                AND tt.taxonomy = 'language'
            ");
            
            $stats['posts_with_language'] = intval($posts_with_lang);
            $stats['posts_without_language'] = intval($total_posts) - intval($posts_with_lang);
        }
        
        $taxonomies = get_taxonomies(['public' => true], 'names');
        $taxonomies = array_diff($taxonomies, $this->get_excluded_taxonomies());
        
        if (!empty($taxonomies)) {
            $taxonomies_str = "'" . implode("','", esc_sql($taxonomies)) . "'";
            
            $total_terms = $wpdb->get_var("
                SELECT COUNT(DISTINCT t.term_id)
                FROM {$wpdb->terms} t
                JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
                WHERE tt.taxonomy IN ($taxonomies_str)
            ");
            
            $terms_with_lang = $wpdb->get_var("
                SELECT COUNT(DISTINCT t.term_id)
                FROM {$wpdb->terms} t
                JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
                WHERE tt.taxonomy IN ($taxonomies_str)
                AND EXISTS (
                    SELECT 1 FROM {$wpdb->term_relationships} tr2
                    JOIN {$wpdb->term_taxonomy} tt2 ON tr2.term_taxonomy_id = tt2.term_taxonomy_id
                    WHERE tr2.object_id = t.term_id AND tt2.taxonomy = 'term_language'
                )
            ");
            
            $stats['terms_with_language'] = intval($terms_with_lang);
            $stats['terms_without_language'] = intval($total_terms) - intval($terms_with_lang);
        }
        
        if ($this->wpml_tables_exist()) {
            $stats['translation_groups'] = $wpdb->get_var("
                SELECT COUNT(DISTINCT trid) FROM {$this->icl_table}
                WHERE trid IN (
                    SELECT trid FROM {$this->icl_table}
                    GROUP BY trid
                    HAVING COUNT(*) > 1
                )
            ");
        }
        
        $stats['problematic_codes'] = $wpdb->get_var("
            SELECT COUNT(DISTINCT t.slug)
            FROM {$wpdb->terms} t
            JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
            WHERE tt.taxonomy IN ('language', 'term_language')
            AND t.slug LIKE 'pll_%'
        ");
        
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
            
            $this->logger->log("Cleaned up {$cleaned} orphaned language terms", 'info');
            
        } catch (Exception $e) {
            $this->logger->log_error("Failed to cleanup orphaned language terms", $e);
        }
        
        return $cleaned;
    }
}
