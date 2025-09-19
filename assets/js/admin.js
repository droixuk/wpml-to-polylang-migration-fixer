/**
 * Admin JavaScript for WPML Migration Fixer plugin
 * Version: 1.1.3 - Enhanced with clear progress display and timeout prevention
 */

jQuery(document).ready(function($) {
    // Initialize the main object
    window.wpmlFixerAjax = window.wpmlFixerAjax || {};
    
    // Set up default properties
    $.extend(window.wpmlFixerAjax, {
        debugEnabled: false,
        ajaxUrl: '',
        nonce: '',
        nonceName: '',
        strings: {},
        processingStates: {}, // Track processing state for each type
        progressControllers: {} // Cache DOM references for reusable progress UI
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
            self.cacheProgressControllers();
            
            // Bind debug toggle
            $('#debug-toggle').on('change', function() {
                self.debugEnabled = $(this).is(':checked');
                self.toggleDebugConsole();
                self.debugLog('Debug mode ' + (self.debugEnabled ? 'enabled' : 'disabled'));
            });
            
            self.debugLog('WPML Migration Fixer initialized with enhanced progress display');
        },
        
        /**
         * Cache all progress controllers declared in the markup so they can be reused
         */
        cacheProgressControllers: function() {
            var self = this;
            self.progressControllers = self.progressControllers || {};
            $('.progress-wrapper[data-progress-for]').each(function() {
                var $wrapper = $(this);
                var type = $wrapper.data('progress-for');
                if (!type || self.progressControllers[type]) {
                    return;
                }
                self.registerProgressType(type, { wrapper: $wrapper });
            });
        },
        
        /**
         * Register a progress UI so it can be controlled programmatically
         * @param {string} type
         * @param {Object} elements (optional overrides)
         * @returns {Object|null}
         */
        registerProgressType: function(type, elements) {
            if (!type) {
                return null;
            }

            var self = this;
            elements = elements || {};

            var $wrapper = elements.wrapper ? $(elements.wrapper) : $('.progress-wrapper[data-progress-for="' + type + '"]');
            if (!$wrapper.length) {
                $wrapper = $('#progress-' + type);
            }
            if (!$wrapper.length) {
                if (self.debugEnabled) {
                    self.debugLog('No progress wrapper found for type "' + type + '"', 'warning');
                }
                return null;
            }
            $wrapper = $wrapper.first();

            var $button = elements.button ? $(elements.button) : $('[data-progress-trigger="' + type + '"]');
            if (!$button.length) {
                $button = $('#btn-' + type);
            }
            $button = $button.first();
            if ($button.length && !$button.data('original-text')) {
                $button.data('original-text', $.trim($button.text()));
            }

            var $status = elements.status ? $(elements.status) : $('[data-progress-status="' + type + '"]');
            if (!$status.length) {
                $status = $('#status-' + type);
            }
            $status = $status.first();

            var controller = {
                type: type,
                button: $button,
                wrapper: $wrapper,
                bar: elements.bar ? $(elements.bar) : $wrapper.find('[data-progress-role="bar"], .progress-bar').first(),
                fill: elements.fill ? $(elements.fill) : $wrapper.find('[data-progress-role="fill"], .progress-fill').first(),
                text: elements.text ? $(elements.text) : $wrapper.find('[data-progress-role="text"], .progress-text').first(),
                processed: elements.processed ? $(elements.processed) : $wrapper.find('[data-progress-role="processed-count"]').first(),
                fixed: elements.fixed ? $(elements.fixed) : $wrapper.find('[data-progress-role="fixed-count"]').first(),
                status: $status,
                counts: {
                    processed: 0,
                    total: 0,
                    fixed: 0
                }
            };

            this.progressControllers[type] = controller;

            if (self.debugEnabled) {
                self.debugLog('Registered progress controller for type "' + type + '"');
            }

            return controller;
        },
        
        /**
         * Get or lazily register a progress controller
         * @param {string} type
         * @returns {Object|null}
         */
        getProgressController: function(type) {
            if (!type) {
                return null;
            }

            this.progressControllers = this.progressControllers || {};
            if (!this.progressControllers[type]) {
                return this.registerProgressType(type);
            }

            return this.progressControllers[type];
        },
        
        /**
         * Update processed and fixed counts displayed beneath the progress bar
         * @param {Object} controller
         * @param {number} processed
         * @param {number} total
         * @param {number} fixed
         */
        setProgressCounts: function(controller, processed, total, fixed) {
            if (!controller) {
                return;
            }

            processed = typeof processed === 'number' ? processed : parseInt(processed || 0, 10) || 0;
            total = typeof total === 'number' ? total : parseInt(total || 0, 10) || 0;
            fixed = typeof fixed === 'number' ? fixed : parseInt(fixed || 0, 10) || 0;

            controller.counts = {
                processed: processed,
                total: total,
                fixed: fixed
            };

            if (controller.processed && controller.processed.length) {
                var processedDisplay = total > 0 ? processed + ' / ' + total : processed;
                controller.processed.text(processedDisplay);
            }

            if (controller.fixed && controller.fixed.length) {
                controller.fixed.text(fixed);
            }
        },

        /**
         * Format the label that appears within the progress bar itself
         * @param {number} percentage
         * @param {number} processed
         * @param {number} total
         * @param {number} fixed
         * @returns {string}
         */
        formatProgressLabel: function(percentage, processed, total, fixed) {
            var parts = [];
            var safePercent = isNaN(percentage) ? 0 : Math.max(0, Math.min(percentage, 100));
            parts.push(Math.round(safePercent) + '%');

            if (total > 0 || processed > 0 || fixed > 0) {
                if (total > 0) {
                    parts.push(processed + ' / ' + total);
                } else {
                    parts.push(processed + ' processed');
                }

                parts.push('Fixed ' + fixed);
            }

            return parts.join(' | ');
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
            
            var controller = self.getProgressController(type);
            if (!controller) {
                if (self.debugEnabled) {
                    self.debugLog('Unable to locate progress UI for type "' + type + '"', 'error');
                }
            }

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
            
            var controller = self.getProgressController(type);
            if (!controller) {
                return;
            }

            var button = controller.button;
            var progressWrapper = controller.wrapper;
            var progressFill = controller.fill;
            var progressText = controller.text;
            var statusDiv = controller.status;
            
            switch (status) {
                case 'starting':
                    if (button && button.length) {
                        button.prop('disabled', true).text(self.strings.processing);
                    }
                    if (progressWrapper && progressWrapper.length) {
                        progressWrapper.stop(true, true).show();
                    }
                    if (progressFill && progressFill.length) {
                        progressFill.css('width', '0%');
                    }
                    self.setProgressCounts(controller, 0, data.total || 0, 0);
                    if (progressText && progressText.length) {
                        var initialText = self.formatProgressLabel(0, 0, data.total || 0, 0);
                        progressText.text(initialText);
                    }

                    if (type === 'translations') {
                        if (statusDiv && statusDiv.length) {
                            statusDiv.html('<div class="status-message status-info">Starting 3-phase repair: Corrupted → Posts → Terms</div>');
                        }
                    } else if (statusDiv && statusDiv.length) {
                        statusDiv.html('<div class="status-message status-info">Scanning for items to process...</div>');
                    }
                    break;
                    
                case 'processing':
                    var total = data.total || 0;
                    var processed = data.processed || 0;
                    var fixedCount = data.fixed || 0;
                    var percentage = total > 0 ? Math.round((processed / total) * 100) : 0;
                    if (progressFill && progressFill.length) {
                        progressFill.css('width', percentage + '%');
                    }

                    // Clear progress text showing current numbers
                    if (progressText && progressText.length) {
                        progressText.text(self.formatProgressLabel(percentage, processed, total, fixedCount));
                    }
                    self.setProgressCounts(controller, processed, total, fixedCount);

                    // Enhanced status message with clear counts
                    var statusMessage = data.message || 'Processing...';

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
                    
                    if (statusDiv && statusDiv.length) {
                        statusDiv.html('<div class="status-message status-info">' + statusMessage + '</div>');
                    }
                    break;
                    
                case 'complete':
                    if (button && button.length) {
                        button.prop('disabled', false).text(button.data('original-text') || 'Fix ' + type);
                    }
                    if (progressFill && progressFill.length) {
                        progressFill.css('width', '100%');
                    }
                    var completeTotal = data.total || 0;
                    var completeProcessed = data.processed || completeTotal;
                    var completeFixed = data.fixed || 0;
                    self.setProgressCounts(controller, completeProcessed, completeTotal || completeProcessed, completeFixed);
                    if (progressText && progressText.length) {
                        var completePercentage = completeTotal > 0 ? 100 : (completeProcessed > 0 ? 100 : 0);
                        progressText.text(self.formatProgressLabel(completePercentage, completeProcessed, completeTotal || completeProcessed, completeFixed));
                    }

                    // Clear completion message
                    var completeMessage = '✅ <strong>Processing Complete!</strong>';
                    if (completeTotal > 0 || completeProcessed > 0) {
                        var processedSummary = completeTotal > 0 ? completeTotal : completeProcessed;
                        completeMessage += '<br>Processed: <strong>' + processedSummary + ' items</strong>';
                        if (data.fixed !== undefined) {
                            completeMessage += ' | Fixed: <strong style="color: #2e7d32;">' + completeFixed + ' items</strong>';
                            
                            if (completeFixed === 0) {
                                completeMessage += ' <em>(All items already had correct language assignments)</em>';
                            }
                        }
                    } else {
                        completeMessage += '<br><em>No items needed processing</em>';
                    }
                    
                    if (statusDiv && statusDiv.length) {
                        statusDiv.html('<div class="status-message status-success">' + completeMessage + '</div>');
                    }
                    
                    // Hide progress after a longer delay so user can read the results
                    if (progressWrapper && progressWrapper.length) {
                        setTimeout(function() {
                            progressWrapper.fadeOut();
                        }, 5000);
                    }
                    break;
                    
                case 'error':
                    if (button && button.length) {
                        button.prop('disabled', false).text(button.data('original-text') || 'Fix ' + type);
                    }
                    if (progressFill && progressFill.length) {
                        progressFill.css('width', '0%');
                    }
                    if (progressText && progressText.length) {
                        progressText.text(self.strings.error || 'Error');
                    }
                    
                    var errorMessage = '❌ <strong>Error occurred</strong>';
                    if (data.message) {
                        errorMessage += '<br>' + data.message;
                    }
                    
                    if (statusDiv && statusDiv.length) {
                        statusDiv.html('<div class="status-message status-error">' + errorMessage + '</div>');
                    }
                    if (progressWrapper && progressWrapper.length) {
                        progressWrapper.fadeOut();
                    }
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
        },

    });

    // Preserve reference so later assignments merge instead of replace
    var fixerInstance = window.wpmlFixerAjax;
    try {
        Object.defineProperty(window, 'wpmlFixerAjax', {
            configurable: true,
            get: function() {
                return fixerInstance;
            },
            set: function(value) {
                if (value && typeof value === 'object') {
                    $.extend(fixerInstance, value);
                }
            }
        });
    } catch (e) {
        // Fallback for environments that do not support defineProperty on window
        window.wpmlFixerAjax = fixerInstance;
    }

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
