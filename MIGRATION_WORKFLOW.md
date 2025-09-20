# WPML to Polylang Migration Fix Workflow

This document provides the complete workflow for fixing language assignments after WPML to Polylang migration.

## Understanding the Problem

When migrating from WPML to Polylang, language assignments are stored differently:

### WPML Storage
- **Posts**: Language stored in `icl_translations` table with `element_type='post_{type}'`
- **Terms**: Language stored in `icl_translations` table with `element_type='tax_{taxonomy}'`
- Uses `element_id` for posts, `term_taxonomy_id` for terms

### Polylang Storage
- **Posts**: Language stored as term relationship to 'language' taxonomy
- **Terms**: Language stored as term relationship to 'term_language' taxonomy
- Requires matching "bucket" entries in both taxonomies

## Pre-Migration Checklist

Before starting the fix process:

1. ✅ **Backup your database**
2. ✅ **Verify Polylang is active** with at least one language configured
3. ✅ **Check WPML tables exist** (`wp_icl_translations` should be present)
4. ✅ **Test on staging first** if possible

## Step-by-Step Fix Workflow

### Phase 1: Initial Assessment

#### 1.1 Run Comprehensive Verification
```
Navigate to: Tools → WPML Fixer
Click: "🔍 Comprehensive Verification"
```

Review the report for:
- Languages configured in Polylang
- Posts without language assignments
- Terms without language assignments
- BetterDocs content issues
- Translation group status

#### 1.2 Check for Critical Issues
Look for:
- ❌ Missing term_language buckets
- ❌ Corrupted language codes (pll_ prefix)
- ❌ Unmapped language variants (en-ie, en-au)

### Phase 2: Pre-Flight Fixes

#### 2.1 Ensure Language Buckets
```
Click: "Ensure Language Buckets"
```
This creates missing `term_language` entries that Polylang requires.

**What it does:**
```sql
INSERT IGNORE INTO term_taxonomy (term_id, taxonomy)
SELECT term_id, 'term_language'
FROM terms WHERE taxonomy='language'
```

### Phase 3: Content Language Assignment

#### 3.1 Fix Posts and Pages
```
Click: "Fix All Posts (Comprehensive)"
```

**Process:**
1. Queries WPML data for each post
2. Canonicalizes the language code
3. Assigns via `pll_set_post_language()`
4. Falls back to default language if no WPML data

**Includes:**
- Standard posts and pages
- Custom post types
- BetterDocs docs
- WooCommerce products

#### 3.2 Fix Taxonomies
```
Click: "Fix All Terms (Comprehensive)"
```

**Process:**
1. Queries WPML data by `term_taxonomy_id`
2. Canonicalizes the language code
3. Assigns via `pll_set_term_language()`
4. Handles all public taxonomies

**Includes:**
- Categories and tags
- Custom taxonomies
- WooCommerce product categories
- BetterDocs taxonomies

### Phase 4: Plugin-Specific Fixes

#### 4.1 BetterDocs (if active)
```
Click: "Fix BetterDocs (Comprehensive)"
```

**Handles:**
- `docs` post type
- `betterdocs_faq` post type
- `doc_category` taxonomy
- `doc_tag` taxonomy
- `knowledge_base` taxonomy
- `betterdocs_faq_category` taxonomy

#### 4.2 WooCommerce Attributes (if active)
```
Click: "Fix Product Attributes (pa_*)"
```

**Handles:**
- All `pa_*` taxonomies (product attributes)
- Attribute terms language assignment

### Phase 5: Final Verification

#### 5.1 Run Final Verification
```
Click: "🔍 Comprehensive Verification"
```

#### 5.2 Check Results
All counts should show:
- ✅ 0 posts without language
- ✅ 0 terms without language
- ✅ 0 BetterDocs issues

## Troubleshooting Specific Issues

### Issue: BetterDocs FAQs Show NULL Language

**SQL to Check:**
```sql
SELECT p.ID, p.post_title,
       it.language_code as wpml_lang,
       t.slug as pll_lang
FROM wp_posts p
LEFT JOIN wp_icl_translations it ON it.element_id=p.ID
LEFT JOIN wp_term_relationships tr ON tr.object_id=p.ID
LEFT JOIN wp_term_taxonomy tt ON tt.term_taxonomy_id=tr.term_taxonomy_id
LEFT JOIN wp_terms t ON t.term_id=tt.term_id
WHERE p.post_type='betterdocs_faq'
AND tt.taxonomy='language';
```

**Fix:** Run "Fix BetterDocs (Comprehensive)"

### Issue: Categories Missing term_language

**SQL to Check:**
```sql
SELECT tt.term_id, tt.taxonomy,
       it.language_code as wpml_lang,
       EXISTS(
         SELECT 1 FROM wp_term_relationships tr2
         JOIN wp_term_taxonomy tt2 ON tt2.term_taxonomy_id=tr2.term_taxonomy_id
         WHERE tr2.object_id=tt.term_id AND tt2.taxonomy='term_language'
       ) as has_pll_lang
FROM wp_term_taxonomy tt
LEFT JOIN wp_icl_translations it ON it.element_id=tt.term_taxonomy_id
WHERE tt.taxonomy='betterdocs_faq_category';
```

**Fix:** Run "Fix All Terms (Comprehensive)"

### Issue: English Variants Not Recognized

**Problem:** Content with en-ie, en-au not mapping to configured languages

**Fix Process:**
1. Update variant mappings if needed (see filter below)
2. Rerun the comprehensive post and term fixers

**Custom Mapping:**
```php
add_filter('wpml_to_polylang_fixer_variant_map', function($map) {
    $map['en-ie'] = 'en';  // Map Irish English to base English
    $map['en-au'] = 'en';  // Map Australian English to base English
    return $map;
});
```

## Batch Processing Guidelines

### For Large Sites (10,000+ items)

1. **Reduce Batch Size**
   - Default: 50 items
   - Large sites: 20-30 items
   - Very large: 10 items

2. **Process in Stages**
   - Fix post types separately
   - Fix taxonomies separately
   - Run during low traffic

3. **Monitor Resources**
   - Check PHP memory usage
   - Watch for timeouts
   - Review debug logs

### Performance Tips

1. **Increase PHP Limits**
```
memory_limit = 512M
max_execution_time = 600
```

2. **Database Optimization**
   - Run `OPTIMIZE TABLE` on affected tables
   - Ensure indexes are intact

3. **Clear Caches**
   - Object cache
   - Page cache
   - CDN cache

## Validation Queries

### Check Posts Language Assignment
```sql
-- Posts without Polylang language
SELECT COUNT(*) FROM wp_posts p
WHERE p.post_status IN ('publish','draft','private')
AND NOT EXISTS (
    SELECT 1 FROM wp_term_relationships tr
    JOIN wp_term_taxonomy tt ON tt.term_taxonomy_id=tr.term_taxonomy_id
    WHERE tr.object_id=p.ID AND tt.taxonomy='language'
);
```

### Check Terms Language Assignment
```sql
-- Terms without Polylang language
SELECT COUNT(*) FROM wp_terms t
JOIN wp_term_taxonomy tt ON tt.term_id=t.term_id
WHERE NOT EXISTS (
    SELECT 1 FROM wp_term_relationships tr
    JOIN wp_term_taxonomy tl ON tl.term_taxonomy_id=tr.term_taxonomy_id
    WHERE tr.object_id=t.term_id AND tl.taxonomy='term_language'
);
```

### Verify Language Buckets
```sql
-- Check term_language buckets exist
SELECT l.slug as language,
       EXISTS(SELECT 1 FROM wp_term_taxonomy WHERE term_id=l.term_id AND taxonomy='term_language') as has_bucket
FROM wp_terms l
JOIN wp_term_taxonomy lt ON lt.term_id=l.term_id
WHERE lt.taxonomy='language';
```

## Recovery Procedures

### If Something Goes Wrong

1. **Stop the Current Process**
   - Refresh the page to halt AJAX operations
   - Check debug logs for errors

2. **Restore from Backup** (if needed)
   ```sql
   -- Example restoration
   RESTORE TABLE wp_term_relationships FROM 'backup.sql';
   ```

3. **Clear Corrupt Data**
   ```sql
   -- Remove corrupted language assignments
   DELETE tr FROM wp_term_relationships tr
   JOIN wp_term_taxonomy tt ON tt.term_taxonomy_id=tr.term_taxonomy_id
   JOIN wp_terms t ON t.term_id=tt.term_id
   WHERE tt.taxonomy IN ('language','term_language')
   AND t.slug LIKE 'pll_%';
   ```

4. **Re-run Fixes**
   - Start with "Ensure Language Buckets"
   - Continue with normal workflow

## Success Indicators

Your migration fix is complete when:

✅ Comprehensive Verification shows 0 issues
✅ All content displays correct language in Polylang
✅ Language switcher works correctly
✅ Translated content is properly linked
✅ No pll_ prefixed codes remain
✅ All BetterDocs/WooCommerce content has languages

## Need Help?

1. Enable Debug Mode in the plugin
2. Run the problematic operation
3. Export logs via the plugin interface
4. Include the verification report
5. Note any custom code or plugins that might interfere
