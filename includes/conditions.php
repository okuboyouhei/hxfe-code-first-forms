<?php
/**
 * Conditions engine — evaluates show_if / required_if / skip_if / to_rules.
 *
 * 全ての条件は同じ評価関数 hxfe_eval_condition() を通る。
 *
 * 条件の記法:
 *   シンプル (1条件):
 *     'show_if' => [ 'field_key', 'operator', 'value' ]
 *
 *   AND (全て満たす):
 *     'show_if' => [ 'and', [
 *         [ 'type', '==', 'corporate' ],
 *         [ 'plan', '!=', 'free' ],
 *     ]]
 *
 *   OR (いずれか満たす):
 *     'show_if' => [ 'or', [
 *         [ 'type', '==', 'corporate' ],
 *         [ 'type', '==', 'npo' ],
 *     ]]
 *
 * 演算子一覧:
 *   ==           完全一致
 *   !=           不一致
 *   >  >=        数値比較
 *   <  <=        数値比較
 *   contains     部分一致 (文字列)
 *   not_contains 部分不一致
 *   in           配列に含まれる ('value' はカンマ区切り文字列 or 配列)
 *   not_in       配列に含まれない
 *   empty        値が空
 *   not_empty    値が空でない
 *
 * @package HtmxFormEngine
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ---------------------------------------------------------------------------
 * 条件評価
 * ------------------------------------------------------------------------- */

/**
 * 条件を評価して true/false を返す。
 *
 * @param array|null $condition show_if / required_if / skip_if の値。
 * @param array      $values    全フィールドの入力値 (key => value)。
 * @return bool
 */
/**
 * 条件式を評価して true/false を返す。
 *
 * 記法:
 *   単純条件: [ 'field_key', 'operator', 'value' ]
 *   AND条件:  [ 'and', [ [...], [...] ] ]
 *   OR条件:   [ 'or',  [ [...], [...] ] ]
 *   空条件:   [] または null → 常に true
 *
 * 演算子: == / != / > / >= / < / <= /
 *         contains / not_contains / in / not_in / empty / not_empty
 *
 * @param mixed $condition 条件式（配列またはnull）。
 * @param array $values    フォームの入力値 [ field_key => value ]。
 * @return bool
 */
function hxfe_eval_condition( $condition, array $values ) {
	if ( empty( $condition ) ) {
		return true; // 条件なし → 常に true
	}

	// AND / OR のグループ条件
	if ( is_array( $condition ) && isset( $condition[0] ) ) {
		if ( 'and' === $condition[0] && is_array( $condition[1] ) ) {
			foreach ( $condition[1] as $sub ) {
				if ( ! hxfe_eval_single_condition( $sub, $values ) ) {
					return false;
				}
			}
			return true;
		}

		if ( 'or' === $condition[0] && is_array( $condition[1] ) ) {
			foreach ( $condition[1] as $sub ) {
				if ( hxfe_eval_single_condition( $sub, $values ) ) {
					return true;
				}
			}
			return false;
		}
	}

	// シンプルな1条件
	return hxfe_eval_single_condition( $condition, $values );
}

/**
 * 単一条件 [ field_key, operator, value ] を評価する。
 *
 * @param array $condition
 * @param array $values
 * @return bool
 */
function hxfe_eval_single_condition( array $condition, array $values ) {
	if ( count( $condition ) < 2 ) {
		return true;
	}

	$field_key = $condition[0];
	$operator  = $condition[1];
	$expected  = $condition[2] ?? '';

	// 入力値を取得（未入力なら空文字）
	$actual = (string) ( $values[ $field_key ] ?? '' );

	switch ( $operator ) {
		case '==':
			return $actual === (string) $expected;
		case '!=':
			return $actual !== (string) $expected;
		case '>':
			return is_numeric( $actual ) && is_numeric( $expected )
				&& (float) $actual > (float) $expected;
		case '>=':
			return is_numeric( $actual ) && is_numeric( $expected )
				&& (float) $actual >= (float) $expected;
		case '<':
			return is_numeric( $actual ) && is_numeric( $expected )
				&& (float) $actual < (float) $expected;
		case '<=':
			return is_numeric( $actual ) && is_numeric( $expected )
				&& (float) $actual <= (float) $expected;
		case 'contains':
			return false !== mb_strpos( $actual, (string) $expected );
		case 'not_contains':
			return false === mb_strpos( $actual, (string) $expected );
		case 'in':
			$list = is_array( $expected )
				? array_map( 'strval', $expected )
				: array_filter( array_map( 'trim', explode( ',', (string) $expected ) ), 'strlen' );
			return in_array( $actual, $list, true );
		case 'not_in':
			$list = is_array( $expected )
				? array_map( 'strval', $expected )
				: array_filter( array_map( 'trim', explode( ',', (string) $expected ) ), 'strlen' );
			return ! in_array( $actual, $list, true );
		case 'empty':
			return '' === $actual;
		case 'not_empty':
			return '' !== $actual;
		default:
			return true;
	}
}

/* ---------------------------------------------------------------------------
 * フィールドの表示判定
 * ------------------------------------------------------------------------- */

/**
 * フィールドが現在の値で表示されるべきかを返す。
 *
 * show_if が true → 表示
 * hide_if は v1.3.5 で廃止。後方互換のため show_if の否定として処理する。
 *
 * @param array $field  フィールド定義。
 * @param array $values 入力値。
 * @return bool
 */
function hxfe_field_is_visible( array $field, array $values ) {
	$visible = true;

	// show_if: この条件を満たすときのみ表示（満たさなければ非表示）
	if ( isset( $field['show_if'] ) ) {
		$visible = $visible && hxfe_eval_condition( $field['show_if'], $values );
	}

	// hide_if: v1.3.5 で廃止。後方互換のため show_if の否定として処理する。
	// 新規スキーマでは show_if を使用すること。
	if ( isset( $field['hide_if'] ) ) {
		$visible = $visible && ! hxfe_eval_condition( $field['hide_if'], $values );
	}

	return $visible;
}

/**
 * フィールドが必須かを返す。
 * required_if が定義されていればその条件を評価する。
 *
 * @param array $field
 * @param array $values
 * @return bool
 */
function hxfe_field_is_required( array $field, array $values ) {
	// required_if: 条件を満たすときに必須
	if ( isset( $field['required_if'] ) ) {
		return hxfe_eval_condition( $field['required_if'], $values );
	}
	return ! empty( $field['required'] );
}

/* ---------------------------------------------------------------------------
 * ステップのスキップ判定
 * ------------------------------------------------------------------------- */

/**
 * ステップをスキップすべきかを返す。
 *
 * @param array $step   ステップ定義。
 * @param array $values 入力値。
 * @return bool
 */
function hxfe_step_should_skip( array $step, array $values ) {
	if ( isset( $step['skip_if'] ) ) {
		return hxfe_eval_condition( $step['skip_if'], $values );
	}
	if ( isset( $step['show_if'] ) ) {
		return ! hxfe_eval_condition( $step['show_if'], $values );
	}
	return false;
}

/**
 * 全ステップのうちスキップしないものだけを返す。
 *
 * @param array[] $steps  全ステップ。
 * @param array   $values 入力値。
 * @return array[]
 */
function hxfe_filter_visible_steps( array $steps, array $values ) {
	return array_values(
		array_filter( $steps, function( $step ) use ( $values ) {
			return ! hxfe_step_should_skip( $step, $values );
		} )
	);
}

/* ---------------------------------------------------------------------------
 * 送信先の切り替え
 * ------------------------------------------------------------------------- */

/**
 * to_rules を評価して送信先メールアドレスを返す。
 *
 * to_rules が未定義または全てマッチしない場合は $schema['to'] を使う。
 *
 * to_rules の記法:
 *   'to_rules' => [
 *       [ 'when' => [ 'category', '==', 'sales' ],   'to' => 'sales@example.com' ],
 *       [ 'when' => [ 'category', '==', 'support' ], 'to' => 'support@example.com' ],
 *       [ 'when' => 'default',                        'to' => 'info@example.com' ],
 *   ],
 *
 * @param array $schema フォームスキーマ。
 * @param array $values 入力値。
 * @return string メールアドレス。
 */
function hxfe_resolve_to( array $schema, array $values ) {
	$rules   = $schema['to_rules'] ?? [];
	$default = $schema['to'] ?? '';

	foreach ( $rules as $rule ) {
		$when = $rule['when'] ?? null;
		$to   = $rule['to']   ?? '';

		// 'default' キーワードは必ずマッチ
		if ( 'default' === $when ) {
			return sanitize_email( $to );
		}

		if ( $when && hxfe_eval_condition( $when, $values ) ) {
			return sanitize_email( $to );
		}
	}

	return sanitize_email( $default );
}

/**
 * subject_rules を評価して件名を返す。
 *
 * subject_rules の記法:
 *   'subject_rules' => [
 *       [ 'when' => [ 'plan', '==', 'enterprise' ], 'subject' => '【重要】{name}様からのお問い合わせ' ],
 *       [ 'when' => 'default',                       'subject' => 'お問い合わせ: {name}' ],
 *   ],
 *
 * @param array $schema
 * @param array $values
 * @return string
 */
function hxfe_resolve_subject( array $schema, array $values ) {
	$rules   = $schema['subject_rules'] ?? [];
	$default = $schema['subject'] ?? __( 'New form submission', 'hxfe-code-first-forms' );

	foreach ( $rules as $rule ) {
		$when    = $rule['when']    ?? null;
		$subject = $rule['subject'] ?? '';

		if ( 'default' === $when ) {
			$resolved = hxfe_interpolate( $subject, $values );
			return hxfe_append_context( $resolved, $schema );
		}
		if ( $when && hxfe_eval_condition( $when, $values ) ) {
			$resolved = hxfe_interpolate( $subject, $values );
			return hxfe_append_context( $resolved, $schema );
		}
	}

	$resolved = hxfe_interpolate( $default, $values );
	return hxfe_append_context( $resolved, $schema );
}

/**
 * ページスラッグ（_context）がある場合、subject に "@slug" を付与する。
 * 例: "お問い合わせ: 大久保" → "お問い合わせ: 大久保 [survey@seminar-2026-06]"
 */
function hxfe_append_context( string $subject, array $schema ) : string {
	$context = $schema['_context'] ?? '';
	if ( '' === $context ) {
		return $subject;
	}
	$form_id = $schema['id'] ?? '';
	return $subject . ' [' . $form_id . '@' . $context . ']';
}

// hxfe_interpolate() は schema.php で定義済み
