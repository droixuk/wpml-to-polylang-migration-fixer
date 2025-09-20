<?php
/**
 * Migration Guide View
 *
 * Displays step-by-step instructions for fixing WPML to Polylang migration
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wpml-card migration-guide" id="migration-guide">
    <h2>
        <span class="icon">📋</span>
        <?php _e('Migration Fix Guide', 'wpml-migration-fixer'); ?>
        <button type="button" class="guide-toggle" onclick="wpmlFixerAjax.toggleGuide()" aria-expanded="false">
            <span class="dashicons dashicons-arrow-down-alt2"></span>
        </button>
    </h2>

    <div class="guide-content" id="guide-content" style="display: none;">
        <div class="guide-intro">
            <p><?php _e('Follow these steps in order to fix language assignments after WPML to Polylang migration:', 'wpml-migration-fixer'); ?></p>
        </div>

        <div class="guide-steps">
            <!-- Step 1: Verify -->
            <div class="guide-step">
                <div class="step-number">1</div>
                <div class="step-content">
                    <h3><?php _e('Initial Verification', 'wpml-migration-fixer'); ?></h3>
                    <p><?php _e('Check the current state of your migration to identify issues.', 'wpml-migration-fixer'); ?></p>
                    <div class="step-action">
                        <button class="wpml-btn wpml-btn-small" onclick="wpmlFixerAjax.runComprehensiveVerification()">
                            <?php _e('Run Verification', 'wpml-migration-fixer'); ?>
                        </button>
                    </div>
                    <div class="step-note">
                        <?php _e('Look for: Missing language assignments, corrupted codes (pll_), unmapped variants (en-ie, en-au)', 'wpml-migration-fixer'); ?>
                    </div>
                </div>
            </div>

            <!-- Step 2: Prepare -->
            <div class="guide-step">
                <div class="step-number">2</div>
                <div class="step-content">
                    <h3><?php _e('Prepare Environment', 'wpml-migration-fixer'); ?></h3>
                    <p><?php _e('Ensure Polylang has the required structure for language assignments.', 'wpml-migration-fixer'); ?></p>
                    <div class="step-action">
                        <button class="wpml-btn wpml-btn-small" onclick="wpmlFixerAjax.ensureBuckets()">
                            <?php _e('Ensure Language Buckets', 'wpml-migration-fixer'); ?>
                        </button>
                    </div>
                    <div class="step-note">
                        <?php _e('Creates missing term_language entries that Polylang requires.', 'wpml-migration-fixer'); ?>
                    </div>
                </div>
            </div>

            <!-- Step 3: Normalize -->
            <div class="guide-step">
                <div class="step-number">3</div>
                <div class="step-content">
                    <h3><?php _e('Normalize Language Codes', 'wpml-migration-fixer'); ?></h3>
                    <p><?php _e('Fix corrupted codes and map language variants to configured languages.', 'wpml-migration-fixer'); ?></p>
                    <div class="step-action">
                        <button class="wpml-btn wpml-btn-small" onclick="wpmlFixerAjax.normalizeLanguages()">
                            <?php _e('Normalize Codes', 'wpml-migration-fixer'); ?>
                        </button>
                    </div>
                    <div class="step-note">
                        <?php _e('Fixes: pll_ prefixes, underscores → hyphens, maps en-au → en-gb', 'wpml-migration-fixer'); ?>
                    </div>
                </div>
            </div>

            <!-- Step 4: Fix Content -->
            <div class="guide-step">
                <div class="step-number">4</div>
                <div class="step-content">
                    <h3><?php _e('Fix Content Languages', 'wpml-migration-fixer'); ?></h3>
                    <p><?php _e('Assign languages to posts and taxonomies using WPML data.', 'wpml-migration-fixer'); ?></p>
                    <div class="step-actions-grid">
                        <div class="step-action">
                            <button class="wpml-btn wpml-btn-small" onclick="wpmlFixerAjax.fixAllPosts()">
                                <?php _e('Fix All Posts', 'wpml-migration-fixer'); ?>
                            </button>
                            <small><?php _e('Includes pages, custom post types', 'wpml-migration-fixer'); ?></small>
                        </div>
                        <div class="step-action">
                            <button class="wpml-btn wpml-btn-small" onclick="wpmlFixerAjax.fixAllTerms()">
                                <?php _e('Fix All Terms', 'wpml-migration-fixer'); ?>
                            </button>
                            <small><?php _e('Categories, tags, custom taxonomies', 'wpml-migration-fixer'); ?></small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 5: Plugin-Specific -->
            <div class="guide-step">
                <div class="step-number">5</div>
                <div class="step-content">
                    <h3><?php _e('Fix Plugin Content (if applicable)', 'wpml-migration-fixer'); ?></h3>
                    <p><?php _e('Handle content from specific plugins that need special attention.', 'wpml-migration-fixer'); ?></p>
                    <div class="step-actions-grid">
                        <?php if (post_type_exists('docs') || post_type_exists('betterdocs_faq')): ?>
                        <div class="step-action">
                            <button class="wpml-btn wpml-btn-small" onclick="wpmlFixerAjax.fixBetterDocs()">
                                <?php _e('Fix BetterDocs', 'wpml-migration-fixer'); ?>
                            </button>
                            <small><?php _e('Docs, FAQs, knowledge bases', 'wpml-migration-fixer'); ?></small>
                        </div>
                        <?php endif; ?>
                        <?php if (class_exists('WooCommerce')): ?>
                        <div class="step-action">
                            <button class="wpml-btn wpml-btn-small" onclick="wpmlFixerAjax.fixWooAttributes()">
                                <?php _e('Fix Product Attributes', 'wpml-migration-fixer'); ?>
                            </button>
                            <small><?php _e('WooCommerce pa_* taxonomies', 'wpml-migration-fixer'); ?></small>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php if (!post_type_exists('docs') && !class_exists('WooCommerce')): ?>
                    <div class="step-note">
                        <?php _e('No plugin-specific content detected. You can skip this step.', 'wpml-migration-fixer'); ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Step 6: Translation Groups -->
            <div class="guide-step">
                <div class="step-number">6</div>
                <div class="step-content">
                    <h3><?php _e('Fix Translation Groups', 'wpml-migration-fixer'); ?></h3>
                    <p><?php _e('Rebuild translation relationships between content in different languages.', 'wpml-migration-fixer'); ?></p>
                    <div class="step-action">
                        <button class="wpml-btn wpml-btn-small" onclick="wpmlFixerAjax.startProcess('translations')">
                            <?php _e('Fix Translation Groups', 'wpml-migration-fixer'); ?>
                        </button>
                    </div>
                    <div class="step-note warning">
                        <strong><?php _e('Important:', 'wpml-migration-fixer'); ?></strong>
                        <?php _e('Run this AFTER all language assignments are fixed.', 'wpml-migration-fixer'); ?>
                    </div>
                </div>
            </div>

            <!-- Step 7: Final Check -->
            <div class="guide-step">
                <div class="step-number">7</div>
                <div class="step-content">
                    <h3><?php _e('Final Verification', 'wpml-migration-fixer'); ?></h3>
                    <p><?php _e('Confirm all issues have been resolved.', 'wpml-migration-fixer'); ?></p>
                    <div class="step-action">
                        <button class="wpml-btn wpml-btn-small wpml-btn-success" onclick="wpmlFixerAjax.runComprehensiveVerification()">
                            <?php _e('Run Final Verification', 'wpml-migration-fixer'); ?>
                        </button>
                    </div>
                    <div class="step-note success">
                        <?php _e('Success indicators: 0 posts without language, 0 terms without language, valid translation groups', 'wpml-migration-fixer'); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Tips -->
        <div class="guide-tips">
            <h3><?php _e('💡 Pro Tips', 'wpml-migration-fixer'); ?></h3>
            <ul>
                <li><?php _e('Always backup your database before running fixes', 'wpml-migration-fixer'); ?></li>
                <li><?php _e('Process in stages for large sites (10,000+ items)', 'wpml-migration-fixer'); ?></li>
                <li><?php _e('Enable Debug Mode to track detailed progress', 'wpml-migration-fixer'); ?></li>
                <li><?php _e('Run during low-traffic hours for best performance', 'wpml-migration-fixer'); ?></li>
            </ul>
        </div>

        <!-- Troubleshooting -->
        <div class="guide-troubleshooting">
            <h3><?php _e('⚠️ Common Issues', 'wpml-migration-fixer'); ?></h3>
            <div class="troubleshooting-items">
                <details>
                    <summary><?php _e('BetterDocs FAQs showing NULL language', 'wpml-migration-fixer'); ?></summary>
                    <p><?php _e('Run "Fix BetterDocs" specifically after normalizing codes.', 'wpml-migration-fixer'); ?></p>
                </details>
                <details>
                    <summary><?php _e('English variants (en-ie, en-au) not recognized', 'wpml-migration-fixer'); ?></summary>
                    <p><?php _e('Run "Normalize Language Codes" first to map variants to your configured English.', 'wpml-migration-fixer'); ?></p>
                </details>
                <details>
                    <summary><?php _e('Process timeouts', 'wpml-migration-fixer'); ?></summary>
                    <p><?php _e('Reduce batch size or increase PHP max_execution_time. Process content types separately.', 'wpml-migration-fixer'); ?></p>
                </details>
            </div>
        </div>
    </div>
</div>

<style>
.migration-guide {
    margin-bottom: 30px;
    background: #f8f9fa;
}

.migration-guide h2 {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
}

.guide-toggle {
    background: none;
    border: none;
    cursor: pointer;
    padding: 5px;
    color: #666;
    transition: transform 0.3s;
}

.guide-toggle:hover {
    color: #333;
}

.guide-intro {
    background: #fff;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    border-left: 4px solid #2271b1;
}

.guide-steps {
    display: grid;
    gap: 20px;
}

.guide-step {
    display: flex;
    background: #fff;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    transition: box-shadow 0.3s;
}

.guide-step:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}

.step-number {
    flex-shrink: 0;
    width: 40px;
    height: 40px;
    background: #2271b1;
    color: #fff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 18px;
    margin-right: 20px;
}

.step-content {
    flex-grow: 1;
}

.step-content h3 {
    margin: 0 0 10px 0;
    color: #1e1e1e;
}

.step-content p {
    color: #666;
    margin-bottom: 15px;
}

.step-action {
    margin: 15px 0;
}

.step-actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin: 15px 0;
}

.step-action small {
    display: block;
    color: #999;
    margin-top: 5px;
    font-size: 11px;
}

.step-note {
    background: #f0f0f1;
    padding: 10px 15px;
    border-radius: 4px;
    font-size: 13px;
    color: #666;
    margin-top: 10px;
}

.step-note.warning {
    background: #fcf9e8;
    border-left: 4px solid #dba617;
    color: #826200;
}

.step-note.success {
    background: #edfaef;
    border-left: 4px solid #00a32a;
    color: #007017;
}

.guide-tips, .guide-troubleshooting {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    margin-top: 30px;
}

.guide-tips h3, .guide-troubleshooting h3 {
    margin-top: 0;
    color: #1e1e1e;
}

.guide-tips ul {
    margin: 15px 0 0 20px;
    color: #666;
}

.guide-tips li {
    margin-bottom: 8px;
}

.troubleshooting-items details {
    background: #f8f9fa;
    padding: 10px 15px;
    border-radius: 4px;
    margin-bottom: 10px;
    cursor: pointer;
}

.troubleshooting-items summary {
    font-weight: 500;
    color: #333;
}

.troubleshooting-items details[open] summary {
    margin-bottom: 10px;
}

.troubleshooting-items p {
    margin: 0;
    color: #666;
    padding-left: 20px;
}

.wpml-btn-small {
    padding: 5px 15px;
    font-size: 13px;
}

.wpml-btn-success {
    background: #00a32a;
    border-color: #00a32a;
}

.wpml-btn-success:hover {
    background: #007017;
    border-color: #007017;
}

@media (max-width: 782px) {
    .step-actions-grid {
        grid-template-columns: 1fr;
    }

    .guide-step {
        flex-direction: column;
    }

    .step-number {
        margin-bottom: 15px;
    }
}
</style>