<?php
/**
 * HXFE Error Logger
 *
 * フォームごとのエラーをファイルに記録する。
 * データベースは使用しない（HXFEのDB不使用思想を維持）。
 *
 * ログ保存場所: wp-content/hxfe-logs/hxfe-YYYY-MM-DD.log
 * 保持期間: 30日（古いファイルは自動削除）
 * Webアクセス: .htaccess でブロック
 *
 * 使い方:
 *   hxfe_log_error( 'SMTP_ERROR', 'contact', 'メール送信失敗: タイムアウト' );
 *   hxfe_log_error( 'WEBHOOK_ERROR', 'contact', 'HTTP 500: ' . $url );
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * ログディレクトリのパスを返す。
 *
 * @return string
 */
function hxfe_log_dir(): string {
	return WP_CONTENT_DIR . '/hxfe-logs';
}

/**
 * ログディレクトリを初期化する（初回のみ）。
 * .htaccess と index.php を作成してWebアクセスをブロックする。
 */
function hxfe_init_log_dir(): void {
	$dir = hxfe_log_dir();

	if ( ! is_dir( $dir ) ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
		mkdir( $dir, 0755, true );
	}

	// .htaccess でWebアクセスをブロック
	$htaccess = $dir . '/.htaccess';
	if ( ! file_exists( $htaccess ) ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $htaccess, "Deny from all\n" );
	}

	// index.php でディレクトリ一覧を非表示
	$index = $dir . '/index.php';
	if ( ! file_exists( $index ) ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $index, "<?php // Silence is golden.\n" );
	}
}

/**
 * エラーをログファイルに記録する。
 *
 * @param string $type    エラー種別（SMTP_ERROR / WEBHOOK_ERROR / RECAPTCHA_ERROR / FILE_ERROR）
 * @param string $form_id フォームID
 * @param string $message エラーメッセージ
 */
function hxfe_log_error( string $type, string $form_id, string $message ): void {
	hxfe_init_log_dir();

	$dir      = hxfe_log_dir();
	$date     = gmdate( 'Y-m-d' );
	$log_file = $dir . '/hxfe-' . $date . '.log';

	$timestamp = gmdate( 'Y-m-d H:i:s' );
	$line      = "[{$timestamp}] {$type} | form:{$form_id} | {$message}" . PHP_EOL;

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	file_put_contents( $log_file, $line, FILE_APPEND | LOCK_EX );

	// 古いログを自動削除（30日以上）
	hxfe_purge_old_logs( $dir );
}

/**
 * 30日以上古いログファイルを削除する。
 *
 * @param string $dir ログディレクトリのパス
 */
function hxfe_purge_old_logs( string $dir ): void {
	$files = glob( $dir . '/hxfe-*.log' );
	if ( empty( $files ) ) {
		return;
	}

	$threshold = strtotime( '-30 days' );
	foreach ( $files as $file ) {
		if ( filemtime( $file ) < $threshold ) {
			wp_delete_file( $file );
		}
	}
}

/**
 * 直近N日分のログを読み込んで返す。
 *
 * @param int $days 取得する日数（デフォルト: 7）
 * @return array { date: string, lines: string[] }[]
 */
function hxfe_get_recent_logs( int $days = 7 ): array {
	hxfe_init_log_dir();

	$dir    = hxfe_log_dir();
	$result = [];

	for ( $i = 0; $i < $days; $i++ ) {
		$date     = gmdate( 'Y-m-d', strtotime( "-{$i} days" ) );
		$log_file = $dir . '/hxfe-' . $date . '.log';

		if ( ! file_exists( $log_file ) ) {
			continue;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$content = file_get_contents( $log_file );
		if ( false === $content || '' === trim( $content ) ) {
			continue;
		}

		$lines = array_filter( explode( PHP_EOL, trim( $content ) ) );
		if ( ! empty( $lines ) ) {
			$result[] = [
				'date'  => $date,
				'lines' => array_reverse( array_values( $lines ) ), // 新しい順
			];
		}
	}

	return $result;
}

/**
 * 全ログファイルを削除する。
 */
function hxfe_clear_all_logs(): void {
	$dir   = hxfe_log_dir();
	$files = glob( $dir . '/hxfe-*.log' );
	if ( empty( $files ) ) {
		return;
	}
	foreach ( $files as $file ) {
		wp_delete_file( $file );
	}
}
