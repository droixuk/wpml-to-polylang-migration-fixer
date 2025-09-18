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
    
    private $log_dir;
    private $log_file;
    private $debug_enabled;
    
    public function __construct() {
        $upload_dir = wp_upload_dir();
        $this->log_dir = $upload_dir['basedir'] . '/wpml-to-polylang-fixer-logs';
        $this->log_file = $this->log_dir . '/debug-' . date('Y-m-d') . '.log';
        $this->debug_enabled = get_option('wpml_to_polylang_fixer_debug_enabled', false);
        
        // Ensure log directory exists
        if (!file_exists($this->log_dir)) {
            wp_mkdir_p($this->log_dir);
            $htaccess = $this->log_dir . '/.htaccess';
            if (!file_exists($htaccess)) {
                file_put_contents($htaccess, 'Deny from all');
            }
        }
    }
    
    /**
     * Log a message
     */
    public function log($message, $level = 'info') {
        if (!$this->debug_enabled && $level !== 'error') {
            return;
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $formatted_message = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
        
        error_log($formatted_message, 3, $this->log_file);
    }
    
    /**
     * Log an error with exception details
     */
    public function log_error($message, $exception = null) {
        $error_message = $message;
        if ($exception) {
            $error_message .= ' - ' . $exception->getMessage();
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $error_message .= ' in ' . $exception->getFile() . ':' . $exception->getLine();
            }
        }
        $this->log($error_message, 'error');
    }
    
    /**
     * Log performance metrics
     */
    public function log_performance($operation, $time_taken, $items_processed = 0) {
        if (!$this->debug_enabled) {
            return;
        }
        
        $message = "Performance: {$operation} took {$time_taken}s";
        if ($items_processed > 0) {
            $message .= " ({$items_processed} items)";
        }
        $this->log($message, 'performance');
    }
    
    /**
     * Get recent log entries
     */
    public function get_recent_logs($lines = 50) {
        if (!file_exists($this->log_file)) {
            return [];
        }
        
        $file = file($this->log_file);
        if (!$file) {
            return [];
        }
        
        return array_slice($file, -$lines);
    }
    
    /**
     * Export logs as ZIP file
     */
    public function export_logs() {
        if (!class_exists('ZipArchive')) {
            return new WP_Error('zip_not_available', 'ZIP extension not available');
        }
        
        $zip_file = $this->log_dir . '/logs-export-' . time() . '.zip';
        $zip = new ZipArchive();
        
        if ($zip->open($zip_file, ZipArchive::CREATE) !== TRUE) {
            return new WP_Error('zip_create_failed', 'Cannot create ZIP file');
        }
        
        $log_files = glob($this->log_dir . '/*.log');
        foreach ($log_files as $log_file) {
            $zip->addFile($log_file, basename($log_file));
        }
        
        $zip->close();
        
        return $zip_file;
    }
    
    /**
     * Clear old log files
     */
    public function clear_old_logs($days = 7) {
        $log_files = glob($this->log_dir . '/*.log');
        $cutoff_time = time() - ($days * 24 * 60 * 60);
        
        foreach ($log_files as $log_file) {
            if (filemtime($log_file) < $cutoff_time) {
                unlink($log_file);
            }
        }
    }
}