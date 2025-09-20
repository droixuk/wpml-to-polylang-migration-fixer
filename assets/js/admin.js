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
        page: '',
        processingStates: {}, // Track processing state for each type
        progressControllers: {}, // Cache DOM references for reusable progress UI
        sqlModalInitialized: false
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
        
        var defaultStrings = {
            confirmReset: 'Are you sure you want to reset the session?',
            confirmFix: 'This will process all items. Continue?',
            processing: 'Processing...',
            complete: 'Complete!',
            error: 'An error occurred. Please check the logs.',
            verifying: 'Running comprehensive verification...',
            verifyingHint: 'This may take a few moments. Please keep this tab open.',
            verifyingButton: 'Processing verification...',
            verificationComplete: 'Verification complete!',
            noLanguages: 'No Polylang languages detected.'
        };
        window.wpmlFixerAjax.strings = $.extend({}, defaultStrings, window.wpmlFixerAjax.strings || {});
        
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

            // Restore migration guide state
            if (typeof(Storage) !== "undefined") {
                var guideVisible = localStorage.getItem('wpml_fixer_guide_visible');
                // Default to visible if not set
                if (guideVisible === null || guideVisible === 'true') {
                    $('#guide-content').show();
                    $('.guide-toggle').attr('aria-expanded', 'true');
                    $('.guide-toggle .dashicons').removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
                }
            }

            // Bind debug toggle
            $('#debug-toggle').on('change', function() {
                self.debugEnabled = $(this).is(':checked');
                self.toggleDebugConsole();
                self.debugLog('Debug mode ' + (self.debugEnabled ? 'enabled' : 'disabled'));
            });

            var $sqlModal = $('#wmf-sql-modal');
            if ($sqlModal.length) {
                self.bindSqlPreflight($sqlModal);
            }

            if ($('#wmf-status-root').length) {
                self.renderStatus();
            }

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
                totalDisplay: elements.total ? $(elements.total) : $wrapper.find('[data-progress-role="total-count"]').first(),
                issuesTotal: elements.issuesTotal ? $(elements.issuesTotal) : $wrapper.find('[data-progress-role="issues-total"]').first(),
                issuesFixed: elements.issuesFixed ? $(elements.issuesFixed) : $wrapper.find('[data-progress-role="issues-fixed"]').first(),
                issuesRemaining: elements.issuesRemaining ? $(elements.issuesRemaining) : $wrapper.find('[data-progress-role="issues-remaining"]').first(),
                status: $status,
                counts: {
                    processed: 0,
                    total: 0,
                    fixed: 0,
                    issues_total: 0,
                    issues_remaining: 0
                }
            };

            if ((!controller.fixed || !controller.fixed.length) && controller.issuesFixed && controller.issuesFixed.length) {
                controller.fixed = controller.issuesFixed;
            }

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
         * Basic HTML escaping helper
         * @param {string} value
         * @returns {string}
         */
        escapeHtml: function(value) {
            return $('<div/>').text(value == null ? '' : value).html();
        },

        /**
         * Format a label/value line for status cards
         * @param {string} label
         * @param {string} value
         * @returns {string}
         */
        formatLine: function(label, value) {
            return '<p><strong>' + this.escapeHtml(label) + ':</strong> ' + this.escapeHtml(value) + '</p>';
        },

        /**
         * Format plain text paragraph for status cards
         * @param {string} text
         * @returns {string}
         */
        formatText: function(text) {
            return '<p>' + this.escapeHtml(text) + '</p>';
        },

        /**
         * Fetch and render Status tab information
         */
        renderStatus: function() {
            var self = this;
            var $root = $('#wmf-status-root');
            if (!$root.length) {
                return;
            }

            self.renderStatusLoading($root);

            var requestData = self.createRequestData('wmf_get_status');
            var nonceAttr = $root.data('nonce');
            if (nonceAttr) {
                requestData[self.nonceName] = nonceAttr;
            }

            $.post(self.ajaxUrl, requestData)
                .done(function(response) {
                    if (response && response.success && response.data) {
                        self.renderStatusCards($root, response.data);
                    } else {
                        var message = response && response.data ? response.data : 'Unable to load status.';
                        self.renderStatusError($root, message);
                    }
                })
                .fail(function(xhr, status, error) {
                    var message = error || status || 'request failed';
                    self.renderStatusError($root, 'Status request failed: ' + message);
                });
        },

        /**
         * Render loading UI for status request
         * @param {jQuery} $root
         */
        renderStatusLoading: function($root) {
            var verifying = this.strings.verifying || 'Loading...';
            var hint = this.strings.verifyingHint || '';
            var hintHtml = hint ? '<p class="verification-loading__hint">⚠️ ' + this.escapeHtml(hint) + '</p>' : '';
            $root.html(
                '<div class="verification-loading">' +
                    '<div class="spinner"></div>' +
                    '<p>' + this.escapeHtml(verifying) + '</p>' +
                    hintHtml +
                '</div>'
            );
        },

        /**
         * Render error UI for status request
         * @param {jQuery} $root
         * @param {string} message
         */
        renderStatusError: function($root, message) {
            var errorLabel = this.strings.error || 'Error';
            $root.html(
                '<div class="status-error-message">' +
                    '<strong>⚠️ ' + this.escapeHtml(errorLabel) + '</strong>' +
                    '<p>' + this.escapeHtml(message || 'Unable to load status.') + '</p>' +
                '</div>'
            );
        },

        /**
         * Render cards for status data
         * @param {jQuery} $root
         * @param {Object} data
         */
        renderStatusCards: function($root, data) {
            var self = this;
            var states = {
                ok: { cardClass: 'status-card--ok', badgeClass: 'badge-success' },
                warning: { cardClass: 'status-card--warning', badgeClass: 'badge-warning' },
                error: { cardClass: 'status-card--error', badgeClass: 'badge-error' },
                info: { cardClass: 'status-card--info', badgeClass: 'badge-info' }
            };

            function buildCard(title, state, badgeText, bodyHtml) {
                var style = states[state] || states.info;
                return '<div class="status-card ' + style.cardClass + '">' +
                    '<div class="status-card__header">' +
                        '<span class="status-card__title">' + self.escapeHtml(title) + '</span>' +
                        '<span class="badge ' + style.badgeClass + '">' + self.escapeHtml(badgeText) + '</span>' +
                    '</div>' +
                    '<div class="status-card__body">' + bodyHtml + '</div>' +
                '</div>';
            }

            function pluralize(count, singular, plural) {
                return count + ' ' + (count === 1 ? singular : plural);
            }

            var languages = data.languages || {};
            var languageList = Array.isArray(languages.list) ? languages.list : [];
            var defaultLanguage = languages.default || '';
            var hasLanguages = languageList.length > 0;

            var cards = [];

            // Polylang status
            var pllState = data.pll_active ? 'ok' : 'error';
            var pllBody = data.pll_active
                ? self.formatText('Polylang detected and ready.')
                : self.formatText('Polylang is not active. Install and activate Polylang to use the fixer.');
            cards.push(buildCard('Polylang', pllState, data.pll_active ? 'Active' : 'Missing', pllBody));

            // WPML tables
            var wpmlState = data.wpml_tables ? 'warning' : 'ok';
            var wpmlBadge = data.wpml_tables ? 'Detected' : 'Clean';
            var wpmlBody = data.wpml_tables
                ? self.formatText('Legacy WPML tables found. Keep them for reference or remove after migration.')
                : self.formatText('No WPML tables detected.');
            cards.push(buildCard('WPML Data', wpmlState, wpmlBadge, wpmlBody));

            // WooCommerce
            var wooActive = data.woo && data.woo.active;
            var wooVersion = data.woo && data.woo.version ? data.woo.version : 'N/A';
            var wooBody = self.formatLine('Status', wooActive ? 'Active' : 'Not detected') +
                self.formatLine('Version', wooVersion) +
                self.formatText(wooActive ? 'WooCommerce integration detected.' : 'WooCommerce is optional.');
            cards.push(buildCard('WooCommerce', wooActive ? 'ok' : 'info', wooActive ? 'Active' : 'Optional', wooBody));

            // BetterDocs
            var betterdocsActive = data.betterdocs && data.betterdocs.active;
            var betterdocsVersion = data.betterdocs && data.betterdocs.version ? data.betterdocs.version : 'N/A';
            var betterdocsBody = self.formatLine('Status', betterdocsActive ? 'Active' : 'Not detected') +
                self.formatLine('Version', betterdocsVersion) +
                self.formatText(betterdocsActive ? 'BetterDocs content will be included in fixes.' : 'BetterDocs not detected.');
            cards.push(buildCard('BetterDocs', betterdocsActive ? 'ok' : 'info', betterdocsActive ? 'Active' : 'Optional', betterdocsBody));

            // ACF
            var acfActive = data.acf && data.acf.active;
            var acfVersion = data.acf && data.acf.version ? data.acf.version : 'N/A';
            var acfBody = self.formatLine('Status', acfActive ? 'Active' : 'Not detected') +
                self.formatLine('Version', acfVersion) +
                self.formatText(acfActive ? 'Advanced Custom Fields detected.' : 'ACF not detected.');
            cards.push(buildCard('ACF', acfActive ? 'ok' : 'info', acfActive ? 'Active' : 'Optional', acfBody));

            // Languages
            var languagesState = hasLanguages ? 'ok' : (data.pll_active ? 'warning' : 'info');
            var languagesBadge = hasLanguages ? pluralize(languageList.length, 'Language', 'Languages') : 'None';
            var languagesLines = self.formatLine('Default', defaultLanguage || 'N/A') +
                self.formatText(hasLanguages ? languageList.join(', ') : (self.strings.noLanguages || 'No Polylang languages detected.'));
            cards.push(buildCard('Languages', languagesState, languagesBadge, languagesLines));

            var statusHtml = '<div class="status-grid">' + cards.join('') + '</div>';
            $root.html(statusHtml);
        },

        /**
         * Bind SQL preflight modal events
         */
        bindSqlPreflight: function($modal) {
            if (this.sqlModalInitialized) {
                return;
            }

            var self = this;
            var $openButton = $('#wmf-open-sql');
            if (!$openButton.length) {
                return;
            }

            this.sqlModalInitialized = true;

            $openButton.on('click', function(e) {
                e.preventDefault();
                self.openSqlModal();
            });

            $modal.find('[data-action="close-sql"]').on('click', function(e) {
                e.preventDefault();
                self.closeSqlModal();
            });

            $(document).on('keydown.wmfSql', function(evt) {
                if (evt.key === 'Escape' && $modal.hasClass('is-open')) {
                    self.closeSqlModal();
                }
            });

            $modal.find('[data-action="preview-sql"]').on('click', function(e) {
                e.preventDefault();
                self.previewSql();
            });

            $modal.find('[data-action="execute-sql"]').on('click', function(e) {
                e.preventDefault();
                if (!confirm('Run the SQL statements now? This cannot be undone.')) {
                    return;
                }
                self.executeSql();
            });

            $modal.find('[data-action="copy-sql"]').on('click', function(e) {
                e.preventDefault();
                self.copySql();
            });
        },

        openSqlModal: function() {
            $('#wmf-sql-modal').addClass('is-open').attr('aria-hidden', 'false');
            $('body').addClass('wmf-modal-open');
            $('#wmf-sql-input').focus();
        },

        closeSqlModal: function() {
            $('#wmf-sql-modal').removeClass('is-open').attr('aria-hidden', 'true');
            $('body').removeClass('wmf-modal-open');
            $('#wmf-sql-output').empty();
        },

        previewSql: function() {
            var self = this;
            var $modal = $('#wmf-sql-modal');
            var sql = $('#wmf-sql-input').val();

            if (!sql || !sql.trim()) {
                self.setSqlOutput('<p class="status-error-message">' + self.escapeHtml('Enter SQL to preview.') + '</p>');
                return;
            }

            self.setSqlOutput(self.renderStatusLoadingHtml());

            var requestData = self.createRequestData('wmf_sql_preview', { sql: sql });
            var rootNonce = $('#wmf-status-root').data('nonce');
            if (rootNonce) {
                requestData[self.nonceName] = rootNonce;
            }

            $.post(self.ajaxUrl, requestData)
                .done(function(response) {
                    if (response && response.success) {
                        self.renderSqlPreviewResult(response.data || {});
                    } else {
                        var message = response && response.data ? response.data : 'Preview failed.';
                        self.setSqlOutput('<div class="status-error-message"><strong>⚠️</strong> ' + self.escapeHtml(message) + '</div>');
                    }
                })
                .fail(function(xhr, status, error) {
                    var message = error || status || 'Request failed.';
                    self.setSqlOutput('<div class="status-error-message"><strong>⚠️</strong> ' + self.escapeHtml(message) + '</div>');
                });
        },

        executeSql: function() {
            var self = this;
            var sql = $('#wmf-sql-input').val();

            if (!sql || !sql.trim()) {
                self.setSqlOutput('<p class="status-error-message">' + self.escapeHtml('Enter SQL to execute.') + '</p>');
                return;
            }

            self.setSqlOutput(self.renderStatusLoadingHtml());

            var requestData = self.createRequestData('wmf_sql_execute', {
                sql: sql,
                confirm: 1
            });
            var rootNonce = $('#wmf-status-root').data('nonce');
            if (rootNonce) {
                requestData[self.nonceName] = rootNonce;
            }

            $.post(self.ajaxUrl, requestData)
                .done(function(response) {
                    if (response && response.success) {
                        self.renderSqlExecuteResult(response.data || {});
                        self.showTemporaryMessage('SQL executed successfully.', 'success');
                    } else {
                        var message = response && response.data ? response.data : 'Execution failed.';
                        self.setSqlOutput('<div class="status-error-message"><strong>⚠️</strong> ' + self.escapeHtml(message) + '</div>');
                    }
                })
                .fail(function(xhr, status, error) {
                    var message = error || status || 'Request failed.';
                    self.setSqlOutput('<div class="status-error-message"><strong>⚠️</strong> ' + self.escapeHtml(message) + '</div>');
                });
        },

        copySql: function() {
            var sql = $('#wmf-sql-input').val();
            if (!sql) {
                return;
            }

            var self = this;

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(sql).then(function() {
                    self.showTemporaryMessage('SQL copied to clipboard.', 'success');
                }).catch(function() {
                    self.fallbackCopySql(sql);
                });
            } else {
                self.fallbackCopySql(sql);
            }
        },

        fallbackCopySql: function(sql) {
            var temp = $('<textarea readonly></textarea>').css({ position: 'absolute', left: '-9999px' }).val(sql);
            $('body').append(temp);
            temp[0].select();
            document.execCommand('copy');
            temp.remove();
            this.showTemporaryMessage('SQL copied to clipboard.', 'success');
        },

        renderStatusLoadingHtml: function() {
            return '<div class="verification-loading" style="margin:0;"><div class="spinner"></div><p>' + this.escapeHtml(this.strings.processing || 'Processing...') + '</p></div>';
        },

        setSqlOutput: function(html) {
            $('#wmf-sql-output').html(html || '');
        },

        renderSqlPreviewResult: function(data) {
            var self = this;
            if (!data || !Array.isArray(data.results) || !data.results.length) {
                self.setSqlOutput('<p>' + self.escapeHtml('No preview data available.') + '</p>');
                return;
            }

            var html = '';
            data.results.forEach(function(entry, index) {
                html += '<div class="wmf-sql-result">';
                html += '<h3>Statement ' + (index + 1) + '</h3>';
                html += '<pre class="wmf-sql-snippet">' + self.escapeHtml(entry.statement || '') + '</pre>';

                if (entry.type === 'select' && Array.isArray(entry.rows)) {
                    html += self.renderSqlResultTable(entry.rows);
                    html += '<p class="wmf-sql-meta">' + self.escapeHtml('Rows returned: ' + (entry.row_count || 0)) + '</p>';
                    if (entry.applied_statement) {
                        html += '<p class="wmf-sql-meta">' + self.escapeHtml('Preview limited statement:') + '</p>';
                        html += '<pre class="wmf-sql-snippet">' + self.escapeHtml(entry.applied_statement) + '</pre>';
                    }
                } else {
                    html += '<p class="wmf-sql-meta">' + self.escapeHtml(entry.message || 'Statement skipped during preview.') + '</p>';
                }

                html += '</div>';
            });

            self.setSqlOutput(html);
        },

        renderSqlExecuteResult: function(data) {
            var self = this;
            if (!data || !Array.isArray(data.results) || !data.results.length) {
                self.setSqlOutput('<p>' + self.escapeHtml('Execution completed, but no details were returned.') + '</p>');
                return;
            }

            var html = '';
            data.results.forEach(function(entry, index) {
                html += '<div class="wmf-sql-result">';
                html += '<h3>Statement ' + (index + 1) + '</h3>';
                html += '<pre class="wmf-sql-snippet">' + self.escapeHtml(entry.statement || '') + '</pre>';

                if (entry.type === 'select' && Array.isArray(entry.rows)) {
                    html += self.renderSqlResultTable(entry.rows);
                    html += '<p class="wmf-sql-meta">' + self.escapeHtml('Rows returned: ' + (entry.row_count || 0)) + '</p>';
                } else {
                    var affected = typeof entry.affected_rows === 'number' ? entry.affected_rows : 0;
                    html += '<p class="wmf-sql-meta">' + self.escapeHtml('Rows affected: ' + affected) + '</p>';
                }

                html += '</div>';
            });

            if (typeof data.total_affected === 'number') {
                html += '<p class="wmf-sql-meta"><strong>' + self.escapeHtml('Total affected rows: ' + data.total_affected) + '</strong></p>';
            }

            self.setSqlOutput(html);
        },

        renderSqlResultTable: function(rows) {
            if (!rows || !rows.length) {
                return '<p class="wmf-sql-meta">' + this.escapeHtml('No rows returned.') + '</p>';
            }

            var self = this;
            var headers = Object.keys(rows[0]);
            var html = '<table class="wmf-sql-table"><thead><tr>';
            headers.forEach(function(header) {
                html += '<th>' + self.escapeHtml(header) + '</th>';
            });
            html += '</tr></thead><tbody>';

            rows.forEach(function(row) {
                html += '<tr>';
                headers.forEach(function(header) {
                    var value = row[header];
                    if (value === null || typeof value === 'undefined') {
                        value = 'NULL';
                    }
                    html += '<td>' + self.escapeHtml(String(value)) + '</td>';
                });
                html += '</tr>';
            });

            html += '</tbody></table>';
            return html;
        },
        
        /**
         * Update processed and fixed counts displayed beneath the progress bar
         * @param {Object} controller
         * @param {number} processed
         * @param {number} total
         * @param {number} fixed
         */
        setProgressCounts: function(controller, processed, total, fixed, issues) {
            if (!controller) {
                return;
            }

            processed = typeof processed === 'number' ? processed : parseInt(processed || 0, 10) || 0;
            total = typeof total === 'number' ? total : parseInt(total || 0, 10) || 0;
            fixed = typeof fixed === 'number' ? fixed : parseInt(fixed || 0, 10) || 0;

            var issuesTotal = controller.counts.issues_total || 0;
            var issuesRemaining = controller.counts.issues_remaining || 0;
            if (issues && typeof issues === 'object') {
                if (typeof issues.total === 'number') {
                    issuesTotal = issues.total;
                }
                if (typeof issues.remaining === 'number') {
                    issuesRemaining = issues.remaining;
                }
            }

            issuesTotal = typeof issuesTotal === 'number' ? issuesTotal : parseInt(issuesTotal || 0, 10) || 0;
            issuesRemaining = typeof issuesRemaining === 'number' ? issuesRemaining : parseInt(issuesRemaining || 0, 10) || 0;
            var issuesFixed = Math.max(issuesTotal - issuesRemaining, 0);

            controller.counts = {
                processed: processed,
                total: total,
                fixed: fixed,
                issues_total: issuesTotal,
                issues_remaining: issuesRemaining
            };

            if (controller.totalDisplay && controller.totalDisplay.length) {
                controller.totalDisplay.text(total);
            }

            if (controller.processed && controller.processed.length) {
                controller.processed.text(processed);
            }

            if (controller.fixed && controller.fixed.length && (!controller.issuesFixed || controller.fixed[0] !== controller.issuesFixed[0])) {
                controller.fixed.text(fixed);
            }

            if (controller.issuesTotal && controller.issuesTotal.length) {
                controller.issuesTotal.text(issuesTotal);
            }

            if (controller.issuesFixed && controller.issuesFixed.length) {
                controller.issuesFixed.text(issuesFixed);
            }

            if (controller.issuesRemaining && controller.issuesRemaining.length) {
                controller.issuesRemaining.text(issuesRemaining);
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
         * Toggle migration guide visibility
         */
        toggleGuide: function() {
            var $guide = $('#migration-guide');
            var $content = $('#guide-content');
            var $toggle = $guide.find('.guide-toggle');
            var $icon = $toggle.find('.dashicons');
            var isVisible = $content.is(':visible');

            if (isVisible) {
                // Hide the guide
                $content.slideUp(300);
                $toggle.attr('aria-expanded', 'false');
                $icon.removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
                // Save state
                if (typeof(Storage) !== "undefined") {
                    localStorage.setItem('wpml_fixer_guide_visible', 'false');
                }
            } else {
                // Show the guide
                $content.slideDown(300);
                $toggle.attr('aria-expanded', 'true');
                $icon.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
                // Save state
                if (typeof(Storage) !== "undefined") {
                    localStorage.setItem('wpml_fixer_guide_visible', 'true');
                }
            }
        },

        /**
         * Ensure term_language buckets exist
         */
        ensureBuckets: function() {
            var self = this;
            $("#btn-ensure-buckets").prop("disabled", true);
            self.debugLog('Ensuring term_language buckets...');

            var requestData = self.createRequestData("wmf_ensure_buckets");

            $.post(self.ajaxUrl, requestData)
                .done(function(response) {
                    if (response && response.success) {
                        self.showTemporaryMessage(response.data.message, 'success');
                        self.debugLog('✅ ' + response.data.message);
                    } else {
                        self.showTemporaryMessage('Failed to ensure buckets', 'error');
                        self.debugLog('❌ Failed to ensure buckets', 'error');
                    }
                })
                .fail(function(xhr, status, error) {
                    self.showTemporaryMessage('Request failed: ' + error, 'error');
                    self.debugLog('❌ Request failed: ' + error, 'error');
                })
                .always(function() {
                    $("#btn-ensure-buckets").prop("disabled", false);
                });
        },

        /**
         * Normalize all language codes
         */
        normalizeLanguages: function() {
            var self = this;
            self.startBatchProcess('normalize', 'wmf_normalize_languages');
        },

        /**
         * Fix all posts (comprehensive)
         */
        fixAllPosts: function() {
            var self = this;
            self.startBatchProcess('all-posts', 'wmf_fix_all_posts');
        },

        /**
         * Fix all terms (comprehensive)
         */
        fixAllTerms: function() {
            var self = this;
            self.startBatchProcess('all-terms', 'wmf_fix_all_terms');
        },

        /**
         * Fix BetterDocs (comprehensive)
         */
        fixBetterDocs: function() {
            var self = this;
            self.startBatchProcess('fix-betterdocs', 'wmf_fix_betterdocs');
        },

        /**
         * Fix WooCommerce attributes
         */
        fixWooAttributes: function() {
            var self = this;
            self.startBatchProcess('woo-attributes', 'wmf_fix_woo_attributes');
        },

        /**
         * Generic batch process starter
         */
        startBatchProcess: function(processId, action, additionalData) {
            var self = this;

            if (self.processingStates[processId] && (self.processingStates[processId].running || self.processingStates[processId].preview)) {
                self.debugLog('Process ' + processId + ' already running', 'warning');
                return;
            }

            var controller = self.getProgressController(processId);
            if (!controller) {
                controller = self.registerProgressType(processId);
            }
            if (!controller && self.debugEnabled) {
                self.debugLog('Unable to initialise progress UI for "' + processId + '"', 'error');
            }
            if (controller) {
                if (controller.wrapper && controller.wrapper.length) {
                    controller.wrapper.stop(true, true).show();
                }
                if (controller.fill && controller.fill.length) {
                    controller.fill.css('width', '0%');
                }
                if (controller.text && controller.text.length) {
                    controller.text.text(self.formatProgressLabel(0, 0, 0, 0));
                }
                self.setProgressCounts(controller, 0, 0, 0, { total: 0, remaining: 0 });
                if (controller.status && controller.status.length) {
                    controller.status.html('<div class="status-message status-info">' + self.escapeHtml(self.strings.analysing || 'Analysing pending items...') + '</div>');
                }
            }

            self.processingStates[processId] = {
                running: false,
                preview: true,
                fixed: 0,
                total: 0,
                issuesTotal: null,
                issuesRemaining: null
            };

            self.runPreflight(processId, action, additionalData || {})
                .done(function(preview) {
                    preview = preview || {};
                    var previewTotal = preview.total !== undefined ? preview.total : 0;
                    var issuesTotal = preview.issues_total !== undefined ? preview.issues_total : null;
                    var issuesRemaining = preview.issues_remaining !== undefined ? preview.issues_remaining : issuesTotal;

                    if (controller) {
                        self.setProgressCounts(controller, 0, previewTotal || (issuesTotal || 0), 0, {
                            total: issuesTotal || 0,
                            remaining: issuesRemaining !== null ? issuesRemaining : (issuesTotal || 0)
                        });
                    }

                    if (!issuesTotal || issuesTotal <= 0) {
                        delete self.processingStates[processId];
                        if (controller && controller.status && controller.status.length) {
                            controller.status.html('<div class="status-message status-success">' + self.escapeHtml(self.strings.noIssues || 'No issues found; nothing to fix.') + '</div>');
                        }
                        if (controller && controller.button && controller.button.length) {
                            controller.button.prop('disabled', false).text(controller.button.data('original-text') || $.trim(controller.button.text()));
                        }
                        self.showTemporaryMessage(self.strings.noIssues || 'No issues found; nothing to fix.', 'info');
                        return;
                    }

                    self.processingStates[processId] = {
                        running: true,
                        preview: false,
                        fixed: 0,
                        total: previewTotal || issuesTotal,
                        issuesTotal: issuesTotal,
                        issuesRemaining: issuesRemaining !== null ? issuesRemaining : issuesTotal
                    };

                    self.updateProcessUI(processId, 'starting', {
                        total: previewTotal || issuesTotal,
                        issues_total: issuesTotal,
                        issues_remaining: issuesRemaining !== null ? issuesRemaining : issuesTotal
                    });

                    self.debugLog('Starting batch process: ' + processId);

                    self.processBatch(processId, action, 0, additionalData || {});
                })
                .fail(function(errorMsg) {
                    delete self.processingStates[processId];
                    if (controller && controller.status && controller.status.length) {
                        controller.status.html('<div class="status-message status-error">❌ ' + self.escapeHtml(errorMsg) + '</div>');
                    }
                    if (controller && controller.button && controller.button.length) {
                        controller.button.prop('disabled', false).text(controller.button.data('original-text') || $.trim(controller.button.text()));
                    }
                    self.debugLog('❌ Preflight ' + processId + ' failed: ' + errorMsg, 'error');
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
            var loadingHint = self.strings.verifyingHint || 'This may take a few moments. Please keep this tab open.';
            var loadingButtonText = self.strings.verifyingButton || self.strings.verifying;

            $("#verify-results")
                .stop(true, true)
                .html(
                    '<div class="verification-loading">' +
                        '<div class="spinner"></div>' +
                        '<p>' + self.strings.verifying + '</p>' +
                        '<p class="verification-loading__hint">' +
                            '⚠️ ' + loadingHint +
                        '</p>' +
                    '</div>'
                );
            $('#btn-comprehensive-verify')
                .prop('disabled', true)
                .addClass('is-loading')
                .text(loadingButtonText);
            
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
                    if (response.data && typeof response.data === 'string') {
                        $("#verify-results").html(response.data);
                    } else {
                        $("#verify-results").html(
                            self.displayError('Comprehensive verification finished, but no view was returned.')
                        );
                    }
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
                $('#btn-comprehensive-verify')
                    .prop("disabled", false)
                    .removeClass('is-loading')
                    .text($('#btn-comprehensive-verify').data('original-text') || 'Comprehensive Verification');
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
                fixed: 0,
                issuesTotal: null,
                issuesRemaining: null
            };
            
            // Update UI
            self.updateProcessUI(type, 'starting');
            
            // Start the batch processing
            self.processBatch(type, 0, 20); // Start with offset 0, batch size 20
        },
        
        /**
         * Process a batch (supports both comprehensive and legacy handlers)
         */
        processBatch: function(processId, arg2, arg3, arg4) {
            var self = this;

            if (!self.processingStates[processId] || !self.processingStates[processId].running) {
                self.debugLog('Process ' + processId + ' stopped or not initialized', 'warning');
                return;
            }

            var state = self.processingStates[processId];

            var isComprehensive = typeof arg2 === 'string' && arg2.indexOf('wmf_') === 0;
            var action = isComprehensive ? arg2 : 'wpml_fixer_ajax_process';
            var offset = isComprehensive ? (typeof arg3 === 'number' ? arg3 : 0) : (typeof arg2 === 'number' ? arg2 : 0);
            var batchSize = isComprehensive ? 50 : (typeof arg3 === 'number' ? arg3 : 20);
            var additionalData = isComprehensive ? (arg4 && typeof arg4 === 'object' ? arg4 : {}) : {};

            self.debugLog('Processing batch for ' + processId + ' - offset: ' + offset + ', batch: ' + batchSize + (isComprehensive ? ', action: ' + action : ''));

            var requestPayload = {
                offset: offset,
                batch_size: batchSize
            };

            if (isComprehensive) {
                if (additionalData && typeof additionalData === 'object') {
                    $.extend(requestPayload, additionalData);
                }

                if (typeof state.issuesTotal === 'number') {
                    requestPayload.issues_total = state.issuesTotal;
                }
                if (typeof state.issuesRemaining === 'number') {
                    requestPayload.issues_remaining = state.issuesRemaining;
                }
            } else {
                requestPayload.type = processId;
            }

            var requestData = self.createRequestData(action, requestPayload);

            $.post(self.ajaxUrl, requestData)
                .done(function(response) {
                    if (response && response.success) {
                        var result = response.data || {};

                        state.total = result.total !== undefined ? result.total : state.total;
                        if (result.fixed !== undefined) {
                            state.fixed += result.fixed;
                        }

                        if (result.issues_total !== undefined) {
                            state.issuesTotal = result.issues_total;
                        }
                        if (result.issues_remaining !== undefined) {
                            state.issuesRemaining = result.issues_remaining;
                        }

                        self.updateProcessUI(processId, 'processing', {
                            processed: result.processed,
                            total: result.total,
                            fixed: state.fixed,
                            message: result.message,
                            issues_total: state.issuesTotal,
                            issues_remaining: state.issuesRemaining
                        });

                        self.debugLog('Batch completed for ' + processId + ': processed=' + result.processed + ', fixed=' + (result.fixed || 0) + ', continue=' + result.continue);

                        if (result.continue && result.next_offset !== undefined) {
                            if (isComprehensive) {
                                setTimeout(function() {
                                    self.processBatch(processId, action, result.next_offset, additionalData);
                                }, 100);
                            } else {
                                setTimeout(function() {
                                    self.processBatch(processId, result.next_offset, batchSize);
                                }, 100);
                            }
                        } else {
                            state.running = false;
                            self.updateProcessUI(processId, 'complete', {
                                processed: result.processed,
                                total: result.total,
                                fixed: state.fixed,
                                message: result.message || 'Complete!',
                                issues_total: state.issuesTotal,
                                issues_remaining: state.issuesRemaining
                            });

                            self.debugLog('Process ' + processId + ' completed successfully');
                            self.showTemporaryMessage('Process ' + processId + ' completed!', 'success');
                        }
                    } else {
                        var errorMsg = response && response.data ? response.data : 'Unknown error';
                            state.running = false;
                            self.updateProcessUI(processId, 'error', { message: errorMsg });
                        self.debugLog('❌ Process ' + processId + ' failed: ' + errorMsg, 'error');
                    }
                })
                .fail(function(xhr, status, error) {
                    state.running = false;
                    self.updateProcessUI(processId, 'error', { message: error });
                    self.debugLog('❌ Process ' + processId + ' failed: ' + error, 'error');
                });
        },

        /**
         * Run a preview request before executing an action
         */
        runPreflight: function(processId, action, additionalData) {
            var self = this;
            var deferred = $.Deferred();

            var payload = $.extend({
                offset: 0,
                batch_size: 1,
                preview: 1
            }, additionalData || {});

            var requestData = self.createRequestData(action, payload);

            $.post(self.ajaxUrl, requestData)
                .done(function(response) {
                    if (response && response.success) {
                        deferred.resolve(response.data || {});
                    } else {
                        deferred.reject(response && response.data ? response.data : (self.strings.previewFailed || 'Unable to analyse issues.'));
                    }
                })
                .fail(function(xhr, status, error) {
                    deferred.reject(error || status || (self.strings.previewFailed || 'Unable to analyse issues.'));
                });

            return deferred.promise();
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
                    self.setProgressCounts(controller, 0, data.total || 0, 0, {
                        total: data.issues_total || 0,
                        remaining: data.issues_remaining !== undefined ? data.issues_remaining : (data.issues_total || 0)
                    });
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
                    var fixedTotal;
                    if (typeof data.fixed_total === 'number') {
                        fixedTotal = data.fixed_total;
                    } else if (typeof data.fixed === 'number') {
                        fixedTotal = data.fixed;
                    } else if (controller.counts && typeof controller.counts.fixed === 'number') {
                        fixedTotal = controller.counts.fixed;
                    } else {
                        fixedTotal = 0;
                    }
                    var percentage = total > 0 ? Math.round((processed / total) * 100) : 0;
                    if (progressFill && progressFill.length) {
                        progressFill.css('width', percentage + '%');
                    }

                    // Clear progress text showing current numbers
                    if (progressText && progressText.length) {
                        progressText.text(self.formatProgressLabel(percentage, processed, total, fixedTotal));
                    }
                    self.setProgressCounts(controller, processed, total, fixedTotal, {
                        total: data.issues_total !== undefined ? data.issues_total : controller.counts.issues_total,
                        remaining: data.issues_remaining !== undefined ? data.issues_remaining : controller.counts.issues_remaining
                    });

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
                    var countsSnapshot = controller.counts || {};
                    var completeFixed;
                    if (typeof data.fixed === 'number') {
                        completeFixed = data.fixed;
                    } else if (typeof data.fixed_total === 'number') {
                        completeFixed = data.fixed_total;
                    } else {
                        completeFixed = typeof countsSnapshot.fixed === 'number' ? countsSnapshot.fixed : 0;
                    }
                    self.setProgressCounts(controller, completeProcessed, completeTotal || completeProcessed, completeFixed, {
                        total: data.issues_total !== undefined ? data.issues_total : controller.counts.issues_total,
                        remaining: data.issues_remaining !== undefined ? data.issues_remaining : controller.counts.issues_remaining
                    });
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
                        }, 12000);
                    }
                    break;
                    
                case 'error':
                    if (button && button.length) {
                        button.prop('disabled', false).text(button.data('original-text') || 'Fix ' + type);
                    }
                    if (progressWrapper && progressWrapper.length) {
                        progressWrapper.stop(true, true).show();
                    }
                    var errorCounts = controller.counts || {};
                    var errorPercent = errorCounts.total > 0
                        ? Math.max(0, Math.min(100, Math.round((errorCounts.processed / errorCounts.total) * 100)))
                        : 0;
                    if (progressFill && progressFill.length) {
                        progressFill.css('width', errorPercent + '%');
                    }
                    if (progressText && progressText.length) {
                        progressText.text(self.formatProgressLabel(
                            errorPercent,
                            errorCounts.processed || 0,
                            errorCounts.total || 0,
                            errorCounts.fixed || 0
                        ));
                    }
                    
                    var errorMessage = '❌ <strong>Error occurred</strong>';
                    if (data.message) {
                        errorMessage += '<br>' + data.message;
                    }
                    
                    if (statusDiv && statusDiv.length) {
                        statusDiv.html('<div class="status-message status-error">' + errorMessage + '</div>');
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
                messageDiv.fadeOut(600, function() {
                    messageDiv.remove();
                });
            }, 12000);
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
