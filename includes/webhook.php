<?php
/**
 * Webhook support — sends form data to external URLs after submission.
 *
 * スキーマの書き方:
 *
 *   $schemas['contact'] = [
 *       'webhooks' => [
 *           [
 *               'url'     => 'https://hooks.zapier.com/hooks/catch/xxx/yyy/',
 *               'method'  => 'POST',         // POST（デフォルト）または GET
 *               'format'  => 'json',         // json（デフォルト）または form
 *               'headers' => [               // カスタムヘッダー（任意）
 *                   'X-API-Key' => 'your-key',
 *               ],
 *               'when'    => [ 'plan', '==', 'premium' ], // 条件付き送信（任意）
 *           ],
 *       ],
 *   ];
 *
 * 送信タイミング: メール送信と同じタイミング（hxfe_send_emails内で呼ばれる）
 * 送信方式: wp_remote_post/get（WordPress標準HTTPクライアント）
 * エラー処理: WP_DEBUG有効時にerror_logに記録。フォーム送信は止めない。
 *
 * @package HtmxFormEngine
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * スキーマのwebhooksを全て送信する。
 * hxfe_send_emails() から呼ばれる。
 *
 * @param array $schema フォームスキーマ。
 * @param array $values 送信された値（サニタイズ済み）。
 */
function hxfe_dispatch_webhooks( array $schema, array $values ) {
	$webhooks = $schema['webhooks'] ?? [];
	if ( empty( $webhooks ) || ! is_array( $webhooks ) ) {
		return;
	}

	foreach ( $webhooks as $i => $webhook ) {
		$url = $webhook['url'] ?? '';
		if ( empty( $url ) || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			hxfe_log_error( 'WEBHOOK_ERROR', $schema['id'] ?? 'unknown', "Webhook [{$i}]: Invalid or missing URL" );
			continue;
		}

		// 条件付き送信: when が設定されていて条件を満たさない場合はスキップ
		if ( ! empty( $webhook['when'] ) && ! hxfe_eval_condition( $webhook['when'], $values ) ) {
			continue;
		}

		hxfe_send_single_webhook( $webhook, $values, $schema );
	}
}

/**
 * 単一のwebhookを送信する。
 *
 * @param array $webhook  webhook定義。
 * @param array $values   送信された値。
 * @param array $schema   フォームスキーマ（メタ情報に使用）。
 */
function hxfe_send_single_webhook( array $webhook, array $values, array $schema ) {
	$url     = esc_url_raw( $webhook['url'] );
	$method  = strtoupper( $webhook['method'] ?? 'POST' );
	$format  = $webhook['format']  ?? 'json';
	$headers = is_array( $webhook['headers'] ?? null ) ? $webhook['headers'] : [];

	// 送信ペイロードを構築
	// フォームID・サイト情報も付与する
	$payload = array_merge( $values, [
		'_form_id'   => $schema['id'] ?? '',
		'_site_url'  => home_url(),
		'_site_name' => get_bloginfo( 'name' ),
		'_sent_at'   => current_time( 'Y-m-d H:i:s' ),
	] );

	// 送信形式
	if ( 'json' === $format ) {
		$headers['Content-Type'] = 'application/json';
		$body = wp_json_encode( $payload );
	} else {
		// form: application/x-www-form-urlencoded
		$body = http_build_query( $payload );
	}

	// wp_remote_post / wp_remote_get
	$args = [
		'method'  => $method,
		'headers' => $headers,
		'body'    => $body,
		'timeout' => 10, // 10秒タイムアウト
		'reject_unsafe_urls' => true, // SSRF対策: 内部IP・不正ポートへのリクエストを拒否（v1.4.6）
	];

	if ( 'GET' === $method ) {
		// GETの場合はクエリ文字列に付与
		$url      = add_query_arg( $payload, $url );
		$args['body'] = null;
		$response = wp_remote_get( $url, $args );
	} else {
		$response = wp_remote_post( $url, $args );
	}

	// エラーハンドリング（フォーム送信は止めない）
	if ( is_wp_error( $response ) ) {
		hxfe_log_error( 'WEBHOOK_ERROR', $schema['id'] ?? 'unknown', 'Request failed: ' . $response->get_error_message() . ' | URL: ' . $url );
		return;
	}

	$code = wp_remote_retrieve_response_code( $response );
	if ( $code < 200 || $code >= 300 ) {
		hxfe_log_error( 'WEBHOOK_ERROR', $schema['id'] ?? 'unknown', "HTTP {$code} | URL: {$url}" );
	}
}
