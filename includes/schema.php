<?php
/**
 * Schema registration and retrieval.
 *
 * Developers register form schemas via the 'hxfe_schemas' filter:
 *
 *   add_filter( 'hxfe_schemas', function( $schemas ) {
 *       $schemas['contact'] = [ ... ];
 *       return $schemas;
 *   });
 *
 * A schema array must contain:
 *   id      (string)  Unique identifier matching the array key.
 *   to      (string)  Recipient email address.
 *   subject (string)  Email subject. Supports {field_key} placeholders.
 *   fields  (array)   Ordered list of field definitions.
 *
 * Optional schema keys:
 *   reply_to_field  (string)  Field key whose value becomes the Reply-To header.
 *   complete_message (string) HTML shown after successful submission.
 *
 * Each field definition array:
 *   key       (string)  Unique field identifier. Used as HTML name and POST key.
 *   type      (string)  text | email | textarea | select | checkbox | honeypot.
 *   label     (string)  Human-readable label.
 *   required  (bool)    Whether the field must be non-empty.
 *   maxlength (int)     Optional character limit (text/textarea).
 *   options   (array)   Required for select: [ ['value'=>'v','label'=>'l'], ... ].
 *   placeholder (string) Optional placeholder text.
 *
 * @package HtmxFormEngine
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Returns all registered schemas.
 *
 * @return array<string, array> Keyed by schema id.
 */
function hxfe_get_all_schemas() {
	static $cache = null;
	if ( null === $cache ) {
		$cache = (array) apply_filters( 'hxfe_schemas', [] );
	}
	return $cache;
}

/**
 * Returns a single schema by id, or null if not found.
 *
 * @param string $id Schema id.
 * @return array|null
 */
function hxfe_get_schema( $id ) {
	$schemas = hxfe_get_all_schemas();
	return $schemas[ $id ] ?? null;
}

/**
 * Validates a schema definition and returns an array of problems.
 *
 * 開発時・デバッグ時に使用。有効化時に自動的に実行される。
 * 本番環境では notices/warnings として出力される。
 *
 * @param array $schema Schema to check.
 * @return string[] List of problem descriptions (empty = valid).
 */
function hxfe_lint_schema( array $schema ) {
	$problems = [];

	// ── 必須キー ────────────────────────────────────────────────────────────
	if ( empty( $schema['id'] ) ) {
		$problems[] = 'Missing: id';
	}

	// complete_html_rules のみ使用（メール送信なし）の場合は to/subject 不要
	$no_email_mode = ! empty( $schema['complete_html_rules'] ) && empty( $schema['to'] ) && empty( $schema['to_rules'] );

	// to または to_rules のどちらかが必要（メール送信なしモードを除く）
	$has_to       = ! empty( $schema['to'] );
	$has_to_rules = ! empty( $schema['to_rules'] ) && is_array( $schema['to_rules'] );
	if ( ! $no_email_mode && ! $has_to && ! $has_to_rules ) {
		$problems[] = "Missing: 'to' (email address) or 'to_rules'";
	}
	if ( $has_to && ! is_array( $schema['to'] ) && ! is_email( $schema['to'] ) ) {
		$problems[] = "Invalid email in 'to': " . $schema['to'];
	}
	if ( $has_to && is_array( $schema['to'] ) ) {
		foreach ( $schema['to'] as $addr ) {
			if ( ! is_email( $addr ) ) {
				$problems[] = "Invalid email in 'to' array: {$addr}";
			}
		}
	}

	// subject は to_rules 使用時またはメール送信なしモードは省略可
	if ( ! $no_email_mode && empty( $schema['subject'] ) && empty( $schema['subject_rules'] ) && empty( $schema['to_rules'] ) ) {
		$problems[] = "Missing: 'subject'";
	}

	if ( empty( $schema['fields'] ) || ! is_array( $schema['fields'] ) ) {
		$problems[] = "Missing or invalid: 'fields'";
		return $problems;
	}

	// ── フィールドバリデーション ─────────────────────────────────────────────
	$valid_types = [
		'text', 'email', 'tel', 'url', 'textarea', 'select', 'checkbox',
		'radio', 'checkbox_group', 'number', 'date', 'file',
		'honeypot', 'recaptcha', 'privacy',
	];
	$keys_seen   = [];
	$has_honeypot = false;

	foreach ( $schema['fields'] as $i => $field ) {
		$prefix = "fields[{$i}]";
		if ( empty( $field['key'] ) ) {
			$problems[] = "{$prefix}: Missing 'key'";
			continue;
		}
		$key = $field['key'];

		if ( isset( $keys_seen[ $key ] ) ) {
			$problems[] = "{$prefix}: Duplicate key '{$key}'";
		}
		$keys_seen[ $key ] = true;

		$type = $field['type'] ?? 'text';
		if ( ! in_array( $type, $valid_types, true ) ) {
			$problems[] = "{$prefix} ({$key}): Unknown type '{$type}'";
		}

		if ( 'honeypot' === $type ) {
			$has_honeypot = true;
		}

		if ( in_array( $type, [ 'select', 'radio', 'checkbox_group' ], true ) && empty( $field['options'] ) ) {
			$problems[] = "{$prefix} ({$key}): '{$type}' field needs 'options' array";
		}

		$label_optional = [ 'honeypot', 'recaptcha' ];
		if ( ! in_array( $type, $label_optional, true ) && empty( $field['label'] ) ) {
			$problems[] = "{$prefix} ({$key}): Missing 'label'";
		}

		// cascade_from が指定されているが cascade_options がない
		if ( ! empty( $field['cascade_from'] ) && empty( $field['cascade_options'] ) ) {
			$problems[] = "{$prefix} ({$key}): 'cascade_from' requires 'cascade_options'";
		}

		// chatbotモードで bot_message がない
		$step_mode = $schema['step_mode'] ?? '';
		if ( 'chatbot' === $step_mode && ! in_array( $type, [ 'honeypot', 'recaptcha' ], true ) && empty( $field['bot_message'] ) ) {
			$problems[] = "{$prefix} ({$key}): chatbot mode requires 'bot_message'";
		}
	}

	if ( ! $has_honeypot ) {
		$problems[] = "Recommended: add a honeypot field for spam protection ( [ 'key' => 'hp', 'type' => 'honeypot' ] )";
	}

	// ── ステップバリデーション ────────────────────────────────────────────────
	if ( ! empty( $schema['steps'] ) && is_array( $schema['steps'] ) ) {
		$all_field_keys = array_column( $schema['fields'], 'key' );
		foreach ( $schema['steps'] as $si => $step ) {
			if ( empty( $step['fields'] ) ) {
				$problems[] = "steps[{$si}]: Missing 'fields'";
				continue;
			}
			foreach ( $step['fields'] as $sk ) {
				if ( ! in_array( $sk, $all_field_keys, true ) ) {
					$problems[] = "steps[{$si}]: Unknown field key '{$sk}'";
				}
			}
		}
	}

	return $problems;
}

/**
 * 全スキーマを lint して問題をWP_DEBUG_LOGに記録する。
 * add_action( 'init', 'hxfe_lint_all_schemas' ) で呼び出す。
 */
function hxfe_lint_all_schemas() {
	if ( ! ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) { return; }

	foreach ( hxfe_get_all_schemas() as $id => $schema ) {
		$problems = hxfe_lint_schema( $schema );
		foreach ( $problems as $problem ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error, WordPress.Security.EscapeOutput.OutputNotEscaped -- trigger_error() is not HTML output
			trigger_error( "[HXFE] Schema '{$id}': {$problem}", E_USER_NOTICE );
		}
	}
}
add_action( 'init', 'hxfe_lint_all_schemas', 999 );

/**
 * Interpolates {field_key} placeholders in a string with submitted values.
 *
 * @param string $template Template string.
 * @param array  $values   Validated field values keyed by field key.
 * @return string Interpolated string.
 */
/**
 * テンプレート文字列のプレースホルダーを値に置換する。
 *
 * 組み込みプレースホルダー（values になくても使える）:
 *   {site_name} → get_bloginfo('name')
 *   {site_url}  → home_url()
 *   {date}      → current_time('Y-m-d')
 *   {time}      → current_time('H:i')
 *
 * フィールドプレースホルダー:
 *   {field_key} → $values['field_key'] の値
 *
 * @param string $template テンプレート文字列。
 * @param array  $values   フォームの入力値 [ field_key => value ]。
 * @return string 置換後の文字列。
 */
function hxfe_interpolate( $template, array $values ) {
	// 組み込みプレースホルダー
	$builtin = [
		'{site_name}' => get_bloginfo( 'name' ),
		'{site_url}'  => home_url(),
		'{date}'      => current_time( 'Y-m-d' ),
		'{time}'      => current_time( 'H:i' ),
	];
	$template = str_replace( array_keys( $builtin ), array_values( $builtin ), $template );

	// フィールド値のプレースホルダー
	foreach ( $values as $key => $value ) {
		$template = str_replace( '{' . $key . '}', (string) $value, $template );
	}

	return $template;
}
