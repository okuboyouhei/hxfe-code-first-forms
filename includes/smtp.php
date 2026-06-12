<?php
/**
 * SMTP configuration — overrides wp_mail() transport via phpmailer_init.
 *
 * 学習ポイント: phpmailer_init フック
 *
 *   WordPress は wp_mail() の内部で PHPMailer インスタンスを生成する。
 *   'phpmailer_init' アクションでそのインスタンスを受け取り、
 *   SMTP設定を直接書き込むことができる。
 *
 *   これにより:
 *   - wp_mail() を呼ぶ全てのコード（HXFE・コアのパスワードリセット等）に効く
 *   - PHPMailerのSMTP実装をそのまま使えるため安全・実績がある
 *   - WP Mail SMTPと同じアプローチ
 *
 * パスワードの保存:
 *   SMTPパスワードはDBにそのまま保存せず、
 *   wp-config.php に定数で定義することを推奨する。
 *   定数が定義されていれば定数を優先し、DBのパスワードは使わない。
 *
 *   define( 'HXFE_SMTP_PASSWORD', 'your-app-password' );
 *   define( 'HXFE_SMTP_API_KEY',  'your-sendgrid-api-key' );
 *
 * @package HtmxFormEngine
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'phpmailer_init',    'hxfe_configure_phpmailer' );
add_filter( 'wp_mail_from',      'hxfe_filter_mail_from' );
add_filter( 'wp_mail_from_name', 'hxfe_filter_mail_from_name' );

/**
 * PHPMailer に SMTP 設定を適用する。
 *
 * @param PHPMailer\PHPMailer\PHPMailer $phpmailer
 */
function hxfe_configure_phpmailer( $phpmailer ) {
	$settings = hxfe_get_smtp_settings();

	// SMTP が無効なら何もしない
	if ( empty( $settings['enabled'] ) ) { return; }

	$host     = $settings['host'] ?? '';
	$port     = (int) ( $settings['port'] ?? 587 );
	$username = $settings['username'] ?? '';
	$password = hxfe_get_smtp_credential( $settings );
	$enc      = $settings['encryption'] ?? 'tls';

	if ( '' === $host ) { return; }

	$phpmailer->isSMTP();
	$phpmailer->Host       = $host;
	$phpmailer->Port       = $port;
	$phpmailer->SMTPAuth   = ( '' !== $username && '' !== $password );
	$phpmailer->Username   = $username;
	$phpmailer->Password   = $password;

	switch ( $enc ) {
		case 'ssl':
			$phpmailer->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
			break;
		case 'tls':
			$phpmailer->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
			break;
		default:
			$phpmailer->SMTPSecure = '';
			$phpmailer->SMTPAuth   = false;
			break;
	}
}

/**
 * From メールアドレスを上書きする。
 *
 * @param string $from
 * @return string
 */
function hxfe_filter_mail_from( $from ) {
	$settings   = hxfe_get_smtp_settings();
	$from_email = $settings['from_email'] ?? '';
	return ( '' !== $from_email && is_email( $from_email ) ) ? $from_email : $from;
}

/**
 * From 名を上書きする。
 *
 * @param string $name
 * @return string
 */
function hxfe_filter_mail_from_name( $name ) {
	$settings  = hxfe_get_smtp_settings();
	$from_name = $settings['from_name'] ?? '';
	return ( '' !== $from_name ) ? $from_name : $name;
}

/**
 * SMTP 設定を取得する。
 *
 * @return array
 */
function hxfe_get_smtp_settings() {
	static $cache = null;
	if ( null !== $cache ) { return $cache; }

	$saved  = get_option( 'hxfe_smtp_settings', [] );
	$cache  = wp_parse_args( is_array( $saved ) ? $saved : [], [
		'enabled'    => false,
		'provider'   => 'custom',
		'host'       => '',
		'port'       => 587,
		'encryption' => 'tls',
		'username'   => '',
		'password'   => '',   // DBに保存（非推奨。定数推奨）
		'from_email' => '',
		'from_name'  => get_bloginfo( 'name' ),
	] );
	return $cache;
}

/**
 * SMTP パスワードを取得する。
 * wp-config.php の定数 > DBの設定 の優先順位。
 *
 * @param array $settings
 * @return string
 */
function hxfe_get_smtp_credential( array $settings ) {
	// SendGrid API Key
	if ( 'sendgrid' === ( $settings['provider'] ?? '' ) ) {
		if ( defined( 'HXFE_SMTP_API_KEY' ) && '' !== HXFE_SMTP_API_KEY ) {
			return HXFE_SMTP_API_KEY;
		}
	}

	// 汎用パスワード（Gmail・Mailgun SMTP・自社サーバー）
	if ( defined( 'HXFE_SMTP_PASSWORD' ) && '' !== HXFE_SMTP_PASSWORD ) {
		return HXFE_SMTP_PASSWORD;
	}

	// DBに保存されたパスワード（フォールバック）
	return (string) ( $settings['password'] ?? '' );
}

/**
 * テストメールを送信する。
 * 管理画面の「テスト送信」ボタンから呼ばれる。
 *
 * @param string $to 送信先メールアドレス。
 * @return array{ ok: bool, message: string }
 */
function hxfe_send_test_mail( string $to ) {
	if ( ! is_email( $to ) ) {
		return [ 'ok' => false, 'message' => __( 'Invalid email address.', 'hxfe-code-first-forms' ) ];
	}

	$subject = sprintf(
		// translators: %s: site name
		__( '[%s] SMTP test email', 'hxfe-code-first-forms' ),
		get_bloginfo( 'name' )
	);

	// translators: 1: site URL, 2: current date/time
	$body = sprintf(
		__( "SMTP is configured correctly.\n\nSent from: %1\$s\nTime: %2\$s", 'hxfe-code-first-forms' ),
		home_url(),
		current_time( 'Y-m-d H:i:s' )
	);

	// PHPMailerのエラーをキャプチャするためアクションフックを一時登録
	$error_message = '';
	$capture = function( $wp_error ) use ( &$error_message ) {
		$error_message = $wp_error->get_error_message();
	};
	add_action( 'wp_mail_failed', $capture );

	$result = wp_mail( $to, $subject, $body );

	remove_action( 'wp_mail_failed', $capture );

	if ( $result ) {
		return [ 'ok' => true, 'message' => __( 'Test email sent successfully.', 'hxfe-code-first-forms' ) ];
	}

	return [
		'ok'      => false,
		'message' => $error_message ?: __( 'Failed to send test email. Check your SMTP settings.', 'hxfe-code-first-forms' ),
	];
}
