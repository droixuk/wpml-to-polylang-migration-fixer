# WPML to Polylang Migration Fixer

A comprehensive WordPress plugin that fixes language assignments and translation groups after migrating from WPML to Polylang. This tool ensures proper language configuration for posts, pages, custom post types, taxonomies, WooCommerce products, and BetterDocs content.

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

### Core Functionality

#### Analysis & Diagnostics
- **Comprehensive Analysis** - Counts posts, pages, and terms with/without language assignments
- **Smart Diagnosis** - Detects corrupted `pll_` language codes and unsupported language variants
- **Migration Verification** - Validates translation groups, identifies orphaned content, and detects duplicate assignments
- **WPML Data Detection** - Checks for existing WPML tables and data remnants

#### Automated Fixes
- **Posts & Pages** - Assigns correct languages to all post types based on WPML data
- **Taxonomies** - Fixes language assignments for all taxonomies (categories, tags, custom)
- **WooCommerce Support**:
  - Products and product variations (inherits parent language)
  - Product categories and tags
  - Product attributes (`pa_*` taxonomies)
- **BetterDocs Integration**:
  - Documentation articles (`docs` post type)
  - Documentation categories and tags
  - Knowledge bases taxonomy
- **Translation Groups** - Rebuilds Polylang translation relationships from WPML data
- **Menu Items** - Fixes language assignments for navigation menus

#### Emergency Fixes
- Remove corrupted `pll_` prefixed language codes
- Normalize language variants (e.g., `en-GB` → `en`, `pt-BR` → `pt`)
- Clean up duplicate language assignments

### Advanced Features

#### Performance & Reliability
- **Batch Processing** - Configurable batch sizes (5-100 items) to prevent timeouts
- **Transaction Safety** - Database rollbacks on errors to prevent data corruption
- **Progress Tracking** - Real-time progress bars with ETA calculations
- **Memory Management** - Optimized queries and chunked processing

#### Debug & Monitoring
- **Detailed Logging** - Daily log files in `wp-content/uploads/wpml-to-polylang-fixer-logs/`
- **Performance Metrics** - Processing speed (items/sec) and batch timing
- **Error Tracking** - Comprehensive error logging with stack traces
- **Log Export** - One-click ZIP export of all logs for support

### User Interface
- **Modern Admin Panel** - Clean, intuitive interface integrated into WordPress admin
- **Accordion Sections** - Organized tools grouped by functionality
- **AJAX Processing** - No page refreshes during operations
- **Real-time Updates** - Live progress bars and status messages
- **Responsive Design** - Works on all screen sizes

-----

## Usage Guide

### Step 1: Initial Analysis
1. Navigate to `Tools → WPML Fixer` in your WordPress admin
2. Click **"Run Analysis"** to get an overview of content needing fixes
3. Review the counts of posts and terms with/without language assignments

### Step 2: Diagnosis
1. Click **"Run Diagnosis"** to identify specific issues
2. Check for:
   - Corrupted `pll_` language codes
   - Unsupported language variants
   - Missing WPML data

### Step 3: Apply Fixes (Recommended Order)
1. **Emergency Fixes** (if needed):
   - Fix `pll_` prefix issues
   - Normalize language variants
2. **Content Fixes**:
   - Fix Posts & Pages
   - Fix Taxonomies
   - Fix WooCommerce Products (if using WooCommerce)
   - Fix Product Attributes
   - Fix BetterDocs (if using BetterDocs)
3. **Relationship Fixes**:
   - Fix Translation Groups (run this last)

### Step 4: Verification
1. Click **"Verify Migration"** for a comprehensive check
2. Review the detailed report showing:
   - Language assignments status
   - Translation group integrity
   - Any remaining issues
3. Re-run specific fixes if issues are detected

-----

## Configuration

### Settings
The plugin provides several configurable options accessible through the admin interface:

- **Batch Size**: Adjust processing batch size (5-100 items) based on server capacity
- **Debug Mode**: Enable detailed logging for troubleshooting
- **Language Mappings**: Configure custom language code mappings

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

## Changelog

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