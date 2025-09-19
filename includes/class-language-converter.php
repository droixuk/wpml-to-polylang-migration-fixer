<?php
/**
 * Language Converter Class
 * 
 * Handles language code conversion between WPML and Polylang
 * 
 * @package WPML_To_Polylang_Migration_Fixer
 * @since 1.0.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPML_To_Polylang_Fixer_Language_Converter {
    
    private $mappings_cache = [];
    private $pll_languages = null;
    private $logger;
    
    public function __construct() {
        // Initialize logger safely - check if class exists first
        if (class_exists('WPML_To_Polylang_Fixer_Debug_Logger')) {
            $this->logger = new WPML_To_Polylang_Fixer_Debug_Logger();
        }
        $this->init_mappings();
    }
    
    private function init_mappings() {
        // Comprehensive mappings for all languages - based on reference code
        $this->mappings_cache = [
            // Standard language codes
            'da' => 'da', 'de' => 'de', 'et' => 'et', 'es' => 'es', 'fr' => 'fr',
            'ga' => 'ga', 'hr' => 'hr', 'id' => 'id', 'it' => 'it', 'lv' => 'lv',
            'lt' => 'lt', 'hu' => 'hu', 'ms' => 'ms', 'nl' => 'nl', 'no' => 'no',
            'pl' => 'pl', 'sl' => 'sl', 'fi' => 'fi', 'sv' => 'sv', 'vi' => 'vi',
            'is' => 'is', 'cs' => 'cs', 'el' => 'el', 'bg' => 'bg', 'he' => 'he',
            'ar' => 'ar', 'th' => 'th', 'ja' => 'ja', 'ko' => 'ko', 'ro' => 'ro',
            'sk' => 'sk', 'ru' => 'ru', 'tr' => 'tr', 'uk' => 'uk', 'zh' => 'zh',
            
            // English variants - map to specific configured variants
            'en' => 'en',
            'en_au' => 'en-au', 'en-au' => 'en-au',
            'en_ie' => 'en-ie', 'en-ie' => 'en-ie',
            'en_gb' => 'en-gb', 'en-gb' => 'en-gb',
            'en_sg' => 'en-sg', 'en-sg' => 'en-sg',
            
            // Map other English variants to base English if specific variant not configured
            'en_us' => 'en', 'en-us' => 'en',
            'en_ca' => 'en', 'en-ca' => 'en',
            'en_nz' => 'en', 'en-nz' => 'en',
            'en_za' => 'en', 'en-za' => 'en',
            
            // Portuguese variant - map to Portugal variant since that's configured
            'pt_pt' => 'pt-pt', 'pt-pt' => 'pt-pt', 'pt' => 'pt-pt',
            'pt_br' => 'pt-br', 'pt-br' => 'pt-br',
            
            // German variants (map to base German)
            'de_de' => 'de', 'de-de' => 'de', 'de_ch' => 'de', 'de-ch' => 'de',
            'de_at' => 'de', 'de-at' => 'de',
            
            // Spanish variants (map to base Spanish)
            'es_es' => 'es', 'es-es' => 'es', 'es_mx' => 'es', 'es-mx' => 'es',
            'es_ar' => 'es', 'es-ar' => 'es',
            
            // French variants (map to base French)
            'fr_fr' => 'fr', 'fr-fr' => 'fr', 'fr_ca' => 'fr', 'fr-ca' => 'fr',
            'fr_be' => 'fr', 'fr-be' => 'fr',
            
            // Norwegian variants
            'nb' => 'no', 'nn' => 'no', 'no_no' => 'no', 'no-no' => 'no',
            
            // Chinese variants (if ever added)
            'zh_cn' => 'zh', 'zh-cn' => 'zh', 'zh_tw' => 'zh', 'zh-tw' => 'zh',
            'zh_hans' => 'zh', 'zh-hans' => 'zh', 'zh_hant' => 'zh', 'zh-hant' => 'zh',
        ];
        
        $this->mappings_cache = apply_filters('wpml_to_polylang_fixer_language_mappings', $this->mappings_cache);
    }
    
    public function convert_language($wpml_code) {
        if (!function_exists('pll_languages_list')) {
            if ($this->logger) {
                $this->logger->log("Polylang not active, returning original code: {$wpml_code}", 'warning');
            }
            return $wpml_code;
        }
        
        if ($this->pll_languages === null) {
            $this->pll_languages = pll_languages_list(['fields' => 'slug']);
        }
        
        $code = strtolower(trim($wpml_code));
        
        // Remove any pll_ prefix if it exists (data corruption issue)
        if (strpos($code, 'pll_') === 0) {
            $code = str_replace('pll_', '', $code);
            if ($this->logger) {
                $this->logger->log("Removed pll_ prefix from corrupted code: {$wpml_code} -> {$code}", 'warning');
            }
        }
        
        // Direct match - this handles most cases
        if (in_array($code, $this->pll_languages)) {
            return $code;
        }
        
        // Check with underscores replaced by hyphens
        $code_hyphen = str_replace('_', '-', $code);
        if (in_array($code_hyphen, $this->pll_languages)) {
            return $code_hyphen;
        }
        
        // Check with hyphens replaced by underscores
        $code_underscore = str_replace('-', '_', $code);
        if (in_array($code_underscore, $this->pll_languages)) {
            return $code_underscore;
        }
        
        // Check our mappings
        if (isset($this->mappings_cache[$code])) {
            $mapped = $this->mappings_cache[$code];
            if (in_array($mapped, $this->pll_languages)) {
                if ($this->logger) {
                    $this->logger->log("Mapped language: {$wpml_code} -> {$mapped}", 'debug');
                }
                return $mapped;
            }
        }
        
        // Special handling for English variants
        if (strpos($code, 'en') === 0) {
            return $this->handle_english_variant($code);
        }
        
        // For other language variants, try to find the best match
        if (strlen($code) > 2) {
            $base = substr($code, 0, 2);
            
            // First check if base language exists
            if (in_array($base, $this->pll_languages)) {
                if ($this->logger) {
                    $this->logger->log("Using base language for variant: {$wpml_code} -> {$base}", 'info');
                }
                return $base;
            }
            
            // Otherwise use any variant of this language
            foreach ($this->pll_languages as $pll_lang) {
                if (strpos($pll_lang, $base) === 0) {
                    if ($this->logger) {
                        $this->logger->log("Using closest variant: {$wpml_code} -> {$pll_lang}", 'info');
                    }
                    return $pll_lang;
                }
            }
        }
        
        // Log unmapped language for debugging
        if ($this->logger) {
            $this->logger->log("Unable to map language code '{$wpml_code}'. Using default language.", 'error');
        }
        
        // If still no match, use default language
        return pll_default_language();
    }
    
    private function handle_english_variant($code) {
        // Check for exact English variant match
        foreach ($this->pll_languages as $pll_lang) {
            if ($pll_lang === $code || 
                $pll_lang === str_replace('_', '-', $code) || 
                $pll_lang === str_replace('-', '_', $code)) {
                return $pll_lang;
            }
        }
        
        // If no exact match, check if we have the base 'en'
        if (in_array('en', $this->pll_languages)) {
            return 'en';
        }
        
        // Otherwise return the first English variant we find
        foreach ($this->pll_languages as $pll_lang) {
            if (strpos($pll_lang, 'en') === 0) {
                if ($this->logger) {
                    $this->logger->log("Using first available English variant: {$code} -> {$pll_lang}", 'info');
                }
                return $pll_lang;
            }
        }
        
        // Fallback to default language
        return pll_default_language();
    }
    
    public function get_problematic_codes() {
        global $wpdb;
        
        $problematic = [];
        
        // Check for pll_ prefixed codes (data corruption)
        $pll_prefixed = $wpdb->get_col("
            SELECT DISTINCT t.slug
            FROM {$wpdb->terms} t
            JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
            WHERE tt.taxonomy IN ('language', 'term_language')
            AND t.slug LIKE 'pll_%'
        ");
        
        if (!empty($pll_prefixed)) {
            $problematic['pll_prefixed'] = $pll_prefixed;
        }
        
        // Find all language codes in use
        $all_codes = $wpdb->get_col("
            SELECT DISTINCT t.slug
            FROM {$wpdb->terms} t
            JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
            WHERE tt.taxonomy IN ('language', 'term_language')
        ");
        
        // Get configured languages
        $configured = pll_languages_list(['fields' => 'slug']);
        
        // Find unconfigured codes
        $unconfigured = array_diff($all_codes, $configured);
        
        if (!empty($unconfigured)) {
            $problematic['unconfigured'] = $unconfigured;
        }
        
        // Find English variants that aren't the main configured English
        $english_variants = [];
        $main_english = $this->get_main_english_code();
        
        foreach ($all_codes as $code) {
            if (strpos($code, 'en') === 0 && $code !== $main_english && !in_array($code, $configured)) {
                $english_variants[] = $code;
            }
        }
        
        if (!empty($english_variants)) {
            $problematic['english_variants'] = $english_variants;
        }
        
        return $problematic;
    }
    
    public function get_main_english_code() {
        $configured_langs = pll_languages_list(['fields' => 'slug']);
        
        // Prefer base 'en'
        if (in_array('en', $configured_langs)) {
            return 'en';
        }
        
        // Otherwise return first English variant found
        foreach ($configured_langs as $lang) {
            if (strpos($lang, 'en') === 0) {
                return $lang;
            }
        }
        
        return null;
    }
    
    public function get_mapping_stats() {
        global $wpdb;
        
        $stats = [
            'total_mappings' => count($this->mappings_cache),
            'configured_languages' => count(pll_languages_list(['fields' => 'slug'])),
            'unmapped_codes' => [],
            'mapping_conflicts' => []
        ];
        
        // Check WPML codes if table exists
        $icl_table = $wpdb->prefix . 'icl_translations';
        if ($wpdb->get_var("SHOW TABLES LIKE '$icl_table'") == $icl_table) {
            $wpml_codes = $wpdb->get_col("SELECT DISTINCT language_code FROM $icl_table");
            
            foreach ($wpml_codes as $code) {
                $mapped = $this->convert_language($code);
                if ($mapped === pll_default_language() && $code !== pll_default_language()) {
                    $stats['unmapped_codes'][] = $code;
                }
            }
        }
        
        return $stats;
    }
    
    /**
     * Canonicalize language code - normalize to Polylang format
     *
     * @param string $code Language code to canonicalize
     * @return string Canonicalized language code
     */
    public function canonicalize_slug($code) {
        if (!$code) return '';

        $code = strtolower(trim($code));
        $code = preg_replace('/^pll_/', '', $code);       // drop pll_ prefix
        $code = str_replace('_', '-', $code);             // WPML uses _, PLL prefers -
        $code = preg_replace('/[^a-z\-]/', '', $code);    // only a-z and '-'

        // Map variants you don't keep to a canonical
        $map = apply_filters('wpml_to_polylang_fixer_variant_map', [
            'en-us' => 'en-gb',
            'en-au' => 'en-gb',
            'en-ie' => 'en-gb',
            // add more mappings as needed
        ]);

        if (isset($map[$code])) {
            $code = $map[$code];
        }

        return $code;
    }

    /**
     * Ensure PLL language exists or map to canonical
     *
     * @param string $slug Language slug
     * @return string|false Valid language slug or false
     */
    public function ensure_pll_language_exists($slug) {
        if (!$slug || !function_exists('pll_languages_list')) {
            return false;
        }

        $slug = $this->canonicalize_slug($slug);
        $langs = pll_languages_list(['fields' => 'slug']);

        if (in_array($slug, $langs, true)) {
            return $slug;
        }

        // If slug not configured, map to default or canonical English
        // This can be customized via filter
        $default = apply_filters('wpml_to_polylang_fixer_default_language', 'en-gb');

        // Try to find the default language
        if (in_array($default, $langs, true)) {
            return $default;
        }

        // Fall back to Polylang's default language
        return pll_default_language();
    }

    /**
     * Fix wrong language codes in content
     */
    public function fix_wrong_language_codes($pattern = 'pll_%', $batch_size = 100, $offset = 0) {
        global $wpdb;
        
        $fixed = 0;
        $total_posts = 0;
        $total_terms = 0;
        
        try {
            // Get total counts first
            $total_posts = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(DISTINCT tr.object_id)
                FROM {$wpdb->term_relationships} tr
                JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
                WHERE tt.taxonomy = 'language'
                AND t.slug LIKE %s
            ", $pattern));
            
            $total_terms = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(DISTINCT tr.object_id)
                FROM {$wpdb->term_relationships} tr
                JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
                WHERE tt.taxonomy = 'term_language'
                AND t.slug LIKE %s
            ", $pattern));
            
            $total = $total_posts + $total_terms;
            
            if ($total == 0) {
                return [
                    'total' => 0,
                    'processed' => 0,
                    'fixed' => 0,
                    'continue' => false
                ];
            }
            
            $processed = 0;
            
            // Process posts first
            if ($offset < $total_posts) {
                $posts_to_fix = $wpdb->get_results($wpdb->prepare("
                    SELECT DISTINCT tr.object_id as post_id, t.slug as wrong_code
                    FROM {$wpdb->term_relationships} tr
                    JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                    JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
                    WHERE tt.taxonomy = 'language'
                    AND t.slug LIKE %s
                    LIMIT %d, %d
                ", $pattern, $offset, min($batch_size, $total_posts - $offset)));
                
                foreach ($posts_to_fix as $post) {
                    $correct_code = $this->convert_language($post->wrong_code);
                    if ($this->fix_object_language($post->post_id, 'post', $post->wrong_code, $correct_code)) {
                        $fixed++;
                    }
                    $processed++;
                }
            }
            
            // Process terms
            $remaining = $batch_size - $processed;
            if ($remaining > 0 && $offset + $processed >= $total_posts) {
                $term_offset = max(0, $offset - $total_posts);
                
                $terms_to_fix = $wpdb->get_results($wpdb->prepare("
                    SELECT DISTINCT tr.object_id as term_id, t.slug as wrong_code
                    FROM {$wpdb->term_relationships} tr
                    JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                    JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
                    WHERE tt.taxonomy = 'term_language'
                    AND t.slug LIKE %s
                    LIMIT %d, %d
                ", $pattern, $term_offset, $remaining));
                
                foreach ($terms_to_fix as $term) {
                    $correct_code = $this->convert_language($term->wrong_code);
                    if ($this->fix_object_language($term->term_id, 'term', $term->wrong_code, $correct_code)) {
                        $fixed++;
                    }
                    $processed++;
                }
            }
            
            $total_processed = $offset + $processed;
            $continue = ($total_processed < $total);
            
            return [
                'total' => $total,
                'processed' => $total_processed,
                'fixed' => $fixed,
                'continue' => $continue,
                'next_offset' => $offset + $batch_size
            ];
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->log_error("Error fixing wrong language codes", $e);
            }
            throw $e;
        }
    }
    
    /**
     * Fix language for a specific object
     */
    private function fix_object_language($object_id, $object_type, $wrong_code, $correct_code) {
        global $wpdb;
        
        try {
            $wpdb->query('START TRANSACTION');
            
            $taxonomy = ($object_type === 'post') ? 'language' : 'term_language';
            
            // Delete wrong assignment
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
            
            // Set correct language
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
}