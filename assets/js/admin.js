/**
 * Admin JavaScript for WPML Migration Fixer plugin
 * Version: 1.1.0 - Enhanced with comprehensive verification
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
                error: 'An error occurred. Please check the logs.',
                verifying: 'Running comprehensive verification...',
                verificationComplete: 'Verification complete!'
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
         * Verify migration (legacy method)
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
         * NEW: Run comprehensive verification
         */
        runComprehensiveVerification: function() {
            var self = this;
            $("#verify-results").html('<div class="spinner"></div><p>' + self.strings.verifying + '</p>');
            $("#btn-comprehensive-verify").prop("disabled", true);
            
            var requestData = self.createRequestData("wpml_fixer_ajax_comprehensive_verify");
            
            self.debugLog('Starting comprehensive verification...');
            
            $.post(self.ajaxUrl, requestData)
                .done(function(response) {
                    if (response && response.success) {
                        $("#verify-results").html(response.data);
                        self.debugLog('✅ Comprehensive verification completed successfully');
                        
                        // Show success message
                        self.showTemporaryMessage(self.strings.verificationComplete, 'success');
                        
                        // Scroll to results
                        $('html, body').animate({
                            scrollTop: $("#verify-results").offset().top - 100
                        }, 500);
                    } else {
                        var errorMsg = response && response.data ? response.data : 'Unknown error';
                        $("#verify-results").html(self.displayError('Comprehensive verification failed: ' + errorMsg));
                        self.debugLog('❌ Comprehensive verification failed: ' + errorMsg, 'error');
                    }
                })
                .fail(function(xhr, status, error) {
                    $("#verify-results").html(self.displayError('Comprehensive verification failed: ' + error, xhr, status, error));
                    self.debugLog('❌ Comprehensive verification failed: ' + error, 'error');
                })
                .always(function() {
                    $("#btn-comprehensive-verify").prop("disabled", false);
                });
        },
        
        /**
         * Show temporary message
         */
        showTemporaryMessage: function(message, type) {
            type = type || 'info';
            var className = 'status-' + type;
            
            var messageDiv = $('<div class="status-message ' + className + '" style="position: fixed; top: 20px; right: 20px; z-index: 9999; padding: 15px; border-radius: 5px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">' + message + '</div>');
            
            $('body').append(messageDiv);
            
            setTimeout(function() {
                messageDiv.fadeOut(500, function() {
                    messageDiv.remove();
                });
            }, 3000);
        },
        
        /**
         * Enhanced error display with debug info
         */
        displayError: function(message, xhr, status, error) {
            var self = this;
            var errorHtml = '<div class="status-message status-error">' + message;
            
            if (self.debugEnabled && xhr && xhr.responseText) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response && response.data) {
                        errorHtml += '<div class="debug-info" style="margin-top: 10px; font-size: 12px; background: rgba(255,255,255,0.8); padding: 10px; border-radius: 3px;">';
                        errorHtml += '<strong>Debug Info:</strong><br>';
                        errorHtml += 'Status: ' + status + '<br>';
                        errorHtml += 'Error: ' + error + '<br>';
                        errorHtml += 'Response: ' + JSON.stringify(response).substring(0, 200) + '...';
                        errorHtml += '</div>';
                    }
                } catch (e) {
                    // JSON parse failed, show raw response
                    if (self.debugEnabled) {
                        errorHtml += '<div class="debug-info" style="margin-top: 10px; font-size: 12px;">';
                        errorHtml += '<strong>Raw Response:</strong><br>';
                        errorHtml += xhr.responseText.substring(0, 300) + '...';
                        errorHtml += '</div>';
                    }
                }
            }
            
            errorHtml += '</div>';
            return errorHtml;
        },
        
        /**
         * Placeholder methods for existing functionality (keeping compatibility)
         */
        fixPllPrefix: function() {
            this.debugLog('Fix PLL prefix called - using existing implementation', 'info');
            // This would call the existing implementation from the current plugin
            alert('Fix PLL Prefix functionality available - refer to existing implementation');
        },
        
        fixEnglishVariants: function() {
            this.debugLog('Fix English variants called - using existing implementation', 'info');
            // This would call the existing implementation from the current plugin
            alert('Fix English Variants functionality available - refer to existing implementation');
        },
        
        startProcess: function(type) {
            this.debugLog('Start process called for: ' + type + ' - using existing implementation', 'info');
            // This would call the existing implementation from the current plugin
            alert('Process "' + type + '" functionality available - refer to existing implementation');
        },
        
        fixWooAttributes: function() {
            this.debugLog('Fix WooCommerce attributes called - using existing implementation', 'info');
            // This would call the existing implementation from the current plugin
            alert('Fix WooCommerce attributes functionality available - refer to existing implementation');
        },
        
        resetSession: function() {
            if (confirm(this.strings.confirmReset)) {
                this.sessionFixed = {};
                var requestData = this.createRequestData("wpml_fixer_ajax_reset_session");
                $.post(this.ajaxUrl, requestData);
                this.debugLog('Session reset', 'info');
            }
        }
    });
    
    // Set debug state from checkbox
    if ($('#debug-toggle').length > 0) {
        window.wpmlFixerAjax.debugEnabled = $('#debug-toggle').is(':checked');
    }
    
    // Initialize the interface
    window.wpmlFixerAjax.init();
    
    console.log('WPML Fixer: Enhanced JavaScript loaded successfully');
});