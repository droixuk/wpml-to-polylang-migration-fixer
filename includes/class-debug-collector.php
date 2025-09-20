<?php
/**
 * Debug Collector Class
 *
 * Collects debug information during operations when debug mode is enabled.
 * Designed for easy removal in production builds.
 *
 * @package WPML_Migration_Fixer
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPML_Fixer_Debug_Collector {

    private $enabled = false;
    private $queries = [];
    private $operations = [];
    private $start_time;
    private $start_memory;
    private $context_stack = [];

    /**
     * Constructor
     */
    public function __construct($enabled = false) {
        // Only enable if debug mode is on AND not in production
        $this->enabled = $enabled && !defined('WPML_FIXER_PRODUCTION');

        if ($this->enabled) {
            $this->start_time = microtime(true);
            $this->start_memory = memory_get_usage();
        }
    }

    /**
     * Check if debug collection is enabled
     */
    public function is_enabled() {
        return $this->enabled;
    }

    /**
     * Push a context onto the stack
     */
    public function push_context($context) {
        if (!$this->enabled) return;
        $this->context_stack[] = $context;
    }

    /**
     * Pop a context from the stack
     */
    public function pop_context() {
        if (!$this->enabled) return;
        array_pop($this->context_stack);
    }

    /**
     * Get current context
     */
    private function get_current_context() {
        return !empty($this->context_stack) ? end($this->context_stack) : '';
    }

    /**
     * Log a SQL query
     */
    public function log_query($sql, $context = '', $result = null) {
        if (!$this->enabled) return;

        $start = microtime(true);

        // Format SQL for readability
        $formatted_sql = $this->format_sql($sql);

        // Determine result metrics
        $rows = 0;
        $affected = 0;
        if (is_array($result)) {
            $rows = count($result);
        } elseif (is_numeric($result)) {
            $affected = $result;
        } elseif (is_object($result) && isset($result->num_rows)) {
            $rows = $result->num_rows;
        }

        $this->queries[] = [
            'sql' => $formatted_sql,
            'raw_sql' => $sql,
            'context' => $context ?: $this->get_current_context(),
            'rows' => $rows,
            'affected' => $affected,
            'time' => round((microtime(true) - $start) * 1000, 3), // milliseconds
            'timestamp' => microtime(true)
        ];
    }

    /**
     * Log an operation
     */
    public function log_operation($action, $details, $status = 'success', $data = null) {
        if (!$this->enabled) return;

        $this->operations[] = [
            'action' => $action,
            'details' => $details,
            'status' => $status,
            'data' => $data,
            'context' => $this->get_current_context(),
            'timestamp' => microtime(true)
        ];
    }

    /**
     * Format SQL for better readability
     */
    private function format_sql($sql) {
        // Remove extra whitespace
        $sql = preg_replace('/\s+/', ' ', trim($sql));

        // Add line breaks for major clauses
        $keywords = ['SELECT', 'FROM', 'LEFT JOIN', 'INNER JOIN', 'WHERE', 'GROUP BY', 'ORDER BY', 'LIMIT', 'INSERT INTO', 'UPDATE', 'DELETE FROM', 'VALUES'];
        foreach ($keywords as $keyword) {
            $sql = preg_replace('/\s+(' . $keyword . ')\s+/i', "\n$1 ", $sql);
        }

        // Indent JOINs
        $sql = preg_replace('/\n(LEFT JOIN|INNER JOIN|RIGHT JOIN)/i', "\n    $1", $sql);

        return trim($sql);
    }

    /**
     * Get collected debug data
     */
    public function get_debug_data() {
        if (!$this->enabled) return null;

        $end_time = microtime(true);
        $end_memory = memory_get_usage();

        return [
            'queries' => $this->queries,
            'operations' => $this->operations,
            'summary' => [
                'total_queries' => count($this->queries),
                'total_operations' => count($this->operations),
                'execution_time' => round(($end_time - $this->start_time) * 1000, 2), // ms
                'memory_used' => $this->format_bytes($end_memory - $this->start_memory),
                'memory_peak' => $this->format_bytes(memory_get_peak_usage()),
                'timestamp_start' => $this->start_time,
                'timestamp_end' => $end_time
            ]
        ];
    }

    /**
     * Clear collected data
     */
    public function clear() {
        $this->queries = [];
        $this->operations = [];
        $this->context_stack = [];
        $this->start_time = microtime(true);
        $this->start_memory = memory_get_usage();
    }

    /**
     * Format bytes to human readable
     */
    private function format_bytes($bytes) {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 2) . ' KB';
        return round($bytes / 1048576, 2) . ' MB';
    }

    /**
     * Get SQL statistics
     */
    public function get_query_stats() {
        if (!$this->enabled || empty($this->queries)) return null;

        $total_time = 0;
        $slowest = ['time' => 0];

        foreach ($this->queries as $query) {
            $total_time += $query['time'];
            if ($query['time'] > $slowest['time']) {
                $slowest = $query;
            }
        }

        return [
            'count' => count($this->queries),
            'total_time' => round($total_time, 2),
            'average_time' => round($total_time / count($this->queries), 2),
            'slowest_query' => $slowest
        ];
    }
}