/**
 * Admin JavaScript for WPML Migration Fixer plugin
 * Version: 1.0.6 - Complete implementation with all fix functions
 */

jQuery(document).ready(function($) {
    // Initialize the main object
    window.wpmlFixerAjax = window.wpmlFixerAjax || {};
    
    // Set up default properties
    $.extend(window.wpmlFixerAjax, {
        sessionFixed: {},
        wooAttributesFixed: 0,
        debugEnabled: false,
        ajaxUrl: '',
        nonce: '',
        nonceName: '',
        strings: {}
    });
    
    // Try to load localization data from multiple sources
    function loadLocalizationData() {
        var sources = [
            'wpmlMigrationData',
            'wpmlFixerBackup', 
            'wpmlFixerAjax'
        ];
        
        var dataFound = false;
        
        for (var i = 0; i < sources.length; i++) {
            var sourceName = sources[i];
            if (typeof window[sourceName] !== 'undefined' && window[sourceName].nonce) {
                console.log('WPML Fixer: Loading data from ' + sourceName);
                $.extend(window.wpmlFixerAjax, window[sourceName]);
                dataFound = true;
                break;
            }
        }
        
        if (!dataFound) {
            console.warn('WPML Fixer: No localization data found, using defaults');
            window.wpmlFixerAjax.ajaxUrl = ajaxurl || '/wp-admin/admin-ajax.php';
            window.wpmlFixerAjax.nonceName = 'wpml_fixer_ajax';
        }
        
        // Set up default strings
        if (!window.wpmlFixerAjax.strings || Object.keys(window.wpmlFixerAjax.strings).length === 0) {
            window.wpmlFixerAjax.strings = {
                confirmReset: 'Are you sure you want to reset the session?',
                confirmFix: 'This will process all items. Continue?',
                processing: 'Processing...',
                complete: 'Complete!',
                error: 'An error occurred. Please check the logs.'
            };
        }
        
        console.log('WPML Fixer: Initialization complete', {
            hasNonce: !!window.wpmlFixerAjax.nonce,
            nonceName: window.wpmlFixerAjax.nonceName,
            ajaxUrl: window.wpmlFixerAjax.ajaxUrl
        });
    }
    
    // Load the data
    loadLocalizationData();
    
    // Main plugin object methods
    $.extend(window.wpmlFixerAjax, {
        
        /**
         * Initialize the interface
         */
        init: function() {
            var self = this;
            
            // Check initial debug state
            self.debugEnabled = $('#debug-toggle').is(':checked');
            self.toggleDebugConsole();
            
            // Bind debug toggle
            $('#debug-toggle').on('change', function() {
                self.debugEnabled = $(this).is(':checked');
                self.toggleDebugConsole();
                self.debugLog('Debug mode ' + (self.debugEnabled ? 'enabled' : 'disabled'));
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
         * Create request data with nonce
         */
        createRequestData: function(action, additionalData) {
            var requestData = { action: action };
            
            if (additionalData && typeof additionalData === 'object') {
                $.extend(requestData, additionalData);
            }
            
            // Add nonce if available
            if (this.nonceName && this.nonce) {
                requestData[this.nonceName] = this.nonce;
                this.debugLog('Adding nonce: ' + this.nonceName);
            } else {
                this.debugLog('Warning: No nonce available for request', 'warning');
            }
            
            return requestData;
        },
        
        /**
         * Test AJAX connection
         */
        testConnection: function() {
            var self = this;
            $("#btn-test-connection").prop("disabled", true);
            self.debugLog('Testing AJAX connection...');
            
            var requestData = self.createRequestData("wpml_fixer_ajax_test_connection", {
                test_data: "Connection test - " + new Date().toISOString()
            });
            
            self.debugLog('Request data keys: ' + Object.keys(requestData).join(', '));
            
            $.post(self.ajaxUrl, requestData)
                .done(function(response) {
                    self.debugLog('Response received: ' + JSON.stringify(response).substring(0, 100) + '...');
                    
                    if (response && response.success) {
                        self.debugLog('✅ Connection test successful');
                        $("#verify-results").html(
                            '<div class="status-message status-success">' +
                            '✅ AJAX connection working properly!<br>' +
                            '<small>Server response: ' + (response.data.message || 'Success') + '</small>' +
                            '</div>'
                        );
                    } else {
                        var errorMsg = response && response.data ? response.data : 'Unknown error';
                        self.debugLog('❌ Connection test failed: ' + errorMsg, 'error');
                        $("#verify-results").html(
                            '<div class="status-message status-error">' +
                            '❌ Connection test failed: ' + errorMsg +
                            '</div>'
                        );
                    }
                })
                .fail(function(xhr, status, error) {
                    self.debugLog('❌ Connection failed: ' + error, 'error');
                    $("#verify-results").html(
                        '<div class="status-message status-error">' +
                        '❌ AJAX connection failed: ' + error +
                        '</div>'
                    );
                })
                .always(function() {
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
            self.sessionFixed = {};
            var requestData = self.createRequestData("wpml_fixer_ajax_reset_session");
            $.post(self.ajaxUrl, requestData);
        },
        
        /**
         * Run analysis
         */
        runAnalysis: function() {
            var self = this;
            $("#analysis-results").html('<div class="spinner"></div><p>Analyzing your content...</p>');
            $("#btn-analyze").prop("disabled", true);
            
            var requestData = self.createRequestData("wpml_fixer_ajax_analyze");
            
            $.post(self.ajaxUrl, requestData)
                .done(function(response) {
                    if (response && response.success) {
                        $("#analysis-results").html(response.data);
                        self.debugLog('✅ Analysis completed successfully');
                    } else {
                        var errorMsg = response && response.data ? response.data : 'Unknown error';
                        $("#analysis-results").html('<div class="status-message status-error">Analysis failed: ' + errorMsg + '</div>');
                        self.debugLog('❌ Analysis failed: ' + errorMsg, 'error');
                    }
                })
                .fail(function(xhr, status, error) {
                    $("#analysis-results").html('<div class="status-message status-error">Analysis failed: ' + error + '</div>');
                    self.debugLog('❌ Analysis failed: ' + error, 'error');
                })
                .always(function() {
                    $("#btn-analyze").prop("disabled", false);
                });
        },
        
        /**
         * Run diagnosis
         */
        runDiagnosis: function() {
            var self = this;
            $("#diagnosis-results").html('<div class="spinner"></div><p>Running language diagnosis...</p>');
            $("#btn-diagnose").prop("disabled", true);
            
            var requestData = self.createRequestData("wpml_fixer_ajax_diagnose");
            
            $.post(self.ajaxUrl, requestData)
                .done(function(response) {
                    if (response && response.success) {
                        $("#diagnosis-results").html(response.data);
                        self.debugLog('✅ Diagnosis completed successfully');
                    } else {
                        var errorMsg = response && response.data ? response.data : 'Unknown error';
                        $("#diagnosis-results").html('<div class="status-message status-error">Diagnosis failed: ' + errorMsg + '</div>');
                        self.debugLog('❌ Diagnosis failed: ' + errorMsg, 'error');
                    }
                })
                .fail(function(xhr, status, error) {
                    $("#diagnosis-results").html('<div class="status-message status-error">Diagnosis failed: ' + error + '</div>');
                    self.debugLog('❌ Diagnosis failed: ' + error, 'error');
                })
                .always(function() {
                    $("#btn-diagnose").prop("disabled", false);
                });
        },
        
        /**
         * Verify migration
         */
        verifyMigration: function() {
            var self = this;
            $("#verify-results").html('<div class="spinner"></div><p>Verifying migration integrity...</p>');
            $("#btn-verify").prop("disabled", true);
            
            var requestData = self.createRequestData("wpml_fixer_ajax_verify_migration");
            
            $.post(self.ajaxUrl, requestData)
                .done(function(response) {
                    if (response && response.success) {
                        $("#verify-results").html(response.data);
                        self.debugLog('✅ Verification completed successfully');
                    } else {
                        var errorMsg = response && response.data ? response.data : 'Unknown error';
                        $("#verify-results").html('<div class="status-message status-error">Verification failed: ' + errorMsg + '</div>');
                        self.debugLog('❌ Verification failed: ' + errorMsg, 'error');
                    }
                })
                .fail(function(xhr, status, error) {
                    $("#verify-results").html('<div class="status-message status-error">Verification failed: ' + error + '</div>');
                    self.debugLog('❌ Verification failed: ' + error, 'error');
                })
                .always(function() {
                    $("#btn-verify").prop("disabled", false);
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
        
        processEnglishBatch: function(offset) {
            var self = this;
            
            var requestData = self.createRequestData("wpml_fixer_ajax_fix_english", {
                offset: offset,
                batch_size: 100
            });
            
            $.post(self.ajaxUrl, requestData)
                .done(function(response) {
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
                })
                .fail(function() {
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
        
        processWooAttributesBatch: function(offset) {
            var self = this;
            
            var requestData = self.createRequestData("wpml_fixer_ajax_fix_woo_attributes", {
                offset: offset,
                batch_size: 100
            });
            
            $.post(self.ajaxUrl, requestData)
                .done(function(response) {
                    if (response && response.success) {
                        // FIXED: Ensure numbers are properly defined
                        var fixed = parseInt(response.data.fixed) || 0;
                        self.wooAttributesFixed += fixed;
                        
                        if (response.data.continue) {
                            var processed = parseInt(response.data.processed) || 0;
                            var total = parseInt(response.data.total) || 0;
                            var percent = total > 0 ? Math.round((processed / total) * 100) : 0;
                            
                            $("#progress-bar-woo-attributes").css("width", percent + "%");
                            $("#progress-text-woo-attributes").text(percent + "%");
                            $("#woo-attributes-fix-status").html(
                                '<div class="status-message status-info">Processing: ' + 
                                processed + ' / ' + total + 
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
                })
                .fail(function() {
                    $("#woo-attributes-fix-status").html(
                        '<div class="status-message status-error">Connection error. Please try again.</div>'
                    );
                    $("#btn-fix-woo-attributes").prop("disabled", false);
                });
        },
        
        /**
         * Fix pll prefix issue (Emergency Fix)
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
        
        processPllBatch: function(offset) {
            var self = this;
            
            var requestData = self.createRequestData("wpml_fixer_ajax_fix_pll_prefix", {
                offset: offset,
                batch_size: 100
            });
            
            $.post(self.ajaxUrl, requestData)
                .done(function(response) {
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
                })
                .fail(function() {
                    $("#pll-prefix-fix-status").html(
                        '<div class="status-message status-error">Connection error. Please try again.</div>'
                    );
                    $("#btn-fix-pll-prefix").prop("disabled", false);
                });
        },
        
        /**
         * Start main processing
         */
        startProcess: function(type) {
            var self = this;
            self.sessionFixed[type] = 0;
            
            $("#progress-" + type).show();
            $("#btn-" + type).prop("disabled", true);
            $("#status-" + type).removeClass().addClass("status-message status-info").html("Initializing...");
            
            self.processBatch(type, 0, 0, 0);
        },
        
        /**
         * Process batch for main actions
         */
        processBatch: function(type, offset, totalItems, totalFixed) {
            var self = this;
            
            var requestData = self.createRequestData("wpml_fixer_ajax_process", {
                type: type,
                offset: offset,
                batch_size: 20,
                total_fixed: totalFixed
            });
            
            $.post(self.ajaxUrl, requestData)
                .done(function(response) {
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
                            
                            setTimeout(function() {
                                self.runAnalysis();
                            }, 1000);
                        }
                    } else {
                        $("#btn-" + type).prop("disabled", false);
                        $("#status-" + type).removeClass().addClass("status-message status-error").html(
                            "Error: " + (response.data || "Unknown error")
                        );
                        self.debugLog('❌ Process failed for ' + type + ': ' + (response.data || "Unknown error"), 'error');
                    }
                })
                .fail(function(xhr, status, error) {
                    $("#btn-" + type).prop("disabled", false);
                    $("#status-" + type).removeClass().addClass("status-message status-error").html(
                        "Connection error: " + error + ". Please try again."
                    );
                    self.debugLog('❌ Connection failed for ' + type + ': ' + error, 'error');
                });
        }
    });
    
    // Set debug state from checkbox
    if ($('#debug-toggle').length > 0) {
        window.wpmlFixerAjax.debugEnabled = $('#debug-toggle').is(':checked');
    }
    
    // Initialize the interface
    window.wpmlFixerAjax.init();
    
    console.log('WPML Fixer: JavaScript loaded successfully');
});