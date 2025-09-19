/**
 * Admin JavaScript for WPML Migration Fixer plugin
 * Version: 1.1.3 - Enhanced with clear progress display and timeout prevention
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
        strings: {},
        processingStates: {} // Track processing state for each type
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
            
            self.debugLog('WPML Migration Fixer initialized with enhanced progress display');
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
        },                })
                .fail(function(xhr, status, error) {
                    $("#analysis-results").html('<div class="status-message status-error">Analysis failed: ' + error + '</div>');
                    self.debugLog('❌ Analysis failed: ' + error, 'error');
                })
                .always(function() {
                    $("#btn-analyze").prop("disabled", false);
                });
        },                })
                .fail(function(xhr, status, error) {
                    $("#diagnosis-results").html('<div class="status-message status-error">Diagnosis failed: ' + error + '</div>');
                    self.debugLog('❌ Diagnosis failed: ' + error, 'error');
                })
                .always(function() {
                    $("#btn-diagnose").prop("disabled", false);
                });
        },                })
                .fail(function(xhr, status, error) {
                    $("#verify-results").html('<div class="status-message status-error">Verification failed: ' + error + '</div>');
                    self.debugLog('❌ Verification failed: ' + error, 'error');
                })
                .always(function() {
                    $("#btn-verify").prop("disabled", false);
                });
        },
        
        /**
         * NEW: Run comprehensive verification with enhanced timeout handling
         */
        runComprehensiveVerification: function() {
            var self = this;
            $("#verify-results").html('<div class="spinner"></div><p>' + self.strings.verifying + '</p>');
            $("#btn-comprehensive-verify").prop("disabled", true);
            
            var requestData = self.createRequestData("wpml_fixer_ajax_comprehensive_verify");
            
            self.debugLog('Starting comprehensive verification with enhanced handling...');
            
            // Increased timeout for comprehensive verification
            $.ajax({
                url: self.ajaxUrl,
                type: 'POST',
                data: requestData,
                timeout: 300000, // 5 minutes timeout
                dataType: 'json'
            })
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
                var errorMessage = 'Comprehensive verification failed: ' + error;
                
                if (status === 'timeout') {
                    errorMessage = 'Verification timed out. Your site has a large amount of content. Try using "Basic Verify" or individual analysis tools instead.';
                }
                
                $("#verify-results").html(self.displayError(errorMessage, xhr, status, error));
                self.debugLog('❌ Comprehensive verification failed: ' + error, 'error');
            })
            .always(function() {
                $("#btn-comprehensive-verify").prop("disabled", false);
            });
        },
        
        /**
         * NEW: Start batch processing for different types
         */
        startProcess: function(type) {
            var self = this;
            
            // Prevent multiple simultaneous processes
            if (self.processingStates[type]) {
                self.debugLog('Process ' + type + ' already running, ignoring request', 'warning');
                return;
            }
            
            // Confirm before starting
            if (!confirm(self.strings.confirmFix)) {
                return;
            }
            
            self.debugLog('Starting process: ' + type);
            
            // Initialize processing state
            self.processingStates[type] = {
                running: true,
                offset: 0,
                total: 0,
                fixed: 0
            };
            
            // Update UI
            self.updateProcessUI(type, 'starting');
            
            // Start the batch processing
            self.processBatch(type, 0, 20); // Start with offset 0, batch size 20
        },
        
        /**
         * NEW: Process a batch for a specific type
         */
        processBatch: function(type, offset, batchSize) {
            var self = this;
            
            if (!self.processingStates[type] || !self.processingStates[type].running) {
                self.debugLog('Process ' + type + ' stopped or not initialized', 'warning');
                return;
            }
            
            self.debugLog('Processing batch for ' + type + ' - offset: ' + offset + ', batch: ' + batchSize);
            
            var requestData = self.createRequestData("wpml_fixer_ajax_process", {
                type: type,
                offset: offset,
                batch_size: batchSize
            });
            
            $.post(self.ajaxUrl, requestData)
                .done(function(response) {
                    if (response && response.success) {
                        var result = response.data;
                        
                        // Update processing state
                        var state = self.processingStates[type];
                        state.total = result.total || state.total;
                        state.fixed += result.fixed || 0;
                        
                        // Update UI
                        self.updateProcessUI(type, 'processing', {
                            processed: result.processed,
                            total: result.total,
                            fixed: state.fixed,
                            message: result.message
                        });
                        
                        self.debugLog('Batch completed for ' + type + ': ' + 
                                     'processed=' + result.processed + 
                                     ', fixed=' + result.fixed + 
                                     ', continue=' + result.continue);
                        
                        // Continue with next batch if needed
                        if (result.continue && result.next_offset !== undefined) {
                            setTimeout(function() {
                                self.processBatch(type, result.next_offset, batchSize);
                            }, 100); // Small delay to prevent overwhelming the server
                        } else {
                            // Processing complete
                            self.processingStates[type].running = false;
                            self.updateProcessUI(type, 'complete', {
                                processed: result.processed,
                                total: result.total,
                                fixed: state.fixed,
                                message: result.message || 'Complete!'
                            });
                            
                            self.debugLog('Process ' + type + ' completed successfully');
                            self.showTemporaryMessage('Process ' + type + ' completed!', 'success');
                        }
                    } else {
                        var errorMsg = response && response.data ? response.data : 'Unknown error';
                        self.processingStates[type].running = false;
                        self.updateProcessUI(type, 'error', { message: errorMsg });
                        self.debugLog('❌ Process ' + type + ' failed: ' + errorMsg, 'error');
                    }
                })
                .fail(function(xhr, status, error) {
                    self.processingStates[type].running = false;
                    self.updateProcessUI(type, 'error', { message: error });
                    self.debugLog('❌ Process ' + type + ' failed: ' + error, 'error');
                });
        },
        
        /**
         * NEW: Update processing UI elements with enhanced progress display
         */
        updateProcessUI: function(type, status, data) {
            var self = this;
            data = data || {};
            
            var button = $('#btn-' + type);
            var progressWrapper = $('#progress-' + type);
            var progressBar = $('#progress-bar-' + type);
            var progressText = $('#progress-text-' + type);
            var statusDiv = $('#status-' + type);
            
            switch (status) {
                case 'starting':
                    button.prop('disabled', true).text(self.strings.processing);
                    progressWrapper.show();
                    progressBar.css('width', '0%');
                    progressText.text('0%');
                    
                    if (type === 'translations') {
                        statusDiv.html('<div class="status-message status-info">Starting 3-phase repair: Corrupted → Posts → Terms</div>');
                    } else {
                        statusDiv.html('<div class="status-message status-info">Scanning for items to process...</div>');
                    }
                    break;
                    
                case 'processing':
                    var percentage = data.total > 0 ? Math.round((data.processed / data.total) * 100) : 0;
                    progressBar.css('width', percentage + '%');
                    
                    // Clear progress text showing current numbers
                    var progressDisplay = percentage + '%';
                    if (data.total > 0) {
                        progressDisplay += ' (' + data.processed + '/' + data.total + ')';
                    }
                    progressText.text(progressDisplay);
                    
                    // Enhanced status message with clear counts
                    var statusMessage = '';
                    if (data.total > 0) {
                        statusMessage = 'Processing: <strong>' + data.processed + ' of ' + data.total + ' items</strong>';
                        if (data.fixed !== undefined && data.fixed > 0) {
                            statusMessage += ' | <strong style="color: #2e7d32;">Fixed: ' + data.fixed + '</strong>';
                        }
                    } else {
                        statusMessage = data.message || 'Processing...';
                    }
                    
                    // Special handling for translation groups phases
                    if (type === 'translations' && data.message) {
                        if (data.message.includes('corrupted')) {
                            statusMessage = 'Phase 1 - Corrupted Groups: ' + statusMessage;
                        } else if (data.message.includes('post groups')) {
                            statusMessage = 'Phase 2 - Post Groups: ' + statusMessage;
                        } else if (data.message.includes('term groups')) {
                            statusMessage = 'Phase 3 - Term Groups: ' + statusMessage;
                        }
                    }
                    
                    statusDiv.html('<div class="status-message status-info">' + statusMessage + '</div>');
                    break;
                    
                case 'complete':
                    button.prop('disabled', false).text(button.data('original-text') || 'Fix ' + type);
                    progressBar.css('width', '100%');
                    progressText.text('Complete!');
                    
                    // Clear completion message
                    var completeMessage = '✅ <strong>Processing Complete!</strong>';
                    if (data.total > 0) {
                        completeMessage += '<br>Processed: <strong>' + data.total + ' items</strong>';
                        if (data.fixed !== undefined) {
                            completeMessage += ' | Fixed: <strong style="color: #2e7d32;">' + data.fixed + ' items</strong>';
                            
                            if (data.fixed === 0) {
                                completeMessage += ' <em>(All items already had correct language assignments)</em>';
                            }
                        }
                    } else {
                        completeMessage += '<br><em>No items needed processing</em>';
                    }
                    
                    statusDiv.html('<div class="status-message status-success">' + completeMessage + '</div>');
                    
                    // Hide progress after a longer delay so user can read the results
                    setTimeout(function() {
                        progressWrapper.fadeOut();
                    }, 5000);
                    break;
                    
                case 'error':
                    button.prop('disabled', false).text(button.data('original-text') || 'Fix ' + type);
                    progressBar.css('width', '0%');
                    progressText.text('Error');
                    
                    var errorMessage = '❌ <strong>Error occurred</strong>';
                    if (data.message) {
                        errorMessage += '<br>' + data.message;
                    }
                    
                    statusDiv.html('<div class="status-message status-error">' + errorMessage + '</div>');
                    progressWrapper.fadeOut();
                    break;
            }
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
        },;
                this.processingStates = {}; // Reset processing states
                var requestData = this.createRequestData("wpml_fixer_ajax_reset_session");
                $.post(this.ajaxUrl, requestData);
                this.debugLog('Session reset', 'info');
            }
        }
    });
    
    // Store original button text for restoration
    $('[id^="btn-"]').each(function() {
        $(this).data('original-text', $(this).text());
    });
    
    // Set debug state from checkbox
    if ($('#debug-toggle').length > 0) {
        window.wpmlFixerAjax.debugEnabled = $('#debug-toggle').is(':checked');
    }
    
    // Initialize the interface
    window.wpmlFixerAjax.init();
    
    console.log('WPML Fixer: Enhanced JavaScript with clear progress display loaded successfully');
});