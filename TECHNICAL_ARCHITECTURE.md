# Technical Architecture Documentation

## System Overview

The WPML to Polylang Migration Fixer is built as a WordPress plugin following MVC architecture patterns with AJAX-driven operations for non-blocking processing.

## Core Architecture Principles

### 1. Separation of Concerns
- **Model**: Database operations (`class-database-helper.php`)
- **View**: UI rendering (`class-ui-renderer.php`, `/admin/views/`)
- **Controller**: Request handling (`class-ajax-handler.php`, `class-admin-handler.php`)

### 2. Batch Processing Architecture
- Prevents PHP timeouts on large datasets
- Configurable batch sizes (default: 50 items)
- Progress tracking with resume capability
- Memory-efficient chunked operations

### 3. Data Integrity
- Database transactions for atomic operations
- Rollback on errors
- Idempotent operations (safe to run multiple times)
- Cache invalidation after updates

## Component Architecture

### Main Plugin File (`wpml-to-polylang-migration-fixer.php`)

```php
class WPML_To_Polylang_Migration_Fixer {
    // Singleton pattern
    private static $instance = null;

    // Component instances
    private $admin_handler;
    private $ajax_handler;

    // Initialize components on 'init' hook
    // Register activation/deactivation hooks
}
```

**Responsibilities:**
- Plugin initialization
- Component loading
- Hook registration
- Singleton management

### Database Helper (`includes/class-database-helper.php`)

```php
class WPML_To_Polylang_Fixer_Database_Helper {
    // WPML data access
    public function get_wpml_post_language($post_id, $post_type)
    public function get_wpml_term_language($term_taxonomy_id, $taxonomy)

    // Polylang operations
    public function ensure_term_language_buckets()
    public function fix_posts_batch($ids)
    public function fix_terms_batch($terms)

    // Specialized handlers
    public function fix_betterdocs_batch($type, $batch_size, $offset)
    public function fix_woocommerce_attributes_batch($batch_size, $offset)

    // Verification
    public function get_comprehensive_verification()
}
```

**Key Design Patterns:**
- Repository pattern for data access
- Batch processing with offset/limit
- Transaction wrapping for data integrity

### Language Converter (`includes/class-language-converter.php`)

```php
class WPML_To_Polylang_Fixer_Language_Converter {
    // Language normalization
    public function canonicalize_slug($code) {
        // Remove pll_ prefix
        // Convert underscores to hyphens
        // Apply variant mappings
    }

    public function ensure_pll_language_exists($slug) {
        // Validate against configured languages
        // Map to canonical if needed
        // Return valid slug or default
    }
}
```

**Mapping Logic:**
```
Input: "pll_en_US"
→ Remove prefix: "en_US"
→ Normalize: "en-us"
→ Check mappings: "en-us" → "en"
→ Validate: "en" exists in Polylang
→ Output: "en"
```

### AJAX Handler (`admin/class-ajax-handler.php`)

```php
class WPML_Fixer_Ajax_Handler {
    // Request verification
    private function verify_request($require_polylang = true)

    // Process routers
    public function handle_process() // Main dispatcher
    public function handle_ensure_buckets()
    public function handle_fix_all_posts()
    public function handle_fix_all_terms()
    public function handle_fix_betterdocs()
    public function handle_fix_woo_attributes()

    // Batch processors
    private function processBatch($processId, $action, $offset, $data)
}
```

**AJAX Flow:**
1. JavaScript initiates request
2. WordPress routes to action handler
3. Handler verifies nonce and permissions
4. Process batch of items
5. Return progress data
6. JavaScript updates UI
7. Repeat until complete

### UI Renderer (`admin/class-ui-renderer.php`)

```php
class WPML_Fixer_UI_Renderer {
    public function render_main_page($system_status)
    public function render_status_page($nonce)

    // Component rendering methods
    private function render_fix_section($type, $data)
    private function render_progress_bar($id)
}
```

**UI Components:**
- Status cards
- Fix sections with progress bars
- Debug console
- Modal dialogs

## Database Schema Usage

### WordPress Core Tables

#### `wp_posts`
- Read: Get posts needing language assignment
- Update: Via `pll_set_post_language()` API

#### `wp_terms`
- Read: Language term definitions
- No direct updates

#### `wp_term_taxonomy`
- Read: Taxonomy relationships
- Insert: Create term_language buckets
- Update: Count adjustments

#### `wp_term_relationships`
- Read: Current language assignments
- Insert: New language assignments
- Delete: Remove corrupted assignments

### WPML Tables (Read-Only)

#### `wp_icl_translations`
```sql
CREATE TABLE wp_icl_translations (
    translation_id bigint(20) NOT NULL AUTO_INCREMENT,
    element_type varchar(36) NOT NULL,
    element_id bigint(20) NOT NULL,
    trid bigint(20) NOT NULL,
    language_code varchar(7) NOT NULL,
    source_language_code varchar(7) DEFAULT NULL,
    PRIMARY KEY (translation_id)
)
```

**Usage:**
- `element_type='post_{type}'` for posts
- `element_type='tax_{taxonomy}'` for terms
- `element_id` = post ID or term_taxonomy_id
- `language_code` = WPML language

### Polylang Data Structure

#### Language Assignment for Posts
```sql
-- Post ID 123 assigned to English
INSERT INTO wp_term_relationships (object_id, term_taxonomy_id)
VALUES (123, [term_taxonomy_id for 'en' in 'language' taxonomy])
```

#### Language Assignment for Terms
```sql
-- Term ID 456 assigned to English
INSERT INTO wp_term_relationships (object_id, term_taxonomy_id)
VALUES (456, [term_taxonomy_id for 'en' in 'term_language' taxonomy])
```

## Processing Algorithms

### Batch Processing Algorithm

```javascript
function processBatch(type, offset) {
    // 1. Request batch from server
    ajax.post({
        action: 'process_' + type,
        offset: offset,
        batch_size: 50
    })

    // 2. Process response
    .then(response => {
        updateProgress(response.processed, response.total)

        // 3. Continue if more items
        if (response.continue) {
            processBatch(type, response.next_offset)
        } else {
            completeProcess(type)
        }
    })
}
```

### Language Assignment Algorithm

```php
function assignLanguage($object_id, $type) {
    // 1. Get current Polylang language
    $current = pll_get_{$type}_language($object_id);

    // 2. Get WPML language
    $wpml = get_wpml_{$type}_language($object_id);

    // 3. Canonicalize
    $target = canonicalize_slug($wpml ?: $current);

    // 4. Ensure exists
    $target = ensure_pll_language_exists($target);

    // 5. Assign if different
    if ($target && $target !== $current) {
        pll_set_{$type}_language($object_id, $target);
        clean_{$type}_cache($object_id);
    }
}
```

## Error Handling Strategy

### Levels of Error Handling

1. **PHP Exceptions**
   - Try/catch blocks around database operations
   - Logged to debug file
   - Transaction rollback on failure

2. **AJAX Errors**
   - HTTP status codes for different failures
   - JSON error responses with messages
   - Client-side retry logic

3. **User Feedback**
   - Visual error states in UI
   - Detailed error messages in debug mode
   - Actionable error recovery suggestions

### Error Recovery

```php
try {
    $wpdb->query('START TRANSACTION');

    // Perform operations
    fix_posts_batch($ids);

    $wpdb->query('COMMIT');
} catch (Exception $e) {
    $wpdb->query('ROLLBACK');
    $this->logger->log_error('Batch failed', $e);
    throw $e; // Re-throw for AJAX handler
}
```

## Performance Optimization

### Query Optimization

1. **Indexed Lookups**
   - Use term_taxonomy_id for joins
   - Leverage WordPress cache API
   - Batch EXISTS queries

2. **Memory Management**
   - Process in chunks of 50 items
   - Unset variables after use
   - Use `wp_cache_flush()` periodically

3. **Database Efficiency**
   - Use `INSERT IGNORE` for idempotency
   - Batch inserts where possible
   - Minimize round trips

### Caching Strategy

```php
// Use WordPress transients for expensive operations
$cache_key = 'wpml_fixer_languages_' . md5(serialize($params));
$cached = get_transient($cache_key);

if ($cached === false) {
    $cached = expensive_operation();
    set_transient($cache_key, $cached, HOUR_IN_SECONDS);
}
```

## Security Measures

### Authentication & Authorization
- Nonce verification on all AJAX requests
- Capability check: `manage_options`
- Admin-only access

### Data Validation
```php
// Input sanitization
$post_id = intval($_POST['post_id']);
$language = sanitize_text_field($_POST['language']);

// SQL injection prevention
$wpdb->prepare("SELECT * FROM {$wpdb->posts} WHERE ID = %d", $post_id);
```

### XSS Prevention
- Escape all output: `esc_html()`, `esc_attr()`
- Sanitize user input
- Use WordPress sanitization functions

## Extension Points

### Filters

```php
// Customize language mappings
apply_filters('wpml_to_polylang_fixer_variant_map', $mappings)

// Exclude content types
apply_filters('wpml_to_polylang_fixer_excluded_post_types', $excluded)
apply_filters('wpml_to_polylang_fixer_excluded_taxonomies', $excluded)

// Default language fallback
apply_filters('wpml_to_polylang_fixer_default_language', $default)
```

### Actions

```php
// Before/after processing hooks
do_action('wpml_fixer_before_fix_posts', $post_ids)
do_action('wpml_fixer_after_fix_posts', $results)
```

## Testing Considerations

### Unit Testing Points
- Language canonicalization logic
- Mapping algorithms
- Batch processing boundaries

### Integration Testing
- WPML data reading
- Polylang API calls
- Database transactions

### Manual Testing Checklist
- [ ] Small batch (10 items)
- [ ] Large batch (1000+ items)
- [ ] Timeout handling
- [ ] Error recovery
- [ ] Language variants
- [ ] Special characters in content

## Debugging Tools

### Debug Logger
```php
$logger = new WPML_To_Polylang_Fixer_Debug_Logger();
$logger->log('Operation started', 'info');
$logger->log_error('Operation failed', $exception);
```

### SQL Debugging
```php
// Enable query logging
define('SAVEQUERIES', true);

// Review queries
global $wpdb;
print_r($wpdb->queries);
```

### JavaScript Debugging
```javascript
// Enable debug mode
window.wpmlFixerAjax.debugEnabled = true;

// Console logging
console.log('WPML Fixer:', data);
```

## Deployment Notes

### Minimum Requirements
- PHP 7.0+ (for type hints and null coalescing)
- WordPress 5.0+ (for REST API support)
- MySQL 5.6+ (for transaction support)

### Recommended Configuration
```
memory_limit = 256M
max_execution_time = 300
post_max_size = 64M
```

### Production Checklist
- [ ] Disable debug logging
- [ ] Set appropriate batch sizes
- [ ] Test on staging first
- [ ] Backup database
- [ ] Monitor error logs
- [ ] Clear caches after migration
