<?php
if (!defined('ABSPATH')) exit;
?>
<h3><?php _e('Content Analysis Results', 'wpml-to-polylang-migration-fixer'); ?></h3>
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
</div>

<?php if (!empty($stats['post_types_breakdown'])): ?>
<h4><?php _e('Posts by Type', 'wpml-to-polylang-migration-fixer'); ?></h4>
<table class="stats-table">
    <tr>
        <th><?php _e('Post Type', 'wpml-to-polylang-migration-fixer'); ?></th>
        <th><?php _e('With Language', 'wpml-to-polylang-migration-fixer'); ?></th>
        <th><?php _e('Missing Language', 'wpml-to-polylang-migration-fixer'); ?></th>
    </tr>
    <?php foreach ($stats['post_types_breakdown'] as $type => $data): ?>
    <tr>
        <td><?php echo esc_html($type); ?></td>
        <td><?php echo intval($data['with_language']); ?></td>
        <td><?php echo intval($data['without_language']); ?></td>
    </tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>
