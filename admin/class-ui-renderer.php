<?php
/**
 * UI Renderer Class
 * 
 * Enhanced UI rendering for the WPML Migration Fixer plugin
 * 
 * @package WPML_Migration_Fixer
 * @since 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPML_Fixer_UI_Renderer {
    
    /**
     * Render main page
     */
    public function render_main_page($system_status) {
        ?>
        <div class="wpml-fixer-container">
            <!-- Header -->
            <div class="wpml-header">
                <h1>🔧 <?php _e('WPML to Polylang Migration Fixer', 'wpml-migration-fixer'); ?></h1>
                <p><?php _e('Professional migration verification and content language assignment fixes', 'wpml-migration-fixer'); ?></p>
            </div>
            
            <!-- Migration Guide -->
            <!-- System Status -->
            <div class="wpml-card" style="margin-bottom: 30px;">
                <h2><span class="icon">⚙️</span> <?php _e('System Status', 'wpml-migration-fixer'); ?></h2>
                
                <?php
                $wpml        = isset($system_status['wpml']) ? $system_status['wpml'] : ['data_exists' => !empty($system_status['wpml_data_exists']), 'version' => ''];
                $polylang    = isset($system_status['polylang']) ? $system_status['polylang'] : ['active' => !empty($system_status['polylang_active']), 'version' => ''];
                $woocommerce = isset($system_status['woocommerce']) ? $system_status['woocommerce'] : ['active' => false, 'version' => ''];
                $betterdocs  = isset($system_status['betterdocs']) ? $system_status['betterdocs'] : ['active' => false, 'version' => ''];
                $acf         = isset($system_status['acf']) ? $system_status['acf'] : ['active' => false, 'version' => ''];
                $languages   = isset($system_status['languages']) ? (array) $system_status['languages'] : [];
                ?>
                <div class="quick-stats">
                    <div class="stat-box">
                        <div class="stat-label"><?php _e('WPML', 'wpml-migration-fixer'); ?></div>
                        <div class="badge <?php echo $system_status['wpml_data_exists'] ? 'badge-success' : 'badge-error'; ?>">
                            <?php echo $system_status['wpml_data_exists'] ? '✅ ' . __('Found', 'wpml-migration-fixer') : '❌ ' . __('Missing', 'wpml-migration-fixer'); ?>
                        </div>
                        <?php if (!empty($wpml['version']) && $system_status['wpml_data_exists']) : ?>
                            <div class="stat-meta"><?php printf(__('Version %s', 'wpml-migration-fixer'), esc_html($wpml['version'])); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="stat-box">
                        <div class="stat-label"><?php _e('Polylang', 'wpml-migration-fixer'); ?></div>
                        <div class="badge <?php echo $system_status['polylang_active'] ? 'badge-success' : 'badge-error'; ?>">
                            <?php echo $system_status['polylang_active'] ? '✅ ' . __('Active', 'wpml-migration-fixer') : '❌ ' . __('Inactive', 'wpml-migration-fixer'); ?>
                        </div>
                        <?php if (!empty($polylang['version']) && $system_status['polylang_active']) : ?>
                            <div class="stat-meta"><?php printf(__('Version %s', 'wpml-migration-fixer'), esc_html($polylang['version'])); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="stat-box">
                        <div class="stat-label"><?php _e('WooCommerce', 'wpml-migration-fixer'); ?></div>
                        <div class="badge <?php echo $woocommerce['active'] ? 'badge-success' : 'badge-info'; ?>">
                            <?php echo $woocommerce['active'] ? '✅ ' . __('Active', 'wpml-migration-fixer') : 'ℹ️ ' . __('Optional', 'wpml-migration-fixer'); ?>
                        </div>
                        <?php if (!empty($woocommerce['version']) && $woocommerce['active']) : ?>
                            <div class="stat-meta"><?php printf(__('Version %s', 'wpml-migration-fixer'), esc_html($woocommerce['version'])); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="stat-box">
                        <div class="stat-label"><?php _e('BetterDocs', 'wpml-migration-fixer'); ?></div>
                        <div class="badge <?php echo $betterdocs['active'] ? 'badge-success' : 'badge-info'; ?>">
                            <?php echo $betterdocs['active'] ? '✅ ' . __('Detected', 'wpml-migration-fixer') : 'ℹ️ ' . __('Not Detected', 'wpml-migration-fixer'); ?>
                        </div>
                        <?php if (!empty($betterdocs['version']) && $betterdocs['active']) : ?>
                            <div class="stat-meta"><?php printf(__('Version %s', 'wpml-migration-fixer'), esc_html($betterdocs['version'])); ?></div>
                        <?php endif; ?>
                    </div>
                    <?php
                    $acf_present = !empty($acf['present']);
                    $acf_active = !empty($acf['active']);
                    $acf_badge_class = $acf_active ? 'badge-success' : ($acf_present ? 'badge-warning' : 'badge-info');
                    $acf_badge_label = $acf_active
                        ? '✅ ' . __('Detected', 'wpml-migration-fixer')
                        : ($acf_present ? '⚠️ ' . __('No Translation Data', 'wpml-migration-fixer') : 'ℹ️ ' . __('Not Detected', 'wpml-migration-fixer'));
                    ?>
                    <div class="stat-box">
                        <div class="stat-label"><?php _e('ACF', 'wpml-migration-fixer'); ?></div>
                        <div class="badge <?php echo esc_attr($acf_badge_class); ?>">
                            <?php echo esc_html($acf_badge_label); ?>
                        </div>
                        <?php if (!empty($acf['version']) && $acf_present) : ?>
                            <div class="stat-meta"><?php printf(__('Version %s', 'wpml-migration-fixer'), esc_html($acf['version'])); ?></div>
                        <?php endif; ?>
                        <?php if ($acf_present && !$acf_active) : ?>
                            <div class="stat-meta"><?php _e('Translation data not detected', 'wpml-migration-fixer'); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="stat-box">
                        <div class="stat-label"><?php _e('Languages', 'wpml-migration-fixer'); ?></div>
                        <div style="font-size: 14px; margin-top: 5px;">
                            <?php
                            if ($system_status['polylang_active'] && !empty($languages)) {
                                $safe_languages = array_map('esc_html', $languages);
                                echo implode(', ', $safe_languages);
                            } else {
                                echo esc_html__('N/A', 'wpml-migration-fixer');
                            }
                            ?>
                        </div>
                        <div class="stat-meta">
                            <?php
                            $default_language = !empty($system_status['default_language'])
                                ? esc_html($system_status['default_language'])
                                : esc_html__('Not set', 'wpml-migration-fixer');
                            printf(__('Default: %s', 'wpml-migration-fixer'), $default_language);
                            ?>
                        </div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-label"><?php _e('Debug Mode', 'wpml-migration-fixer'); ?></div>
                        <div style="margin-top: 5px;">
                            <label>
                                <input type="checkbox" id="debug-toggle" <?php checked(get_option('wpml_to_polylang_fixer_debug_enabled', false)); ?>>
                                <?php _e('Show Debug Info', 'wpml-migration-fixer'); ?>
                            </label>
                        </div>
                    </div>
                </div>
                
                <!-- Enhanced Verification Section -->
                <div style="margin-top: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
                    <button id="btn-comprehensive-verify" class="wpml-btn wpml-btn-large" data-wmf-action="runComprehensiveVerification">
                        <?php _e('🔍 Comprehensive Verification', 'wpml-migration-fixer'); ?>
                    </button>
                </div>
                
                <div style="margin-top: 15px; padding: 10px; background: #e3f2fd; border-radius: 5px; font-size: 14px;">
                    <strong><?php _e('💡 Tip:', 'wpml-migration-fixer'); ?></strong>
                    <?php _e('Use "Comprehensive Verification" to get a detailed analysis of your migration. It checks translation groups, language assignments, and data integrity.', 'wpml-migration-fixer'); ?>
                </div>
                
                <div id="verify-results" style="margin-top: 15px;"></div>
            </div>
            
            <!-- Fix Actions (Always Open) -->
            <div class="accordion accordion--static active always-open" id="fixes-accordion">
                <div class="accordion-header" style="cursor: default; pointer-events: none;">
                    <h3>🛠️ <?php _e('Fix Actions', 'wpml-migration-fixer'); ?></h3>
                </div>
                <div class="accordion-content">
                    <p style="color: #666; margin-bottom: 20px;">
                        <?php _e('Process content in batches to prevent timeouts. Each section handles different content types.', 'wpml-migration-fixer'); ?>
                    </p>
                    

                    <!-- Posts & Pages -->
                    <div class="fix-section">
                        <h3>📝 <?php _e('Posts & Pages', 'wpml-migration-fixer'); ?></h3>
                        <p class="fix-description">
                            <?php _e('Fix language assignments for all posts, pages, and custom post types using WPML data', 'wpml-migration-fixer'); ?>
                        </p>
                        <button id="btn-posts" class="wpml-btn" data-progress-trigger="posts" data-wmf-process="posts">
                            <?php _e('Fix Posts & Pages (Legacy)', 'wpml-migration-fixer'); ?>
                        </button>
                        <div id="progress-posts" class="progress-wrapper" data-progress-for="posts">
                            <div class="progress-bar" data-progress-role="bar">
                                <div id="progress-bar-posts" class="progress-fill" data-progress-role="fill"></div>
                                <div id="progress-text-posts" class="progress-text" data-progress-role="text">0%</div>
                            </div>
                            <?php $this->render_progress_meta(); ?>
                        </div>
                    </div>

                    <!-- Categories & Tags -->
                    <div class="fix-section">
                        <h3>🏷️ <?php _e('Categories & Tags', 'wpml-migration-fixer'); ?></h3>
                        <p class="fix-description">
                            <?php _e('Fix all taxonomies including categories, tags, custom taxonomies, and WooCommerce attributes', 'wpml-migration-fixer'); ?>
                        </p>
                        <button id="btn-taxonomies" class="wpml-btn" data-progress-trigger="taxonomies" data-wmf-process="taxonomies">
                            <?php _e('Fix Taxonomies (Legacy)', 'wpml-migration-fixer'); ?>
                        </button>
                        <div id="progress-taxonomies" class="progress-wrapper" data-progress-for="taxonomies">
                            <div class="progress-bar" data-progress-role="bar">
                                <div id="progress-bar-taxonomies" class="progress-fill" data-progress-role="fill"></div>
                                <div id="progress-text-taxonomies" class="progress-text" data-progress-role="text">0%</div>
                            </div>
                            <?php $this->render_progress_meta(); ?>
                        </div>
                    </div>

                    <?php if (class_exists('WooCommerce')): ?>
                    <!-- WooCommerce Section -->
                    <div class="fix-section">
                        <h3>🛍️ <?php _e('WooCommerce', 'wpml-migration-fixer'); ?></h3>
                        <p class="fix-description">
                            <?php _e('Fix product content, categories, shipping classes, and variations', 'wpml-migration-fixer'); ?>
                        </p>
                        <button id="btn-woocommerce" class="wpml-btn" data-progress-trigger="woocommerce" data-wmf-process="woocommerce">
                            <?php _e('Fix WooCommerce Products', 'wpml-migration-fixer'); ?>
                        </button>
                        <button id="btn-woo-attributes" class="wpml-btn wpml-btn-primary" data-progress-trigger="woo-attributes" data-wmf-action="fixWooAttributes">
                            <?php _e('Fix Product Attributes (pa_*)', 'wpml-migration-fixer'); ?>
                        </button>
                        <div id="progress-woocommerce" class="progress-wrapper" data-progress-for="woocommerce">
                            <div class="progress-bar" data-progress-role="bar">
                                <div id="progress-bar-woocommerce" class="progress-fill" data-progress-role="fill"></div>
                                <div id="progress-text-woocommerce" class="progress-text" data-progress-role="text">0%</div>
                            </div>
                            <?php $this->render_progress_meta(); ?>
                        </div>
                        <div id="progress-woo-attributes" class="progress-wrapper" data-progress-for="woo-attributes">
                            <div class="progress-bar" data-progress-role="bar">
                                <div id="progress-bar-woo-attributes" class="progress-fill" data-progress-role="fill"></div>
                                <div id="progress-text-woo-attributes" class="progress-text" data-progress-role="text">0%</div>
                            </div>
                            <?php $this->render_progress_meta(); ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (post_type_exists('docs') || post_type_exists('betterdocs_faq')): ?>
                    <!-- BetterDocs -->
                    <div class="fix-section">
                        <h3>📚 <?php _e('BetterDocs', 'wpml-migration-fixer'); ?></h3>
                        <p class="fix-description">
                            <?php _e('Fix BetterDocs documentation, FAQs, and their categories', 'wpml-migration-fixer'); ?>
                        </p>
                        <button id="btn-betterdocs" class="wpml-btn" data-progress-trigger="betterdocs" data-wmf-process="betterdocs">
                            <?php _e('Fix BetterDocs (Legacy)', 'wpml-migration-fixer'); ?>
                        </button>
                        <div id="progress-betterdocs" class="progress-wrapper" data-progress-for="betterdocs">
                            <div class="progress-bar" data-progress-role="bar">
                                <div id="progress-bar-betterdocs" class="progress-fill" data-progress-role="fill"></div>
                                <div id="progress-text-betterdocs" class="progress-text" data-progress-role="text">0%</div>
                            </div>
                            <?php $this->render_progress_meta(); ?>
                        </div>
                    </div>
                    <?php endif; ?>

                </div>
            </div>

            <!-- Debug Console (Hidden by default) -->
            <div id="debug-console" class="wpml-card" style="display: none; margin-top: 20px; background: #f5f5f5; border-left: 4px solid #ff9800;">
                <h3>🐛 <?php _e('Debug Console', 'wpml-migration-fixer'); ?></h3>
                <div id="debug-output" style="background: #000; color: #0f0; font-family: monospace; font-size: 12px; padding: 15px; border-radius: 5px; height: 200px; overflow-y: auto;">
                    <div><?php _e('Debug console initialized...', 'wpml-migration-fixer'); ?></div>
                </div>
                <button class="wpml-btn wpml-btn-secondary" data-wmf-action="clearDebug" style="margin-top: 10px;">
                    <?php _e('Clear Debug Log', 'wpml-migration-fixer'); ?>
                </button>
            </div>
        </div>
        <?php
    }

    /**
     * Render status overview page
     */
    public function render_status_page($nonce) {
        $view_file = WPML_TO_POLYLANG_FIXER_PLUGIN_DIR . 'admin/views/status.php';
        $status_nonce = $nonce;

        if (file_exists($view_file)) {
            include $view_file;
        } else {
            echo '<div class="wrap"><div class="error"><p>' . esc_html__('Status view is not available.', 'wpml-migration-fixer') . '</p></div></div>';
        }
    }

    /**
     * Render reusable progress meta layout
     */
    private function render_progress_meta() {
        ?>
        <div class="progress-meta">
            <div class="progress-meta__item">
                <span class="progress-meta__label"><?php _e('Total Items', 'wpml-migration-fixer'); ?></span>
                <span class="progress-meta__value" data-progress-role="total-count">0</span>
            </div>
            <div class="progress-meta__item">
                <span class="progress-meta__label"><?php _e('Processed', 'wpml-migration-fixer'); ?></span>
                <span class="progress-meta__value" data-progress-role="processed-count">0</span>
            </div>
            <div class="progress-meta__item">
                <span class="progress-meta__label"><?php _e('Issues Found', 'wpml-migration-fixer'); ?></span>
                <span class="progress-meta__value" data-progress-role="issues-total">0</span>
            </div>
            <div class="progress-meta__item">
                <span class="progress-meta__label"><?php _e('Issues Fixed', 'wpml-migration-fixer'); ?></span>
                <span class="progress-meta__value" data-progress-role="issues-fixed">0</span>
            </div>
            <div class="progress-meta__item">
                <span class="progress-meta__label"><?php _e('Issues Remaining', 'wpml-migration-fixer'); ?></span>
                <span class="progress-meta__value" data-progress-role="issues-remaining">0</span>
            </div>
        </div>
        <div class="progress-status" data-progress-role="status"></div>
        <?php
    }
}
