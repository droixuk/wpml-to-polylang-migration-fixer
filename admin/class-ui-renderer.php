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
            
            <!-- System Status -->
            <div class="wpml-card" style="margin-bottom: 30px;">
                <h2><span class="icon">⚙️</span> <?php _e('System Status', 'wpml-migration-fixer'); ?></h2>
                
                <div class="quick-stats">
                    <div class="stat-box">
                        <div class="stat-label"><?php _e('WPML Data', 'wpml-migration-fixer'); ?></div>
                        <div class="badge <?php echo $system_status['wpml_data_exists'] ? 'badge-success' : 'badge-error'; ?>">
                            <?php echo $system_status['wpml_data_exists'] ? '✅ ' . __('Found', 'wpml-migration-fixer') : '❌ ' . __('Missing', 'wpml-migration-fixer'); ?>
                        </div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-label"><?php _e('Polylang', 'wpml-migration-fixer'); ?></div>
                        <div class="badge <?php echo $system_status['polylang_active'] ? 'badge-success' : 'badge-error'; ?>">
                            <?php echo $system_status['polylang_active'] ? '✅ ' . __('Active', 'wpml-migration-fixer') : '❌ ' . __('Inactive', 'wpml-migration-fixer'); ?>
                        </div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-label"><?php _e('Languages', 'wpml-migration-fixer'); ?></div>
                        <div style="font-size: 14px; margin-top: 5px;">
                            <?php 
                            if ($system_status['polylang_active']) {
                                echo implode(', ', $system_status['languages']);
                            } else {
                                echo 'N/A';
                            }
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
                    <button id="btn-comprehensive-verify" class="wpml-btn wpml-btn-large" onclick="wpmlFixerAjax.runComprehensiveVerification()">
                        <?php _e('🔍 Comprehensive Verification', 'wpml-migration-fixer'); ?>
                    </button>
                    <button id="btn-verify" class="wpml-btn wpml-btn-secondary" onclick="wpmlFixerAjax.verifyMigration()">
                        <?php _e('Basic Verify', 'wpml-migration-fixer'); ?>
                    </button>
                    <button id="btn-test-connection" class="wpml-btn wpml-btn-secondary" onclick="wpmlFixerAjax.testConnection()">
                        <?php _e('Test Connection', 'wpml-migration-fixer'); ?>
                    </button>
                </div>
                
                <div style="margin-top: 15px; padding: 10px; background: #e3f2fd; border-radius: 5px; font-size: 14px;">
                    <strong><?php _e('💡 Tip:', 'wpml-migration-fixer'); ?></strong>
                    <?php _e('Use "Comprehensive Verification" to get a detailed analysis of your migration. It checks translation groups, language assignments, and data integrity.', 'wpml-migration-fixer'); ?>
                </div>
                
                <div id="verify-results" style="margin-top: 15px;"></div>
            </div>
            
            <!-- Main Fix Actions Accordion -->
            <div class="accordion" id="fixes-accordion">
                <div class="accordion-header" onclick="wpmlFixerAjax.toggleAccordion('fixes-accordion')">
                    <h3>🛠️ <?php _e('Main Fix Actions', 'wpml-migration-fixer'); ?></h3>
                    <span class="accordion-arrow">▼</span>
                </div>
                <div class="accordion-content">
                    <p style="color: #666; margin-bottom: 20px;">
                        <?php _e('Process content in batches to prevent timeouts. Each section handles different content types.', 'wpml-migration-fixer'); ?>
                    </p>
                    
                    <!-- Posts & Pages -->
                    <div class="fix-section">
                        <h3>📝 <?php _e('Posts & Pages', 'wpml-migration-fixer'); ?></h3>
                        <p class="fix-description">
                            <?php _e('Fix language assignments for all posts, pages, and custom post types', 'wpml-migration-fixer'); ?>
                        </p>
                        <button id="btn-posts" class="wpml-btn" onclick="wpmlFixerAjax.startProcess('posts')">
                            <?php _e('Fix Posts & Pages', 'wpml-migration-fixer'); ?>
                        </button>
                        <div id="progress-posts" class="progress-wrapper">
                            <div class="progress-bar">
                                <div id="progress-bar-posts" class="progress-fill"></div>
                                <div id="progress-text-posts" class="progress-text">0%</div>
                            </div>
                        </div>
                        <div id="status-posts" class="status-message"></div>
                    </div>
                    
                    <!-- Categories & Tags -->
                    <div class="fix-section">
                        <h3>🏷️ <?php _e('Categories & Tags', 'wpml-migration-fixer'); ?></h3>
                        <p class="fix-description">
                            <?php _e('Fix all taxonomies including categories, tags, and custom taxonomies', 'wpml-migration-fixer'); ?>
                        </p>
                        <button id="btn-taxonomies" class="wpml-btn" onclick="wpmlFixerAjax.startProcess('taxonomies')">
                            <?php _e('Fix All Taxonomies', 'wpml-migration-fixer'); ?>
                        </button>
                        <div id="progress-taxonomies" class="progress-wrapper">
                            <div class="progress-bar">
                                <div id="progress-bar-taxonomies" class="progress-fill"></div>
                                <div id="progress-text-taxonomies" class="progress-text">0%</div>
                            </div>
                        </div>
                        <div id="status-taxonomies" class="status-message"></div>
                    </div>
                    
                    <?php if (class_exists('WooCommerce')): ?>
                    <!-- WooCommerce Section -->
                    <div class="fix-section" style="background: #f0f8ff; border: 1px solid #d0e5ff;">
                        <h3>🛍️ <?php _e('WooCommerce', 'wpml-migration-fixer'); ?></h3>
                        
                        <!-- WooCommerce Products & Taxonomies -->
                        <div style="margin-bottom: 15px; padding: 15px; background: white; border-radius: 5px;">
                            <h4 style="margin: 0 0 10px 0; font-size: 16px;">
                                <?php _e('Products & Categories', 'wpml-migration-fixer'); ?>
                            </h4>
                            <p class="fix-description">
                                <?php _e('Fix product categories, tags, shipping classes, and variations', 'wpml-migration-fixer'); ?>
                            </p>
                            <button id="btn-woocommerce" class="wpml-btn" onclick="wpmlFixerAjax.startProcess('woocommerce')">
                                <?php _e('Fix WooCommerce Content', 'wpml-migration-fixer'); ?>
                            </button>
                            <div id="progress-woocommerce" class="progress-wrapper">
                                <div class="progress-bar">
                                    <div id="progress-bar-woocommerce" class="progress-fill"></div>
                                    <div id="progress-text-woocommerce" class="progress-text">0%</div>
                                </div>
                            </div>
                            <div id="status-woocommerce" class="status-message"></div>
                        </div>
                        
                        <!-- WooCommerce Attributes -->
                        <div style="padding: 15px; background: white; border-radius: 5px;">
                            <h4 style="margin: 0 0 10px 0; font-size: 16px;">
                                <?php _e('Product Attributes', 'wpml-migration-fixer'); ?>
                            </h4>
                            <p class="fix-description">
                                <?php _e('Special fix for product attribute terms that aren\'t showing in the correct language', 'wpml-migration-fixer'); ?>
                            </p>
                            <button id="btn-fix-woo-attributes" class="wpml-btn wpml-btn-secondary" onclick="wpmlFixerAjax.fixWooAttributes()">
                                <?php _e('Fix Attribute Terms Language', 'wpml-migration-fixer'); ?>
                            </button>
                            <div id="progress-woo-attributes" class="progress-wrapper">
                                <div class="progress-bar">
                                    <div id="progress-bar-woo-attributes" class="progress-fill"></div>
                                    <div id="progress-text-woo-attributes" class="progress-text">0%</div>
                                </div>
                            </div>
                            <div id="woo-attributes-fix-status"></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (post_type_exists('docs')): ?>
                    <!-- BetterDocs -->
                    <div class="fix-section">
                        <h3>📚 <?php _e('BetterDocs', 'wpml-migration-fixer'); ?></h3>
                        <p class="fix-description">
                            <?php _e('Fix BetterDocs documentation and their categories', 'wpml-migration-fixer'); ?>
                        </p>
                        <button id="btn-betterdocs" class="wpml-btn" onclick="wpmlFixerAjax.startProcess('betterdocs')">
                            <?php _e('Fix BetterDocs', 'wpml-migration-fixer'); ?>
                        </button>
                        <div id="progress-betterdocs" class="progress-wrapper">
                            <div class="progress-bar">
                                <div id="progress-bar-betterdocs" class="progress-fill"></div>
                                <div id="progress-text-betterdocs" class="progress-text">0%</div>
                            </div>
                        </div>
                        <div id="status-betterdocs" class="status-message"></div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Translation Groups -->
                    <div class="fix-section">
                        <h3>🔗 <?php _e('Translation Groups', 'wpml-migration-fixer'); ?></h3>
                        <p class="fix-description">
                            <?php _e('Link translated content together', 'wpml-migration-fixer'); ?>
                        </p>
                        <button id="btn-translations" class="wpml-btn" onclick="wpmlFixerAjax.startProcess('translations')">
                            <?php _e('Fix Translation Groups', 'wpml-migration-fixer'); ?>
                        </button>
                        <div id="progress-translations" class="progress-wrapper">
                            <div class="progress-bar">
                                <div id="progress-bar-translations" class="progress-fill"></div>
                                <div id="progress-text-translations" class="progress-text">0%</div>
                            </div>
                        </div>
                        <div id="status-translations" class="status-message"></div>
                    </div>
                </div>
            </div>
            
            <!-- Debug Console (Hidden by default) -->
            <div id="debug-console" class="wpml-card" style="display: none; margin-top: 20px; background: #f5f5f5; border-left: 4px solid #ff9800;">
                <h3>🐛 <?php _e('Debug Console', 'wpml-migration-fixer'); ?></h3>
                <div id="debug-output" style="background: #000; color: #0f0; font-family: monospace; font-size: 12px; padding: 15px; border-radius: 5px; height: 200px; overflow-y: auto;">
                    <div><?php _e('Debug console initialized...', 'wpml-migration-fixer'); ?></div>
                </div>
                <button onclick="wpmlFixerAjax.clearDebug()" class="wpml-btn wpml-btn-secondary" style="margin-top: 10px;">
                    <?php _e('Clear Debug Log', 'wpml-migration-fixer'); ?>
                </button>
            </div>
        </div>
        <?php
    }
}