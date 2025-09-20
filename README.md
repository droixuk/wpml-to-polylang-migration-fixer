# WPML to Polylang Migration Fixer

A comprehensive WordPress plugin that fixes language assignments and translation groups after migrating from WPML to Polylang. This tool ensures proper language configuration for posts, pages, custom post types, taxonomies, WooCommerce products, and BetterDocs content.

## 🚨 Recent Major Update (December 2024)

This plugin has been significantly enhanced to handle complex migration scenarios, particularly for sites using BetterDocs and WooCommerce. The update addresses critical issues where content loses language assignments or has corrupted language codes after WPML → Polylang migration.

-----

## Directory Structure

```
wpml-to-polylang-migration-fixer/
├── wpml-to-polylang-migration-fixer.php    # Main plugin file (v1.1.0)
├── README.md                                # Documentation
├── includes/                                # Core functionality
│   ├── class-debug-logger.php              # Debug and error logging system
│   ├── class-language-converter.php        # WPML to Polylang language code conversion
│   ├── class-database-helper.php           # Database operations & diagnostics
│   └── class-migration-verifier.php        # Comprehensive migration verification
├── admin/                                   # Admin functionality
│   ├── class-admin-handler.php             # Admin interface and menu handler
│   ├── class-ajax-handler.php              # AJAX request processing
│   ├── class-ui-renderer.php               # UI component rendering
│   └── views/                              # View templates
│       └── verification-results-comprehensive.php  # Enhanced verification results
├── assets/                                  # Static resources
│   ├── css/
│   │   └── admin.css                       # Admin panel styles
│   └── js/
│       └── admin.js                        # Admin JavaScript (AJAX, progress bars)
└── backups_cleanup_20250919_181201/        # Backup files (optional)
```

-----

## Installation

1.  **Upload the plugin:**
    - Download or clone this repository
    - Upload to `wp-content/plugins/wpml-to-polylang-migration-fixer/`
2.  **Activate the plugin** in WordPress Admin:
    - Navigate to `Plugins → Installed Plugins`
    - Find "WPML to Polylang Migration Fixer" and click `Activate`
3.  **Access the tool:**
    - Go to `Tools → WPML Fixer` in your WordPress admin panel

-----

## Features

### Core Diagnostics
- **System Status Badges** – Instantly confirm WPML tables, Polylang activation, and the presence of WooCommerce, BetterDocs, and ACF.
- **Comprehensive Verification** – SQL-backed checks for orphaned languages, missing translation groups, and BetterDocs mismatches.
- **WPML Data Detection** – Confirms legacy WPML tables before running automated fixes.

### Automated Fixes
- **Posts & Pages** – Reassigns languages for every public post type using WPML translation data (BetterDocs and WooCommerce included).
- **Taxonomies** – Repairs language assignments for public taxonomies, including WooCommerce attribute taxonomies.
- **BetterDocs Workflow** – Dedicated batch covers docs, FAQs, and BetterDocs taxonomies in one pass.
- **Translation Groups** – Rebuilds Polylang translation sets with multi-phase processing.

### Recovery Tools
- **Ensure Language Buckets** – Creates missing `term_language` containers required by Polylang.
- **Normalize Codes** – Canonicalises corrupted `pll_` slugs and language variants prior to reassignment.
- **Dry-Run Insights** – Detailed per-batch messaging clarifies what changed and what was already correct.

### UX & Observability
- **Batch Processing** – Chunked processing (50 items per request) keeps long migrations responsive.
- **Live Progress** – Progress bars with cumulative counters and per-batch summaries.
- **Debug Logging** – Daily log files in `wp-content/uploads/wpml-to-polylang-fixer-logs/` with optional verbose mode.
- **Status Dashboard** – Tools → *WPML Fixer Status* provides deeper integration checks and SQL preflight tooling.

-----

## Usage Guide

### Step 1: Review Status
1. Navigate to `Tools → WPML Fixer` and confirm the WPML, Polylang, WooCommerce, BetterDocs, and ACF badges.
2. Open `Tools → WPML Fixer Status` for in-depth diagnostics when you need table-level detail or SQL previews.

### Step 2: Prepare the Environment
1. Click **"Ensure Language Buckets"** to make sure Polylang has the required term containers.
2. Run **"Normalize All Language Codes"** whenever you suspect corrupted `pll_` slugs or regional variants.

### Step 3: Apply Fixes (Recommended Order)
1. **Fix All Posts (Comprehensive)** – covers every public post type, including BetterDocs and WooCommerce content.
2. **Fix All Terms (Comprehensive)** – repairs languages for categories, tags, custom taxonomies, and WooCommerce terms.
3. **Fix BetterDocs (Comprehensive)** – reruns targeted passes for docs, FAQs, and BetterDocs taxonomies.
4. **Fix Product Attributes (pa_*)** – run when WooCommerce attributes need dedicated attention.
5. **Fix Translation Groups** – rebuilds post and term groupings once language assignments look healthy.

### Step 4: Verify
1. Click **"Comprehensive Verification"** to generate a post-migration report.
2. Review highlighted issues (remaining null languages, mismatched groups, BetterDocs totals) and rerun individual fixers as needed.

-----

## Configuration

### Settings
The primary runtime toggle lives inside the System Status card:

- **Debug Mode**: Enable detailed logging for troubleshooting (outputs to `wp-content/uploads/wpml-to-polylang-fixer-logs/`).

### Filters & Hooks

#### Exclude Post Types
```php
add_filter('wpml_fixer_excluded_post_types', function($excluded) {
    $excluded[] = 'my_custom_type';  // Add your post type to exclude
    return $excluded;
});
```

#### Exclude Taxonomies
```php
add_filter('wpml_fixer_excluded_taxonomies', function($excluded) {
    $excluded[] = 'my_custom_taxonomy';  // Add your taxonomy to exclude
    return $excluded;
});
```

#### Custom Language Mappings
```php
add_filter('wpml_fixer_language_mappings', function($mappings) {
    $mappings['custom_wpml_code'] = 'polylang_code';
    return $mappings;
});
```

## Requirements

### System Requirements
- **WordPress**: 5.0 or higher
- **PHP**: 7.0 or higher
- **MySQL**: 5.6 or higher

### Plugin Dependencies
- **Polylang**: Free or Pro version must be installed and activated
- **WPML Data**: Original WPML database tables (`icl_*`) must still be present

### Optional Integrations
- **WooCommerce**: For e-commerce product migration
- **BetterDocs**: For documentation migration

## Troubleshooting

### Common Issues

#### "WPML data not found"
- **Cause**: WPML database tables have been removed
- **Solution**: Ensure tables with prefix `icl_` still exist in your database
- **Check**: Look for `wp_icl_translations` table in phpMyAdmin or database manager

#### "Polylang not active"
- **Cause**: Polylang plugin is not installed or activated
- **Solution**:
  1. Install Polylang from WordPress.org repository
  2. Activate the plugin
  3. Configure at least one language

#### Timeouts during processing
- **Cause**: Server timeout limits or large dataset
- **Solutions**:
  - Reduce batch size to 10-20 items (default is 20)
  - Increase PHP `max_execution_time` in php.ini
  - Process content types separately

#### Memory exhausted errors
- **Cause**: Large number of posts/terms being processed
- **Solutions**:
  - Increase PHP `memory_limit` to at least 256M
  - Use smaller batch sizes
  - Run fixes during low-traffic periods

#### JavaScript errors in admin panel
- **Cause**: Conflict with other plugins or themes
- **Solution**:
  1. Check browser console for errors
  2. Temporarily deactivate other plugins to identify conflicts
  3. Ensure jQuery is loaded properly

### Getting Support

1. **Enable Debug Logging**:
   - Go to plugin settings
   - Enable "Debug Mode"
   - Reproduce the issue

2. **Collect Information**:
   - WordPress version
   - PHP version
   - Polylang version
   - Error messages from debug log

3. **Export Logs**:
   - Click "Export Logs" button in plugin settings
   - Download the ZIP file containing all logs

4. **Report Issue**:
   - Include the exported logs
   - Describe steps to reproduce
   - List any custom code or modifications

## Developer Information

### Plugin Architecture
The plugin follows WordPress coding standards and uses a modular architecture:
- **Singleton Pattern**: Main plugin class prevents multiple instantiation
- **Component-based**: Separate classes for each functionality
- **AJAX Processing**: Non-blocking operations for better UX
- **Database Transactions**: Ensures data integrity during batch operations

### Key Classes
- `WPML_To_Polylang_Migration_Fixer`: Main plugin orchestrator
- `WPML_To_Polylang_Fixer_Database_Helper`: Database operations and queries
- `WPML_To_Polylang_Fixer_Language_Converter`: Language code mapping
- `WPML_To_Polylang_Migration_Verifier`: Comprehensive migration verification
- `WPML_Fixer_Admin_Handler`: Admin interface management
- `WPML_Fixer_Ajax_Handler`: AJAX request processing

### Database Tables Used
- **WPML Tables** (read-only):
  - `{prefix}icl_translations`: Source for language assignments
  - `{prefix}icl_strings`: String translations
- **Polylang Tables** (read/write):
  - `{prefix}term_relationships`: Language assignments
  - `{prefix}term_taxonomy`: Translation groups

## Recent Updates & Architecture Changes

### December 2024 Enhancement

#### Problem Addressed
The plugin was enhanced to fix specific migration issues:
- **BetterDocs FAQs** with `WPML='en'` but `PLL=NULL`
- **BetterDocs categories** with WPML language but missing Polylang term_language
- **Language variants** (en-ie, en-au) not mapped correctly
- **Corrupted codes** with `pll_` prefixes

#### New Architecture Components

##### 1. Language Canonicalization System (`class-language-converter.php`)
```php
// New methods added:
canonicaliz e_slug($code)        // Normalizes codes: en_US → en-us, removes pll_ prefix
ensure_pll_language_exists($slug) // Validates language exists or maps to canonical
```
- Configurable variant mappings (en-au → en-gb)
- Filter: `wpml_to_polylang_fixer_variant_map`
- Filter: `wpml_to_polylang_fixer_default_language`

##### 2. Enhanced Database Helper (`class-database-helper.php`)
```php
// Critical new methods:
ensure_term_language_buckets()    // Pre-flight check for Polylang buckets
fix_posts_batch($ids)              // Comprehensive post fixer with WPML fallback
fix_terms_batch($terms)            // Term fixer using term_taxonomy_id
fix_betterdocs_batch()             // BetterDocs-specific handler
fix_woocommerce_attributes_batch() // WooCommerce pa_* attributes
get_comprehensive_verification()    // Full diagnostic suite
```

##### 3. New AJAX Handlers (`class-ajax-handler.php`)
- `wmf_ensure_buckets` - Creates missing term_language entries
- `wmf_normalize_languages` - Batch language canonicalization
- `wmf_fix_all_posts` - Enhanced post processing
- `wmf_fix_all_terms` - Enhanced term processing
- `wmf_fix_betterdocs` - BetterDocs-specific
- `wmf_fix_woo_attributes` - Product attributes

##### 4. UI Enhancements (`class-ui-renderer.php`)
New buttons added:
- "Ensure Language Buckets" - Pre-flight safeguard
- "Normalize All Language Codes" - Fix variants/corruption
- "Fix All Posts (Comprehensive)" - New enhanced fixer
- "Fix All Terms (Comprehensive)" - New enhanced fixer
- "Fix BetterDocs (Comprehensive)" - Dedicated handler
- "Fix Product Attributes (pa_*)" - WooCommerce specific

#### SQL Operations Flow

1. **Pre-flight Checks**
```sql
-- Ensure term_language buckets exist
INSERT IGNORE INTO {prefix}term_taxonomy (term_id, taxonomy)
SELECT term_id, 'term_language' FROM ...
```

2. **Post Language Assignment**
```sql
-- Get WPML language
SELECT language_code FROM {prefix}icl_translations
WHERE element_id={post_id} AND element_type='post_{type}'
-- Then use pll_set_post_language()
```

3. **Term Language Assignment**
```sql
-- Get WPML language by term_taxonomy_id
SELECT language_code FROM {prefix}icl_translations
WHERE element_id={term_taxonomy_id} AND element_type='tax_{taxonomy}'
-- Then use pll_set_term_language()
```

#### Key Design Decisions

1. **Idempotent Operations**: All fixes can be run multiple times safely
2. **WPML Fallback**: When WPML data missing, uses Polylang default
3. **Batch Processing**: 50 items default to prevent timeouts
4. **Transaction Safety**: Database operations wrapped in transactions
5. **Cache Clearing**: Automatic after each fix operation

#### Recommended Workflow

1. **Normalize Language Codes** - Fix corrupted/variant codes first
2. **Ensure Language Buckets** - Create missing Polylang structures
3. **Fix All Posts** - Assign languages from WPML data
4. **Fix All Terms** - Fix taxonomies including pa_*
5. **Fix BetterDocs** (if active) - Handle docs/FAQs specifically
6. **Fix Translation Groups** - Rebuild relationships
7. **Run Verification** - Confirm all fixed

## Changelog

### Version 1.2.1 (January 2025)
- Added System Status badges for WooCommerce, BetterDocs, and ACF to the main dashboard.
- Ensured comprehensive fixers report cumulative progress and reliable per-batch summaries.
- Updated the BetterDocs fixer to cover posts, FAQs, and taxonomies within a single run.
- Refreshed documentation so the workflow mirrors the current UI and tooling.

### Version 1.2.0 (December 2024)
- Added comprehensive language canonicalization system
- Implemented pre-flight term_language bucket checks
- Enhanced batch processing with WPML data fallback
- Added BetterDocs FAQ and category specific fixes
- Added WooCommerce product attribute (pa_*) support
- New comprehensive verification with SQL diagnostics
- Improved UI with dedicated fix buttons per content type
- Added configurable language variant mappings
- Transaction safety for all database operations
- Automatic cache clearing after fixes

### Version 1.1.0
- Added comprehensive migration verifier
- Enhanced BetterDocs support
- Improved progress tracking with ETA
- Added memory usage monitoring
- Stabilized JavaScript event handlers
- Enhanced error reporting

### Version 1.0.0
- Initial release
- Core migration functionality
- WooCommerce support
- Basic verification tools

## License

This plugin is licensed under the GPL v2 or later.

```
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.
```

## Author

Developed for WordPress sites migrating from WPML to Polylang.

## Acknowledgments

- Built upon community knowledge and best practices for WPML to Polylang migration
- Uses WordPress core APIs for maximum compatibility
- Integrates with Polylang's translation management system
