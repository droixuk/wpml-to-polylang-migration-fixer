# WPML to Polylang Migration Fixer Plugin

## Complete Plugin Structure

This WordPress plugin has been restructured from a single snippet into a professional, maintainable plugin with proper architecture and error handling.

## Directory Structure

```
wpml-migration-fixer/
├── wpml-migration-fixer.php         # Main plugin file
├── README.md                         # This file
├── languages/                        # Translation files (optional)
│   └── wpml-migration-fixer.pot
├── includes/                         # Core functionality
│   ├── class-debug-logger.php       # Debug and error logging
│   ├── class-language-converter.php  # Language code conversion
│   └── class-database-helper.php    # Database operations
├── admin/                            # Admin functionality
│   ├── class-admin-handler.php      # Admin interface handler
│   ├── class-ajax-handler.php       # AJAX request handler
│   ├── class-ui-renderer.php        # UI rendering
│   └── views/                        # View templates
│       ├── analysis-results.php     # Analysis results template
│       ├── diagnosis-results.php    # Diagnosis results template
│       └── verification-results.php # Verification results template
└── assets/                           # Static resources
    ├── css/
    │   └── admin.css                 # Admin styles
    └── js/
        └── admin.js                  # Admin JavaScript
```

## Installation Instructions

1. **Create the plugin directory:**
   ```
   wp-content/plugins/wpml-migration-fixer/
   ```

2. **Create the file structure as shown above**

3. **Copy the provided code files into their respective locations:**
   - Main plugin file: `wpml-migration-fixer.php`
   - Include files in `includes/` directory
   - Admin files in `admin/` directory
   - Asset files in `assets/css/` and `assets/js/` directories

4. **Create the view templates** (simplified versions below):

### admin/views/analysis-results.php
```php
<?php
if (!defined('ABSPATH')) exit;
?>
<h3><?php _e('Content Analysis Results', 'wpml-migration-fixer'); ?></h3>
<div class="quick-stats">
    <div class="stat-box">
        <div class="stat-number"><?php echo $stats['posts_with_language']; ?></div>
        <div class="stat-label"><?php _e('Posts with Language', 'wpml-migration-fixer'); ?></div>
    </div>
    <div class="stat-box">
        <div class="stat-number"><?php echo $stats['posts_without_language']; ?></div>
        <div class="stat-label"><?php _e('Posts Missing Language', 'wpml-migration-fixer'); ?></div>
    </div>
    <div class="stat-box">
        <div class="stat-number"><?php echo $stats['terms_with_language']; ?></div>
        <div class="stat-label"><?php _e('Terms with Language', 'wpml-migration-fixer'); ?></div>
    </div>
    <div class="stat-box">
        <div class="stat-number"><?php echo $stats['terms_without_language']; ?></div>
        <div class="stat-label"><?php _e('Terms Missing Language', 'wpml-migration-fixer'); ?></div>
    </div>
</div>
```

### admin/views/diagnosis-results.php
```php
<?php
if (!defined('ABSPATH')) exit;
?>
<h3><?php _e('Language Assignment Diagnosis', 'wpml-migration-fixer'); ?></h3>
<?php if (!empty($problematic['pll_prefixed'])): ?>
<div style="margin-top: 15px; padding: 15px; background: #ffebee; border-radius: 8px; border-left: 4px solid #f44336;">
    <strong>🚨 <?php _e('CRITICAL DATA CORRUPTION DETECTED!', 'wpml-migration-fixer'); ?></strong><br>
    <?php _e('Invalid pll_ prefixed codes found:', 'wpml-migration-fixer'); ?> <?php echo implode(', ', $problematic['pll_prefixed']); ?><br>
    <strong><?php _e('Use the EMERGENCY FIX button immediately to restore your content!', 'wpml-migration-fixer'); ?></strong>
</div>
<?php endif; ?>

<?php if (!empty($problematic['english_variants'])): ?>
<div style="margin-top: 15px; padding: 15px; background: #fff3cd; border-radius: 8px; border-left: 4px solid #ffc107;">
    <strong>⚠️ <?php _e('English Variants Detected:', 'wpml-migration-fixer'); ?></strong> <?php echo implode(', ', $problematic['english_variants']); ?><br>
    <?php _e('Content is assigned to variants not configured in Polylang.', 'wpml-migration-fixer'); ?>
</div>
<?php endif; ?>
```

### admin/views/verification-results.php
```php
<?php
if (!defined('ABSPATH')) exit;
?>
<h3><?php _e('Migration Verification', 'wpml-migration-fixer'); ?></h3>
<div class="quick-stats">
    <div class="stat-box">
        <div class="stat-label"><?php _e('Translation Groups', 'wpml-migration-fixer'); ?></div>
        <div class="stat-number"><?php echo intval($verification['translation_groups']); ?></div>
    </div>
    <div class="stat-box">
        <div class="stat-label"><?php _e('Orphaned Languages', 'wpml-migration-fixer'); ?></div>
        <div class="stat-number"><?php echo intval($verification['orphaned_languages']); ?></div>
    </div>
    <div class="stat-box">
        <div class="stat-label"><?php _e('Duplicate Assignments', 'wpml-migration-fixer'); ?></div>
        <div class="stat-number"><?php echo intval($verification['duplicate_assignments']); ?></div>
    </div>
</div>
```

## Features

### Core Functionality
- **Language Assignment Fix**: Corrects language assignments for posts, pages, and custom post types
- **Taxonomy Language Fix**: Fixes language assignments for all taxonomies
- **Translation Groups**: Links translated content together
- **WooCommerce Support**: Special handling for products, variations, and attributes
- **BetterDocs Support**: Fixes documentation post types and taxonomies
- **Emergency Fixes**: Handles corrupted pll_ prefixed language codes
- **English Variant Fix**: Reassigns content from wrong English variants

### Debug & Monitoring
- **Debug Logging**: Comprehensive logging system with daily log files
- **Performance Tracking**: Logs processing times and items per second
- **Error Handling**: Try-catch blocks and transaction rollbacks
- **Log Export**: Export logs as ZIP for troubleshooting

### UI Features
- **Accordion Interface**: Same clean UI as original snippet
- **Progress Bars**: Visual feedback during processing
- **Real-time Updates**: AJAX-based processing without page reloads
- **Batch Processing**: Prevents timeouts with configurable batch sizes

### Settings
- **Debug Mode**: Enable/disable debug logging
- **Batch Size**: Configure processing batch size (5-100 items)
- **Excluded Types**: Select post types to exclude from processing

## Configuration

### Customizing Excluded Post Types
Edit the `get_excluded_post_types()` method in `includes/class-database-helper.php`:

```php
public function get_excluded_post_types() {
    $excluded = [
        'oembed_cache',
        'wp_global_styles',
        // Add your custom exclusions here
    ];
    
    return apply_filters('wpml_fixer_excluded_post_types', $excluded);
}
```

### Customizing Excluded Taxonomies
Edit the `get_excluded_taxonomies()` method in `includes/class-database-helper.php`:

```php
public function get_excluded_taxonomies() {
    $excluded = [
        'language',
        'post_translations',
        // Add your custom exclusions here
    ];
    
    return apply_filters('wpml_fixer_excluded_taxonomies', $excluded);
}
```

## Usage

1. **Activate the Plugin**: Go to Plugins → Installed Plugins → Activate

2. **Access the Tool**: Navigate to Tools → WPML Fixer

3. **Run Analysis**: Click "Run Analysis" to check content status

4. **Run Diagnosis**: Click "Run Language Diagnosis" to identify issues

5. **Apply Fixes**: Use the appropriate fix buttons based on diagnosis:
   - Emergency Fix for pll_ prefix issues
   - Fix English Variants if needed
   - Fix Posts & Pages
   - Fix Taxonomies
   - Fix WooCommerce (if applicable)
   - Fix Translation Groups

6. **Verify**: Click "Verify Migration" to confirm everything is fixed

## Requirements

- WordPress 5.0+
- PHP 7.0+
- Polylang or Polylang Pro (must be active)
- WPML data tables (for migration)

## Hooks & Filters

### Available Filters
- `wpml_fixer_language_mappings`: Customize language code mappings
- `wpml_fixer_excluded_post_types`: Modify excluded post types
- `wpml_fixer_excluded_taxonomies`: Modify excluded taxonomies

### Example Usage
```php
add_filter('wpml_fixer_language_mappings', function($mappings) {
    $mappings['custom_code'] = 'mapped_code';
    return $mappings;
});
```

## Troubleshooting

### Debug Mode
Enable debug mode in Settings to see detailed logs:
1. Go to Tools → WPML Fixer Settings
2. Check "Enable Debug Logging"
3. View logs in the same settings page

### Common Issues

**Issue**: "WPML data not found"
- **Solution**: Ensure WPML tables still exist in database

**Issue**: "Polylang not active"
- **Solution**: Install and activate Polylang before using this tool

**Issue**: Processing timeout
- **Solution**: Reduce batch size in settings (default is 20)

## Support

For issues or questions:
1. Enable debug logging
2. Run the problematic operation
3. Export logs from Settings page
4. Review logs for error messages

## License

GPL v2 or later

## Credits

Based on the original WPML to Polylang migration fix snippet, restructured into a professional WordPress plugin with enhanced features and error handling.
