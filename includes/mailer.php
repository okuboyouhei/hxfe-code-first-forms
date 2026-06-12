<?php
/**
 * Email sending.
 *
 * v1.1 変更点:
 *   - 自動返信を主機能に変更 (reply_to_field が設定されている場合に送信)
 *   - 管理者宛メールをオプションに変更 (schema['admin_notify'] => false で無効化)
 *   - 自動返信の件名・本文をスキーマで完全にカスタマイズ可能
 *   - BCC / CC 対応を追加
 *
 * スキーマキー:
 *   to                 (string)  管理者宛メールアドレス。空にすると管理者通知を無効化。
 *   admin_notify       (bool)    false にすると管理者通知を送らない。デフォルト true。
 *   subject            (string)  管理者宛件名。{field_key} 補間可。
 *   bcc                (string)  管理者宛 BCC アドレス（カンマ区切り）。
 *   reply_to_field     (string)  自動返信先フィールドのkey。設定すると自動返信を送信。
 *   autoreply_subject  (string)  自動返信の件名。省略時はデフォルト文。
 *   autoreply_body     (string)  自動返信の本文。{field_key} 補間可。
 *   autoreply_from     (string)  自動返信の送信元アドレス。省略時は管理者メール。
 *   autoreply_from_name (string) 自動返信の送信元名。
 *   complete_message   (string)  完了画面の HTML。
 *
 * @package HtmxFormEngine
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * スキーマの設定に従ってメールを送信する。
 *
 * 送信するメールの組み合わせ:
 *   - admin_notify が true (デフォルト) かつ to が設定されている → 管理者通知
 *   - reply_to_field が設定されている → 自動返信
 *   どちらも設定されていない場合は何も送信しない。
 *
 * @param array $schema フォームスキーマ。
 * @param array $values バリデーション済みの入力値。
 * @return bool 少なくとも1通の送信が成功したか。
 */
function hxfe_send_emails( array $schema, array $values, array $file_paths = [] ) {
	$sent = false;

	// ① 管理者宛通知 (admin_notify が明示的に false でなければ送信)
	$admin_notify = $schema['admin_notify'] ?? true;
	// to_rules で送信先を動的に解決。解決できない場合は $schema['to'] にフォールバック
	$resolved_to = hxfe_resolve_to( $schema, $values );
	if ( $admin_notify && '' !== $resolved_to ) {
		$resolved_schema       = $schema;
		$resolved_schema['to'] = $resolved_to;
		$sent = hxfe_send_admin_notification( $resolved_schema, $values, $file_paths ) || $sent;
	} elseif ( $admin_notify && '' === $resolved_to ) {
		// 送信先が解決できない場合はWP_DEBUGログに記録
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[HXFE] No valid recipient resolved for form: ' . ( $schema['id'] ?? 'unknown' ) );
		}
	}

	// ② 自動返信 (reply_to_field が設定されている場合のみ)
	if ( ! empty( $schema['reply_to_field'] ) ) {
		hxfe_send_autoreply( $schema, $values );
		$sent = true;
	}

	// ③ Webhook送信 (webhooks が設定されている場合)
	hxfe_dispatch_webhooks( $schema, $values );

	return $sent;
}

/**
 * 管理者宛通知メールを送信する。
 */
function hxfe_send_admin_notification( array $schema, array $values, array $file_paths = [] ) {
	// to は文字列でも配列でも受け付ける
	$to_raw  = $schema['to'] ?? '';
	$to_list = is_array( $to_raw )
		? array_filter( array_map( 'sanitize_email', $to_raw ) )
		: [ sanitize_email( $to_raw ) ];
	$to      = implode( ',', $to_list );
	// subject_rules で件名を動的に解決
	$subject = hxfe_resolve_subject( $schema, $values );
	$body    = hxfe_build_admin_body( $schema, $values );
	$headers = hxfe_build_admin_headers( $schema, $values );

	// ファイル添付（アップロードされたファイルがある場合）
	$attachments = array_values( array_filter( $file_paths, 'file_exists' ) );

	$ok = wp_mail( $to, $subject, $body, $headers, $attachments );
	if ( ! $ok ) {
		hxfe_log_error( 'SMTP_ERROR', $schema['id'] ?? 'unknown', 'wp_mail() failed — To: ' . $to . ' | Subject: ' . $subject );
	}
	return $ok;
}

/**
 * 自動返信メールを申込者に送信する。
 *
 * v1.1 変更点:
 *   - 送信元アドレス / 名前をスキーマで設定可能に
 *   - 本文テンプレートで {field_key} 補間に対応
 */
function hxfe_send_autoreply( array $schema, array $values ) {
	$reply_email = sanitize_email( $values[ $schema['reply_to_field'] ] ?? '' );
	if ( ! is_email( $reply_email ) ) { return; }

	$from_email = sanitize_email(
		$schema['autoreply_from'] ?? get_option( 'admin_email' )
	);
	$from_name = sanitize_text_field(
		$schema['autoreply_from_name'] ?? get_bloginfo( 'name' )
	);

	$subject = hxfe_interpolate(
		$schema['autoreply_subject']
			?? sprintf(
				// translators: %s: site name
				__( 'Thank you for contacting %s', 'hxfe-code-first-forms' ),
				get_bloginfo( 'name' )
			),
		$values
	);

	$body = hxfe_interpolate(
		$schema['autoreply_body']
			?? __( "Thank you for your message.\nWe will get back to you as soon as possible.", 'hxfe-code-first-forms' ),
		$values
	);

	$headers = [
		'Content-Type: text/plain; charset=UTF-8',
		'From: ' . $from_name . ' <' . $from_email . '>',
	];

	wp_mail( $reply_email, $subject, $body, $headers );
}

/**
 * 管理者宛メールの本文を生成する。
 */
function hxfe_build_admin_body( array $schema, array $values ) {
	$site  = get_bloginfo( 'name' );
	$time  = current_time( 'Y-m-d H:i:s' );
	$lines = [
		// translators: %s: site name
		sprintf( __( 'New submission from %s', 'hxfe-code-first-forms' ), $site ),
		// translators: %s: date and time
		sprintf( __( 'Received: %s', 'hxfe-code-first-forms' ), $time ),
		str_repeat( '-', 40 ),
	];

	foreach ( $schema['fields'] as $field ) {
		$type = $field['type'] ?? 'text';

		// メール本文に不要なフィールドを除外
		if ( in_array( $type, [ 'honeypot', 'recaptcha', 'privacy' ], true ) ) {
			continue;
		}

		// 条件分岐で非表示のフィールドはメールにも含めない
		if ( ! hxfe_field_is_visible( $field, $values ) ) {
			continue;
		}

		$key   = $field['key'];
		$label = $field['label'] ?? $key;
		$raw   = $values[ $key ] ?? '';

		// タイプ別の表示値（確認画面と同じロジック）
		switch ( $type ) {
			case 'checkbox':
				$display = $raw ? __( 'Yes', 'hxfe-code-first-forms' ) : __( 'No', 'hxfe-code-first-forms' );
				break;
			case 'radio':
			case 'select':
				$display = $raw;
				foreach ( $field['options'] ?? [] as $opt ) {
					if ( (string) ( $opt['value'] ?? '' ) === (string) $raw ) {
						$display = $opt['label'] ?? $raw;
						break;
					}
				}
				break;
			case 'checkbox_group':
				if ( '' === (string) $raw ) {
					$display = '';
					break;
				}
				$selected = array_map( 'trim', explode( ',', (string) $raw ) );
				$opt_map  = array_column( $field['options'] ?? [], 'label', 'value' );
				$display  = implode( ', ', array_map( fn( $v ) => $opt_map[ $v ] ?? $v, $selected ) );
				break;
			default:
				$display = (string) $raw;
		}

		if ( '' === $display ) { continue; } // 空値はメールに含めない
		$lines[] = $label . ': ' . $display;
	}

	return implode( "\n", $lines );
}

/**
 * 管理者宛メールのヘッダーを生成する。
 * Reply-To / BCC を含む。
 */
function hxfe_build_admin_headers( array $schema, array $values ) {
	$headers = [ 'Content-Type: text/plain; charset=UTF-8' ];

	// Reply-To
	if ( ! empty( $schema['reply_to_field'] ) ) {
		$reply = sanitize_email( $values[ $schema['reply_to_field'] ] ?? '' );
		if ( is_email( $reply ) ) {
			$headers[] = 'Reply-To: ' . $reply;
		}
	}

	// BCC
	if ( ! empty( $schema['bcc'] ) ) {
		foreach ( explode( ',', $schema['bcc'] ) as $bcc ) {
			$bcc = sanitize_email( trim( $bcc ) );
			if ( is_email( $bcc ) ) {
				$headers[] = 'Bcc: ' . $bcc;
			}
		}
	}

	return $headers;
}
