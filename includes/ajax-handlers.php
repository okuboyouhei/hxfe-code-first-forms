<?php
/**
 * htmx AJAX endpoints — three handlers, one security gate.
 *
 * All three actions are public (no_priv variants) because contact forms
 * must work for logged-out visitors.
 *
 * hxfe_validate  POST → validate → render confirm (or re-render input with errors)
 * hxfe_submit    POST → verify nonce → send mail → render complete
 * hxfe_back      POST → re-render input with previously entered values
 *
 * Security layers applied before any processing:
 *   1. Nonce verification  (CSRF guard)
 *   2. Schema existence    (unknown form_id = 400)
 *   3. Field-level sanitize / validate (sanitizers.php)
 *   4. Honeypot check      (spam guard — no JS needed)
 *
 * @package HtmxFormEngine
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ---------------------------------------------------------------------------
 * Handler registration
 * ------------------------------------------------------------------------- */

add_action( 'wp_ajax_hxfe_validate',        'hxfe_handle_validate' );
add_action( 'wp_ajax_nopriv_hxfe_validate', 'hxfe_handle_validate' );

add_action( 'wp_ajax_hxfe_submit',          'hxfe_handle_submit' );
add_action( 'wp_ajax_nopriv_hxfe_submit',   'hxfe_handle_submit' );

add_action( 'wp_ajax_hxfe_back',            'hxfe_handle_back' );
add_action( 'wp_ajax_nopriv_hxfe_back',     'hxfe_handle_back' );

/* ---------------------------------------------------------------------------
 * CAPTCHA 検証（共通）
 * ------------------------------------------------------------------------- */

/**
 * スキーマ内の reCAPTCHA / Turnstile フィールドを検証する。
 *
 * 入力画面のフォーム送信（validate）時に呼ぶこと。確認画面にはウィジェットが
 * 存在せずトークンを引き継げないため、submit 時には検証できない。
 *
 * nonce は呼び出し元（hxfe_validate_request）で検証済み。
 *
 * @param array $schema フォームスキーマ。
 * @return array<string,string> フィールドキー => エラーメッセージ。空配列なら検証成功。
 */
function hxfe_verify_captcha_fields( array $schema ): array {
	$errors = [];

	foreach ( $schema['fields'] as $field ) {
		$type = $field['type'] ?? '';

		if ( 'recaptcha' === $type ) {
			$token  = sanitize_text_field( wp_unslash( $_POST['hxfe_recaptcha_token'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified by hxfe_validate_request()
			$result = hxfe_validate_recaptcha( $field, $token );
			if ( '' !== $result['error'] ) {
				$errors[ $field['key'] ] = $result['error'];
			}
		} elseif ( 'turnstile' === $type ) {
			$token  = sanitize_text_field( wp_unslash( $_POST['cf-turnstile-response'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified by hxfe_validate_request()
			$result = hxfe_validate_turnstile( $field, $token );
			if ( '' !== $result['error'] ) {
				$errors[ $field['key'] ] = $result['error'];
			}
		}
	}

	return $errors;
}

/* ---------------------------------------------------------------------------
 * Shared gate
 * ------------------------------------------------------------------------- */

/**
 * Validates the nonce and resolves the schema for a given action.
 * Dies with a 400/403 response on failure.
 *
 * @param string $nonce_action WordPress nonce action string.
 * @param string $nonce_field  POST field name carrying the nonce.
 * @return array Resolved schema.
 */
function hxfe_validate_request( $nonce_action, $nonce_field = 'hxfe_nonce' ) {
	// ① form_id を先に取得（nonceアクションに form_id が含まれる場合に必要）
	// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$form_id = isset( $_POST['hxfe_form_id'] ) ? sanitize_key( wp_unslash( $_POST['hxfe_form_id'] ) ) : '';

	// ② nonce（アクションに {form_id} プレースホルダーがある場合は置換）
	$resolved_action = str_replace( '{form_id}', $form_id, $nonce_action );
	$nonce           = isset( $_POST[ $nonce_field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $nonce_field ] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, $resolved_action ) ) {
		status_header( 403 );
		wp_die( '', '', 403 );
	}

	// ③ スキーマ存在確認
	$schema = hxfe_get_schema( $form_id );
	if ( null === $schema ) {
		status_header( 400 );
		wp_die( '', '', 400 );
	}

	return $schema;
}

/**
 * POSTされた hxfe_context（ページスラッグ）をスキーマに付与するヘルパー。
 */
function hxfe_inject_context( array $schema ) : array {
	$ctx = isset( $_POST['hxfe_context'] ) ? sanitize_key( wp_unslash( $_POST['hxfe_context'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( '' !== $ctx ) {
		$schema['_context'] = $ctx;
	}
	return $schema;
}

/**
 * Step系リクエストの共通ゲート。
 * nonce・スキーマ・step_index を一括で検証して返す。
 *
 * @param string $nonce_suffix  nonce アクションのサフィックス（例: 'step_next'）
 * @param int    $default_index step_index のデフォルト値
 * @return array{ schema: array, form_id: string, step_index: int }
 */
function hxfe_validate_step_request( string $nonce_suffix, int $default_index = 0 ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$form_id    = sanitize_key( wp_unslash( $_POST['hxfe_form_id'] ?? '' ) );
	$step_index = absint( wp_unslash( $_POST['hxfe_step_index'] ?? $default_index ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

	$nonce = sanitize_text_field( wp_unslash( $_POST['hxfe_nonce'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	if ( ! wp_verify_nonce( $nonce, 'hxfe_' . $nonce_suffix . '_' . $form_id ) ) {
		status_header( 403 );
		wp_die( '', '', 403 );
	}

	$schema = hxfe_get_schema( $form_id );
	if ( null === $schema ) {
		status_header( 400 );
		wp_die( '', '', 400 );
	}

	return [
		'schema'     => $schema,
		'form_id'    => $form_id,
		'step_index' => (int) $step_index,
	];
}

/* ---------------------------------------------------------------------------
 * Handler: validate (input → confirm or input with errors)
 * ------------------------------------------------------------------------- */

function hxfe_handle_validate() {
	$form_id = isset( $_POST['hxfe_form_id'] ) ? sanitize_key( wp_unslash( $_POST['hxfe_form_id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$schema  = hxfe_inject_context( hxfe_validate_request( 'hxfe_validate_' . $form_id ) );

	// ③ + ④  sanitize all fields (includes honeypot check)
	$result = hxfe_process_fields( $schema, $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified by hxfe_validate_request()
	$values = $result['values'];
	$errors = $result['errors'];

	// ⑤ ファイルアップロード処理（fileフィールドがある場合のみ）
	$file_result = hxfe_process_file_uploads( $schema, $form_id );
	$file_paths  = $file_result['paths'];
	$file_names  = $file_result['names'];
	foreach ( $file_result['errors'] as $key => $err ) {
		$errors[ $key ] = $err;
	}
	// アップロード済みファイルパスを hidden フィールドで次のステップに引き渡す
	$values['__file_paths'] = wp_json_encode( $file_paths );
	$values['__file_names'] = wp_json_encode( $file_names );

	// Honeypot triggered — silently show complete to fool bots.
	if ( isset( $errors['__honeypot'] ) ) {
		echo hxfe_render_complete( $schema, $values ?? [] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		wp_die();
	}

	// CAPTCHA検証（入力画面のフォームにウィジェット/トークンが存在するこのタイミングで実施）。
	// 確認画面にはウィジェットが無くトークンを引き継げないため、submitではなくvalidateで検証する。
	$captcha_errors = hxfe_verify_captcha_fields( $schema );
	foreach ( $captcha_errors as $key => $msg ) {
		$errors[ $key ] = $msg;
	}

	if ( ! empty( $errors ) ) {
		echo hxfe_render_input( $schema, $errors, $values ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		wp_die();
	}

	// confirm: false の場合は確認画面をスキップして即送信
	if ( isset( $schema['confirm'] ) && false === $schema['confirm'] ) {
		$immediate_values = $values;
		$imm_file_paths   = json_decode( $immediate_values['__file_paths'] ?? '{}', true ) ?: [];
		unset( $immediate_values['__file_paths'], $immediate_values['__file_names'] );
		hxfe_send_emails( $schema, $immediate_values, $imm_file_paths );

		/**
		 * フォーム送信完了後のフック。
		 *
		 * @since 1.4.5
		 * @param string $form_id フォームID
		 * @param array  $values  送信された値（サニタイズ済み）
		 * @param array  $schema  フォームスキーマ
		 */
		do_action( 'hxfe_after_submit', $schema['id'] ?? '', $immediate_values, $schema );

		hxfe_cleanup_uploaded_files( array_values( $imm_file_paths ) );
		echo hxfe_render_complete( $schema, $immediate_values ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		wp_die();
	}

	echo hxfe_render_confirm( $schema, $values ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	wp_die();
}

/* ---------------------------------------------------------------------------
 * Handler: submit (confirm → complete + send mail)
 * ------------------------------------------------------------------------- */

function hxfe_handle_submit() {
	$form_id = isset( $_POST['hxfe_form_id'] ) ? sanitize_key( wp_unslash( $_POST['hxfe_form_id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$schema  = hxfe_inject_context( hxfe_validate_request( 'hxfe_submit_' . $form_id ) );

	// Values arrive as JSON-encoded string from hx-vals.
	$raw_json = isset( $_POST['hxfe_values'] ) ? wp_unslash( $_POST['hxfe_values'] ) : '{}'; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$values   = json_decode( $raw_json, true );

	if ( ! is_array( $values ) ) {
		status_header( 400 );
		wp_die( '', '', 400 );
	}

	// CAPTCHA検証は validate ハンドラ（入力画面→確認画面の手前）で実施済み。
	// 確認画面にはCAPTCHAウィジェットが存在せずトークンを引き継げないため、
	// ここ（submit）では検証しない。

	// json_decode 後の値を hxfe_process_fields() でサニタイズ＋バリデーションする。
	// sanitize_text_field() 等はべき等なため、confirm 画面経由の値を再サニタイズしても値は変わらない。
	$result = hxfe_process_fields( $schema, $values );
	if ( ! empty( $result['errors'] ) ) {
		echo hxfe_render_input( $schema, $result['errors'], $result['values'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		wp_die();
	}

	// ファイルパスを hidden フィールドまたは values から取り出す
	$submit_values = $result['values'];
	// confirm 画面から戻った場合は hidden の hxfe_file_paths を使う
	$fp_raw     = isset( $_POST['hxfe_file_paths'] ) ? wp_unslash( $_POST['hxfe_file_paths'] ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	if ( is_array( $fp_raw ) ) {
		$file_paths = array_map( 'sanitize_text_field', $fp_raw );
	} else {
		$fp_json    = $fp_raw ? wp_unslash( $fp_raw ) : ( $submit_values['__file_paths'] ?? '{}' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$file_paths = json_decode( $fp_json, true ) ?: [];
	}

	// ディレクトリトラバーサル対策: WPアップロードディレクトリ内のパスのみ許可する。
	$upload_base = wp_upload_dir()['basedir'];
	$file_paths  = array_filter(
		array_map( 'sanitize_text_field', (array) $file_paths ),
		function( $path ) use ( $upload_base ) {
			if ( ! is_string( $path ) || '' === $path ) {
				return false;
			}
			$real = realpath( $path );
			return $real && 0 === strpos( $real, $upload_base . DIRECTORY_SEPARATOR );
		}
	);

	$file_names = json_decode( $submit_values['__file_names'] ?? '{}', true ) ?: [];
	unset( $submit_values['__file_paths'], $submit_values['__file_names'] );

	hxfe_send_emails( $schema, $submit_values, $file_paths );

	/** This action is documented in includes/ajax-handlers.php */
	do_action( 'hxfe_after_submit', $schema['id'] ?? '', $submit_values, $schema );

	// メール送信後にアップロードファイルを削除
	hxfe_cleanup_uploaded_files( array_values( $file_paths ) );

	echo hxfe_render_complete( $schema, $submit_values ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	wp_die();
}

/* ---------------------------------------------------------------------------
 * Handler: back (confirm → re-render input with previous values)
 * ------------------------------------------------------------------------- */

function hxfe_handle_back() {
	// form_id を先に取得してnonce検証に使う。
	$form_id = isset( $_POST['hxfe_form_id'] ) ? sanitize_key( wp_unslash( $_POST['hxfe_form_id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

	// nonce 検証: validate アクションの nonce を再利用する。
	$nonce = isset( $_POST['hxfe_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['hxfe_nonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( ! wp_verify_nonce( $nonce, 'hxfe_validate_' . $form_id ) ) {
		status_header( 403 );
		wp_die( '', '', 403 );
	}

	$schema = hxfe_get_schema( $form_id );
	if ( null === $schema ) {
		status_header( 400 );
		wp_die( '', '', 400 );
	}

	// hxfe_values は JSON 文字列で送られる。json_decode 後に hxfe_process_fields で各値をサニタイズする。
	$raw_json = isset( $_POST['hxfe_values'] ) ? wp_unslash( $_POST['hxfe_values'] ) : '{}'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$values   = json_decode( $raw_json, true );
	if ( ! is_array( $values ) ) {
		$values = [];
	}

	// json_decode 後の各値を hxfe_process_fields でサニタイズしてからフォームを再レンダリングする。
	$result = hxfe_process_fields( $schema, $values );

	echo hxfe_render_input( $schema, [], $result['values'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	wp_die();
}

/* ---------------------------------------------------------------------------
 * ステップフォーム用エンドポイント
 * ------------------------------------------------------------------------- */

add_action( 'wp_ajax_hxfe_step_next',        'hxfe_handle_step_next' );
add_action( 'wp_ajax_nopriv_hxfe_step_next', 'hxfe_handle_step_next' );

add_action( 'wp_ajax_hxfe_step_submit',        'hxfe_handle_step_submit' );
add_action( 'wp_ajax_nopriv_hxfe_step_submit', 'hxfe_handle_step_submit' );

add_action( 'wp_ajax_hxfe_step_back',        'hxfe_handle_step_back' );
add_action( 'wp_ajax_nopriv_hxfe_step_back', 'hxfe_handle_step_back' );

/**
 * 現在のステップをバリデーションして次のステップを返す。
 */
function hxfe_handle_step_next() {
	$req        = hxfe_validate_step_request( 'step_next', 0 );
	$schema     = $req['schema'];
	$step_index = $req['step_index'];

	$steps      = hxfe_resolve_steps( $schema );
	$step_index = hxfe_validate_step_index( $step_index, $steps );
	$step       = $steps[ $step_index ];

	// 前ステップまでの値をデコード
	$prev_json  = sanitize_text_field( wp_unslash( $_POST['hxfe_prev_values'] ?? '{}' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$prev       = json_decode( $prev_json, true );
	$prev       = is_array( $prev ) ? $prev : [];

	// 現在のステップのフィールドだけをバリデーション
	$step_schema = array_merge( $schema, [ 'fields' => $step['fields'] ] );
	$result      = hxfe_process_fields( $step_schema, $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified by hxfe_validate_step_request()
	$values      = array_merge( $prev, $result['values'] );
	$errors      = $result['errors'];

	// honeypot チェック
	if ( isset( $errors['__honeypot'] ) ) {
		echo hxfe_render_complete( $schema, $values ?? [] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		wp_die();
	}

	if ( ! empty( $errors ) ) {
		echo hxfe_render_step( $schema, $steps, $step_index, $errors, $values ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		wp_die();
	}

	// 次のステップへ
	$next_index = $step_index + 1;
	if ( $next_index >= count( $steps ) ) {
		// confirm: false の場合は確認画面をスキップして即送信
		if ( isset( $schema['confirm'] ) && false === $schema['confirm'] ) {
			hxfe_send_emails( $schema, $values );
			/** This action is documented in includes/ajax-handlers.php */
			do_action( 'hxfe_after_submit', $schema['id'] ?? '', $values, $schema );
			echo hxfe_render_complete( $schema, $values ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			wp_die();
		}
		// 確認画面へ
		echo hxfe_render_confirm( $schema, $values ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		wp_die();
	}

	echo hxfe_render_step( $schema, $steps, $next_index, [], $values ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	wp_die();
}

/**
 * 最終ステップの送信: バリデーション → メール送信 → 完了。
 */
function hxfe_handle_step_submit() {
	$req        = hxfe_validate_step_request( 'step_submit', 0 );
	$schema     = $req['schema'];
	$step_index = $req['step_index'];

	$steps      = hxfe_resolve_steps( $schema );
	$step_index = hxfe_validate_step_index( $step_index, $steps );
	$step       = $steps[ $step_index ];

	// 前ステップまでの値
	$prev_json = sanitize_text_field( wp_unslash( $_POST['hxfe_prev_values'] ?? '{}' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$prev      = json_decode( $prev_json, true );
	$prev      = is_array( $prev ) ? $prev : [];

	// 最終ステップのバリデーション
	$step_schema = array_merge( $schema, [ 'fields' => $step['fields'] ] );
	$result      = hxfe_process_fields( $step_schema, $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified by hxfe_validate_step_request()
	$values      = array_merge( $prev, $result['values'] );
	$errors      = $result['errors'];

	if ( isset( $errors['__honeypot'] ) ) {
		echo hxfe_render_complete( $schema, $values ?? [] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		wp_die();
	}

	if ( ! empty( $errors ) ) {
		echo hxfe_render_step( $schema, $steps, $step_index, $errors, $values ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		wp_die();
	}

	// json_decode 後の値を hxfe_process_fields() でサニタイズ＋バリデーションする。
	$full_result = hxfe_process_fields( $schema, $values );
	if ( ! empty( $full_result['errors'] ) ) {
		// 最初のエラーがあるステップに戻す
		$err_step = hxfe_find_error_step( $steps, $full_result['errors'] );
		echo hxfe_render_step( $schema, $steps, $err_step, $full_result['errors'], $values ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		wp_die();
	}

	hxfe_send_emails( $schema, $values );
	/** This action is documented in includes/ajax-handlers.php */
	do_action( 'hxfe_after_submit', $schema['id'] ?? '', $values, $schema );
	echo hxfe_render_complete( $schema, $values ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	wp_die();
}

/**
 * 前のステップに戻る。
 */
function hxfe_handle_step_back() {
	$req        = hxfe_validate_step_request( 'step_back', 1 );
	$schema     = $req['schema'];
	$step_index = $req['step_index'];

	$steps      = hxfe_resolve_steps( $schema );
	$prev_index = max( 0, $step_index - 1 );

	$prev_json = sanitize_text_field( wp_unslash( $_POST['hxfe_prev_values'] ?? '{}' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$values    = json_decode( $prev_json, true );
	$values    = is_array( $values ) ? $values : [];

	echo hxfe_render_step( $schema, $steps, $prev_index, [], $values ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	wp_die();
}

/**
 * エラーが含まれる最初のステップインデックスを返す。
 *
 * @param array[] $steps  解決済みのステップ配列。
 * @param array   $errors フィールドキー => エラーメッセージ。
 * @return int
 */
function hxfe_find_error_step( array $steps, array $errors ) {
	foreach ( $steps as $i => $step ) {
		foreach ( $step['fields'] as $field ) {
			if ( isset( $errors[ $field['key'] ] ) ) {
				return $i;
			}
		}
	}
	return 0;
}
