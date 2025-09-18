/**
 * Admin JavaScript for WPML Migration Fixer plugin
 * Version: 1.0.1 - Enhanced with better debugging
 */

jQuery(document).ready(function($) {
    window.wpmlFixerAjax = {
        sessionFixed: {},
        wooAttributesFixed: 0,
        debugEnabled: false,
        
        /**
         * Initialize the interface
         */
        init: function() {
            var self = this;
            
            // Debug nonce information
            self.debugLog('Initializing WPML Fixer...');
            self.debugLog('AJAX URL: ' + self.ajaxUrl);
            self.debugLog('Nonce: ' + (self.nonce ? self.nonce.substring(0, 10) + '...' : 'NOT SET'));
            self.debugLog('Nonce Name: ' + (self.nonceName || 'NOT SET'));
            
            // Check initial debug state
            self.debugEnabled = $('#debug-toggle').is(':checked');
            self.toggleDebugConsole();
            
            // Bind debug toggle
            $('#debug-toggle').on('change', function() {
                self.debugEnabled = $(this).is(':checked');
                self.toggleDebugConsole();
                self.debugLog('Debug mode ' + (self.debugEnabled ? 'enabled' : 'disabled'));
                
                // Save debug preference
                $.post(self.ajaxUrl, {
                    action: 'wpml_fixer_ajax_save_debug_setting',
                    nonce: self.nonce,
                    enabled: self.debugEnabled
                });
            });
            
            self.debugLog('WPML Migration Fixer initialized');
        },
        
        /**
         * Toggle debug console visibility
         */
        toggleDebugConsole: function() {
            if (this.debugEnabled) {
                $('#debug-console').show();
            } else {
                $('#debug-console').hide();
            }
        },
        
        /**
         * Add debug log entry
         */
        debugLog: function(message, type) {
            if (!this.debugEnabled) return;
            
            type = type || 'info';
            var timestamp = new Date().toLocaleTimeString();
            var color = type === 'error' ? '#f44336' : type === 'warning' ? '#ff9800' : '#4caf50';
            var prefix = type === 'error' ? '❌' : type === 'warning' ? '⚠️' : '📝';
            
            var logEntry = '<div style="color: ' + color + '; margin: 2px 0;">' +
                          '[' + timestamp + '] ' + prefix + ' ' + message + '</div>';
            
            $('#debug-output').append(logEntry);
            $('#debug-output').scrollTop($('#debug-output')[0].scrollHeight);
        },
        
        /**
         * Clear debug log
         */
        clearDebug: function() {
            $('#debug-output').html('<div>Debug console cleared...</div>');
        },
        
        /**
         * Test AJAX connection
         */
        testConnection: function() {
            var self = this;
            $("#btn-test-connection").prop("disabled", true);
            self.debugLog('Testing AJAX connection...');
            self.debugLog('Using nonce: ' + (self.nonce ? self.nonce.substring(0, 10) + '...' : 'NONE'));
            self.debugLog('AJAX URL: ' + self.ajaxUrl);
            
            var requestData = {
                action: "wpml_fixer_ajax_test_connection",
                nonce: self.nonce,
                test_data: "Connection test from frontend - " + new Date().toISOString()
            };
            
            self.debugLog('Request data: ' + JSON.stringify(requestData, null, 2));
            
            $.post(self.ajaxUrl, requestData, function(response) {
                self.debugLog('Response received: ' + JSON.stringify(response, null, 2).substring(0, 200) + '...');
                
                if (response && response.success) {
                    self.debugLog('✅ Connection test successful: ' + JSON.stringify(response.data), 'info');
                    $("#verify-results").html(
                        '<div class="status-message status-success">✅ AJAX connection working properly!</div>' +
                        '<div style="margin-top: 10px; font-size: 12px;">' +
                        '<strong>Server Response:</strong><br>' +
                        'Message: ' + (response.data.message || 'N/A') + '<br>' +
                        'Timestamp: ' + (response.data.timestamp || 'N/A') + '<br>' +
                        'Components: ' + JSON.stringify(response.data.components || {}) +
                        '</div>'
                    );
                } else {
                    var errorMsg = response && response.data ? response.data : 'Unknown response format';
                    var troubleshoot = '';
                    
                    // Add specific troubleshooting based on error type
                    if (typeof errorMsg === 'object' && errorMsg.error) {
                        if (errorMsg.error.includes('nonce') || errorMsg.error.includes('Security')) {
                            troubleshoot = '<div style="margin-top: 10px; padding: 10px; background: #fff3cd; border-radius: 5px;">' +
                                         '<strong>🔧 Troubleshooting:</strong><br>' +
                                         '• Try refreshing the page to get a new security token<br>' +
                                         '• Check if you are logged in as an administrator<br>' +
                                         '• Clear browser cache and cookies<br>' +
                                         'Nonce Debug: Valid=' + (errorMsg.nonce_debug ? errorMsg.nonce_debug.valid : 'Unknown') +
                                         '</div>';
                        }
                        errorMsg = errorMsg.error;
                    }
                    
                    self.debugLog('❌ Connection test failed: ' + errorMsg, 'error');
                    $("#verify-results").html(
                        '<div class="status-message status-error">' +
                        '❌ Connection test failed: ' + errorMsg + '<br>' +
                        '<small>Check the debug console for more details</small>' +
                        '</div>' + troubleshoot
                    );
                }
                $("#btn-test-connection").prop("disabled", false);
            }).fail(function(xhr, status, error) {
                var errorDetails = 'Status: ' + status + ', Error: ' + error;
                if (xhr.responseText) {
                    errorDetails += ', Response: ' + xhr.responseText.substring(0, 200);
                    self.debugLog('Server response text: ' + xhr.responseText, 'error');
                }
                self.debugLog('❌ Connection failed completely: ' + errorDetails, 'error');
                $("#verify-results").html(
                    '<div class="status-message status-error">' +
                    '❌ AJAX connection failed completely!<br>' +
                    '<small>Status: ' + status + ', Error: ' + error + '</small><br>' +
                    '<small>Response Code: ' + (xhr.status || 'Unknown') + '</small><br>' +
                    '<small>Check browser console and server error logs</small>' +
                    '</div>'
                );
                $("#btn-test-connection").prop("disabled", false);
            });
        },
        
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
            self.debugLog('Resetting session...');
            
            $.post(self.ajaxUrl, {
                action: "wpml_fixer_ajax_reset_session",
                nonce: self.nonce
            }, function(response) {
                if (response && response.success) {
                    self.debugLog('✅ Session reset successfully');
                } else {
                    self.debugLog('❌ Session reset failed: ' + (response.data || 'Unknown error'), 'error');
                }
            }).fail(function(xhr, status, error) {
                self.debugLog('❌ Session reset failed: ' + error, 'error');
            });
        },
        
        /**
         * Verify migration
         */
        verifyMigration: function() {
            var self = this;
            $("#verify-results").html('<div id="analysis-loading"><div class="spinner"></div><p>Verifying migration integrity...</p></div>');
            $("#btn-verify").prop("disabled", true);
            self.debugLog('Starting migration verification...');
            
            $.post(self.ajaxUrl, {
                action: "wpml_fixer_ajax_verify_migration",
                nonce: self.nonce
            }, function(response) {
                if (response && response.success) {
                    $("#verify-results").html(response.data);
                    self.debugLog('✅ Verification completed successfully');
                } else {
                    var errorMsg = response && response.data ? response.data : 'Unknown error';
                    $("#verify-results").html('<div class="status-message status-error">Verification failed: ' + errorMsg + '</div>');
                    self.debugLog('❌ Verification failed: ' + errorMsg, 'error');
                }
                $("#btn-verify").prop("disabled", false);
            }).fail(function(xhr, status, error) {
                var errorMsg = 'Connection error: ' + error;
                $("#verify-results").html('<div class="status-message status-error">' + errorMsg + '</div>');
                self.debugLog('❌ Verification failed: ' + errorMsg, 'error');
                $("#btn-verify").prop("disabled", false);
            });
        },
        
        /**
         * Run analysis with enhanced error reporting
         */
        runAnalysis: function() {
            var self = this;
            $("#analysis-results").html('<div id="analysis-loading"><div class="spinner"></div><p>Analyzing your content...</p></div>');
            $("#btn-analyze").prop("disabled", true);
            self.debugLog('Starting content analysis...');
            
            $.post(self.ajaxUrl, {
                action: "wpml_fixer_ajax_analyze",
                nonce: self.nonce,
                debug: self.debugEnabled
            }, function(response) {
                self.debugLog('Analysis response received: ' + JSON.stringify(response).substring(0, 100) + '...');
                
                if (response && response.success) {
                    $("#analysis-results").html(response.data);
                    self.debugLog('✅ Analysis completed successfully');
                } else {
                    var errorMsg = response && response.data ? response.data : 'Unknown error - no response.data';
                    var errorHtml = '<div class="status-message status-error">' +
                                   '<strong>Analysis failed:</strong> ' + errorMsg + 
                                   '</div>';
                    
                    if (self.debugEnabled) {
                        errorHtml += '<div class="debug-info" style="margin-top: 10px; font-size: 11px; background: #f5f5f5; padding: 10px; border-radius: 5px;">' +
                                    '<strong>Debug Info:</strong><br>' +
                                    'Response: ' + JSON.stringify(response, null, 2) +
                                    '</div>';
                    }
                    
                    $("#analysis-results").html(errorHtml);
                    self.debugLog('❌ Analysis failed: ' + errorMsg, 'error');
                }
                $("#btn-analyze").prop("disabled", false);
            }).fail(function(xhr, status, error) {
                var errorMsg = 'AJAX Error - Status: ' + status + ', Error: ' + error;
                var errorHtml = '<div class="status-message status-error">' +
                               '<strong>Analysis failed with connection error:</strong><br>' +
                               'Status: ' + status + '<br>' +
                               'Error: ' + error +
                               '</div>';
                
                if (self.debugEnabled && xhr.responseText) {
                    errorHtml += '<div class="debug-info" style="margin-top: 10px; font-size: 11px; background: #f5f5f5; padding: 10px; border-radius: 5px; max-height: 200px; overflow: auto;">' +
                                '<strong>Server Response:</strong><br>' +
                                '<pre>' + xhr.responseText.substring(0, 1000) + (xhr.responseText.length > 1000 ? '...' : '') + '</pre>' +
                                '</div>';
                }
                
                $("#analysis-results").html(errorHtml);
                self.debugLog('❌ Analysis failed: ' + errorMsg, 'error');
                $("#btn-analyze").prop("disabled", false);
            });
        },
        
        /**
         * Run diagnosis with enhanced error reporting
         */
        runDiagnosis: function() {
            var self = this;
            $("#diagnosis-results").html('<div id="diagnosis-loading"><div class="spinner"></div><p>Running language diagnosis...</p></div>');
            $("#btn-diagnose").prop("disabled", true);
            self.debugLog('Starting language diagnosis...');
            
            $.post(self.ajaxUrl, {
                action: "wpml_fixer_ajax_diagnose",
                nonce: self.nonce,
                debug: self.debugEnabled
            }, function(response) {
                self.debugLog('Diagnosis response received');
                
                if (response && response.success) {
                    $("#diagnosis-results").html(response.data);
                    self.debugLog('✅ Diagnosis completed successfully');
                } else {
                    var errorMsg = response && response.data ? response.data : 'Unknown error';
                    var errorHtml = '<div class="status-message status-error">Diagnosis failed: ' + errorMsg + '</div>';
                    
                    if (self.debugEnabled) {
                        errorHtml += '<div class="debug-info" style="margin-top: 10px; font-size: 11px; background: #f5f5f5; padding: 10px; border-radius: 5px;">' +
                                    'Response: ' + JSON.stringify(response, null, 2) +
                                    '</div>';
                    }
                    
                    $("#diagnosis-results").html(errorHtml);
                    self.debugLog('❌ Diagnosis failed: ' + errorMsg, 'error');
                }
                $("#btn-diagnose").prop("disabled", false);
            }).fail(function(xhr, status, error) {
                var errorMsg = 'Connection error: ' + error;
                $("#diagnosis-results").html('<div class="status-message status-error">' + errorMsg + '</div>');
                self.debugLog('❌ Diagnosis failed: ' + errorMsg, 'error');
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
            self.debugLog('Starting English variants fix...');
            
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
                if (response && response.success) {
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
                        self.debugLog('✅ English variants fix completed');
                        
                        setTimeout(function() {
                            self.runDiagnosis();
                            self.runAnalysis();
                        }, 1500);
                    }
                } else {
                    var errorMsg = response && response.data ? response.data : 'Unknown error';
                    $("#english-fix-status").html(
                        '<div class="status-message status-error">Error: ' + errorMsg + '</div>'
                    );
                    self.debugLog('❌ English variants fix failed: ' + errorMsg, 'error');
                    $("#btn-fix-english").prop("disabled", false);
                }
            }).fail(function(xhr, status, error) {
                var errorMsg = 'Connection error: ' + error;
                $("#english-fix-status").html(
                    '<div class="status-message status-error">' + errorMsg + '</div>'
                );
                self.debugLog('❌ English variants fix failed: ' + errorMsg, 'error');
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
            self.debugLog('Starting WooCommerce attributes fix...');
            
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
                if (response && response.success) {
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
                        self.debugLog('✅ WooCommerce attributes fix completed');
                        
                        setTimeout(function() {
                            self.runAnalysis();
                        }, 1500);
                    }
                } else {
                    var errorMsg = response && response.data ? response.data : 'Unknown error';
                    $("#woo-attributes-fix-status").html(
                        '<div class="status-message status-error">Error: ' + errorMsg + '</div>'
                    );
                    self.debugLog('❌ WooCommerce attributes fix failed: ' + errorMsg, 'error');
                    $("#btn-fix-woo-attributes").prop("disabled", false);
                }
            }).fail(function() {
                var errorMsg = 'Connection error. Please try again.';
                $("#woo-attributes-fix-status").html(
                    '<div class="status-message status-error">' + errorMsg + '</div>'
                );
                self.debugLog('❌ WooCommerce attributes fix failed: ' + errorMsg, 'error');
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
            self.debugLog('Starting pll_ prefix emergency fix...');
            
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
                if (response && response.success) {
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
                        self.debugLog('✅ pll_ prefix emergency fix completed');
                        
                        setTimeout(function() {
                            self.runDiagnosis();
                            self.runAnalysis();
                        }, 1500);
                    }
                } else {
                    var errorMsg = response && response.data ? response.data : 'Unknown error';
                    $("#pll-prefix-fix-status").html(
                        '<div class="status-message status-error">Error: ' + errorMsg + '</div>'
                    );
                    self.debugLog('❌ pll_ prefix fix failed: ' + errorMsg, 'error');
                    $("#btn-fix-pll-prefix").prop("disabled", false);
                }
            }).fail(function() {
                var errorMsg = 'Connection error. Please try again.';
                $("#pll-prefix-fix-status").html(
                    '<div class="status-message status-error">' + errorMsg + '</div>'
                );
                self.debugLog('❌ pll_ prefix fix failed: ' + errorMsg, 'error');
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
            self.debugLog('Starting process: ' + type);
            
            self.processBatch(type, 0, 0, 0);
        },
        
        /**
         * Process batch with enhanced error reporting
         */
        processBatch: function(type, offset, totalItems, totalFixed) {
            var self = this;
            
            $.post(self.ajaxUrl, {
                action: "wpml_fixer_ajax_process",
                nonce: self.nonce,
                type: type,
                offset: offset,
                batch_size: 20,
                total_fixed: totalFixed,
                debug: self.debugEnabled
            }, function(response) {
                if (response && response.success) {
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
                        self.debugLog('✅ Process ' + type + ' completed. Fixed: ' + newTotalFixed + ' items');
                        
                        setTimeout(function() {
                            self.runAnalysis();
                        }, 1000);
                    }
                } else {
                    var errorMsg = response && response.data ? response.data : 'Unknown error';
                    $("#btn-" + type).prop("disabled", false);
                    $("#status-" + type).removeClass().addClass("status-message status-error").html(
                        "Error: " + errorMsg
                    );
                    self.debugLog('❌ Process ' + type + ' failed: ' + errorMsg, 'error');
                }
            }).fail(function(xhr, status, error) {
                var errorMsg = 'Connection error: ' + error + ' (Status: ' + status + ')';
                $("#btn-" + type).prop("disabled", false);
                $("#status-" + type).removeClass().addClass("status-message status-error").html(
                    errorMsg + '. Please try again.'
                );
                self.debugLog('❌ Process ' + type + ' failed: ' + errorMsg, 'error');
            });
        }
    };
    
    // Initialize with localized data if available
    if (typeof wpmlFixerAjax !== 'undefined') {
        $.extend(window.wpmlFixerAjax, wpmlFixerAjax);
    }
    
    // Set up default strings if not provided
    if (!window.wpmlFixerAjax.strings) {
        window.wpmlFixerAjax.strings = {
            confirmReset: 'Are you sure you want to reset the session?',
            confirmFix: 'This will process all items. Continue?',
            processing: 'Processing...',
            complete: 'Complete!',
            error: 'An error occurred. Please check the logs.'
        };
    }
    
    // Set up AJAX URL and nonce with proper fallbacks
    if (!window.wpmlFixerAjax.ajaxUrl) {
        window.wpmlFixerAjax.ajaxUrl = (typeof ajaxurl !== 'undefined') ? ajaxurl : '/wp-admin/admin-ajax.php';
    }
    
    // Multiple fallback methods for nonce
    if (!window.wpmlFixerAjax.nonce) {
        // Try multiple sources for nonce
        var possibleNonce = window.wpmlFixerNonce || 
                           $('input[name="wpml_fixer_nonce"]').val() || 
                           $('meta[name="wpml_fixer_nonce"]').attr('content') ||
                           '';
        
        if (possibleNonce) {
            window.wpmlFixerAjax.nonce = possibleNonce;
            console.log('WPML Fixer: Using fallback nonce');
        } else {
            console.error('WPML Fixer: No nonce found! AJAX requests will fail.');
        }
    }
    
    // Set debug state from localized data
    if (typeof window.wpmlFixerAjax.debug !== 'undefined') {
        $('#debug-toggle').prop('checked', window.wpmlFixerAjax.debug);
    }
    
    // Initialize the interface
    window.wpmlFixerAjax.init();
});