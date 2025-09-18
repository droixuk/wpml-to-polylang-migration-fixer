<?php
if (!defined('ABSPATH')) exit;

// Ensure we have the required variables with defaults
$problematic = isset($problematic) && is_array($problematic) ? $problematic : [];
$wrong_codes = isset($wrong_codes) && is_array($wrong_codes) ? $wrong_codes : [];
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

<?php if (!empty($wrong_codes['details']) && is_array($wrong_codes['details'])): ?>
<div style="margin-top: 15px;">
    <h4><?php _e('Detected Wrong Language Codes', 'wpml-to-polylang-migration-fixer'); ?></h4>
    <div style="background: #f5f5f5; padding: 10px; border-radius: 5px; margin-top: 10px;">
        <p><?php _e('Found these problematic language codes:', 'wpml-to-polylang-migration-fixer'); ?></p>
        <ul style="margin: 10px 0;">
            <?php foreach ($wrong_codes['details'] as $code): ?>
            <li><code><?php echo esc_html($code); ?></code></li>
            <?php endforeach; ?>
        </ul>
        <?php if (!empty($wrong_codes['posts']) || !empty($wrong_codes['terms'])): ?>
        <p style="margin-top: 15px; font-weight: bold;">
            <?php printf(
                __('Affected content: %d posts, %d terms', 'wpml-to-polylang-migration-fixer'),
                intval($wrong_codes['posts']),
                intval($wrong_codes['terms'])
            ); ?>
        </p>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if (empty($problematic) && empty($wrong_codes['details'])): ?>
<div style="margin-top: 15px; padding: 15px; background: #e8f5e9; border-radius: 8px; border-left: 4px solid #2e7d32;">
    <strong>✅ <?php _e('No Issues Detected', 'wpml-to-polylang-migration-fixer'); ?></strong><br>
    <?php _e('Your language assignments appear to be correct. No problematic language codes were found.', 'wpml-to-polylang-migration-fixer'); ?>
</div>
<?php endif; ?>

<?php 
// Show debug information if WP_DEBUG is enabled
if (defined('WP_DEBUG') && WP_DEBUG && current_user_can('manage_options')): 
?>
<details style="margin-top: 20px;">
    <summary style="cursor: pointer; padding: 10px; background: #f5f5f5; border-radius: 5px;">
        <?php _e('Debug Information', 'wpml-to-polylang-migration-fixer'); ?>
    </summary>
    <div style="padding: 10px; background: #fafafa; border-radius: 5px; margin-top: 5px;">
        <p><strong><?php _e('Available Variables:', 'wpml-to-polylang-migration-fixer'); ?></strong></p>
        <pre style="font-size: 11px; background: white; padding: 10px; border-radius: 3px; overflow: auto;">
Problematic codes: <?php echo var_export($problematic, true); ?>
Wrong codes: <?php echo var_export($wrong_codes, true); ?>
        </pre>
        
        <?php if (function_exists('pll_languages_list')): ?>
        <p><strong><?php _e('Configured Languages:', 'wpml-to-polylang-migration-fixer'); ?></strong></p>
        <pre style="font-size: 11px; background: white; padding: 10px; border-radius: 3px; overflow: auto;">
<?php echo var_export(pll_languages_list(['fields' => 'slug']), true); ?>
        </pre>
        <?php endif; ?>
    </div>
</details>
<?php endif; ?>