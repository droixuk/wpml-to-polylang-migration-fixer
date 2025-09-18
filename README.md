# WPML to Polylang Migration Fixer Plugin

## Complete Plugin Structure

This WordPress plugin has been restructured from a single snippet into a professional, maintainable plugin with proper architecture, verification, and automated fixes.

-----

## Directory Structure

```
wpml-migration-fixer/
├── wpml-migration-fixer.php         # Main plugin file
├── README.md                        # This file
├── languages/                       # Translation files (optional)
│   └── wpml-migration-fixer.pot
├── includes/                        # Core functionality
│   ├── class-debug-logger.php       # Debug and error logging
│   ├── class-language-converter.php # Language code conversion
│   └── class-database-helper.php    # Database operations & diagnostics
├── admin/                           # Admin functionality
│   ├── class-admin-handler.php      # Admin interface handler
│   ├── class-ajax-handler.php       # AJAX request handler
│   ├── class-ui-renderer.php        # UI rendering
│   └── views/                       # View templates
│       ├── analysis-results.php     # Analysis results template
│       ├── diagnosis-results.php    # Diagnosis results template
│       └── verification-results.php # Verification results template
└── assets/                          # Static resources
    ├── css/
    │   └── admin.css                # Admin styles
    └── js/
        └── admin.js                 # Admin JavaScript
```

-----

## Installation Instructions

1.  **Create the plugin directory:**
    `wp-content/plugins/wpml-migration-fixer/`
2.  **Copy files** into the structure shown above.
3.  **Activate the plugin** in WordPress via `Plugins → Installed Plugins → WPML Fixer → Activate`.
4.  **Access the tool** in `Tools → WPML Fixer`.

-----

## Features

### Core Functionality

  * **Analysis** – Counts posts/terms with and without language assignments.
  * **Diagnosis** – Detects corrupted `pll_` codes and unsupported English variants.
  * **Verification** – Checks translation groups, orphaned languages, and duplicate assignments.
  * **Fixes**:
      * **Posts & Pages** – Assigns languages to posts, pages, and custom post types.
      * **Taxonomies** – Fixes all term taxonomies.
      * **WooCommerce** – Products, variations (inherit parent language), and WC taxonomies.
      * **WooCommerce Attributes** – Ensures `pa_*` terms have a language.
      * **BetterDocs** – Fixes docs, categories, tags, and knowledge bases.
      * **Translation Groups** – Rebuilds Polylang translation containers.
  * **Emergency Fixes**:
      * Remove corrupted `pll_` prefixed codes.
      * Normalize English variants (e.g., `en-GB` → `en`).

### Debug & Monitoring

  * Debug logging with daily files stored in the uploads directory.
  * Performance tracking (items/sec, batch times).
  * Error handling with transaction rollbacks for database safety.
  * Export logs as a ZIP file for support.

### UI Features

  * Accordion interface with clearly defined sections.
  * AJAX-based processing with **progress bars**.
  * Real-time updates during batch runs.
  * Configurable batch size (from 5 to 100).

-----

## Usage

1.  **Run Analysis** → Get quick stats on your posts and terms.
2.  **Run Diagnosis** → Highlight issues like the `pll_` prefix or English variants.
3.  **Apply Fixes** → Run the fixes in the recommended order:
      * Emergency Fix → `pll_` prefix issues.
      * Fix English Variants.
      * Fix Posts & Pages.
      * Fix Taxonomies.
      * Fix WooCommerce (products/variations + categories).
      * Fix Attributes (`pa_*`).
      * Fix BetterDocs.
      * Fix Translation Groups.
4.  **Verify Migration** → Perform a final confirmation that all content is consistent.

-----

## Configuration

### Customizing Excluded Post Types

```php
public function get_excluded_post_types() {
    $excluded = [
        'oembed_cache',
        'wp_global_styles',
        // Add your exclusions here
    ];
    return apply_filters('wpml_fixer_excluded_post_types', $excluded);
}
```

### Customizing Excluded Taxonomies

```php
public function get_excluded_taxonomies() {
    $excluded = [
        'language',
        'post_translations',
        // Add your exclusions here
    ];
    return apply_filters('wpml_fixer_excluded_taxonomies', $excluded);
}
```

## Requirements

  * WordPress 5.0+
  * PHP 7.0+
  * Polylang (free or Pro) must be active.
  * WPML tables (`icl_*`) must be present for reference.

## Hooks & Filters

  * `wpml_fixer_language_mappings` – Customize language code mappings.
  * `wpml_fixer_excluded_post_types` – Modify excluded post types.
  * `wpml_fixer_excluded_taxonomies` – Modify excluded taxonomies.

**Example:**

```php
add_filter('wpml_fixer_language_mappings', function($mappings) {
    $mappings['custom_code'] = 'mapped_code';
    return $mappings;
});
```

## Troubleshooting

  * **Issue:** “WPML data not found”
      * Ensure the original WPML tables still exist in your database.
  * **Issue:** “Polylang not active”
      * Install and activate the Polylang plugin.
  * **Issue:** Timeouts during processing
      * Lower the batch size setting in the plugin's UI (default is 20).

## Support

1.  Enable debug logging in the plugin's settings.
2.  Run the operation that is failing.
3.  Export the logs via `Settings → Export Logs`.
4.  Share the exported ZIP file for review.

## License

GPL v2 or later

## Credits

Based on the original WPML→Polylang migration snippet, restructured into a professional WordPress plugin with verification and automatic fix workflows.