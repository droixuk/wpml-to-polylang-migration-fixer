<?php
/**
 * UI Renderer Class
 * 
 * Handles UI rendering for the WPML Migration Fixer plugin
 * 
 * @package WPML_Migration_Fixer
 * @since 1.0.0
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
                <p><?php _e('Fix language assignments for content migrated from WPML to Polylang', 'wpml-migration-fixer'); ?></p>
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
                
                <div style="margin-top: 20px; display: flex; gap: 10px;">
                    <button id="btn-verify" class="wpml-btn wpml-btn-secondary" onclick="wpmlFixerAjax.verifyMigration()">
                        <?php _e('Verify Migration', 'wpml-migration-fixer'); ?>
                    </button>
                    <button id="btn-test-connection" class="wpml-btn wpml-btn-secondary" onclick="wpmlFixerAjax.testConnection()">
                        <?php _e('Test Connection', 'wpml-migration-fixer'); ?>
                    </button>
                </div>
                
                <div id="verify-results" style="margin-top: 15px;"></div>
            </div>
            
            <!-- Content Analysis Accordion -->
            <div class="accordion" id="analysis-accordion">
                <div class="accordion-header" onclick="wpmlFixerAjax.toggleAccordion('analysis-accordion')">
                    <h3>📊 <?php _e('Content Analysis', 'wpml-migration-fixer'); ?></h3>
                    <span class="accordion-arrow">▼</span>
                </div>
                <div class="accordion-content">
                    <div style="margin-bottom: 15px;">
                        <button id="btn-analyze" class="wpml-btn" onclick="wpmlFixerAjax.runAnalysis()">
                            <?php _e('Run Analysis', 'wpml-migration-fixer'); ?>
                        </button>
                        <small style="margin-left: 10px; color: #666;">
                            <?php _e('Analyze your content to check language assignments', 'wpml-migration-fixer'); ?>
                        </small>
                    </div>
                    
                    <div id="analysis-results">
                        <p style="color: #666; font-size: 14px;">
                            <?php _e('Click "Run Analysis" above to check your content for language issues.', 'wpml-migration-fixer'); ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Language Diagnosis Accordion -->
            <div class="accordion" id="diagnosis-accordion">
                <div class="accordion-header" onclick="wpmlFixerAjax.toggleAccordion('diagnosis-accordion')">
                    <h3>🔍 <?php _e('Language Diagnosis & Quick Fixes', 'wpml-migration-fixer'); ?></h3>
                    <span class="accordion-arrow">▼</span>
                </div>
                <div class="accordion-content">
                    <div style="margin-bottom: 15px;">
                        <button id="btn-diagnose" class="wpml-btn" onclick="wpmlFixerAjax.runDiagnosis()">
                            <?php _e('Run Language Diagnosis', 'wpml-migration-fixer'); ?>
                        </button>
                        <small style="margin-left: 10px; color: #666;">
                            <?php _e('Identify specific language assignment problems', 'wpml-migration-fixer'); ?>
                        </small>
                    </div>
                    
                    <div id="diagnosis-results" style="margin-top: 15px;"></div>
                    
                    <!-- Emergency Fix Section -->
                    <div class="fix-section" style="margin-top: 20px; background: #ffebee; border: 2px solid #f44336;">
                        <h3>🚨 <?php _e('EMERGENCY: Fix pll_ Prefix Issue', 'wpml-migration-fixer'); ?></h3>
                        <p class="fix-description" style="color: #d32f2f; font-weight: bold;">
                            <?php _e('CRITICAL: If diagnosis shows "pll_en", "pll_es", etc. codes, your data is corrupted. Use this to fix all wrong pll_ prefixed language codes immediately!', 'wpml-migration-fixer'); ?>
                        </p>
                        <button id="btn-fix-pll-prefix" class="wpml-btn" style="background: linear-gradient(135deg, #f44336 0%, #d32f2f 100%);" onclick="wpmlFixerAjax.fixPllPrefix()">
                            <?php _e('EMERGENCY FIX: Remove pll_ Prefixes', 'wpml-migration-fixer'); ?>
                        </button>
                        <div id="progress-pll-prefix" class="progress-wrapper">
                            <div class="progress-bar">
                                <div id="progress-bar-pll-prefix" class="progress-fill"></div>
                                <div id="progress-text-pll-prefix" class="progress-text">0%</div>
                            </div>
                        </div>
                        <div id="pll-prefix-fix-status"></div>
                    </div>
                    
                    <!-- English Variants Fix -->
                    <div class="fix-section" style="margin-top: 20px; background: #fff3cd;">
                        <h3>🔧 <?php _e('Fix English Variants', 'wpml-migration-fixer'); ?></h3>
                        <p class="fix-description">
                            <?php _e('If content is assigned to wrong English variants (en-gb, en-us, etc.) instead of your configured English, use this to reassign.', 'wpml-migration-fixer'); ?>
                        </p>
                        <button id="btn-fix-english" class="wpml-btn wpml-btn-warning" onclick="wpmlFixerAjax.fixEnglishVariants()">
                            <?php _e('Fix English Variants', 'wpml-migration-fixer'); ?>
                        </button>
                        <div id="progress-english-fix" class="progress-wrapper">
                            <div class="progress-bar">
                                <div id="progress-bar-english-fix" class="progress-fill"></div>
                                <div id="progress-text-english-fix" class="progress-text">0%</div>
                            </div>
                        </div>
                        <div id="english-fix-status"></div>
                    </div>
                </div>
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