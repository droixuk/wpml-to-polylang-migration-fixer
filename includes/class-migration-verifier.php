<?php
/**
 * Enhanced Migration Verifier Class
 * 
 * Comprehensive verification of WPML to Polylang migration
 * Enhanced with BetterDocs support
 * 
 * @package WPML_To_Polylang_Migration_Fixer
 * @since 1.1.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPML_To_Polylang_Migration_Verifier {
    
    private $wpdb;
    private $logger;
    private $icl_table;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->icl_table = $wpdb->prefix . 'icl_translations';
        
        if (class_exists('WPML_To_Polylang_Fixer_Debug_Logger')) {
            $this->logger = new WPML_To_Polylang_Fixer_Debug_Logger();
        }
    }
    
    /**
     * Comprehensive migration verification
     */
    public function verify_migration() {
        $results = [
            'overall_status' => 'unknown',
            'languages' => $this->verify_languages(),
            'posts' => $this->verify_posts(),
            'terms' => $this->verify_terms(),
            'translation_groups' => $this->verify_translation_groups(),
            'menus' => $this->verify_menus(),
            'strings' => $this->verify_strings(),
            'options' => $this->verify_options(),
            'betterdocs' => $this->verify_betterdocs(),  // NEW: BetterDocs verification
            'wpml_data' => $this->check_wpml_data_exists()
        ];
        
        // Calculate overall status
        $critical_issues = 0;
        foreach ($results as $key => $result) {
            if ($key !== 'overall_status' && isset($result['critical_issues'])) {
                $critical_issues += $result['critical_issues'];
            }
        }
        
        $results['overall_status'] = $critical_issues === 0 ? 'success' : 'issues_found';
        $results['total_critical_issues'] = $critical_issues;
        
        return $results;
    }
    
    /**
     * NEW: Verify BetterDocs migration
     */
    private function verify_betterdocs() {
        $verification = [
            'status' => 'checking',
            'issues' => [],
            'critical_issues' => 0,
            'betterdocs_active' => false,
            'docs_with_language' => 0,
            'docs_without_language' => 0,
            'total_docs' => 0,
            'doc_categories_with_language' => 0,
            'doc_categories_without_language' => 0,
            'total_doc_categories' => 0,
            'doc_tags_with_language' => 0,
            'doc_tags_without_language' => 0,
            'total_doc_tags' => 0,
            'knowledge_bases_with_language' => 0,
            'knowledge_bases_without_language' => 0,
            'total_knowledge_bases' => 0,
            'wpml_docs_trids' => 0,
            'pll_docs_groups' => 0,
            'wpml_doc_term_trids' => 0,
            'pll_doc_term_groups' => 0
        ];
        
        try {
            // Check if BetterDocs is active (check for docs post type)
            $verification['betterdocs_active'] = post_type_exists('docs');
            
            if (!$verification['betterdocs_active']) {
                $verification['status'] = 'not_applicable';
                return $verification;
            }
            
            // Verify docs post type
            $this->verify_betterdocs_posts($verification);
            
            // Verify BetterDocs taxonomies
            $this->verify_betterdocs_taxonomies($verification);
            
            // Verify BetterDocs translation groups (if WPML data exists)
            if ($this->wpml_tables_exist()) {
                $this->verify_betterdocs_translation_groups($verification);
            }
            
            // Determine overall status
            $verification['status'] = $verification['critical_issues'] === 0 ? 'success' : 'issues';
            
            if ($this->logger) {
                $this->logger->log("BetterDocs verification completed - Issues: {$verification['critical_issues']}", 'info');
            }
            
        } catch (Exception $e) {
            $verification['status'] = 'error';
            $verification['issues'][] = 'Error checking BetterDocs: ' . $e->getMessage();
            if ($this->logger) {
                $this->logger->log_error('BetterDocs verification failed', $e);
            }
        }
        
        return $verification;
    }
    
    /**
     * Verify BetterDocs posts (docs post type)
     */
    private function verify_betterdocs_posts(&$verification) {
        // Get total docs count
        $verification['total_docs'] = $this->wpdb->get_var("
            SELECT COUNT(*)
            FROM {$this->wpdb->posts}
            WHERE post_type = 'docs'
            AND post_status IN ('publish', 'draft', 'private')
        ");
        
        // Get docs with language assignment
        $verification['docs_with_language'] = $this->wpdb->get_var("
            SELECT COUNT(DISTINCT p.ID)
            FROM {$this->wpdb->posts} p
            JOIN {$this->wpdb->term_relationships} tr ON p.ID = tr.object_id
            JOIN {$this->wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            WHERE p.post_type = 'docs'
            AND p.post_status IN ('publish', 'draft', 'private')
            AND tt.taxonomy = 'language'
        ");
        
        $verification['docs_without_language'] = $verification['total_docs'] - $verification['docs_with_language'];
        
        if ($verification['docs_without_language'] > 0) {
            $verification['critical_issues'] += $verification['docs_without_language'];
            $verification['issues'][] = "Found {$verification['docs_without_language']} docs without language assignment";
        }
    }
    
    /**
     * Verify BetterDocs taxonomies
     */
    private function verify_betterdocs_taxonomies(&$verification) {
        $bd_taxonomies = [
            'doc_category' => 'doc_categories',
            'doc_tag' => 'doc_tags', 
            'knowledge_base' => 'knowledge_bases'
        ];
        
        foreach ($bd_taxonomies as $taxonomy => $field_prefix) {
            // Check if taxonomy exists
            if (!taxonomy_exists($taxonomy)) {
                continue;
            }
            
            // Get total terms count
            $total_field = 'total_' . $field_prefix;
            $verification[$total_field] = $this->wpdb->get_var($this->wpdb->prepare("
                SELECT COUNT(DISTINCT t.term_id)
                FROM {$this->wpdb->terms} t
                JOIN {$this->wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
                WHERE tt.taxonomy = %s
            ", $taxonomy));
            
            // Get terms with language assignment
            $with_lang_field = $field_prefix . '_with_language';
            $verification[$with_lang_field] = $this->wpdb->get_var($this->wpdb->prepare("
                SELECT COUNT(DISTINCT t.term_id)
                FROM {$this->wpdb->terms} t
                JOIN {$this->wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
                WHERE tt.taxonomy = %s
                AND EXISTS (
                    SELECT 1 FROM {$this->wpdb->term_relationships} tr2
                    JOIN {$this->wpdb->term_taxonomy} tt2 ON tr2.term_taxonomy_id = tt2.term_taxonomy_id
                    WHERE tr2.object_id = t.term_id AND tt2.taxonomy = 'term_language'
                )
            ", $taxonomy));
            
            $without_lang_field = $field_prefix . '_without_language';
            $verification[$without_lang_field] = $verification[$total_field] - $verification[$with_lang_field];
            
            if ($verification[$without_lang_field] > 0) {
                $verification['critical_issues'] += $verification[$without_lang_field];
                $verification['issues'][] = "Found {$verification[$without_lang_field]} {$taxonomy} terms without language assignment";
            }
        }
    }
    
    /**
     * Verify BetterDocs translation groups
     */
    private function verify_betterdocs_translation_groups(&$verification) {
        // Get WPML docs translation groups
        $verification['wpml_docs_trids'] = $this->wpdb->get_var("
            SELECT COUNT(DISTINCT trid)
            FROM {$this->icl_table}
            WHERE element_type = 'post_docs'
            AND trid IN (
                SELECT trid FROM {$this->icl_table}
                WHERE element_type = 'post_docs'
                GROUP BY trid HAVING COUNT(*) > 1
            )
        ");
        
        // Get PLL docs translation groups
        $verification['pll_docs_groups'] = $this->wpdb->get_var("
            SELECT COUNT(*)
            FROM {$this->wpdb->term_taxonomy} tt
            JOIN {$this->wpdb->terms} t ON tt.term_id = t.term_id
            WHERE tt.taxonomy = 'post_translations'
            AND t.slug LIKE 'pll_wpml_%'
            AND tt.description LIKE '%\"docs\"%'
        ");
        
        // Get WPML BetterDocs term translation groups
        $verification['wpml_doc_term_trids'] = $this->wpdb->get_var("
            SELECT COUNT(DISTINCT trid)
            FROM {$this->icl_table}
            WHERE element_type IN ('tax_doc_category', 'tax_doc_tag', 'tax_knowledge_base')
            AND trid IN (
                SELECT trid FROM {$this->icl_table}
                WHERE element_type IN ('tax_doc_category', 'tax_doc_tag', 'tax_knowledge_base')
                GROUP BY trid HAVING COUNT(*) > 1
            )
        ");
        
        // Get PLL BetterDocs term translation groups
        $verification['pll_doc_term_groups'] = $this->wpdb->get_var("
            SELECT COUNT(*)
            FROM {$this->wpdb->term_taxonomy} tt
            JOIN {$this->wpdb->terms} t ON tt.term_id = t.term_id
            WHERE tt.taxonomy = 'term_translations'
            AND t.slug LIKE 'pll_wpml_%'
            AND (tt.description LIKE '%\"doc_category\"%' 
                 OR tt.description LIKE '%\"doc_tag\"%' 
                 OR tt.description LIKE '%\"knowledge_base\"%')
        ");
        
        // Check for missing translation groups
        if ($verification['wpml_docs_trids'] > 0 && $verification['pll_docs_groups'] == 0) {
            $verification['critical_issues']++;
            $verification['issues'][] = "WPML had {$verification['wpml_docs_trids']} docs translation groups but no Polylang groups found";
        }
        
        if ($verification['wpml_doc_term_trids'] > 0 && $verification['pll_doc_term_groups'] == 0) {
            $verification['critical_issues']++;
            $verification['issues'][] = "WPML had {$verification['wpml_doc_term_trids']} BetterDocs term translation groups but no Polylang groups found";
        }
    }
    
    /**
     * Verify languages migration
     */
    private function verify_languages() {
        $verification = [
            'status' => 'checking',
            'issues' => [],
            'critical_issues' => 0,
            'wpml_languages' => 0,
            'pll_languages' => 0,
            'missing_in_pll' => []
        ];
        
        try {
            // Check if WPML data exists
            if (!$this->wpml_tables_exist()) {
                $verification['status'] = 'no_wpml_data';
                return $verification;
            }
            
            // Get WPML languages
            $wpml_languages = $this->wpdb->get_results("
                SELECT l.code, l.default_locale, lt.name
                FROM {$this->wpdb->prefix}icl_languages l
                INNER JOIN {$this->wpdb->prefix}icl_languages_translations lt 
                    ON l.code = lt.language_code
                WHERE l.active = 1 AND lt.language_code = lt.display_language_code
            ");
            
            $verification['wpml_languages'] = count($wpml_languages);
            
            if (!function_exists('pll_languages_list')) {
                $verification['issues'][] = 'Polylang not active';
                $verification['critical_issues']++;
                return $verification;
            }
            
            $pll_languages = pll_languages_list(['fields' => 'slug']);
            $verification['pll_languages'] = count($pll_languages);
            
            // Check for missing languages
            foreach ($wpml_languages as $wpml_lang) {
                if (!in_array($wpml_lang->code, $pll_languages)) {
                    $verification['missing_in_pll'][] = $wpml_lang->code;
                    $verification['critical_issues']++;
                }
            }
            
            $verification['status'] = $verification['critical_issues'] === 0 ? 'success' : 'issues';
            
        } catch (Exception $e) {
            $verification['status'] = 'error';
            $verification['issues'][] = 'Error checking languages: ' . $e->getMessage();
            if ($this->logger) {
                $this->logger->log_error('Language verification failed', $e);
            }
        }
        
        return $verification;
    }
    
    /**
     * Verify posts migration
     */
    private function verify_posts() {
        $verification = [
            'status' => 'checking',
            'issues' => [],
            'critical_issues' => 0,
            'wpml_post_trids' => 0,
            'pll_post_groups' => 0,
            'posts_with_language' => 0,
            'posts_without_language' => 0,
            'posts_wrong_language' => 0,
            'orphaned_wpml_groups' => 0,
            'corrupted_groups' => 0
        ];
        
        try {
            if (!$this->wpml_tables_exist()) {
                $verification['status'] = 'no_wpml_data';
                return $verification;
            }
            
            // Get WPML post translation groups
            $wpml_post_trids = $this->wpdb->get_var("
                SELECT COUNT(DISTINCT trid)
                FROM {$this->icl_table}
                WHERE element_type LIKE 'post_%'
                AND trid IN (
                    SELECT trid FROM {$this->icl_table}
                    WHERE element_type LIKE 'post_%'
                    GROUP BY trid HAVING COUNT(*) > 1
                )
            ");
            $verification['wpml_post_trids'] = intval($wpml_post_trids);
            
            // Get PLL post translation groups
            $pll_post_groups = $this->wpdb->get_var("
                SELECT COUNT(*)
                FROM {$this->wpdb->term_taxonomy}
                WHERE taxonomy = 'post_translations'
            ");
            $verification['pll_post_groups'] = intval($pll_post_groups);
            
            // Check for orphaned WPML groups (not converted to PLL)
            $orphaned_groups = $this->wpdb->get_var("
                SELECT COUNT(DISTINCT trid)
                FROM {$this->icl_table}
                WHERE element_type LIKE 'post_%'
                AND trid NOT IN (
                    SELECT CAST(REPLACE(t.slug, 'pll_wpml_', '') AS UNSIGNED)
                    FROM {$this->wpdb->terms} t
                    JOIN {$this->wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
                    WHERE tt.taxonomy = 'post_translations'
                    AND t.slug LIKE 'pll_wpml_%'
                    AND t.slug REGEXP '^pll_wpml_[0-9]+$'
                )
            ");
            $verification['orphaned_wpml_groups'] = intval($orphaned_groups);
            
            if ($verification['orphaned_wpml_groups'] > 0) {
                $verification['critical_issues'] += $verification['orphaned_wpml_groups'];
                $verification['issues'][] = "Found {$verification['orphaned_wpml_groups']} WPML post groups not converted to Polylang";
            }
            
            // Check posts with/without language
            $posts_with_language = $this->wpdb->get_var("
                SELECT COUNT(DISTINCT p.ID)
                FROM {$this->wpdb->posts} p
                JOIN {$this->wpdb->term_relationships} tr ON p.ID = tr.object_id
                JOIN {$this->wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                WHERE tt.taxonomy = 'language'
                AND p.post_type IN ('post', 'page', 'product', 'docs')
                AND p.post_status IN ('publish', 'draft', 'private')
            ");
            $verification['posts_with_language'] = intval($posts_with_language);
            
            $total_posts = $this->wpdb->get_var("
                SELECT COUNT(*)
                FROM {$this->wpdb->posts}
                WHERE post_type IN ('post', 'page', 'product', 'docs')
                AND post_status IN ('publish', 'draft', 'private')
            ");
            
            $verification['posts_without_language'] = intval($total_posts) - $verification['posts_with_language'];

            if ($verification['posts_without_language'] > 0) {
                $verification['critical_issues'] += $verification['posts_without_language'];
                $verification['issues'][] = "Found {$verification['posts_without_language']} posts without language assignment";
            }

            // Check for posts with WRONG language (mismatch between WPML and Polylang)
            // First count posts with correct matching languages
            $posts_correct_language = $this->wpdb->get_var("
                SELECT COUNT(DISTINCT p.ID)
                FROM {$this->wpdb->posts} p
                JOIN {$this->wpdb->term_relationships} tr ON tr.object_id = p.ID
                JOIN {$this->wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
                JOIN {$this->wpdb->terms} t ON t.term_id = tt.term_id
                JOIN {$this->icl_table} wpml ON wpml.element_id = p.ID
                    AND wpml.element_type LIKE 'post_%'
                WHERE p.post_type IN ('post', 'page', 'product', 'docs')
                AND p.post_status IN ('publish', 'draft', 'private')
                AND tt.taxonomy = 'language'
                AND wpml.language_code IS NOT NULL
                AND (
                    t.slug = wpml.language_code
                    OR t.slug = REPLACE(wpml.language_code, '_', '-')
                    OR wpml.language_code = REPLACE(t.slug, '-', '_')
                )
            ");

            // Wrong language = posts with language - posts with correct language
            $posts_wrong_language = max(0, $verification['posts_with_language'] - intval($posts_correct_language));
            $verification['posts_wrong_language'] = $posts_wrong_language;

            if ($verification['posts_wrong_language'] > 0) {
                $verification['critical_issues'] += $verification['posts_wrong_language'];
                $verification['issues'][] = "Found {$verification['posts_wrong_language']} posts with wrong language assignment (mismatch between WPML and Polylang)";
            }
            
            // Check for corrupted translation groups (invalid serialized data)
            $corrupted_groups = $this->wpdb->get_results("
                SELECT tt.term_id, tt.description
                FROM {$this->wpdb->term_taxonomy} tt
                WHERE tt.taxonomy = 'post_translations'
                AND tt.description != ''
            ");
            
            foreach ($corrupted_groups as $group) {
                $data = @unserialize($group->description);
                if ($data === false && $group->description !== 'b:0;') {
                    $verification['corrupted_groups']++;
                    $verification['critical_issues']++;
                }
            }
            
            if ($verification['corrupted_groups'] > 0) {
                $verification['issues'][] = "Found {$verification['corrupted_groups']} corrupted post translation groups";
            }
            
            $verification['status'] = $verification['critical_issues'] === 0 ? 'success' : 'issues';
            
        } catch (Exception $e) {
            $verification['status'] = 'error';
            $verification['issues'][] = 'Error checking posts: ' . $e->getMessage();
            if ($this->logger) {
                $this->logger->log_error('Post verification failed', $e);
            }
        }
        
        return $verification;
    }
    
    /**
     * Verify terms migration (uses term_language taxonomy)
     */
    private function verify_terms() {
        $verification = [
            'status' => 'checking',
            'issues' => [],
            'critical_issues' => 0,
            'wpml_term_trids' => 0,
            'pll_term_groups' => 0,
            'terms_with_language' => 0,
            'terms_without_language' => 0,
            'terms_wrong_language' => 0,
            'orphaned_wpml_term_groups' => 0,
            'corrupted_term_groups' => 0
        ];
        
        try {
            if (!$this->wpml_tables_exist()) {
                $verification['status'] = 'no_wpml_data';
                return $verification;
            }
            
            // Get WPML term translation groups
            $wpml_term_trids = $this->wpdb->get_var("
                SELECT COUNT(DISTINCT trid)
                FROM {$this->icl_table}
                WHERE element_type LIKE 'tax_%'
                AND trid IN (
                    SELECT trid FROM {$this->icl_table}
                    WHERE element_type LIKE 'tax_%'
                    GROUP BY trid HAVING COUNT(*) > 1
                )
            ");
            $verification['wpml_term_trids'] = intval($wpml_term_trids);
            
            // Get PLL term translation groups
            $pll_term_groups = $this->wpdb->get_var("
                SELECT COUNT(*)
                FROM {$this->wpdb->term_taxonomy}
                WHERE taxonomy = 'term_translations'
            ");
            $verification['pll_term_groups'] = intval($pll_term_groups);
            
            // Check for orphaned WPML term groups
            $orphaned_term_groups = $this->wpdb->get_var("
                SELECT COUNT(DISTINCT trid)
                FROM {$this->icl_table}
                WHERE element_type LIKE 'tax_%'
                AND trid NOT IN (
                    SELECT CAST(REPLACE(t.slug, 'pll_wpml_', '') AS UNSIGNED)
                    FROM {$this->wpdb->terms} t
                    JOIN {$this->wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
                    WHERE tt.taxonomy = 'term_translations'
                    AND t.slug LIKE 'pll_wpml_%'
                    AND t.slug REGEXP '^pll_wpml_[0-9]+$'
                )
            ");
            $verification['orphaned_wpml_term_groups'] = intval($orphaned_term_groups);
            
            if ($verification['orphaned_wpml_term_groups'] > 0) {
                $verification['critical_issues'] += $verification['orphaned_wpml_term_groups'];
                $verification['issues'][] = "Found {$verification['orphaned_wpml_term_groups']} WPML term groups not converted to Polylang";
            }
            
            // Check terms with/without language (using term_language taxonomy)
            $terms_with_language = $this->wpdb->get_var("
                SELECT COUNT(DISTINCT t.term_id)
                FROM {$this->wpdb->terms} t
                JOIN {$this->wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
                JOIN {$this->wpdb->term_relationships} tr ON t.term_id = tr.object_id
                JOIN {$this->wpdb->term_taxonomy} tt2 ON tr.term_taxonomy_id = tt2.term_taxonomy_id
                WHERE tt.taxonomy IN ('category', 'post_tag', 'product_cat', 'product_tag', 'doc_category')
                AND tt2.taxonomy = 'term_language'
            ");
            $verification['terms_with_language'] = intval($terms_with_language);
            
            $total_terms = $this->wpdb->get_var("
                SELECT COUNT(DISTINCT t.term_id)
                FROM {$this->wpdb->terms} t
                JOIN {$this->wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
                WHERE tt.taxonomy IN ('category', 'post_tag', 'product_cat', 'product_tag', 'doc_category')
            ");
            
            $verification['terms_without_language'] = intval($total_terms) - $verification['terms_with_language'];

            if ($verification['terms_without_language'] > 0) {
                $verification['critical_issues'] += $verification['terms_without_language'];
                $verification['issues'][] = "Found {$verification['terms_without_language']} terms without language assignment";
            }

            // Check for terms with WRONG language (mismatch between WPML and Polylang)
            // First count terms with correct matching languages
            $terms_correct_language = $this->wpdb->get_var("
                SELECT COUNT(DISTINCT t.term_id)
                FROM {$this->wpdb->terms} t
                JOIN {$this->wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
                JOIN {$this->wpdb->term_relationships} tr ON t.term_id = tr.object_id
                JOIN {$this->wpdb->term_taxonomy} tl ON tr.term_taxonomy_id = tl.term_taxonomy_id
                JOIN {$this->wpdb->terms} lang ON lang.term_id = tl.term_id
                JOIN {$this->icl_table} wpml ON wpml.element_id = tt.term_taxonomy_id
                    AND wpml.element_type LIKE 'tax_%'
                WHERE tt.taxonomy IN ('category', 'post_tag', 'product_cat', 'product_tag', 'doc_category')
                AND tl.taxonomy = 'term_language'
                AND wpml.language_code IS NOT NULL
                AND (
                    lang.slug = wpml.language_code
                    OR lang.slug = REPLACE(wpml.language_code, '_', '-')
                    OR wpml.language_code = REPLACE(lang.slug, '-', '_')
                )
            ");

            // Wrong language = terms with language - terms with correct language
            $terms_wrong_language = max(0, $verification['terms_with_language'] - intval($terms_correct_language));
            $verification['terms_wrong_language'] = $terms_wrong_language;

            if ($verification['terms_wrong_language'] > 0) {
                $verification['critical_issues'] += $verification['terms_wrong_language'];
                $verification['issues'][] = "Found {$verification['terms_wrong_language']} terms with wrong language assignment (mismatch between WPML and Polylang)";
            }

            $verification['status'] = $verification['critical_issues'] === 0 ? 'success' : 'issues';
            
        } catch (Exception $e) {
            $verification['status'] = 'error';
            $verification['issues'][] = 'Error checking terms: ' . $e->getMessage();
            if ($this->logger) {
                $this->logger->log_error('Term verification failed', $e);
            }
        }
        
        return $verification;
    }
    
    /**
     * Verify translation groups structure
     */
    private function verify_translation_groups() {
        $verification = [
            'status' => 'checking',
            'issues' => [],
            'critical_issues' => 0,
            'total_groups' => 0,
            'valid_groups' => 0,
            'invalid_groups' => 0,
            'empty_groups' => 0
        ];
        
        try {
            // Check post translation groups
            $post_groups = $this->wpdb->get_results("
                SELECT t.slug, tt.description, tt.count
                FROM {$this->wpdb->terms} t
                JOIN {$this->wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
                WHERE tt.taxonomy = 'post_translations'
                AND t.slug LIKE 'pll_wpml_%'
            ");
            
            foreach ($post_groups as $group) {
                $verification['total_groups']++;
                
                // Verify description is valid serialized data
                $data = @unserialize($group->description);
                if ($data === false && $group->description !== 'b:0;') {
                    $verification['invalid_groups']++;
                    $verification['critical_issues']++;
                    continue;
                }
                
                // Check if group has actual translations
                if (empty($data) || !is_array($data)) {
                    $verification['empty_groups']++;
                    continue;
                }
                
                // Verify count matches actual translations
                if ($group->count != count($data)) {
                    $verification['invalid_groups']++;
                    $verification['critical_issues']++;
                    continue;
                }
                
                $verification['valid_groups']++;
            }
            
            // Check term translation groups
            $term_groups = $this->wpdb->get_results("
                SELECT t.slug, tt.description, tt.count
                FROM {$this->wpdb->terms} t
                JOIN {$this->wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
                WHERE tt.taxonomy = 'term_translations'
                AND t.slug LIKE 'pll_wpml_%'
            ");
            
            foreach ($term_groups as $group) {
                $verification['total_groups']++;
                
                $data = @unserialize($group->description);
                if ($data === false && $group->description !== 'b:0;') {
                    $verification['invalid_groups']++;
                    $verification['critical_issues']++;
                    continue;
                }
                
                if (empty($data) || !is_array($data)) {
                    $verification['empty_groups']++;
                    continue;
                }
                
                if ($group->count != count($data)) {
                    $verification['invalid_groups']++;
                    $verification['critical_issues']++;
                    continue;
                }
                
                $verification['valid_groups']++;
            }
            
            if ($verification['invalid_groups'] > 0) {
                $verification['issues'][] = "Found {$verification['invalid_groups']} corrupted translation groups";
            }
            
            if ($verification['empty_groups'] > 0) {
                $verification['issues'][] = "Found {$verification['empty_groups']} empty translation groups";
            }
            
            $verification['status'] = $verification['critical_issues'] === 0 ? 'success' : 'issues';
            
        } catch (Exception $e) {
            $verification['status'] = 'error';
            $verification['issues'][] = 'Error checking translation groups: ' . $e->getMessage();
            if ($this->logger) {
                $this->logger->log_error('Translation groups verification failed', $e);
            }
        }
        
        return $verification;
    }
    
    /**
     * Verify menu migration
     */
    private function verify_menus() {
        $verification = [
            'status' => 'checking',
            'issues' => [],
            'critical_issues' => 0,
            'wpml_menu_trids' => 0,
            'pll_menu_config' => false,
            'configured_themes' => 0
        ];
        
        try {
            if (!$this->wpml_tables_exist()) {
                $verification['status'] = 'no_wpml_data';
                return $verification;
            }
            
            // Check WPML menu translation groups
            $wpml_menu_trids = $this->wpdb->get_var("
                SELECT COUNT(DISTINCT trid)
                FROM {$this->icl_table}
                WHERE element_type = 'tax_nav_menu'
                AND trid IN (
                    SELECT trid FROM {$this->icl_table}
                    WHERE element_type = 'tax_nav_menu'
                    GROUP BY trid HAVING COUNT(*) > 1
                )
            ");
            $verification['wpml_menu_trids'] = intval($wpml_menu_trids);
            
            // Check Polylang menu configuration
            $polylang_options = get_option('polylang');
            if (isset($polylang_options['nav_menus']) && !empty($polylang_options['nav_menus'])) {
                $verification['pll_menu_config'] = true;
                $verification['configured_themes'] = count($polylang_options['nav_menus']);
            }
            
            // If WPML had menu translations but PLL doesn't, that's an issue
            if ($verification['wpml_menu_trids'] > 0 && !$verification['pll_menu_config']) {
                $verification['critical_issues']++;
                $verification['issues'][] = "WPML had {$verification['wpml_menu_trids']} menu translation groups but Polylang menu configuration is missing";
            }
            
            $verification['status'] = $verification['critical_issues'] === 0 ? 'success' : 'issues';
            
        } catch (Exception $e) {
            $verification['status'] = 'error';
            $verification['issues'][] = 'Error checking menus: ' . $e->getMessage();
            if ($this->logger) {
                $this->logger->log_error('Menu verification failed', $e);
            }
        }
        
        return $verification;
    }
    
    /**
     * Verify string migration
     */
    private function verify_strings() {
        $verification = [
            'status' => 'checking',
            'issues' => [],
            'critical_issues' => 0,
            'wpml_strings' => 0,
            'pll_mo_entries' => 0
        ];
        
        try {
            if (!$this->wpml_tables_exist()) {
                $verification['status'] = 'no_wpml_data';
                return $verification;
            }
            
            // Check WPML strings
            $wpml_strings = $this->wpdb->get_var("
                SELECT COUNT(*)
                FROM {$this->wpdb->prefix}icl_strings s
                INNER JOIN {$this->wpdb->prefix}icl_string_translations st ON st.string_id = s.id
            ");
            $verification['wpml_strings'] = intval($wpml_strings);
            
            // Check Polylang MO entries (basic check)
            if (function_exists('pll_languages_list')) {
                $languages = pll_languages_list(['fields' => 'slug']);
                $mo_entries = 0;
                
                foreach ($languages as $lang) {
                    if ($lang !== pll_default_language()) {
                        $mo = new PLL_MO();
                        $mo->import_from_db($lang);
                        $mo_entries += count($mo->entries);
                    }
                }
                
                $verification['pll_mo_entries'] = $mo_entries;
            }
            
            // Basic comparison (not exact as some strings might be filtered)
            if ($verification['wpml_strings'] > 0 && $verification['pll_mo_entries'] === 0) {
                $verification['critical_issues']++;
                $verification['issues'][] = "WPML had {$verification['wpml_strings']} string translations but Polylang MO is empty";
            }
            
            $verification['status'] = $verification['critical_issues'] === 0 ? 'success' : 'needs_review';
            
        } catch (Exception $e) {
            $verification['status'] = 'error';
            $verification['issues'][] = 'Error checking strings: ' . $e->getMessage();
            if ($this->logger) {
                $this->logger->log_error('String verification failed', $e);
            }
        }
        
        return $verification;
    }
    
    /**
     * Verify options migration
     */
    private function verify_options() {
        $verification = [
            'status' => 'checking',
            'issues' => [],
            'critical_issues' => 0,
            'polylang_configured' => false,
            'default_language_set' => false,
            'url_structure_set' => false,
            'post_types_registered' => 0,
            'taxonomies_registered' => 0
        ];
        
        try {
            $polylang_options = get_option('polylang');
            
            if (!$polylang_options) {
                $verification['critical_issues']++;
                $verification['issues'][] = 'Polylang options not found';
                $verification['status'] = 'issues';
                return $verification;
            }
            
            $verification['polylang_configured'] = true;
            
            // Check default language
            if (isset($polylang_options['default_lang']) && !empty($polylang_options['default_lang'])) {
                $verification['default_language_set'] = true;
            } else {
                $verification['critical_issues']++;
                $verification['issues'][] = 'Default language not set in Polylang options';
            }
            
            // Check URL structure
            if (isset($polylang_options['force_lang'])) {
                $verification['url_structure_set'] = true;
            } else {
                $verification['issues'][] = 'URL structure not configured';
            }
            
            // Check post types
            if (isset($polylang_options['post_types']) && is_array($polylang_options['post_types'])) {
                $verification['post_types_registered'] = count($polylang_options['post_types']);
            }
            
            // Check taxonomies
            if (isset($polylang_options['taxonomies']) && is_array($polylang_options['taxonomies'])) {
                $verification['taxonomies_registered'] = count($polylang_options['taxonomies']);
            }
            
            $verification['status'] = $verification['critical_issues'] === 0 ? 'success' : 'issues';
            
        } catch (Exception $e) {
            $verification['status'] = 'error';
            $verification['issues'][] = 'Error checking options: ' . $e->getMessage();
            if ($this->logger) {
                $this->logger->log_error('Options verification failed', $e);
            }
        }
        
        return $verification;
    }
    
    /**
     * Check if WPML data exists
     */
    private function check_wpml_data_exists() {
        return [
            'icl_translations_exists' => $this->wpml_tables_exist(),
            'icl_translations_count' => $this->wpml_tables_exist() ? 
                intval($this->wpdb->get_var("SELECT COUNT(*) FROM {$this->icl_table}")) : 0
        ];
    }
    
    /**
     * Check if WPML tables exist
     */
    private function wpml_tables_exist() {
        return $this->wpdb->get_var("SHOW TABLES LIKE '{$this->icl_table}'") == $this->icl_table;
    }
    
    /**
     * Get summary for quick overview
     */
    public function get_verification_summary() {
        $results = $this->verify_migration();
        
        $summary = [
            'overall_status' => $results['overall_status'],
            'total_critical_issues' => $results['total_critical_issues'],
            'components_checked' => count($results) - 3, // Exclude overall_status, total_critical_issues, wpml_data
            'components_with_issues' => 0,
            'quick_stats' => []
        ];
        
        foreach ($results as $component => $data) {
            if (in_array($component, ['overall_status', 'total_critical_issues', 'wpml_data'])) {
                continue;
            }
            
            if (isset($data['critical_issues']) && $data['critical_issues'] > 0) {
                $summary['components_with_issues']++;
            }
            
            // Add quick stats for display
            switch ($component) {
                case 'posts':
                    $summary['quick_stats']['posts_without_language'] = $data['posts_without_language'] ?? 0;
                    break;
                case 'terms':
                    $summary['quick_stats']['terms_without_language'] = $data['terms_without_language'] ?? 0;
                    break;
                case 'translation_groups':
                    $summary['quick_stats']['invalid_groups'] = $data['invalid_groups'] ?? 0;
                    break;
                case 'betterdocs':
                    $summary['quick_stats']['docs_without_language'] = $data['docs_without_language'] ?? 0;
                    break;
            }
        }
        
        return $summary;
    }
}