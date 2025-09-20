<?php
if (!defined('ABSPATH')) exit;
?>
<h3><?php _e('Comprehensive Migration Verification', 'wpml-to-polylang-migration-fixer'); ?></h3>

<!-- Overall Status -->
<div class="verification-status <?php echo $results['overall_status'] === 'success' ? 'status-success' : 'status-error'; ?>" 
     style="padding: 15px; margin-bottom: 20px; border-radius: 8px;">
    <?php if ($results['overall_status'] === 'success'): ?>
        <h4 style="margin: 0; color: #2e7d32;">✅ Migration Verification: PASSED</h4>
        <p style="margin: 5px 0 0 0;">All critical components have been successfully migrated from WPML to Polylang.</p>
    <?php else: ?>
        <h4 style="margin: 0; color: #c62828;">❌ Migration Verification: ISSUES FOUND</h4>
        <p style="margin: 5px 0 0 0;">
            <strong><?php echo $results['total_critical_issues']; ?> critical issues</strong> need attention.
        </p>
    <?php endif; ?>
</div>

<!-- Enhanced Quick Stats with BetterDocs -->
<div class="quick-stats">
    <div class="stat-box">
        <div class="stat-label"><?php _e('Languages', 'wpml-to-polylang-migration-fixer'); ?></div>
        <div class="stat-number">
            <?php echo $results['languages']['pll_languages']; ?>/<?php echo $results['languages']['wpml_languages']; ?>
        </div>
        <div class="badge <?php echo $results['languages']['critical_issues'] === 0 ? 'badge-success' : 'badge-error'; ?>">
            <?php echo $results['languages']['critical_issues'] === 0 ? 'OK' : 'Issues'; ?>
        </div>
    </div>
    
    <div class="stat-box">
        <div class="stat-label"><?php _e('Posts', 'wpml-to-polylang-migration-fixer'); ?></div>
        <div class="stat-number">
            <?php echo $results['posts']['posts_with_language']; ?>
        </div>
        <div class="badge <?php echo $results['posts']['critical_issues'] === 0 ? 'badge-success' : 'badge-error'; ?>">
            <?php echo $results['posts']['critical_issues'] === 0 ? 'OK' : $results['posts']['critical_issues'] . ' Issues'; ?>
        </div>
    </div>
    
    <div class="stat-box">
        <div class="stat-label"><?php _e('Terms', 'wpml-to-polylang-migration-fixer'); ?></div>
        <div class="stat-number">
            <?php echo $results['terms']['terms_with_language']; ?>
        </div>
        <div class="badge <?php echo $results['terms']['critical_issues'] === 0 ? 'badge-success' : 'badge-error'; ?>">
            <?php echo $results['terms']['critical_issues'] === 0 ? 'OK' : $results['terms']['critical_issues'] . ' Issues'; ?>
        </div>
    </div>
    
    <div class="stat-box">
        <div class="stat-label"><?php _e('Translation Groups', 'wpml-to-polylang-migration-fixer'); ?></div>
        <div class="stat-number">
            <?php echo $results['translation_groups']['valid_groups']; ?>/<?php echo $results['translation_groups']['total_groups']; ?>
        </div>
        <div class="badge <?php echo $results['translation_groups']['critical_issues'] === 0 ? 'badge-success' : 'badge-error'; ?>">
            <?php echo $results['translation_groups']['critical_issues'] === 0 ? 'OK' : 'Issues'; ?>
        </div>
    </div>
    
    <!-- NEW: BetterDocs Status Box -->
    <?php if ($results['betterdocs']['betterdocs_active']): ?>
    <div class="stat-box">
        <div class="stat-label"><?php _e('BetterDocs', 'wpml-to-polylang-migration-fixer'); ?></div>
        <div class="stat-number">
            <?php echo $results['betterdocs']['docs_with_language']; ?>/<?php echo $results['betterdocs']['total_docs']; ?>
        </div>
        <div class="badge <?php echo $results['betterdocs']['critical_issues'] === 0 ? 'badge-success' : 'badge-error'; ?>">
            <?php echo $results['betterdocs']['critical_issues'] === 0 ? 'OK' : $results['betterdocs']['critical_issues'] . ' Issues'; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Action Recommendations -->
<?php if ($results['overall_status'] !== 'success'): ?>
<div style="margin-top: 20px; padding: 15px; background: #fff3cd; border-radius: 8px; border-left: 4px solid #ffc107;">
    <h5 style="margin: 0 0 10px 0;">🔧 Recommended Actions (Priority Order)</h5>
    <ul style="margin: 0; padding-left: 20px;">
        <?php if (($results['posts']['posts_wrong_language'] ?? 0) > 0 || ($results['terms']['terms_wrong_language'] ?? 0) > 0): ?>
        <li><strong>⚠️ Critical:</strong> Fix language mismatches:
            <?php if (($results['posts']['posts_wrong_language'] ?? 0) > 0): ?>
            <?php echo $results['posts']['posts_wrong_language']; ?> posts with wrong language
            <?php endif; ?>
            <?php if (($results['terms']['terms_wrong_language'] ?? 0) > 0): ?>
            <?php echo ($results['posts']['posts_wrong_language'] ?? 0) > 0 ? ' and ' : ''; ?>
            <?php echo $results['terms']['terms_wrong_language']; ?> terms with wrong language
            <?php endif; ?>
        </li>
        <?php endif; ?>

        <?php if ($results['terms']['terms_without_language'] > 0): ?>
        <li><strong>🏷️ Priority 1:</strong> Fix <?php echo $results['terms']['terms_without_language']; ?> terms without language using "Fix Taxonomies (Legacy)"</li>
        <?php endif; ?>

        <?php if ($results['translation_groups']['invalid_groups'] > 0 || $results['posts']['orphaned_wpml_groups'] > 0 || $results['terms']['orphaned_wpml_term_groups'] > 0): ?>
        <li><strong>🔗 Priority 2:</strong> Review translation groups (<?php echo $results['translation_groups']['invalid_groups']; ?> corrupted, <?php echo $results['posts']['orphaned_wpml_groups']; ?> missing post groups, <?php echo $results['terms']['orphaned_wpml_term_groups']; ?> missing term groups) in Polylang’s translation set editor</li>
        <?php endif; ?>

        <?php if ($results['posts']['posts_without_language'] > 0): ?>
        <li><strong>📝 Priority 3:</strong> Fix <?php echo $results['posts']['posts_without_language']; ?> posts without language using "Fix Posts & Pages (Legacy)"</li>
        <?php endif; ?>

        <?php if ($results['betterdocs']['betterdocs_active'] && $results['betterdocs']['critical_issues'] > 0): ?>
        <li><strong>📚 Priority 4:</strong> Fix <?php echo $results['betterdocs']['critical_issues']; ?> BetterDocs issues using "Fix BetterDocs (Legacy)"</li>
        <?php endif; ?>
    </ul>
    
    <?php if ($results['translation_groups']['invalid_groups'] > 0): ?>
    <div style="margin-top: 15px; padding: 10px; background: #ffebee; border-radius: 5px; border-left: 3px solid #f44336;">
        <strong>⚠️ Translation Groups Critical:</strong> 
        <?php echo $results['translation_groups']['invalid_groups']; ?> corrupted translation groups detected. 
        This affects content relationships and should be fixed immediately to restore proper multilingual functionality.
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Detailed Component Results -->
<div style="margin-top: 25px;">
    <h4><?php _e('Detailed Verification Results', 'wpml-to-polylang-migration-fixer'); ?></h4>
    
    <!-- Languages Verification -->
    <div class="verification-component">
        <h5>
            🌐 <?php _e('Languages', 'wpml-to-polylang-migration-fixer'); ?>
            <span class="badge <?php echo $results['languages']['status'] === 'success' ? 'badge-success' : 'badge-error'; ?>">
                <?php echo ucfirst($results['languages']['status']); ?>
            </span>
        </h5>
        <?php if (!empty($results['languages']['issues'])): ?>
        <ul style="margin: 10px 0; color: #c62828;">
            <?php foreach ($results['languages']['issues'] as $issue): ?>
            <li><?php echo esc_html($issue); ?></li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
        <?php if (!empty($results['languages']['missing_in_pll'])): ?>
        <p style="color: #c62828; margin: 5px 0;">
            <strong><?php _e('Missing languages:', 'wpml-to-polylang-migration-fixer'); ?></strong> 
            <?php echo implode(', ', $results['languages']['missing_in_pll']); ?>
        </p>
        <?php endif; ?>
    </div>
    
    <!-- Posts Verification -->
    <div class="verification-component">
        <h5>
            📝 <?php _e('Posts & Pages', 'wpml-to-polylang-migration-fixer'); ?>
            <span class="badge <?php echo $results['posts']['status'] === 'success' ? 'badge-success' : 'badge-error'; ?>">
                <?php echo ucfirst($results['posts']['status']); ?>
            </span>
        </h5>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; margin: 10px 0;">
            <div><strong><?php _e('With Language:', 'wpml-to-polylang-migration-fixer'); ?></strong> <?php echo $results['posts']['posts_with_language']; ?></div>
            <div><strong><?php _e('Missing Language:', 'wpml-to-polylang-migration-fixer'); ?></strong> <?php echo $results['posts']['posts_without_language']; ?></div>
            <div><strong><?php _e('Wrong Language:', 'wpml-to-polylang-migration-fixer'); ?></strong> <span class="<?php echo ($results['posts']['posts_wrong_language'] ?? 0) > 0 ? 'text-error' : ''; ?>"><?php echo $results['posts']['posts_wrong_language'] ?? 0; ?></span></div>
            <div><strong><?php _e('WPML Groups:', 'wpml-to-polylang-migration-fixer'); ?></strong> <?php echo $results['posts']['wpml_post_trids']; ?></div>
            <div><strong><?php _e('PLL Groups:', 'wpml-to-polylang-migration-fixer'); ?></strong> <?php echo $results['posts']['pll_post_groups']; ?></div>
        </div>
        <?php if (!empty($results['posts']['issues'])): ?>
        <ul style="margin: 10px 0; color: #c62828;">
            <?php foreach ($results['posts']['issues'] as $issue): ?>
            <li><?php echo esc_html($issue); ?></li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>

        <?php if (!empty($results['detailed_posts'])): ?>
        <table class="verification-table">
            <thead>
                <tr>
                    <th><?php _e('Post Type', 'wpml-to-polylang-migration-fixer'); ?></th>
                    <th><?php _e('Total', 'wpml-to-polylang-migration-fixer'); ?></th>
                    <th><?php _e('With Language', 'wpml-to-polylang-migration-fixer'); ?></th>
                    <th><?php _e('Missing', 'wpml-to-polylang-migration-fixer'); ?></th>
                    <th><?php _e('Wrong Language', 'wpml-to-polylang-migration-fixer'); ?></th>
                    <th><?php _e('WPML Groups', 'wpml-to-polylang-migration-fixer'); ?></th>
                    <th><?php _e('PLL Groups', 'wpml-to-polylang-migration-fixer'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results['detailed_posts'] as $type_key => $data): ?>
                <tr>
                    <td><strong><?php echo esc_html($data['label']); ?></strong> <small style="color:#607d8b;">(<?php echo esc_html($type_key); ?>)</small></td>
                    <td><?php echo number_format_i18n($data['total']); ?></td>
                    <td><?php echo number_format_i18n($data['with_language']); ?></td>
                    <td><?php echo number_format_i18n($data['missing_language']); ?></td>
                    <td class="<?php echo $data['wrong_language'] > 0 ? 'text-error' : ''; ?>"><?php echo number_format_i18n($data['wrong_language'] ?? 0); ?></td>
                    <td><?php echo number_format_i18n($data['wpml_groups']); ?></td>
                    <td><?php echo number_format_i18n($data['pll_groups']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    
    <!-- Terms Verification -->
    <div class="verification-component">
        <h5>
            🏷️ <?php _e('Terms & Taxonomies', 'wpml-to-polylang-migration-fixer'); ?>
            <span class="badge <?php echo $results['terms']['status'] === 'success' ? 'badge-success' : 'badge-error'; ?>">
                <?php echo ucfirst($results['terms']['status']); ?>
            </span>
        </h5>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; margin: 10px 0;">
            <div><strong><?php _e('With Language:', 'wpml-to-polylang-migration-fixer'); ?></strong> <?php echo $results['terms']['terms_with_language']; ?></div>
            <div><strong><?php _e('Missing Language:', 'wpml-to-polylang-migration-fixer'); ?></strong> <?php echo $results['terms']['terms_without_language']; ?></div>
            <div><strong><?php _e('Wrong Language:', 'wpml-to-polylang-migration-fixer'); ?></strong> <span class="<?php echo ($results['terms']['terms_wrong_language'] ?? 0) > 0 ? 'text-error' : ''; ?>"><?php echo $results['terms']['terms_wrong_language'] ?? 0; ?></span></div>
            <div><strong><?php _e('WPML Groups:', 'wpml-to-polylang-migration-fixer'); ?></strong> <?php echo $results['terms']['wpml_term_trids']; ?></div>
            <div><strong><?php _e('PLL Groups:', 'wpml-to-polylang-migration-fixer'); ?></strong> <?php echo $results['terms']['pll_term_groups']; ?></div>
        </div>
        <?php if (!empty($results['terms']['issues'])): ?>
        <ul style="margin: 10px 0; color: #c62828;">
            <?php foreach ($results['terms']['issues'] as $issue): ?>
            <li><?php echo esc_html($issue); ?></li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>

        <?php if (!empty($results['detailed_terms'])): ?>
        <table class="verification-table">
            <thead>
                <tr>
                    <th><?php _e('Taxonomy', 'wpml-to-polylang-migration-fixer'); ?></th>
                    <th><?php _e('Total', 'wpml-to-polylang-migration-fixer'); ?></th>
                    <th><?php _e('With Language', 'wpml-to-polylang-migration-fixer'); ?></th>
                    <th><?php _e('Missing', 'wpml-to-polylang-migration-fixer'); ?></th>
                    <th><?php _e('Wrong Language', 'wpml-to-polylang-migration-fixer'); ?></th>
                    <th><?php _e('WPML Groups', 'wpml-to-polylang-migration-fixer'); ?></th>
                    <th><?php _e('PLL Groups', 'wpml-to-polylang-migration-fixer'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results['detailed_terms'] as $tax_key => $data): ?>
                <tr>
                    <td><strong><?php echo esc_html($data['label']); ?></strong> <small style="color:#607d8b;">(<?php echo esc_html($tax_key); ?>)</small></td>
                    <td><?php echo number_format_i18n($data['total']); ?></td>
                    <td><?php echo number_format_i18n($data['with_language']); ?></td>
                    <td><?php echo number_format_i18n($data['missing_language']); ?></td>
                    <td class="<?php echo $data['wrong_language'] > 0 ? 'text-error' : ''; ?>"><?php echo number_format_i18n($data['wrong_language'] ?? 0); ?></td>
                    <td><?php echo number_format_i18n($data['wpml_groups']); ?></td>
                    <td><?php echo number_format_i18n($data['pll_groups']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
+    <?php if (!empty($results['terms_per_language'])): ?>
    <div class="verification-subsection">
        <h6 style="margin: 15px 0 5px;">🌍 <?php _e('Terms per Language', 'wpml-to-polylang-migration-fixer'); ?></h6>
        <ul class="verification-split-list">
            <?php foreach ($results['terms_per_language'] as $slug => $count): ?>
            <li><strong><?php echo esc_html($slug); ?>:</strong> <?php echo number_format_i18n($count); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
    
    <!-- Translation Groups Verification -->
    <div class="verification-component">
        <h5>
            🔗 <?php _e('Translation Groups', 'wpml-to-polylang-migration-fixer'); ?>
            <span class="badge <?php echo $results['translation_groups']['status'] === 'success' ? 'badge-success' : 'badge-error'; ?>">
                <?php echo ucfirst($results['translation_groups']['status']); ?>
            </span>
        </h5>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; margin: 10px 0;">
            <div><strong><?php _e('Total Groups:', 'wpml-to-polylang-migration-fixer'); ?></strong> <?php echo $results['translation_groups']['total_groups']; ?></div>
            <div><strong><?php _e('Valid Groups:', 'wpml-to-polylang-migration-fixer'); ?></strong> <?php echo $results['translation_groups']['valid_groups']; ?></div>
            <div><strong><?php _e('Invalid Groups:', 'wpml-to-polylang-migration-fixer'); ?></strong> <?php echo $results['translation_groups']['invalid_groups']; ?></div>
            <div><strong><?php _e('Empty Groups:', 'wpml-to-polylang-migration-fixer'); ?></strong> <?php echo $results['translation_groups']['empty_groups']; ?></div>
        </div>
        <?php if (!empty($results['translation_groups']['issues'])): ?>
        <ul style="margin: 10px 0; color: #c62828;">
            <?php foreach ($results['translation_groups']['issues'] as $issue): ?>
            <li><?php echo esc_html($issue); ?></li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
    
    <!-- BetterDocs Verification -->
    <?php if ($results['betterdocs']['betterdocs_active']): ?>
    <div class="verification-component">
        <h5>
            📚 <?php _e('BetterDocs', 'wpml-to-polylang-migration-fixer'); ?>
            <span class="badge <?php echo $results['betterdocs']['status'] === 'success' ? 'badge-success' : 'badge-error'; ?>">
                <?php echo ucfirst($results['betterdocs']['status']); ?>
            </span>
        </h5>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; margin: 10px 0;">
            <div><strong><?php _e('Total Docs:', 'wpml-to-polylang-migration-fixer'); ?></strong> <?php echo $results['betterdocs']['total_docs']; ?></div>
            <div><strong><?php _e('With Language:', 'wpml-to-polylang-migration-fixer'); ?></strong> <?php echo $results['betterdocs']['docs_with_language']; ?></div>
            <div><strong><?php _e('Missing Language:', 'wpml-to-polylang-migration-fixer'); ?></strong> <?php echo $results['betterdocs']['docs_without_language']; ?></div>
            <div><strong><?php _e('Categories:', 'wpml-to-polylang-migration-fixer'); ?></strong> <?php echo $results['betterdocs']['doc_categories_with_language']; ?>/<?php echo $results['betterdocs']['total_doc_categories']; ?></div>
        </div>
        <?php if (!empty($results['betterdocs']['issues'])): ?>
        <ul style="margin: 10px 0; color: #c62828;">
            <?php foreach ($results['betterdocs']['issues'] as $issue): ?>
            <li><?php echo esc_html($issue); ?></li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- WPML Data Status -->
<?php if ($results['wpml_data']['icl_translations_exists']): ?>
<div style="margin-top: 20px; padding: 15px; background: #f0f8ff; border-radius: 8px; border-left: 4px solid #2196f3;">
    <h5 style="margin: 0 0 10px 0;">ℹ️ <?php _e('WPML Data Status', 'wpml-to-polylang-migration-fixer'); ?></h5>
    <p style="margin: 0; font-size: 14px;">
        <?php printf(
            __('WPML translation data found: %d entries in icl_translations table. This data is used for reference during migration fixes.', 'wpml-to-polylang-migration-fixer'),
            $results['wpml_data']['icl_translations_count']
        ); ?>
    </p>
</div>
<?php endif; ?>
