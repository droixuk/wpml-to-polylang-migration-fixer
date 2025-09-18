<?php
/**
 * Debug Logger Class
 * 
 * Handles debug logging for the WPML to Polylang Migration Fixer plugin
 * 
 * @package WPML_To_Polylang_Migration_Fixer
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPML_To_Polylang_Fixer_Debug_Logger {
    
    private $log_file;
    private $debug_enabled;
    
    public function __construct() {
        $upload_dir = wp_upload_dir();
        $this->log_file = $upload_dir['basedir'] . '/wpml-to-polylang-fixer-logs/debug-' . date('Y-m-d') . '.log';
        $this->debug_enabled = get_option('wpml_to_polylang_fixer_debug_enabled', false) || WPML_TO_POLYLANG_FIXER_DEBUG;
    }
    
    public function log($message, $level = 'info', $context = []) {
        if (!$this->debug_enabled) {
            return;
        }
        
        $timestamp = current_time('Y-m-d H:i:s');
        $level = strtoupper($level);
        
        $context_str = '';
        if (!empty($context)) {
            $context_str = ' | Context: ' . json_encode($context);
        }
        
        $log_entry = "[{$timestamp}] [{$level}] {$message}{$context_str}" . PHP_EOL;
        
        if (!file_exists(dirname($this->log_file))) {
            wp_mkdir_p(dirname($this->log_file));
        }
        
        error_log($log_entry, 3, $this->log_file);
        
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[WPML to Polylang Fixer] ' . $log_entry);
        }
    }
    
    public function log_error($message, $exception = null) {
        $context = [];
        
        if ($exception instanceof Exception) {
            $context = [
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ];
        }
        
        $this->log($message, 'error', $context);
    }
    
    public function log_performance($operation, $time_taken, $items_processed = 0) {
        $context = [
            'time_taken' => $time_taken . 's',
            'items_processed' => $items_processed,
            'items_per_second' => $items_processed > 0 ? round($items_processed / $time_taken, 2) : 0
        ];
        
        $this->log("Performance: {$operation}", 'info', $context);
    }
    
    public function cleanup_old_logs() {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/wpml-to-polylang-fixer-logs';
        
        if (!is_dir($log_dir)) {
            return;
        }
        
        $files = glob($log_dir . '/debug-*.log');
        $now = time();
        
        foreach ($files as $file) {
            if (is_file($file)) {
                if ($now - filemtime($file) >= 7 * 24 * 60 * 60) {
                    unlink($file);
                }
            }
        }
    }
    
    public function get_recent_logs($lines = 100) {
        if (!file_exists($this->log_file)) {
            return [];
        }
        
        $file = new SplFileObject($this->log_file, 'r');
        $file->seek(PHP_INT_MAX);
        $last_line = $file->key();
        
        $lines_to_get = min($lines, $last_line);
        $start = $last_line - $lines_to_get;
        
        $logs = [];
        for ($i = $start; $i <= $last_line; $i++) {
            $file->seek($i);
            $logs[] = trim($file->current());
        }
        
        return array_filter($logs);
    }
    
    public function export_logs() {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/wpml-to-polylang-fixer-logs';
        $export_file = $log_dir . '/export-' . date('Y-m-d-His') . '.zip';
        
        if (!class_exists('ZipArchive')) {
            return new WP_Error('zip_not_available', 'ZipArchive class not available');
        }
        
        $zip = new ZipArchive();
        if ($zip->open($export_file, ZipArchive::CREATE) !== TRUE) {
            return new WP_Error('zip_create_failed', 'Could not create zip file');
        }
        
        $files = glob($log_dir . '/*.log');
        foreach ($files as $file) {
            $zip->addFile($file, basename($file));
        }
        
        $zip->close();
        
        return $export_file;
    }
}
