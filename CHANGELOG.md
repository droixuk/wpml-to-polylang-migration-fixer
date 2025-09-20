# Changelog

All notable changes to the WPML to Polylang Migration Fixer plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.0] - 2024-12-20

### Added

#### Core Features
- **Language Canonicalization System** - Comprehensive language code normalization
  - `canonicalize_slug()` method to fix corrupted codes (pll_ prefixes, underscores)
  - `ensure_pll_language_exists()` for language validation and mapping
  - Configurable variant mappings (en-au → en-gb, en-ie → en-gb)
  - New filter: `wpml_to_polylang_fixer_variant_map`
  - New filter: `wpml_to_polylang_fixer_default_language`

- **Pre-flight Safeguards**
  - `ensure_term_language_buckets()` to create missing Polylang structures
  - Automatic detection of missing term_language entries
  - One-click fix for structural issues

- **Enhanced Batch Processing**
  - `fix_posts_batch()` - Comprehensive post fixer with WPML fallback
  - `fix_terms_batch()` - Term fixer using term_taxonomy_id
  - Transaction safety for all operations
  - Automatic cache clearing after fixes

#### BetterDocs Support
- Dedicated `fix_betterdocs_batch()` handler
- Support for `docs` and `betterdocs_faq` post types
- Fixes for BetterDocs taxonomies:
  - `doc_category`
  - `doc_tag`
  - `knowledge_base`
  - `betterdocs_faq_category`
- Specific handling for FAQ posts with NULL languages

#### WooCommerce Enhancements
- `fix_woocommerce_attributes_batch()` for product attributes
- Automatic detection of all `pa_*` taxonomies
- Dedicated UI button for attribute fixing

#### UI Improvements
- New comprehensive fix buttons:
  - "Ensure Language Buckets" - Pre-flight check
  - "Normalize All Language Codes" - Fix variants/corruption
  - "Fix All Posts (Comprehensive)" - Enhanced post processor
  - "Fix All Terms (Comprehensive)" - Enhanced term processor
  - "Fix BetterDocs (Comprehensive)" - Plugin-specific
  - "Fix Product Attributes (pa_*)" - WooCommerce specific
- Real-time progress with processed/fixed counts
- Better organization of fix sections

#### AJAX Handlers
- `wmf_ensure_buckets` - Create missing buckets
- `wmf_normalize_languages` - Batch normalization
- `wmf_fix_all_posts` - Comprehensive post fixing
- `wmf_fix_all_terms` - Comprehensive term fixing
- `wmf_fix_betterdocs` - BetterDocs handler
- `wmf_fix_woo_attributes` - WooCommerce attributes

#### Verification System
- `get_comprehensive_verification()` method
- SQL queries from migration playbook
- BetterDocs-specific checks
- Detailed recommendations based on findings
- Terms per language breakdown

### Changed

#### Database Operations
- Improved idempotency - all operations safe to run multiple times
- Better WPML data fallback when source missing
- Enhanced error handling with transaction rollbacks
- Optimized queries for large datasets

#### Language Converter
- Refactored to handle more edge cases
- Better handling of corrupted data
- Improved variant mapping logic
- Fallback to Polylang default when mapping fails

### Fixed
- BetterDocs FAQ posts showing NULL language despite WPML data
- BetterDocs categories missing term_language assignments
- English variants (en-ie, en-au) not mapping correctly
- Corrupted language codes with pll_ prefixes
- WooCommerce product attributes language assignments
- Cache not clearing after language updates

### Technical Improvements
- Added `lang_converter` property to Database Helper
- Improved memory efficiency in batch processing
- Better nonce handling in AJAX requests
- Enhanced debug logging throughout

## [1.1.3] - 2024-12-19

### Added
- Status dashboard with system overview
- SQL preflight tools for advanced users
- Enhanced verification loading states
- WooCommerce UI improvements

### Changed
- Improved JavaScript event handling
- Better progress display clarity
- Enhanced timeout prevention

### Fixed
- Verification timeout on large sites
- Progress bar display issues
- AJAX nonce verification

## [1.1.2] - 2024-12-18

### Added
- Enhanced BetterDocs support
- Improved taxonomy handling
- Translation groups repair

### Changed
- Better error reporting
- Improved batch processing

## [1.1.1] - 2024-12-17

### Fixed
- JavaScript initialization issues
- Progress tracking bugs

## [1.1.0] - 2024-12-15

### Added
- Comprehensive migration verifier
- Progress tracking with ETA
- Memory usage monitoring
- Debug console in UI

### Changed
- Improved UI responsiveness
- Better error messages
- Enhanced logging system

### Fixed
- Timeout issues on large sites
- Memory exhaustion problems

## [1.0.0] - 2024-12-01

### Added
- Initial release
- Core migration functionality
- Basic post and term fixing
- WooCommerce support
- Simple verification tools
- Batch processing system
- AJAX-driven interface

## Migration Guide

### From 1.1.x to 1.2.0

1. **New Features Available**
   - Run "Ensure Language Buckets" before other fixes
   - Use "Normalize Language Codes" for variant issues
   - Try new "Comprehensive" buttons for better results

2. **Changed Workflows**
   - Old "Fix Posts" still works but "Fix All Posts (Comprehensive)" is recommended
   - Same for terms - use comprehensive version

3. **No Breaking Changes**
   - All existing functionality preserved
   - New features are additions, not replacements

### From 1.0.x to 1.2.0

1. **Backup First**
   - Database structure unchanged but backup recommended

2. **New Workflow**
   - Start with "Comprehensive Verification"
   - Use new fix buttons instead of old ones
   - Run "Ensure Language Buckets" first

3. **Better Results**
   - More accurate language detection
   - Handles edge cases better
   - Fixes BetterDocs and WooCommerce issues