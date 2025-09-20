<?php
/**
 * Detailed Verification Results View
 *
 * Shows comprehensive breakdown by content type and plugin
 */

if (!defined('ABSPATH')) exit;

// Helper function to format numbers
function format_stat($with, $total, $show_missing = true) {
    if ($total == 0) return '0';

    $missing = $total - $with;
    if ($missing > 0 && $show_missing) {
        return sprintf('<span style="color: #4CAF50;">%d</span>/<span style="color: #f44336;">%d</span> of %d',
            $with, $missing, $total);
    } else {
        return sprintf('<span style="color: #4CAF50;">%d</span>/%d', $with, $total);
    }
}
?>

<h3><?php _e('Detailed Migration Verification', 'wpml-to-polylang-migration-fixer'); ?></h3>

<!-- Core WordPress Content -->
<div class="verification-section" style="margin-bottom: 30px;">
    <h4 style="background: #f0f4f8; padding: 10px; margin: 0 0 15px 0; border-left: 4px solid #2271b1;">
        📝 <?php _e('Core WordPress Content', 'wpml-to-polylang-migration-fixer'); ?>
    </h4>

    <?php if (!empty($results['detailed_posts'])): ?>
    <table class="widefat striped" style="margin-bottom: 20px;">
        <thead>
            <tr>
                <th><?php _e('Content Type', 'wpml-to-polylang-migration-fixer'); ?></th>
                <th><?php _e('Total', 'wpml-to-polylang-migration-fixer'); ?></th>
                <th><?php _e('With Language', 'wpml-to-polylang-migration-fixer'); ?></th>
                <th><?php _e('Missing', 'wpml-to-polylang-migration-fixer'); ?></th>
                <th><?php _e('Wrong Language', 'wpml-to-polylang-migration-fixer'); ?></th>
                <th><?php _e('WPML Groups', 'wpml-to-polylang-migration-fixer'); ?></th>
                <th><?php _e('PLL Groups', 'wpml-to-polylang-migration-fixer'); ?></th>
                <th><?php _e('Status', 'wpml-to-polylang-migration-fixer'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            foreach ($results['content_groups']['core'] as $type_key):
                if (isset($results['detailed_posts'][$type_key])):
                    $data = $results['detailed_posts'][$type_key];
            ?>
            <tr>
                <td><strong><?php echo esc_html($data['label']); ?></strong></td>
                <td><?php echo number_format($data['total']); ?></td>
                <td style="color: #4CAF50;"><?php echo number_format($data['with_language']); ?></td>
                <td style="color: <?php echo $data['missing_language'] > 0 ? '#f44336' : '#4CAF50'; ?>;">
                    <?php echo number_format($data['missing_language']); ?>
                </td>
                <td style="color: <?php echo ($data['wrong_language'] ?? 0) > 0 ? '#ff9800' : '#4CAF50'; ?>;">
                    <?php echo number_format($data['wrong_language'] ?? 0); ?>
                </td>
                <td><?php echo number_format($data['wpml_groups']); ?></td>
                <td><?php echo number_format($data['pll_groups']); ?></td>
                <td>
                    <?php if ($data['missing_language'] > 0): ?>
                        <span class="dashicons dashicons-warning" style="color: #f44336;"></span>
                    <?php else: ?>
                        <span class="dashicons dashicons-yes-alt" style="color: #4CAF50;"></span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php
                endif;
            endforeach;
            ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<!-- WooCommerce Content -->
<?php if (!empty($results['content_groups']['woocommerce'])): ?>
<div class="verification-section" style="margin-bottom: 30px;">
    <h4 style="background: #f0f4f8; padding: 10px; margin: 0 0 15px 0; border-left: 4px solid #7c3aed;">
        🛍️ <?php _e('WooCommerce', 'wpml-to-polylang-migration-fixer'); ?>
    </h4>

    <table class="widefat striped" style="margin-bottom: 20px;">
        <thead>
            <tr>
                <th><?php _e('Content Type', 'wpml-to-polylang-migration-fixer'); ?></th>
                <th><?php _e('Total', 'wpml-to-polylang-migration-fixer'); ?></th>
                <th><?php _e('With Language', 'wpml-to-polylang-migration-fixer'); ?></th>
                <th><?php _e('Missing', 'wpml-to-polylang-migration-fixer'); ?></th>
                <th><?php _e('Wrong Language', 'wpml-to-polylang-migration-fixer'); ?></th>
                <th><?php _e('WPML Groups', 'wpml-to-polylang-migration-fixer'); ?></th>
                <th><?php _e('PLL Groups', 'wpml-to-polylang-migration-fixer'); ?></th>
                <th><?php _e('Status', 'wpml-to-polylang-migration-fixer'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            foreach ($results['content_groups']['woocommerce'] as $type_key):
                if (isset($results['detailed_posts'][$type_key])):
                    $data = $results['detailed_posts'][$type_key];
            ?>
            <tr>
                <td><strong><?php echo esc_html($data['label']); ?></strong></td>
                <td><?php echo number_format($data['total']); ?></td>
                <td style="color: #4CAF50;"><?php echo number_format($data['with_language']); ?></td>
                <td style="color: <?php echo $data['missing_language'] > 0 ? '#f44336' : '#4CAF50'; ?>;">
                    <?php echo number_format($data['missing_language']); ?>
                </td>
                <td style="color: <?php echo ($data['wrong_language'] ?? 0) > 0 ? '#ff9800' : '#4CAF50'; ?>;">
                    <?php echo number_format($data['wrong_language'] ?? 0); ?>
                </td>
                <td><?php echo number_format($data['wpml_groups']); ?></td>
                <td><?php echo number_format($data['pll_groups']); ?></td>
                <td>
                    <?php if ($data['missing_language'] > 0): ?>
                        <span class="dashicons dashicons-warning" style="color: #f44336;"></span>
                    <?php else: ?>
                        <span class="dashicons dashicons-yes-alt" style="color: #4CAF50;"></span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php
                endif;
            endforeach;
            ?>
        </tbody>
    </table>

    <!-- WooCommerce Taxonomies -->
    <?php if (!empty($results['detailed_terms'])): ?>
    <h5><?php _e('WooCommerce Taxonomies', 'wpml-to-polylang-migration-fixer'); ?></h5>
    <table class="widefat striped" style="margin-bottom: 20px;">
        <thead>
            <tr>
                <th><?php _e('Taxonomy', 'wpml-to-polylang-migration-fixer'); ?></th>
                <th><?php _e('Total Terms', 'wpml-to-polylang-migration-fixer'); ?></th>
                <th><?php _e('With Language', 'wpml-to-polylang-migration-fixer'); ?></th>
                <th><?php _e('Missing Language', 'wpml-to-polylang-migration-fixer'); ?></th>
                <th><?php _e('Wrong Language', 'wpml-to-polylang-migration-fixer'); ?></th>
                <th><?php _e('Status', 'wpml-to-polylang-migration-fixer'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            $woo_taxonomies = ['product_cat', 'product_tag', 'product_shipping_class'];
            foreach ($woo_taxonomies as $tax_key):
                if (isset($results['detailed_terms'][$tax_key])):
                    $data = $results['detailed_terms'][$tax_key];
            ?>
            <tr>
                <td><strong><?php echo esc_html($data['label']); ?></strong></td>
                <td><?php echo number_format($data['total']); ?></td>
                <td style="color: #4CAF50;"><?php echo number_format($data['with_language']); ?></td>
                <td style="color: <?php echo $data['missing_language'] > 0 ? '#f44336' : '#4CAF50'; ?>;">
                    <?php echo number_format($data['missing_language']); ?>
                </td>
                <td style="color: <?php echo ($data['wrong_language'] ?? 0) > 0 ? '#ff9800' : '#4CAF50'; ?>;">
                    <?php echo number_format($data['wrong_language'] ?? 0); ?>
                </td>
                <td>
                    <?php if ($data['missing_language'] > 0 || ($data['wrong_language'] ?? 0) > 0): ?>
                        <span class="dashicons dashicons-warning" style="color: #f44336;"></span>
                    <?php else: ?>
                        <span class="dashicons dashicons-yes-alt" style="color: #4CAF50;"></span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php
                endif;
            endforeach;

            // Product attributes (pa_*)
            foreach ($results['detailed_terms'] as $tax_key => $data):
                if (strpos($tax_key, 'pa_') === 0):
            ?>
            <tr>
                <td><strong><?php echo esc_html($data['label']); ?></strong></td>
                <td><?php echo number_format($data['total']); ?></td>
                <td style="color: #4CAF50;"><?php echo number_format($data['with_language']); ?></td>
                <td style="color: <?php echo $data['missing_language'] > 0 ? '#f44336' : '#4CAF50'; ?>;">
                    <?php echo number_format($data['missing_language']); ?>
                </td>
                <td style="color: <?php echo ($data['wrong_language'] ?? 0) > 0 ? '#ff9800' : '#4CAF50'; ?>;">
                    <?php echo number_format($data['wrong_language'] ?? 0); ?>
                </td>
                <td>
                    <?php if ($data['missing_language'] > 0 || ($data['wrong_language'] ?? 0) > 0): ?>
                        <span class="dashicons dashicons-warning" style="color: #f44336;"></span>
                    <?php else: ?>
                        <span class="dashicons dashicons-yes-alt" style="color: #4CAF50;"></span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php
                endif;
            endforeach;
            ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- BetterDocs Content -->
<?php if (!empty($results['content_groups']['betterdocs'])): ?>
<div class="verification-section" style="margin-bottom: 30px;">
    <h4 style="background: #f0f4f8; padding: 10px; margin: 0 0 15px 0; border-left: 4px solid #10b981;">
        📚 <?php _e('BetterDocs', 'wpml-to-polylang-migration-fixer'); ?>
    </h4>

    <table class="widefat striped" style="margin-bottom: 20px;">
        <thead>
            <tr>
                <th><?php _e('Content Type', 'wpml-to-polylang-migration-fixer'); ?></th>
                <th><?php _e('Total', 'wpml-to-polylang-migration-fixer'); ?></th>
                <th><?php _e('With Language', 'wpml-to-polylang-migration-fixer'); ?></th>
                <th><?php _e('Missing', 'wpml-to-polylang-migration-fixer'); ?></th>
                <th><?php _e('Wrong Language', 'wpml-to-polylang-migration-fixer'); ?></th>
                <th><?php _e('WPML Groups', 'wpml-to-polylang-migration-fixer'); ?></th>
                <th><?php _e('PLL Groups', 'wpml-to-polylang-migration-fixer'); ?></th>
                <th><?php _e('Status', 'wpml-to-polylang-migration-fixer'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            foreach ($results['content_groups']['betterdocs'] as $type_key):
                if (isset($results['detailed_posts'][$type_key])):
                    $data = $results['detailed_posts'][$type_key];
            ?>
            <tr>
                <td><strong><?php echo esc_html($data['label']); ?></strong></td>
                <td><?php echo number_format($data['total']); ?></td>
                <td style="color: #4CAF50;"><?php echo number_format($data['with_language']); ?></td>
                <td style="color: <?php echo $data['missing_language'] > 0 ? '#f44336' : '#4CAF50'; ?>;">
                    <?php echo number_format($data['missing_language']); ?>
                </td>
                <td style="color: <?php echo ($data['wrong_language'] ?? 0) > 0 ? '#ff9800' : '#4CAF50'; ?>;">
                    <?php echo number_format($data['wrong_language'] ?? 0); ?>
                </td>
                <td><?php echo number_format($data['wpml_groups']); ?></td>
                <td><?php echo number_format($data['pll_groups']); ?></td>
                <td>
                    <?php if ($data['missing_language'] > 0 || ($data['wpml_groups'] > 0 && $data['pll_groups'] == 0)): ?>
                        <span class="dashicons dashicons-warning" style="color: #f44336;"></span>
                    <?php else: ?>
                        <span class="dashicons dashicons-yes-alt" style="color: #4CAF50;"></span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php
                endif;
            endforeach;
            ?>
        </tbody>
    </table>

    <!-- BetterDocs Taxonomies -->
    <?php
    $bd_taxonomies = ['doc_category', 'doc_tag', 'betterdocs_faq_category'];
    $has_bd_terms = false;
    foreach ($bd_taxonomies as $tax_key) {
        if (isset($results['detailed_terms'][$tax_key])) {
            $has_bd_terms = true;
            break;
        }
    }
    ?>

    <?php if ($has_bd_terms): ?>
    <h5><?php _e('BetterDocs Taxonomies', 'wpml-to-polylang-migration-fixer'); ?></h5>
    <table class="widefat striped" style="margin-bottom: 20px;">
        <thead>
            <tr>
                <th><?php _e('Taxonomy', 'wpml-to-polylang-migration-fixer'); ?></th>
                <th><?php _e('Total Terms', 'wpml-to-polylang-migration-fixer'); ?></th>
                <th><?php _e('With Language', 'wpml-to-polylang-migration-fixer'); ?></th>
                <th><?php _e('Missing Language', 'wpml-to-polylang-migration-fixer'); ?></th>
                <th><?php _e('Wrong Language', 'wpml-to-polylang-migration-fixer'); ?></th>
                <th><?php _e('Status', 'wpml-to-polylang-migration-fixer'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            foreach ($bd_taxonomies as $tax_key):
                if (isset($results['detailed_terms'][$tax_key])):
                    $data = $results['detailed_terms'][$tax_key];
            ?>
            <tr>
                <td><strong><?php echo esc_html($data['label']); ?></strong></td>
                <td><?php echo number_format($data['total']); ?></td>
                <td style="color: #4CAF50;"><?php echo number_format($data['with_language']); ?></td>
                <td style="color: <?php echo $data['missing_language'] > 0 ? '#f44336' : '#4CAF50'; ?>;">
                    <?php echo number_format($data['missing_language']); ?>
                </td>
                <td style="color: <?php echo ($data['wrong_language'] ?? 0) > 0 ? '#ff9800' : '#4CAF50'; ?>;">
                    <?php echo number_format($data['wrong_language'] ?? 0); ?>
                </td>
                <td>
                    <?php if ($data['missing_language'] > 0 || ($data['wrong_language'] ?? 0) > 0): ?>
                        <span class="dashicons dashicons-warning" style="color: #f44336;"></span>
                    <?php else: ?>
                        <span class="dashicons dashicons-yes-alt" style="color: #4CAF50;"></span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php
                endif;
            endforeach;
            ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Translation Groups Summary -->
<div class="verification-section" style="margin-bottom: 30px;">
    <h4 style="background: #f0f4f8; padding: 10px; margin: 0 0 15px 0; border-left: 4px solid #f59e0b;">
        🔗 <?php _e('Translation Groups', 'wpml-to-polylang-migration-fixer'); ?>
    </h4>

    <table class="widefat striped">
        <thead>
            <tr>
                <th><?php _e('Type', 'wpml-to-polylang-migration-fixer'); ?></th>
                <th><?php _e('WPML Groups', 'wpml-to-polylang-migration-fixer'); ?></th>
                <th><?php _e('Polylang Groups', 'wpml-to-polylang-migration-fixer'); ?></th>
                <th><?php _e('Unconverted', 'wpml-to-polylang-migration-fixer'); ?></th>
                <th><?php _e('Status', 'wpml-to-polylang-migration-fixer'); ?></th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong><?php _e('Posts', 'wpml-to-polylang-migration-fixer'); ?></strong></td>
                <td><?php echo number_format($results['translation_groups']['posts']['wpml_total']); ?></td>
                <td style="color: #4CAF50;"><?php echo number_format($results['translation_groups']['posts']['pll_total']); ?></td>
                <td style="color: <?php echo $results['translation_groups']['posts']['unconverted'] > 0 ? '#f44336' : '#4CAF50'; ?>;">
                    <?php echo number_format($results['translation_groups']['posts']['unconverted']); ?>
                </td>
                <td>
                    <?php if ($results['translation_groups']['posts']['unconverted'] > 0): ?>
                        <span class="dashicons dashicons-warning" style="color: #f44336;"></span>
                    <?php else: ?>
                        <span class="dashicons dashicons-yes-alt" style="color: #4CAF50;"></span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td><strong><?php _e('Terms', 'wpml-to-polylang-migration-fixer'); ?></strong></td>
                <td><?php echo number_format($results['translation_groups']['terms']['wpml_total']); ?></td>
                <td style="color: #4CAF50;"><?php echo number_format($results['translation_groups']['terms']['pll_total']); ?></td>
                <td style="color: <?php echo $results['translation_groups']['terms']['unconverted'] > 0 ? '#f44336' : '#4CAF50'; ?>;">
                    <?php echo number_format($results['translation_groups']['terms']['unconverted']); ?>
                </td>
                <td>
                    <?php if ($results['translation_groups']['terms']['unconverted'] > 0): ?>
                        <span class="dashicons dashicons-warning" style="color: #f44336;"></span>
                    <?php else: ?>
                        <span class="dashicons dashicons-yes-alt" style="color: #4CAF50;"></span>
                    <?php endif; ?>
                </td>
            </tr>
        </tbody>
    </table>
</div>

<!-- String Translations -->
<?php if (!empty($results['string_translations']['wpml_strings']) || !empty($results['string_translations']['polylang_strings'])): ?>
<div class="verification-section" style="margin-bottom: 30px;">
    <h4 style="background: #f0f4f8; padding: 10px; margin: 0 0 15px 0; border-left: 4px solid #6366f1;">
        🌐 <?php _e('String Translations', 'wpml-to-polylang-migration-fixer'); ?>
    </h4>

    <table class="widefat striped">
        <thead>
            <tr>
                <th><?php _e('Source', 'wpml-to-polylang-migration-fixer'); ?></th>
                <th><?php _e('Total Strings', 'wpml-to-polylang-migration-fixer'); ?></th>
                <th><?php _e('Status', 'wpml-to-polylang-migration-fixer'); ?></th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong><?php _e('WPML Strings', 'wpml-to-polylang-migration-fixer'); ?></strong></td>
                <td><?php echo number_format($results['string_translations']['wpml_strings']); ?></td>
                <td rowspan="2" style="vertical-align: middle;">
                    <?php if ($results['string_translations']['unconverted'] > 0): ?>
                        <span style="color: #f44336;">
                            <?php echo number_format($results['string_translations']['unconverted']); ?>
                            <?php _e('strings not converted', 'wpml-to-polylang-migration-fixer'); ?>
                        </span>
                    <?php else: ?>
                        <span style="color: #4CAF50;">
                            <?php _e('All strings converted', 'wpml-to-polylang-migration-fixer'); ?>
                        </span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td><strong><?php _e('Polylang Strings', 'wpml-to-polylang-migration-fixer'); ?></strong></td>
                <td><?php echo number_format($results['string_translations']['polylang_strings']); ?></td>
            </tr>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Summary and Actions -->
<div class="verification-section" style="margin-top: 30px; padding: 20px; background: #fff3cd; border-radius: 8px; border-left: 4px solid #ffc107;">
    <h4 style="margin: 0 0 15px 0;">📊 <?php _e('Summary & Recommended Actions', 'wpml-to-polylang-migration-fixer'); ?></h4>

    <?php
    $total_issues = 0;
    $action_items = [];

    // Check for posts with wrong language
    foreach ($results['detailed_posts'] as $type_key => $data) {
        if (($data['wrong_language'] ?? 0) > 0) {
            $total_issues += $data['wrong_language'];
            $action_items[] = sprintf(
                __('Fix %d %s with wrong language', 'wpml-to-polylang-migration-fixer'),
                $data['wrong_language'],
                $data['label']
            );
        }
    }

    // Check for posts without language
    foreach ($results['detailed_posts'] as $type_key => $data) {
        if ($data['missing_language'] > 0) {
            $total_issues += $data['missing_language'];
            $action_items[] = sprintf(
                __('Fix %d %s without language', 'wpml-to-polylang-migration-fixer'),
                $data['missing_language'],
                $data['label']
            );
        }
    }

    // Check for terms with wrong language
    foreach ($results['detailed_terms'] as $tax_key => $data) {
        if (($data['wrong_language'] ?? 0) > 0) {
            $total_issues += $data['wrong_language'];
            $action_items[] = sprintf(
                __('Fix %d %s terms with wrong language', 'wpml-to-polylang-migration-fixer'),
                $data['wrong_language'],
                $data['label']
            );
        }
    }

    // Check for terms without language
    foreach ($results['detailed_terms'] as $tax_key => $data) {
        if ($data['missing_language'] > 0) {
            $total_issues += $data['missing_language'];
            $action_items[] = sprintf(
                __('Fix %d %s terms without language', 'wpml-to-polylang-migration-fixer'),
                $data['missing_language'],
                $data['label']
            );
        }
    }

    // Check for unconverted translation groups
    if ($results['translation_groups']['posts']['unconverted'] > 0) {
        $total_issues += $results['translation_groups']['posts']['unconverted'];
        $action_items[] = sprintf(
            __('Convert %d post translation groups', 'wpml-to-polylang-migration-fixer'),
            $results['translation_groups']['posts']['unconverted']
        );
    }

    if ($results['translation_groups']['terms']['unconverted'] > 0) {
        $total_issues += $results['translation_groups']['terms']['unconverted'];
        $action_items[] = sprintf(
            __('Convert %d term translation groups', 'wpml-to-polylang-migration-fixer'),
            $results['translation_groups']['terms']['unconverted']
        );
    }
    ?>

    <?php if ($total_issues == 0): ?>
        <p style="color: #4CAF50; font-weight: bold; font-size: 16px;">
            ✅ <?php _e('Migration is complete! All content has been successfully converted.', 'wpml-to-polylang-migration-fixer'); ?>
        </p>
    <?php else: ?>
        <p style="color: #f44336; font-weight: bold; font-size: 16px;">
            ⚠️ <?php printf(__('Found %d total issues that need attention:', 'wpml-to-polylang-migration-fixer'), $total_issues); ?>
        </p>
        <ol style="margin: 15px 0; padding-left: 20px;">
            <?php foreach ($action_items as $index => $item): ?>
            <li style="margin-bottom: 8px;">
                <strong><?php _e('Priority', 'wpml-to-polylang-migration-fixer'); ?> <?php echo $index + 1; ?>:</strong>
                <?php echo esc_html($item); ?>
            </li>
            <?php endforeach; ?>
        </ol>

        <div style="margin-top: 20px; padding: 15px; background: #fff; border-radius: 5px;">
            <strong><?php _e('Quick Fix Actions:', 'wpml-to-polylang-migration-fixer'); ?></strong>
            <ul style="margin: 10px 0; padding-left: 20px;">
                <li><?php _e('Use "Fix Posts & Pages (Legacy)" for post language issues', 'wpml-to-polylang-migration-fixer'); ?></li>
                <li><?php _e('Use "Fix Taxonomies (Legacy)" for taxonomy issues', 'wpml-to-polylang-migration-fixer'); ?></li>
                <li><?php _e('Review Polylang translation sets for relationship issues flagged above', 'wpml-to-polylang-migration-fixer'); ?></li>
                <?php if (!empty($results['content_groups']['betterdocs'])): ?>
                <li><?php _e('Use "Fix BetterDocs (Legacy)" for documentation issues', 'wpml-to-polylang-migration-fixer'); ?></li>
                <?php endif; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>
