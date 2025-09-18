<?php
if (!defined('ABSPATH')) exit;
?>
<h3><?php _e('Migration Verification', 'wpml-to-polylang-migration-fixer'); ?></h3>
<div class="quick-stats">
    <div class="stat-box">
        <div class="stat-label"><?php _e('Translation Groups', 'wpml-to-polylang-migration-fixer'); ?></div>
        <div class="stat-number"><?php echo intval($verification['translation_groups']); ?></div>
    </div>
    <div class="stat-box">
        <div class="stat-label"><?php _e('Orphaned Languages', 'wpml-to-polylang-migration-fixer'); ?></div>
        <div class="stat-number"><?php echo intval($verification['orphaned_languages']); ?></div>
    </div>
    <div class="stat-box">
        <div class="stat-label"><?php _e('Duplicate Assignments', 'wpml-to-polylang-migration-fixer'); ?></div>
        <div class="stat-number"><?php echo intval($verification['duplicate_assignments']); ?></div>
    </div>
</div>

<?php if (!empty($verification['language_mapping'])): ?>
<h4><?php _e('Language Mapping Status', 'wpml-to-polylang-migration-fixer'); ?></h4>
<table class="language-comparison-table">
    <tr>
        <th><?php _e('WPML Code', 'wpml-to-polylang-migration-fixer'); ?></th>
        <th><?php _e('Polylang Code', 'wpml-to-polylang-migration-fixer'); ?></th>
        <th><?php _e('Status', 'wpml-to-polylang-migration-fixer'); ?></th>
    </tr>
    <?php foreach ($verification['language_mapping'] as $map): ?>
    <tr>
        <td><?php echo esc_html($map['wpml']); ?></td>
        <td><?php echo esc_html($map['pll']); ?></td>
        <td>
            <span class="badge badge-<?php echo $map['status'] === 'mapped' ? 'success' : 'error'; ?>">
                <?php echo $map['status'] === 'mapped' ? __('Mapped', 'wpml-to-polylang-migration-fixer') : __('Missing', 'wpml-to-polylang-migration-fixer'); ?>
            </span>
        </td>
    </tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>
