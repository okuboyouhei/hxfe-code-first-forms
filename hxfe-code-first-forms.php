<?php
/**
 * Plugin Name: HXFE — Code-First Forms
 * Plugin URI:  https://wordpress.org/plugins/hxfe-code-first-forms/
 * Description: Code-first form engine. Define forms as PHP arrays — contact forms, step forms, chatbots, and surveys from one schema. Git-manageable, deploy-safe, zero cookies.
 * Version:     1.3.8
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author:      Youhei Okubo
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: hxfe-code-first-forms
 * Domain Path: /languages
 *
 * @package HtmxFormEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HXFE_VERSION',     '1.3.8' );
define( 'HXFE_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'HXFE_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'HXFE_PLUGIN_FILE', __FILE__ );
define( 'HXFE_HTMX_VERSION', '1.9.12' );

require_once HXFE_PLUGIN_DIR . 'includes/conditions.php';
require_once HXFE_PLUGIN_DIR . 'includes/sanitizers.php';
require_once HXFE_PLUGIN_DIR . 'includes/schema.php';
require_once HXFE_PLUGIN_DIR . 'includes/renderer.php';
require_once HXFE_PLUGIN_DIR . 'includes/mailer.php';
require_once HXFE_PLUGIN_DIR . 'includes/ajax-handlers.php';
require_once HXFE_PLUGIN_DIR . 'includes/recaptcha.php';
require_once HXFE_PLUGIN_DIR . 'includes/privacy.php';
require_once HXFE_PLUGIN_DIR . 'includes/steps.php';
require_once HXFE_PLUGIN_DIR . 'includes/step-renderer.php';
require_once HXFE_PLUGIN_DIR . 'includes/smtp.php';
require_once HXFE_PLUGIN_DIR . 'includes/access-control.php';
require_once HXFE_PLUGIN_DIR . 'includes/shortcode.php';
require_once HXFE_PLUGIN_DIR . 'includes/file-upload.php';
require_once HXFE_PLUGIN_DIR . 'includes/webhook.php';
require_once HXFE_PLUGIN_DIR . 'includes/chatbot.php';
require_once HXFE_PLUGIN_DIR . 'includes/iframe.php';
require_once HXFE_PLUGIN_DIR . 'includes/settings-page.php';

register_activation_hook( __FILE__, 'hxfe_activate' );
register_deactivation_hook( __FILE__, 'hxfe_deactivate' );

function hxfe_activate() {
	flush_rewrite_rules();
	if ( ! wp_next_scheduled( 'hxfe_cleanup_tmp_files' ) ) {
		wp_schedule_event( time(), 'hourly', 'hxfe_cleanup_tmp_files' );
	}
}

function hxfe_deactivate() {
	flush_rewrite_rules();
	wp_clear_scheduled_hook( 'hxfe_cleanup_tmp_files' );
}

/**
 * 1時間以上前の一時ファイルを削除する。
 * 送信されずにページを閉じた場合などに残るファイルをクリーンアップする。
 */
add_action( 'hxfe_cleanup_tmp_files', 'hxfe_do_cleanup_tmp_files' );
function hxfe_do_cleanup_tmp_files() {
	$upload_dir = wp_upload_dir();
	$tmp_dir    = $upload_dir['basedir'] . '/hxfe-uploads';
	if ( ! is_dir( $tmp_dir ) ) {
		return;
	}
	$files = glob( $tmp_dir . '/*' );
	if ( ! $files ) {
		return;
	}
	$expires = time() - HOUR_IN_SECONDS;
	foreach ( $files as $file ) {
		if ( is_file( $file ) && filemtime( $file ) < $expires ) {
			wp_delete_file( $file );
		}
	}
}
