/**
 * Admin JavaScript for WPML Migration Fixer plugin
 * Version: 1.0.5 - Clean, simplified version to fix syntax errors
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