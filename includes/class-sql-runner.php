<?php
/**
 * SQL Runner utility
 *
 * Provides preview and execution helpers for administrator preflight checks.
 * Replaces {{prefix}} tokens with the current table prefix and supports
 * multi-statement payloads.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WMF_SQL_Runner')) {

class WMF_SQL_Runner {

    /** @var wpdb */
    private $wpdb;

    /** @var WPML_To_Polylang_Fixer_Debug_Logger|null */
    private $logger;

    public function __construct($logger = null) {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->logger = $logger instanceof WPML_To_Polylang_Fixer_Debug_Logger ? $logger : null;
    }

    /**
     * Preview SQL (read-only).
     *
     * @param string $sql
     * @param int    $limit
     * @return array
     * @throws Exception
     */
    public function preview($sql, $limit = 50): array {
        $statements = $this->prepare_statements($sql);

        if (empty($statements)) {
            throw new Exception(__('No SQL statements found.', 'wpml-migration-fixer'));
        }

        $results = [];

        foreach ($statements as $rawStatement) {
            $statement = $this->replace_prefix($rawStatement);
            $type = $this->get_statement_type($statement);
            $entry = [
                'statement' => $statement,
                'type' => $type,
            ];

            if ($type === 'select') {
                $previewStatement = $this->ensure_limit($statement, $limit);
                $rows = $this->wpdb->get_results($previewStatement, ARRAY_A);
                if ($this->wpdb->last_error) {
                    throw new Exception($this->wpdb->last_error);
                }

                $entry['rows'] = $rows;
                $entry['row_count'] = is_array($rows) ? count($rows) : 0;
                if ($previewStatement !== $statement) {
                    $entry['applied_statement'] = $previewStatement;
                }
            } else {
                $entry['message'] = __('Skipped during preview (non-SELECT statement).', 'wpml-migration-fixer');
            }

            $results[] = $entry;
        }

        return [
            'statements' => $statements,
            'results' => $results,
            'total' => count($results)
        ];
    }

    /**
     * Execute SQL statements.
     *
     * @param string $sql
     * @return array
     * @throws Exception
     */
    public function execute($sql): array {
        $statements = $this->prepare_statements($sql);

        if (empty($statements)) {
            throw new Exception(__('No SQL statements found.', 'wpml-migration-fixer'));
        }

        $results = [];
        $totalAffected = 0;

        foreach ($statements as $rawStatement) {
            $statement = $this->replace_prefix($rawStatement);
            $type = $this->get_statement_type($statement);
            $entry = [
                'statement' => $statement,
                'type' => $type,
            ];

            if ($type === 'select') {
                $rows = $this->wpdb->get_results($statement, ARRAY_A);
                if ($this->wpdb->last_error) {
                    throw new Exception($this->wpdb->last_error);
                }
                $entry['rows'] = $rows;
                $entry['row_count'] = is_array($rows) ? count($rows) : 0;
            } else {
                $affected = $this->wpdb->query($statement);
                if ($this->wpdb->last_error) {
                    throw new Exception($this->wpdb->last_error);
                }
                $entry['affected_rows'] = is_numeric($affected) ? intval($affected) : 0;
                $totalAffected += intval($affected);
            }

            $results[] = $entry;
        }

        if ($this->logger) {
            $this->logger->log('SQL runner executed ' . count($results) . ' statements', 'info');
        }

        return [
            'statements' => $statements,
            'results' => $results,
            'total_statements' => count($results),
            'total_affected' => $totalAffected
        ];
    }

    /**
     * Replace {{prefix}} tokens with the actual table prefix.
     */
    private function replace_prefix(string $statement): string {
        $prefix = $this->wpdb->prefix;
        return str_replace(['{{prefix}}', '{prefix}'], $prefix, $statement);
    }

    /**
     * Split SQL string into individual statements.
     */
    private function prepare_statements(string $sql): array {
        $buffer = '';
        $statements = [];
        $inSingle = false;
        $inDouble = false;
        $inBacktick = false;
        $length = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $nextChar = $i + 1 < $length ? $sql[$i + 1] : '';

            if ($char === "'" && !$inDouble && !$inBacktick) {
                $escaped = $i > 0 && $sql[$i - 1] === '\\';
                if (!$escaped) {
                    $inSingle = !$inSingle;
                }
            } elseif ($char === '"' && !$inSingle && !$inBacktick) {
                $escaped = $i > 0 && $sql[$i - 1] === '\\';
                if (!$escaped) {
                    $inDouble = !$inDouble;
                }
            } elseif ($char === '`' && !$inSingle && !$inDouble) {
                $inBacktick = !$inBacktick;
            }

            if ($char === ';' && !$inSingle && !$inDouble && !$inBacktick) {
                $trimmed = trim($buffer);
                if ($trimmed !== '') {
                    $statements[] = $trimmed;
                }
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        $trimmed = trim($buffer);
        if ($trimmed !== '') {
            $statements[] = $trimmed;
        }

        return array_filter($statements);
    }

    /**
     * Determine statement type (select/update/etc.).
     */
    private function get_statement_type(string $statement): string {
        $statement = ltrim($statement);
        $keyword = strtoupper(strtok($statement, " \t\n\r"));

        if (in_array($keyword, ['SELECT', 'SHOW', 'DESCRIBE', 'EXPLAIN'], true)) {
            return 'select';
        }

        return strtolower($keyword);
    }

    /**
     * Apply a LIMIT to SELECT queries when previewing (if not already limited).
     */
    private function ensure_limit(string $statement, int $limit): string {
        if (preg_match('/\blimit\b/i', $statement)) {
            return $statement;
        }

        return rtrim($statement) . ' LIMIT ' . intval($limit);
    }
}

}
