<?php
/**
 * Language Converter Class
 * 
 * Handles language code conversion between WPML and Polylang
 * 
 * @package WPML_To_Polylang_Migration_Fixer
 * @since 1.0.0
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
        $this->mappings_cache = [
            // Standard language codes
            'da' => 'da', 'de' => 'de', 'et' => 'et', 'es' => 'es', 'fr' => 'fr',
            'ga' => 'ga', 'hr' => 'hr', 'id' => 'id', 'it' => 'it', 'lv' => 'lv',
            'lt' => 'lt', 'hu' => 'hu', 'ms' => 'ms', 'nl' => 'nl', 'no' => 'no',
            'pl' => 'pl', 'sl' => 'sl', 'fi' => 'fi', 'sv' => 'sv', 'vi' => 'vi',
            'is' => 'is', 'cs' => 'cs', 'el' => 'el', 'bg' => 'bg', 'he' => 'he',
            'ar' => 'ar', 'th' => 'th', 'ja' => 'ja', 'ko' => 'ko', 'ro' => 'ro',
            'sk' => 'sk', 'ru' => 'ru', 'tr' => 'tr', 'uk' => 'uk', 'zh' => 'zh',
            
            // English variants
            'en' => 'en', 'en_au' => 'en-au', 'en-au' => 'en-au',
            'en_ie' => 'en-ie', 'en-ie' => 'en-ie',
            'en_gb' => 'en-gb', 'en-gb' => 'en-gb',
            'en_sg' => 'en-sg', 'en-sg' => 'en-sg',
            'en_us' => 'en', 'en-us' => 'en',
            'en_ca' => 'en', 'en-ca' => 'en',
            'en_nz' => 'en', 'en-nz' => 'en',
            'en_za' => 'en', 'en-za' => 'en',
            
            // Portuguese variant
            'pt_pt' => 'pt-pt', 'pt-pt' => 'pt-pt', 'pt' => 'pt-pt',
            'pt_br' => 'pt-br', 'pt-br' => 'pt-br',
            
            // Other variants
            'de_de' => 'de', 'de-de' => 'de', 'de_ch' => 'de', 'de-ch' => 'de',
            'de_at' => 'de', 'de-at' => 'de',
            'es_es' => 'es', 'es-es' => 'es', 'es_mx' => 'es', 'es-mx' => 'es',
            'es_ar' => 'es', 'es-ar' => 'es',
            'fr_fr' => 'fr', 'fr-fr' => 'fr', 'fr_ca' => 'fr', 'fr-ca' => 'fr',
            'fr_be' => 'fr', 'fr-be' => 'fr',
            'nb' => 'no', 'nn' => 'no', 'no_no' => 'no', 'no-no' => 'no',
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
        
        if (strpos($code, 'pll_') === 0) {
            $code = str_replace('pll_', '', $code);
            if ($this->logger) {
                $this->logger->log("Removed pll_ prefix from corrupted code: {$wpml_code} -> {$code}", 'warning');
            }
        }
        
        if (in_array($code, $this->pll_languages)) {
            return $code;
        }
        
        $code_hyphen = str_replace('_', '-', $code);
        if (in_array($code_hyphen, $this->pll_languages)) {
            return $code_hyphen;
        }
        
        $code_underscore = str_replace('-', '_', $code);
        if (in_array($code_underscore, $this->pll_languages)) {
            return $code_underscore;
        }
        
        if (isset($this->mappings_cache[$code])) {
            $mapped = $this->mappings_cache[$code];
            if (in_array($mapped, $this->pll_languages)) {
                if ($this->logger) {
                    $this->logger->log("Mapped language: {$wpml_code} -> {$mapped}", 'debug');
                }
                return $mapped;
            }
        }
        
        if (strpos($code, 'en') === 0) {
            return $this->handle_english_variant($code);
        }
        
        if (strlen($code) > 2) {
            $base = substr($code, 0, 2);
            
            if (in_array($base, $this->pll_languages)) {
                if ($this->logger) {
                    $this->logger->log("Using base language for variant: {$wpml_code} -> {$base}", 'info');
                }
                return $base;
            }
            
            foreach ($this->pll_languages as $pll_lang) {
                if (strpos($pll_lang, $base) === 0) {
                    if ($this->logger) {
                        $this->logger->log("Using closest variant: {$wpml_code} -> {$pll_lang}", 'info');
                    }
                    return $pll_lang;
                }
            }
        }
        
        if ($this->logger) {
            $this->logger->log("Unable to map language code '{$wpml_code}'. Using default language.", 'error');
        }
        
        return pll_default_language();
    }
    
    private function handle_english_variant($code) {
        foreach ($this->pll_languages as $pll_lang) {
            if ($pll_lang === $code || 
                $pll_lang === str_replace('_', '-', $code) || 
                $pll_lang === str_replace('-', '_', $code)) {
                return $pll_lang;
            }
        }
        
        if (in_array('en', $this->pll_languages)) {
            return 'en';
        }
        
        foreach ($this->pll_languages as $pll_lang) {
            if (strpos($pll_lang, 'en') === 0) {
                if ($this->logger) {
                    $this->logger->log("Using first available English variant: {$code} -> {$pll_lang}", 'info');
                }
                return $pll_lang;
            }
        }
        
        return pll_default_language();
    }
    
    public function get_problematic_codes() {
        global $wpdb;
        
        $problematic = [];
        
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
        
        $all_codes = $wpdb->get_col("
            SELECT DISTINCT t.slug
            FROM {$wpdb->terms} t
            JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
            WHERE tt.taxonomy IN ('language', 'term_language')
        ");
        
        $configured = pll_languages_list(['fields' => 'slug']);
        $unconfigured = array_diff($all_codes, $configured);
        
        if (!empty($unconfigured)) {
            $problematic['unconfigured'] = $unconfigured;
        }
        
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
        
        if (in_array('en', $configured_langs)) {
            return 'en';
        }
        
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
}