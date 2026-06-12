<?php
/**
 * Field-level sanitization and validation — pure functions only.
 *
 * Every function takes raw input and returns a result array:
 *   [ 'value' => mixed, 'error' => string ]
 * An empty 'error' string means success.
 *
 * @package HtmxFormEngine
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Dispatches validation to the correct handler by field type.
 *
 * @param array  $field Field schema definition.
 * @param mixed  $raw   Raw value from $_POST.
 * @return array{value: mixed, error: string}
 */
function hxfe_validate_field( array $field, $raw ) {
	$type = $field['type'] ?? 'text';

	switch ( $type ) {
		case 'email':
			$result = hxfe_validate_email( $raw, ! empty( $field['required'] ) );
			break;
		case 'textarea':
			$result = hxfe_validate_textarea( $raw, ! empty( $field['required'] ), (int) ( $field['maxlength'] ?? 2000 ) );
			break;
		case 'select':
			$result = hxfe_validate_select( $raw, $field['options'] ?? [], ! empty( $field['required'] ) );
			break;
		case 'checkbox':
			$result = hxfe_validate_checkbox( $raw, ! empty( $field['required'] ) );
			break;
		case 'honeypot':
			return hxfe_validate_honeypot( $raw );
		case 'recaptcha':
			// reCAPTCHA の検証は ajax-handlers.php で行う。
			// sanitizers では値をそのまま通す。
			return [ 'value' => sanitize_text_field( wp_unslash( (string) $raw ) ), 'error' => '' ];
		case 'privacy':
			$result = hxfe_validate_checkbox( $raw, ! empty( $field['required'] ) );
			break;
		case 'radio':
			$result = hxfe_validate_radio( $field, $raw );
			break;
		case 'checkbox_group':
			$result = hxfe_validate_checkbox_group( $field, $raw );
			break;
		case 'number':
			$result = hxfe_validate_number( $field, $raw );
			break;
		case 'date':
			$result = hxfe_validate_date( $field, $raw );
			break;
		case 'tel':
			$result = hxfe_validate_tel( $field, $raw );
			break;
		case 'url':
			$result = hxfe_validate_url_field( $field, $raw );
			break;
		case 'file':
			return [ 'value' => '', 'error' => '' ]; // ファイルは ajax-handlers で別途処理
		default: // text など
			$result = hxfe_validate_text( $raw, ! empty( $field['required'] ), (int) ( $field['maxlength'] ?? 500 ) );
			break;
	}

	// ── A: スキーマキーによる追加バリデーション ──────────────────────────

	// エラーが既にある場合はスキップ
	if ( '' === $result['error'] && '' !== $result['value'] ) {

		$value         = $result['value'];
		$error_message = $field['error_message'] ?? '';

		// minlength
		if ( isset( $field['minlength'] ) && mb_strlen( $value ) < (int) $field['minlength'] ) {
			$result['error'] = $error_message ?: sprintf(
				/* translators: %d: minimum character count */
				_n( 'Please enter at least %d character.', 'Please enter at least %d characters.', (int) $field['minlength'], 'hxfe-code-first-forms' ),
				(int) $field['minlength']
			);
		}

		// maxlength（textareaはhxfe_validate_textareaで処理済みだがtextは再チェック）
		if ( '' === $result['error'] && isset( $field['maxlength'] ) && mb_strlen( $value ) > (int) $field['maxlength'] ) {
			$result['error'] = $error_message ?: sprintf(
				/* translators: %d: maximum character count */
				_n( 'Please enter no more than %d character.', 'Please enter no more than %d characters.', (int) $field['maxlength'], 'hxfe-code-first-forms' ),
				(int) $field['maxlength']
			);
		}

		// pattern（正規表現）
		if ( '' === $result['error'] && ! empty( $field['pattern'] ) ) {
			$pattern = $field['pattern'];
			// デリミタを自動付与（ユーザーがデリミタなしで書けるように）
			if ( @preg_match( '/' . $pattern . '/', '' ) === false ) {
				// 不正な正規表現は無視
			} elseif ( ! preg_match( '/' . $pattern . '/', $value ) ) {
				$result['error'] = $error_message ?: __( 'The format is invalid.', 'hxfe-code-first-forms' );
			}
		}
	}

	// ── B: フィルターフックによるカスタムバリデーション ────────────────────
	// 使用例:
	//   add_filter( 'hxfe_validate_field', function( $result, $field, $raw ) {
	//       if ( $field['key'] === 'zip' && ! preg_match( '/^\d{3}-?\d{4}$/', $result['value'] ) ) {
	//           return [ 'value' => $result['value'], 'error' => '郵便番号の形式が正しくありません。' ];
	//       }
	//       return $result;
	//   }, 10, 3 );
	$result = apply_filters( 'hxfe_validate_field', $result, $field, $raw );

	return $result;
}

/**
 * Sanitizes a plain text field.
 */
function hxfe_validate_text( $raw, bool $required, int $maxlength = 500 ) {
	$value = sanitize_text_field( wp_unslash( (string) $raw ) );

	if ( $required && '' === $value ) {
		return [ 'value' => '', 'error' => __( 'This field is required.', 'hxfe-code-first-forms' ) ];
	}
	if ( mb_strlen( $value ) > $maxlength ) {
		// translators: %d: max character count
		$err = sprintf( __( 'Please keep this under %d characters.', 'hxfe-code-first-forms' ), $maxlength );
		return [ 'value' => mb_substr( $value, 0, $maxlength ), 'error' => $err ];
	}
	return [ 'value' => $value, 'error' => '' ];
}

/**
 * Validates and sanitizes an email address.
 */
function hxfe_validate_email( $raw, bool $required ) {
	$raw_value = wp_unslash( (string) $raw );

	if ( $required && '' === trim( $raw_value ) ) {
		return [ 'value' => '', 'error' => __( 'This field is required.', 'hxfe-code-first-forms' ) ];
	}
	if ( '' !== trim( $raw_value ) && ! is_email( $raw_value ) ) {
		return [ 'value' => '', 'error' => __( 'Please enter a valid email address.', 'hxfe-code-first-forms' ) ];
	}

	$value = sanitize_email( $raw_value );
	return [ 'value' => $value, 'error' => '' ];
}

/**
 * Sanitizes a textarea field.
 */
function hxfe_validate_textarea( $raw, bool $required, int $maxlength = 2000 ) {
	$value = sanitize_textarea_field( wp_unslash( (string) $raw ) );

	if ( $required && '' === $value ) {
		return [ 'value' => '', 'error' => __( 'This field is required.', 'hxfe-code-first-forms' ) ];
	}
	if ( mb_strlen( $value ) > $maxlength ) {
		// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment -- translators comment on next line
		// translators: %d: maximum character count
		$err_msg = sprintf( __( 'Please keep this under %d characters.', 'hxfe-code-first-forms' ), $maxlength );
		return [ 'value' => mb_substr( $value, 0, $maxlength ), 'error' => $err_msg ];
	}
	return [ 'value' => $value, 'error' => '' ];
}

/**
 * Validates a select field against the allowed options whitelist.
 */
function hxfe_validate_select( $raw, array $options, bool $required ) {
	$value = sanitize_text_field( wp_unslash( (string) $raw ) );

	if ( $required && '' === $value ) {
		return [ 'value' => '', 'error' => __( 'Please select an option.', 'hxfe-code-first-forms' ) ];
	}
	// Whitelist: value must exist in the schema options keys.
	$keys = array_column( $options, 'value' );
	if ( '' !== $value && ! in_array( $value, $keys, true ) ) {
		return [ 'value' => '', 'error' => __( 'Invalid selection.', 'hxfe-code-first-forms' ) ];
	}
	return [ 'value' => $value, 'error' => '' ];
}

/**
 * Validates a single checkbox (agree/disagree).
 */
function hxfe_validate_checkbox( $raw, bool $required ) {
	$checked = ( '1' === (string) $raw || 'on' === (string) $raw );

	if ( $required && ! $checked ) {
		return [ 'value' => false, 'error' => __( 'This field is required.', 'hxfe-code-first-forms' ) ];
	}
	return [ 'value' => $checked, 'error' => '' ];
}

/**
 * Honeypot validation — must be empty to pass.
 */
function hxfe_validate_honeypot( $raw ) {
	if ( '' !== sanitize_text_field( wp_unslash( (string) $raw ) ) ) {
		// Silently reject; don't reveal why.
		return [ 'value' => '', 'error' => 'SPAM' ];
	}
	return [ 'value' => '', 'error' => '' ];
}

/**
 * Processes all fields in a schema against $_POST data.
 *
 * @param array $schema  Form schema definition.
 * @param array $post    Typically $_POST.
 * @return array{values: array, errors: array} Clean values and per-field errors.
 */
/**
 * 全フィールドをサニタイズ・バリデーションして values と errors を返す。
 *
 * @param array $schema         フォームスキーマ。
 * @param array $post           $_POST または json_decode 後の配列。
 * @return array{values: array<string,string>, errors: array<string,string>}
 */
function hxfe_process_fields( array $schema, array $post ) {
	$values = [];
	$errors = [];

	// ── パス1: 値のサニタイズ（条件評価に必要なため先に全フィールドを処理）──
	// sanitize_text_field() 等はべき等なので、confirm 画面経由の値を再サニタイズしても値は変わらない。
	foreach ( $schema['fields'] as $field ) {
		$key            = $field['key'];
		$raw            = $post[ $key ] ?? '';
		$result         = hxfe_validate_field( $field, $raw );
		$values[ $key ] = $result['value'];
	}

	// ── パス2: 条件を評価してバリデーション ──
	foreach ( $schema['fields'] as $field ) {
		$key  = $field['key'];
		$type = $field['type'] ?? 'text';

		// ハニーポット
		if ( 'honeypot' === $type ) {
			if ( '' !== $values[ $key ] ) {
				$errors['__honeypot'] = 'SPAM';
			}
			continue;
		}

		// 非表示フィールドはバリデーションをスキップ・値もクリア
		if ( ! hxfe_field_is_visible( $field, $values ) ) {
			$values[ $key ] = ''; // 非表示フィールドの値は送信しない
			continue;
		}

		// required_if を考慮して再バリデーション
		$required = hxfe_field_is_required( $field, $values );
		$field_with_resolved_required = array_merge( $field, [ 'required' => $required ] );

		// email フィールドはパス1でsanitize_email()が無効値を空文字にするため、
		// パス2では生の値（$post）で再バリデーションする
		$validate_raw = ( 'email' === $type ) ? ( $post[ $key ] ?? '' ) : $values[ $key ];
		$result = hxfe_validate_field( $field_with_resolved_required, $validate_raw );

		if ( '' !== $result['error'] ) {
			$errors[ $key ] = $result['error'];
		}
	}

	// ── パス3: フォーム全体バリデーション（フィルターフック）──
	// 複数フィールドをまたいだ検証に使用する。
	// 使用例:
	//   add_filter( 'hxfe_validate_form', function( $errors, $values, $schema ) {
	//       if ( $values['password'] !== $values['password_confirm'] ) {
	//           $errors['password_confirm'] = 'パスワードが一致しません。';
	//       }
	//       return $errors;
	//   }, 10, 3 );
	$errors = apply_filters( 'hxfe_validate_form', $errors, $values, $schema );

	return compact( 'values', 'errors' );
}

/* ---------------------------------------------------------------------------
 * 新フィールドタイプのバリデーション関数
 * ------------------------------------------------------------------------- */

/**
 * radio フィールドのバリデーション。
 * 選択肢に存在する値かどうかをチェックする。
 */
function hxfe_validate_radio( array $field, $raw ) {
	$value = sanitize_text_field( wp_unslash( (string) $raw ) );

	// 必須チェック
	if ( ! empty( $field['required'] ) && '' === $value ) {
		return [
			'value' => '',
			'error' => __( 'Please select an option.', 'hxfe-code-first-forms' ),
		];
	}

	// 選択肢に存在するか確認
	if ( '' !== $value && ! empty( $field['options'] ) ) {
		$allowed = array_column( $field['options'], 'value' );
		if ( ! in_array( $value, $allowed, true ) ) {
			return [ 'value' => '', 'error' => __( 'Invalid selection.', 'hxfe-code-first-forms' ) ];
		}
	}

	return [ 'value' => $value, 'error' => '' ];
}

/**
 * checkbox_group フィールドのバリデーション。
 * 複数選択を配列で受け取り、カンマ区切り文字列で返す。
 */
function hxfe_validate_checkbox_group( array $field, $raw ) {
	// $_POST から配列で受け取る
	$raw_arr = is_array( $raw ) ? $raw : ( '' !== (string) $raw ? explode( ',', (string) $raw ) : [] );

	// 許可された値のみフィルタリング
	$allowed = ! empty( $field['options'] ) ? array_column( $field['options'], 'value' ) : [];
	$values  = [];
	foreach ( $raw_arr as $v ) {
		$v = sanitize_text_field( wp_unslash( (string) $v ) );
		if ( '' !== $v && ( empty( $allowed ) || in_array( $v, $allowed, true ) ) ) {
			$values[] = $v;
		}
	}

	// 必須チェック
	if ( ! empty( $field['required'] ) && empty( $values ) ) {
		return [
			'value' => '',
			'error' => __( 'Please select at least one option.', 'hxfe-code-first-forms' ),
		];
	}

	// 最小・最大選択数チェック
	if ( ! empty( $field['min'] ) && count( $values ) < (int) $field['min'] ) {
		// translators: %d: minimum number of selections
		$err_msg = sprintf( __( 'Please select at least %d options.', 'hxfe-code-first-forms' ), (int) $field['min'] );
		return [ 'value' => implode( ',', $values ), 'error' => $err_msg ];
	}
	if ( ! empty( $field['max'] ) && count( $values ) > (int) $field['max'] ) {
		// translators: %d: maximum number of selections
		$err_msg = sprintf( __( 'Please select no more than %d options.', 'hxfe-code-first-forms' ), (int) $field['max'] );
		return [ 'value' => implode( ',', $values ), 'error' => $err_msg ];
	}

	return [ 'value' => implode( ',', $values ), 'error' => '' ];
}

/**
 * number フィールドのバリデーション。
 */
function hxfe_validate_number( array $field, $raw ) {
	$value = sanitize_text_field( wp_unslash( (string) $raw ) );

	if ( ! empty( $field['required'] ) && '' === $value ) {
		return [ 'value' => '', 'error' => __( 'This field is required.', 'hxfe-code-first-forms' ) ];
	}
	if ( '' === $value ) {
		return [ 'value' => '', 'error' => '' ];
	}
	if ( ! is_numeric( $value ) ) {
		return [ 'value' => $value, 'error' => __( 'Please enter a valid number.', 'hxfe-code-first-forms' ) ];
	}

	$num = (float) $value;

	if ( isset( $field['min'] ) && $num < (float) $field['min'] ) {
		// translators: %s: minimum value
		return [ 'value' => $value, 'error' => sprintf( __( 'Please enter a value of %s or more.', 'hxfe-code-first-forms' ), $field['min'] ) ];
	}
	if ( isset( $field['max'] ) && $num > (float) $field['max'] ) {
		// translators: %s: maximum value
		return [ 'value' => $value, 'error' => sprintf( __( 'Please enter a value of %s or less.', 'hxfe-code-first-forms' ), $field['max'] ) ];
	}

	return [ 'value' => $value, 'error' => '' ];
}

/**
 * date フィールドのバリデーション。
 */
function hxfe_validate_date( array $field, $raw ) {
	$value = sanitize_text_field( wp_unslash( (string) $raw ) );

	if ( ! empty( $field['required'] ) && '' === $value ) {
		return [ 'value' => '', 'error' => __( 'This field is required.', 'hxfe-code-first-forms' ) ];
	}
	if ( '' === $value ) {
		return [ 'value' => '', 'error' => '' ];
	}

	// Y-m-d 形式チェック
	$dt = DateTimeImmutable::createFromFormat( 'Y-m-d', $value );
	if ( ! $dt || $dt->format( 'Y-m-d' ) !== $value ) {
		return [ 'value' => $value, 'error' => __( 'Please enter a valid date (YYYY-MM-DD).', 'hxfe-code-first-forms' ) ];
	}

	// 最小・最大日付チェック
	if ( ! empty( $field['min_date'] ) ) {
		$min = DateTimeImmutable::createFromFormat( 'Y-m-d', $field['min_date'] );
		if ( $min && $dt < $min ) {
			// translators: %s: minimum date
			return [ 'value' => $value, 'error' => sprintf( __( 'Please select a date on or after %s.', 'hxfe-code-first-forms' ), $field['min_date'] ) ];
		}
	}
	if ( ! empty( $field['max_date'] ) ) {
		$max = DateTimeImmutable::createFromFormat( 'Y-m-d', $field['max_date'] );
		if ( $max && $dt > $max ) {
			// translators: %s: maximum date
			return [ 'value' => $value, 'error' => sprintf( __( 'Please select a date on or before %s.', 'hxfe-code-first-forms' ), $field['max_date'] ) ];
		}
	}

	return [ 'value' => $value, 'error' => '' ];
}


/**
 * tel フィールドのバリデーション。
 * 電話番号形式（数字・ハイフン・括弧・スペース・+）を許可する。
 */
function hxfe_validate_tel( array $field, $raw ) {
	$value = sanitize_text_field( wp_unslash( (string) $raw ) );

	if ( ! empty( $field['required'] ) && '' === $value ) {
		return [ 'value' => '', 'error' => __( 'This field is required.', 'hxfe-code-first-forms' ) ];
	}
	if ( '' === $value ) {
		return [ 'value' => '', 'error' => '' ];
	}

	// 電話番号として妥当な文字のみ許可
	if ( ! preg_match( '/^[\d\s\+\-\(\)\.]+$/', $value ) ) {
		return [ 'value' => $value, 'error' => __( 'Please enter a valid phone number.', 'hxfe-code-first-forms' ) ];
	}

	return [ 'value' => $value, 'error' => '' ];
}

/**
 * url フィールドのバリデーション。
 * http / https で始まる有効なURLを許可する。
 */
function hxfe_validate_url_field( array $field, $raw ) {
	$value = esc_url_raw( wp_unslash( (string) $raw ) );

	if ( ! empty( $field['required'] ) && '' === $value ) {
		return [ 'value' => '', 'error' => __( 'This field is required.', 'hxfe-code-first-forms' ) ];
	}
	if ( '' === $value ) {
		return [ 'value' => '', 'error' => '' ];
	}

	if ( ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
		return [ 'value' => $value, 'error' => __( 'Please enter a valid URL (e.g. https://example.com).', 'hxfe-code-first-forms' ) ];
	}

	return [ 'value' => $value, 'error' => '' ];
}
