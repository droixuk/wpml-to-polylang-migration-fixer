/**
 * Admin JavaScript for WPML Migration Fixer plugin
 * Version: 1.0.0
 */

jQuery(document).ready(function($) {
    window.wpmlFixerAjax = {
        sessionFixed: {},
        wooAttributesFixed: 0,
        
        /**
         * Toggle accordion
         */
        toggleAccordion: function(id) {
            $("#" + id).toggleClass("active");
        },
        
        /**
         * Reset session
         */
        resetSession: function() {
            var self = this;
            if (!confirm(self.strings.confirmReset)) {
                return;
            }
            
            self.sessionFixed = {};
            $.post(self.ajaxUrl, {
                action: "wpml_fixer_ajax_reset_session",
                nonce: self.nonce
            }, function(response) {
                if (response.success) {
                    console.log('Session reset successfully');
                }
            });
        },
        
        /**
         * Verify migration
         */
        verifyMigration: function() {
            var self = this;
            $("#verify-results").html('<div id="analysis-loading"><div class="spinner"></div><p>Verifying migration integrity...</p></div>');
            $("#btn-verify").prop("disabled", true);
            
            $.post(self.ajaxUrl, {
                action: "wpml_fixer_ajax_verify_migration",
                nonce: self.nonce
            }, function(response) {
                if (response.success) {
                    $("#verify-results").html(response.data);
                } else {
                    $("#verify-results").html('<div class="status-message status-error">Verification failed: ' + (response.data || "Unknown error") + '</div>');
                }
                $("#btn-verify").prop("disabled", false);
            }).fail(function() {
                $("#verify-results").html('<div class="status-message status-error">Connection error. Please try again.</div>');
                $("#btn-verify").prop("disabled", false);
            });
        },
        
        /**
         * Run analysis
         */
        runAnalysis: function() {
            var self = this;
            $("#analysis-results").html('<div id="analysis-loading"><div class="spinner"></div><p>Analyzing your content...</p></div>');
            $("#btn-analyze").prop("disabled", true);
            
            $.post(self.ajaxUrl, {
                action: "wpml_fixer_ajax_analyze",
                nonce: self.nonce
            }, function(response) {
                if (response.success) {
                    $("#analysis-results").html(response.data);
                } else {
                    $("#analysis-results").html('<div class="status-message status-error">Analysis failed: ' + (response.data || "Unknown error") + '</div>');
                }
                $("#btn-analyze").prop("disabled", false);
            }).fail(function() {
                $("#analysis-results").html('<div class="status-message status-error">Connection error. Please try again.</div>');
                $("#btn-analyze").prop("disabled", false);
            });
        },
        
        /**
         * Run diagnosis
         */
        runDiagnosis: function() {
            var self = this;
            $("#diagnosis-results").html('<div id="diagnosis-loading"><div class="spinner"></div><p>Running language diagnosis...</p></div>');
            $("#btn-diagnose").prop("disabled", true);
            
            $.post(self.ajaxUrl, {
                action: "wpml_fixer_ajax_diagnose",
                nonce: self.nonce
            }, function(response) {
                if (response.success) {
                    $("#diagnosis-results").html(response.data);
                } else {
                    $("#diagnosis-results").html('<div class="status-message status-error">Diagnosis failed: ' + (response.data || "Unknown error") + '</div>');
                }
                $("#btn-diagnose").prop("disabled", false);
            }).fail(function() {
                $("#diagnosis-results").html('<div class="status-message status-error">Connection error. Please try again.</div>');
                $("#btn-diagnose").prop("disabled", false);
            });
        },
        
        /**
         * Fix English variants
         */
        fixEnglishVariants: function() {
            var self = this;
            if (!confirm("This will reassign all English variant content to your main English language. Continue?")) {
                return;
            }
            
            $("#progress-english-fix").show();
            $("#english-fix-status").html('<div class="status-message status-info">Starting English variants fix...</div>');
            $("#btn-fix-english").prop("disabled", true);
            
            self.processEnglishBatch(0);
        },
        
        /**
         * Process English batch
         */
        processEnglishBatch: function(offset) {
            var self = this;
            
            $.post(self.ajaxUrl, {
                action: "wpml_fixer_ajax_fix_english",
                nonce: self.nonce,
                offset: offset,
                batch_size: 100
            }, function(response) {
                if (response.success) {
                    if (response.data.continue) {
                        var percent = Math.round((response.data.processed / response.data.total) * 100);
                        $("#progress-bar-english-fix").css("width", percent + "%");
                        $("#progress-text-english-fix").text(percent + "%");
                        $("#english-fix-status").html(
                            '<div class="status-message status-info">Processing: ' + 
                            response.data.processed + ' / ' + response.data.total + 
                            ' | Fixed: ' + response.data.fixed_posts + ' posts, ' + 
                            response.data.fixed_terms + ' terms</div>'
                        );
                        
                        setTimeout(function() {
                            self.processEnglishBatch(response.data.next_offset);
                        }, 100);
                    } else {
                        $("#progress-bar-english-fix").css("width", "100%");
                        $("#progress-text-english-fix").text("100%");
                        $("#english-fix-status").html(
                            '<div class="status-message status-success">✅ Complete! Fixed ' + 
                            response.data.fixed_posts + ' posts and ' + 
                            response.data.fixed_terms + ' terms</div>'
                        );
                        $("#btn-fix-english").prop("disabled", false);
                        
                        setTimeout(function() {
                            self.runDiagnosis();
                            self.runAnalysis();
                        }, 1500);
                    }
                } else {
                    $("#english-fix-status").html(
                        '<div class="status-message status-error">Error: ' + (response.data || "Unknown error") + '</div>'
                    );
                    $("#btn-fix-english").prop("disabled", false);
                }
            }).fail(function() {
                $("#english-fix-status").html(
                    '<div class="status-message status-error">Connection error. Please try again.</div>'
                );
                $("#btn-fix-english").prop("disabled", false);
            });
        },
        
        /**
         * Fix WooCommerce attributes
         */
        fixWooAttributes: function() {
            var self = this;
            if (!confirm("This will assign the default language to all product attribute terms that have no language. Continue?")) {
                return;
            }
            
            $("#progress-woo-attributes").show();
            $("#woo-attributes-fix-status").html('<div class="status-message status-info">Analyzing attribute terms...</div>');
            $("#btn-fix-woo-attributes").prop("disabled", true);
            
            self.wooAttributesFixed = 0;
            self.processWooAttributesBatch(0);
        },
        
        /**
         * Process WooCommerce attributes batch
         */
        processWooAttributesBatch: function(offset) {
            var self = this;
            
            $.post(self.ajaxUrl, {
                action: "wpml_fixer_ajax_fix_woo_attributes",
                nonce: self.nonce,
                offset: offset,
                batch_size: 100
            }, function(response) {
                if (response.success) {
                    self.wooAttributesFixed += response.data.fixed;
                    
                    if (response.data.continue) {
                        var percent = Math.round((response.data.processed / response.data.total) * 100);
                        $("#progress-bar-woo-attributes").css("width", percent + "%");
                        $("#progress-text-woo-attributes").text(percent + "%");
                        $("#woo-attributes-fix-status").html(
                            '<div class="status-message status-info">Processing: ' + 
                            response.data.processed + ' / ' + response.data.total + 
                            ' | Fixed: ' + self.wooAttributesFixed + ' terms</div>'
                        );
                        
                        setTimeout(function() {
                            self.processWooAttributesBatch(response.data.next_offset);
                        }, 100);
                    } else {
                        $("#progress-bar-woo-attributes").css("width", "100%");
                        $("#progress-text-woo-attributes").text("100%");
                        $("#woo-attributes-fix-status").html(
                            '<div class="status-message status-success">✅ Complete! Fixed ' + 
                            self.wooAttributesFixed + ' attribute terms</div>'
                        );
                        $("#btn-fix-woo-attributes").prop("disabled", false);
                        
                        setTimeout(function() {
                            self.runAnalysis();
                        }, 1500);
                    }
                } else {
                    $("#woo-attributes-fix-status").html(
                        '<div class="status-message status-error">Error: ' + (response.data || "Unknown error") + '</div>'
                    );
                    $("#btn-fix-woo-attributes").prop("disabled", false);
                }
            }).fail(function() {
                $("#woo-attributes-fix-status").html(
                    '<div class="status-message status-error">Connection error. Please try again.</div>'
                );
                $("#btn-fix-woo-attributes").prop("disabled", false);
            });
        },
        
        /**
         * Fix pll_ prefix
         */
        fixPllPrefix: function() {
            var self = this;
            if (!confirm("EMERGENCY FIX: This will fix all content with wrong pll_ prefixed language codes. Continue?")) {
                return;
            }
            
            $("#progress-pll-prefix").show();
            $("#pll-prefix-fix-status").html('<div class="status-message status-info">Starting emergency fix...</div>');
            $("#btn-fix-pll-prefix").prop("disabled", true);
            
            self.processPllBatch(0);
        },
        
        /**
         * Process pll_ batch
         */
        processPllBatch: function(offset) {
            var self = this;
            
            $.post(self.ajaxUrl, {
                action: "wpml_fixer_ajax_fix_pll_prefix",
                nonce: self.nonce,
                offset: offset,
                batch_size: 100
            }, function(response) {
                if (response.success) {
                    if (response.data.continue) {
                        var percent = Math.round((response.data.processed / response.data.total) * 100);
                        $("#progress-bar-pll-prefix").css("width", percent + "%");
                        $("#progress-text-pll-prefix").text(percent + "%");
                        $("#pll-prefix-fix-status").html(
                            '<div class="status-message status-info">Processing: ' + 
                            response.data.processed + ' / ' + response.data.total + 
                            ' | Fixed: ' + response.data.fixed + '</div>'
                        );
                        
                        setTimeout(function() {
                            self.processPllBatch(response.data.next_offset);
                        }, 100);
                    } else {
                        $("#progress-bar-pll-prefix").css("width", "100%");
                        $("#progress-text-pll-prefix").text("100%");
                        $("#pll-prefix-fix-status").html(
                            '<div class="status-message status-success">✅ EMERGENCY FIX COMPLETE! Fixed ' + 
                            response.data.fixed + ' items with wrong pll_ prefix.</div>'
                        );
                        $("#btn-fix-pll-prefix").prop("disabled", false);
                        
                        setTimeout(function() {
                            self.runDiagnosis();
                            self.runAnalysis();
                        }, 1500);
                    }
                } else {
                    $("#pll-prefix-fix-status").html(
                        '<div class="status-message status-error">Error: ' + (response.data || "Unknown error") + '</div>'
                    );
                    $("#btn-fix-pll-prefix").prop("disabled", false);
                }
            }).fail(function() {
                $("#pll-prefix-fix-status").html(
                    '<div class="status-message status-error">Connection error. Please try again.</div>'
                );
                $("#btn-fix-pll-prefix").prop("disabled", false);
            });
        },
        
        /**
         * Start process
         */
        startProcess: function(type) {
            var self = this;
            
            if (!confirm(self.strings.confirmFix)) {
                return;
            }
            
            self.sessionFixed[type] = 0;
            
            $("#progress-" + type).show();
            $("#btn-" + type).prop("disabled", true);
            $("#status-" + type).removeClass().addClass("status-message status-info").html("Initializing...");
            
            self.processBatch(type, 0, 0, 0);
        },
        
        /**
         * Process batch
         */
        processBatch: function(type, offset, totalItems, totalFixed) {
            var self = this;
            
            $.post(self.ajaxUrl, {
                action: "wpml_fixer_ajax_process",
                nonce: self.nonce,
                type: type,
                offset: offset,
                batch_size: 20,
                total_fixed: totalFixed
            }, function(response) {
                if (response.success) {
                    if (totalItems === 0) {
                        totalItems = response.data.total;
                    }
                    
                    var newTotalFixed = totalFixed + response.data.fixed;
                    self.sessionFixed[type] = newTotalFixed;
                    
                    var percent = totalItems > 0 ? 
                        Math.round((response.data.processed / totalItems) * 100) : 0;
                    
                    $("#progress-bar-" + type).css("width", percent + "%");
                    $("#progress-text-" + type).text(percent + "%");
                    $("#status-" + type).html(
                        "Processing: " + response.data.processed + " / " + totalItems + 
                        " | <strong>Total Fixed: " + newTotalFixed + "</strong>" +
                        (response.data.debug ? '<div class="debug-info">' + response.data.debug + '</div>' : "")
                    );
                    
                    if (response.data.continue) {
                        setTimeout(function() {
                            self.processBatch(type, response.data.next_offset, totalItems, newTotalFixed);
                        }, 200);
                    } else {
                        $("#btn-" + type).prop("disabled", false);
                        $("#status-" + type).removeClass("status-info").addClass("status-success").html(
                            "✅ Complete! <strong>Fixed " + newTotalFixed + " items</strong> out of " + totalItems + " total"
                        );
                        
                        setTimeout(function() {
                            self.runAnalysis();
                        }, 1000);
                    }
                } else {
                    $("#btn-" + type).prop("disabled", false);
                    $("#status-" + type).removeClass().addClass("status-message status-error").html(
                        "Error: " + (response.data || "Unknown error")
                    );
                }
            }).fail(function(xhr, status, error) {
                $("#btn-" + type).prop("disabled", false);
                $("#status-" + type).removeClass().addClass("status-message status-error").html(
                    "Connection error: " + error + ". Please try again."
                );
            });
        }
    };
    
    // Initialize with localized data if available
    if (typeof wpmlFixerAjaxData !== 'undefined') {
        $.extend(window.wpmlFixerAjax, wpmlFixerAjaxData);
    }
});