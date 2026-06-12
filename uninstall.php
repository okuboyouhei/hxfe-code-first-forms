<?php
/**
 * Uninstall hook — runs when the plugin is deleted from the WordPress admin.
 * Removes all plugin-created data from the database.
 *
 * @package HtmxFormEngine
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// ── HXFE設定の削除 ──────────────────────────────────────────────────────────

// reCAPTCHA設定
delete_option( 'hxfe_recaptcha_site_key' );
delete_option( 'hxfe_recaptcha_secret_key' );
delete_option( 'hxfe_recaptcha_version' );

// SMTP設定
delete_option( 'hxfe_smtp_enabled' );
delete_option( 'hxfe_smtp_host' );
delete_option( 'hxfe_smtp_port' );
delete_option( 'hxfe_smtp_encryption' );
delete_option( 'hxfe_smtp_username' );
delete_option( 'hxfe_smtp_password' );
delete_option( 'hxfe_smtp_provider' );
delete_option( 'hxfe_smtp_from_email' );
delete_option( 'hxfe_smtp_from_name' );

// iframe / CORS設定
delete_option( 'hxfe_iframe_allowed_origins' );
delete_option( 'hxfe_iframe_enabled' );

// その他設定
delete_option( 'hxfe_disable_default_css' );

// トランジェント削除
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- intentional uninstall cleanup
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
        '_transient_hxfe_%',
        '_transient_timeout_hxfe_%'
    )
);
