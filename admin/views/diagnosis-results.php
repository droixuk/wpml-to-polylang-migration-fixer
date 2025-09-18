<?php
if (!defined('ABSPATH')) exit;
?>
<h3><?php _e('Language Assignment Diagnosis', 'wpml-to-polylang-migration-fixer'); ?></h3>

<?php if (!empty($problematic['pll_prefixed'])): ?>
<div style="margin-top: 15px; padding: 15px; background: #ffebee; border-radius: 8px; border-left: 4px solid #f44336;">
    <strong>🚨 <?php _e('CRITICAL DATA CORRUPTION DETECTED!', 'wpml-to-polylang-migration-fixer'); ?></strong><br>
    <?php _e('Invalid pll_ prefixed codes found:', 'wpml-to-polylang-migration-fixer'); ?> 
    <?php echo esc_html(implode(', ', $problematic['pll_prefixed'])); ?><br>
    <strong><?php _e('Use the EMERGENCY FIX button immediately to restore your content!', 'wpml-to-polylang-migration-fixer'); ?></strong>
</div>
<?php endif; ?>

<?php if (!empty($problematic['english_variants'])): ?>
<div style="margin-top: 15px; padding: 15px; background: #fff3cd; border-radius: 8px; border-left: 4px solid #ffc107;">
    <strong>⚠️ <?php _e('English Variants Detected:', 'wpml-to-polylang-migration-fixer'); ?></strong> 
    <?php echo esc_html(implode(', ', $problematic['english_variants'])); ?><br>
    <?php _e('Content is assigned to variants not configured in Polylang.', 'wpml-to-polylang-migration-fixer'); ?>
</div>
<?php endif; ?>

<?php if (!empty($problematic['unconfigured'])): ?>
<div style="margin-top: 15px;">
    <h4><?php _e('Unconfigured Language Codes', 'wpml-to-polylang-migration-fixer'); ?></h4>
    <table class="diagnosis-table">
        <tr>
            <th><?php _e('Code', 'wpml-to-polylang-migration-fixer'); ?></th>
            <th><?php _e('Posts', 'wpml-to-polylang-migration-fixer'); ?></th>
            <th><?php _e('Terms', 'wpml-to-polylang-migration-fixer'); ?></th>
        </tr>
        <?php foreach ($problematic['unconfigured'] as $code): ?>
        <tr>
            <td><?php echo esc_html($code); ?></td>
            <td><?php echo intval($wrong_codes[$code]['posts'] ?? 0); ?></td>
            <td><?php echo intval($wrong_codes[$code]['terms'] ?? 0); ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php endif; ?>
