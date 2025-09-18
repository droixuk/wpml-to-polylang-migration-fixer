<?php
if (!defined('ABSPATH')) exit;
?>
<h3><?php _e('Content Analysis Results', 'wpml-to-polylang-migration-fixer'); ?></h3>

<!-- Enhanced Quick Stats with BetterDocs -->
<div class="quick-stats">
    <div class="stat-box">
        <div class="stat-number"><?php echo $stats['posts_with_language']; ?></div>
        <div class="stat-label"><?php _e('Posts with Language', 'wpml-to-polylang-migration-fixer'); ?></div>
    </div>
    <div class="stat-box">
        <div class="stat-number"><?php echo $stats['posts_without_language']; ?></div>
        <div class="stat-label"><?php _e('Posts Missing Language', 'wpml-to-polylang-migration-fixer'); ?></div>
    </div>
    <div class="stat-box">
        <div class="stat-number"><?php echo $stats['terms_with_language']; ?></div>
        <div class="stat-label"><?php _e('Terms with Language', 'wpml-to-polylang-migration-fixer'); ?></div>
    </div>
    <div class="stat-box">
        <div class="stat-number"><?php echo $stats['terms_without_language']; ?></div>
        <div class="stat-label"><?php _e('Terms Missing Language', 'wpml-to-polylang-migration-fixer'); ?></div>
    </div>
    
    <!-- NEW: BetterDocs Stats -->
    <?php if (isset($stats['betterdocs_stats']) && $stats['betterdocs_stats']['active']): ?>
    <div class="stat-box" style="background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); border-left: 3px solid #4caf50;">
        <div class="stat-number"><?php echo $stats['betterdocs_stats']['docs_with_language']; ?></div>
        <div class="stat-label"><?php _e('Docs with Language', 'wpml-to-polylang-migration-fixer'); ?></div>
    </div>
    <div class="stat-box" style="background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%); border-left: 3px solid #ffc107;">
        <div class="stat-number"><?php echo $stats['betterdocs_stats']['docs_without_language']; ?></div>
        <div class="stat-label"><?php _e('Docs Missing Language', 'wpml-to-polylang-migration-fixer'); ?></div>
    </div>
    <?php endif; ?>
</div>

<!-- Migration Issues Summary -->
<?php if (isset($stats['verification_summary']) && $stats['verification_summary']['total_critical_issues'] > 0): ?>
<div style="margin: 20px 0; padding: 15px; background: #ffebee; border-radius: 8px; border-left: 4px solid #f44336;">
    <h4 style="margin: 0 0 10px 0; color: #c62828;">
        ⚠️ <?php _e('Migration Issues Detected', 'wpml-to-polylang-migration-fixer'); ?>
    </h4>
    <p style="margin: 0; font-size: 14px;">
        <?php printf(
            __('Found %d critical issues across %d components. Use the verification tools for detailed analysis.', 'wpml-to-polylang-migration-fixer'),
            $stats['verification_summary']['total_critical_issues'],
            $stats['verification_summary']['components_with_issues']
        ); ?>
    </p>
</div>
<?php elseif (isset($stats['verification_summary'])): ?>
<div style="margin: 20px 0; padding: 15px; background: #e8f5e9; border-radius: 8px; border-left: 4px solid #4caf50;">
    <h4 style="margin: 0 0 10px 0; color: #2e7d32;">
        ✅ <?php _e('Migration Status: Good', 'wpml-to-polylang-migration-fixer'); ?>
    </h4>
    <p style="margin: 0; font-size: 14px;">
        <?php _e('No critical migration issues detected. Your content appears to be properly migrated.', 'wpml-to-polylang-migration-fixer'); ?>
    </p>
</div>
<?php endif; ?>

<!-- BetterDocs Detailed Analysis -->
<?php if (isset($stats['betterdocs_stats']) && $stats['betterdocs_stats']['active']): ?>
<div style="margin-top: 25px; padding: 20px; background: #f0f8ff; border-radius: 8px; border-left: 4px solid #2196f3;">
    <h4 style="margin: 0 0 15px 0; color: #1976d2;">
        📚 <?php _e('BetterDocs Analysis', 'wpml-to-polylang-migration-fixer'); ?>
    </h4>
    
    <div class="quick-stats" style="margin-bottom: 15px;">
        <div class="stat-box">
            <div class="stat-number"><?php echo $stats['betterdocs_stats']['total_docs']; ?></div>
            <div class="stat-label"><?php _e('Total Docs', 'wpml-to-polylang-migration-fixer'); ?></div>
        </div>
        <div class="stat-box">
            <div class="stat-number"><?php echo $stats['betterdocs_stats']['docs_with_language']; ?></div>
            <div class="stat-label"><?php _e('With Language', 'wpml-to-polylang-migration-fixer'); ?></div>
        </div>
        <div class="stat-box">
            <div class="stat-number"><?php echo $stats['betterdocs_stats']['docs_without_language']; ?></div>
            <div class="stat-label"><?php _e('Need Language Fix', 'wpml-to-polylang-migration-fixer'); ?></div>
        </div>
        <div class="stat-box">
            <div class="stat-number">
                <?php 
                $percentage = $stats['betterdocs_stats']['total_docs'] > 0 
                    ? round(($stats['betterdocs_stats']['docs_with_language'] / $stats['betterdocs_stats']['total_docs']) * 100, 1)
                    : 100;
                echo $percentage . '%';
                ?>
            </div>
            <div class="stat-label"><?php _e('Coverage', 'wpml-to-polylang-migration-fixer'); ?></div>
        </div>
    </div>
    
    <?php if ($stats['betterdocs_stats']['docs_without_language'] > 0): ?>
    <div style="padding: 10px; background: rgba(255, 193, 7, 0.1); border-radius: 5px; margin-top: 10px;">
        <p style="margin: 0; font-size: 14px; color: #856404;">
            <strong><?php _e('Action Required:', 'wpml-to-polylang-migration-fixer'); ?></strong>
            <?php printf(
                __('%d BetterDocs need language assignment. Use the "Fix BetterDocs" button in the Main Fix Actions section.', 'wpml-to-polylang-migration-fixer'),
                $stats['betterdocs_stats']['docs_without_language']
            ); ?>
        </p>
    </div>
    <?php else: ?>
    <div style="padding: 10px; background: rgba(76, 175, 80, 0.1); border-radius: 5px; margin-top: 10px;">
        <p style="margin: 0; font-size: 14px; color: #2e7d32;">
            <strong><?php _e('Status:', 'wpml-to-polylang-migration-fixer'); ?></strong>
            <?php _e('All BetterDocs have proper language assignments. No action needed.', 'wpml-to-polylang-migration-fixer'); ?>
        </p>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Posts by Type Breakdown -->
<?php if (!empty($stats['post_types_breakdown'])): ?>
<h4><?php _e('Posts by Type', 'wpml-to-polylang-migration-fixer'); ?></h4>
<table class="stats-table">
    <tr>
        <th><?php _e('Post Type', 'wpml-to-polylang-migration-fixer'); ?></th>
        <th><?php _e('With Language', 'wpml-to-polylang-migration-fixer'); ?></th>
        <th><?php _e('Missing Language', 'wpml-to-polylang-migration-fixer'); ?></th>
        <th><?php _e('Coverage', 'wpml-to-polylang-migration-fixer'); ?></th>
    </tr>
    <?php foreach ($stats['post_types_breakdown'] as $type => $data): ?>
    <tr>
        <td>
            <?php 
            echo esc_html($type);
            // Add icon for BetterDocs
            if ($type === 'docs') {
                echo ' 📚';
            } elseif ($type === 'product') {
                echo ' 🛍️';
            }
            ?>
        </td>
        <td><?php echo intval($data['with_language']); ?></td>
        <td>
            <span class="<?php echo $data['without_language'] > 0 ? 'mismatch' : 'match'; ?>">
                <?php echo intval($data['without_language']); ?>
            </span>
        </td>
        <td>
            <?php 
            $coverage = $data['total'] > 0 ? round(($data['with_language'] / $data['total']) * 100, 1) : 100;
            $coverage_class = $coverage >= 100 ? 'match' : ($coverage >= 80 ? '' : 'mismatch');
            ?>
            <span class="<?php echo $coverage_class; ?>"><?php echo $coverage; ?>%</span>
        </td>
    </tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>

<!-- Quick Action Recommendations -->
<?php if ($stats['posts_without_language'] > 0 || $stats['terms_without_language'] > 0 || (isset($stats['betterdocs_stats']) && $stats['betterdocs_stats']['docs_without_language'] > 0)): ?>
<div style="margin-top: 25px; padding: 20px; background: #e3f2fd; border-radius: 8px; border-left: 4px solid #2196f3;">
    <h4 style="margin: 0 0 15px 0; color: #1976d2;">
        🎯 <?php _e('Quick Action Recommendations', 'wpml-to-polylang-migration-fixer'); ?>
    </h4>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
        <?php if ($stats['posts_without_language'] > 0): ?>
        <div style="padding: 15px; background: white; border-radius: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h5 style="margin: 0 0 8px 0; color: #333;">📝 Posts & Pages</h5>
            <p style="margin: 0 0 10px 0; font-size: 13px; color: #666;">
                <?php printf(__('%d posts need language assignment', 'wpml-to-polylang-migration-fixer'), $stats['posts_without_language']); ?>
            </p>
            <button class="wpml-btn wpml-btn-secondary" onclick="wpmlFixerAjax.startProcess('posts')" style="font-size: 12px; padding: 8px 16px;">
                <?php _e('Fix Posts', 'wpml-to-polylang-migration-fixer'); ?>
            </button>
        </div>
        <?php endif; ?>
        
        <?php if ($stats['terms_without_language'] > 0): ?>
        <div style="padding: 15px; background: white; border-radius: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h5 style="margin: 0 0 8px 0; color: #333;">🏷️ Taxonomies</h5>
            <p style="margin: 0 0 10px 0; font-size: 13px; color: #666;">
                <?php printf(__('%d terms need language assignment', 'wpml-to-polylang-migration-fixer'), $stats['terms_without_language']); ?>
            </p>
            <button class="wpml-btn wpml-btn-secondary" onclick="wpmlFixerAjax.startProcess('taxonomies')" style="font-size: 12px; padding: 8px 16px;">
                <?php _e('Fix Taxonomies', 'wpml-to-polylang-migration-fixer'); ?>
            </button>
        </div>
        <?php endif; ?>
        
        <?php if (isset($stats['betterdocs_stats']) && $stats['betterdocs_stats']['docs_without_language'] > 0): ?>
        <div style="padding: 15px; background: white; border-radius: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h5 style="margin: 0 0 8px 0; color: #333;">📚 BetterDocs</h5>
            <p style="margin: 0 0 10px 0; font-size: 13px; color: #666;">
                <?php printf(__('%d docs need language assignment', 'wpml-to-polylang-migration-fixer'), $stats['betterdocs_stats']['docs_without_language']); ?>
            </p>
            <button class="wpml-btn wpml-btn-secondary" onclick="wpmlFixerAjax.startProcess('betterdocs')" style="font-size: 12px; padding: 8px 16px;">
                <?php _e('Fix BetterDocs', 'wpml-to-polylang-migration-fixer'); ?>
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>