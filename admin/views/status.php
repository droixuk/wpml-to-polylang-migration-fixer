<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap wpml-fixer-status-page">
    <h1 style="margin-bottom: 10px;">
        <?php esc_html_e('WPML Fixer Status', 'wpml-migration-fixer'); ?>
    </h1>
    <p style="max-width: 720px; color: #555;">
        <?php esc_html_e('Quick overview of your multilingual environment, plugin integrations, and language configuration.', 'wpml-migration-fixer'); ?>
    </p>

    <div class="wmf-status-actions">
        <button type="button" class="wpml-btn wpml-btn-secondary" id="wmf-open-sql">
            <?php esc_html_e('Preflight SQL', 'wpml-migration-fixer'); ?>
        </button>
    </div>

    <div id="wmf-status-root" class="wmf-status-root" data-nonce="<?php echo esc_attr($status_nonce); ?>">
        <div class="verification-loading">
            <div class="spinner"></div>
            <p><?php esc_html_e('Gathering status details...', 'wpml-migration-fixer'); ?></p>
            <p class="verification-loading__hint">⚠️ <?php esc_html_e('This may take a few moments. Please keep this tab open.', 'wpml-migration-fixer'); ?></p>
        </div>
    </div>
</div>

<div class="wmf-sql-modal" id="wmf-sql-modal" aria-hidden="true">
    <div class="wmf-sql-modal__backdrop" data-action="close-sql"></div>
    <div class="wmf-sql-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="wmf-sql-modal-title">
        <div class="wmf-sql-modal__header">
            <h2 id="wmf-sql-modal-title"><?php esc_html_e('Preflight SQL', 'wpml-migration-fixer'); ?></h2>
            <button type="button" class="wmf-sql-modal__close" data-action="close-sql" aria-label="<?php esc_attr_e('Close', 'wpml-migration-fixer'); ?>">&times;</button>
        </div>
        <p class="wmf-sql-modal__intro">
            <?php esc_html_e('Run read-only previews or execute prepared statements. Use {{prefix}} as a placeholder for the current table prefix.', 'wpml-migration-fixer'); ?>
        </p>
        <textarea id="wmf-sql-input" class="wmf-sql-modal__textarea" spellcheck="false" placeholder="SELECT * FROM {{prefix}}posts LIMIT 5;"></textarea>
        <div class="wmf-sql-modal__footer">
            <button type="button" class="wpml-btn wpml-btn-secondary" data-action="preview-sql">
                <?php esc_html_e('Preview', 'wpml-migration-fixer'); ?>
            </button>
            <button type="button" class="wpml-btn wpml-btn-danger" data-action="execute-sql">
                <?php esc_html_e('Run', 'wpml-migration-fixer'); ?>
            </button>
            <button type="button" class="wpml-btn" data-action="copy-sql">
                <?php esc_html_e('Copy SQL', 'wpml-migration-fixer'); ?>
            </button>
        </div>
        <div class="wmf-sql-output" id="wmf-sql-output" data-placeholder="<?php esc_attr_e('Preview results will appear here.', 'wpml-migration-fixer'); ?>"></div>
    </div>
</div>
