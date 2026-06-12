<?php
/**
 * Step resolution — converts a schema into an ordered list of steps.
 *
 * 2つのステップモードをサポート:
 *
 *   1. グループ型 (steps キーで定義)
 *      複数のフィールドを1つのステップにまとめる。
 *      $schema['steps'] = [
 *          [ 'label' => '基本情報', 'fields' => ['name', 'email'] ],
 *          [ 'label' => '詳細',     'fields' => ['body', 'type'] ],
 *      ]
 *
 *   2. 1問1答型 (step_mode => 'one_by_one')
 *      フィールドを自動的に1つずつステップに分割する。
 *      $schema['step_mode'] = 'one_by_one'
 *
 *   3. 従来モード (steps / step_mode どちらも未指定)
 *      全フィールドを1画面に表示。後方互換。
 *
 * @package HtmxFormEngine
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * スキーマがステップモードかどうかを確認する。
 *
 * @param array $schema
 * @return bool
 */
function hxfe_is_step_mode( array $schema ) {
	return ! empty( $schema['steps'] ) || ( $schema['step_mode'] ?? '' ) === 'one_by_one';
}

/**
 * スキーマからステップ配列を解決して返す。
 *
 * 各ステップは以下の形式:
 *   [ 'label' => string, 'fields' => WP_Field[] ]
 *
 * @param array $schema フォームスキーマ。
 * @return array[] ステップの配列。
 */
function hxfe_resolve_steps( array $schema ) {
	$all_fields = $schema['fields'] ?? [];

	// フィールドをkeyで引けるようにインデックス化
	$fields_by_key = [];
	foreach ( $all_fields as $field ) {
		$fields_by_key[ $field['key'] ] = $field;
	}

	// ── グループ型 ────────────────────────────────────────────────
	if ( ! empty( $schema['steps'] ) ) {
		$steps = [];
		foreach ( $schema['steps'] as $step_def ) {
			$label  = $step_def['label'] ?? '';
			$f_keys = $step_def['fields'] ?? [];
			$fields = [];
			foreach ( $f_keys as $key ) {
				if ( isset( $fields_by_key[ $key ] ) ) {
					$fields[] = $fields_by_key[ $key ];
				}
			}
			if ( ! empty( $fields ) ) {
				$steps[] = [ 'label' => $label, 'fields' => $fields ];
			}
		}
		// honeypot は最終ステップに自動追加
		$hp = hxfe_find_honeypot( $all_fields );
		if ( $hp && ! empty( $steps ) ) {
			$steps[ count( $steps ) - 1 ]['fields'][] = $hp;
		}
		return $steps;
	}

	// ── 1問1答型 ──────────────────────────────────────────────────
	if ( ( $schema['step_mode'] ?? '' ) === 'one_by_one' ) {
		$steps = [];
		foreach ( $all_fields as $field ) {
			// honeypot は最後の通常ステップに追加 (別ステップにしない)
			if ( ( $field['type'] ?? '' ) === 'honeypot' ) {
				continue;
			}
			$steps[] = [
				'label'  => $field['label'] ?? '',
				'fields' => [ $field ],
			];
		}
		// honeypot を最終ステップに追加
		$hp = hxfe_find_honeypot( $all_fields );
		if ( $hp && ! empty( $steps ) ) {
			$steps[ count( $steps ) - 1 ]['fields'][] = $hp;
		}
		return $steps;
	}

	// ── 従来モード: 全フィールドを1ステップとして返す ──────────────
	return [ [ 'label' => '', 'fields' => $all_fields ] ];
}

/**
 * honeypot フィールドを fields 配列から探して返す。
 *
 * @param array[] $fields
 * @return array|null
 */
function hxfe_find_honeypot( array $fields ) {
	foreach ( $fields as $field ) {
		if ( ( $field['type'] ?? '' ) === 'honeypot' ) {
			return $field;
		}
	}
	return null;
}

/**
 * 入力値を考慮してスキップ不要なステップだけを返す。
 * ajax-handlers.php の next/back/submit で呼ぶ。
 *
 * @param array $schema
 * @param array $values 入力値。
 * @return array[]
 */
function hxfe_resolve_active_steps( array $schema, array $values ) {
	$all = hxfe_resolve_steps( $schema );
	return hxfe_filter_visible_steps( $all, $values );
}

/**
 * 現在のステップインデックスを検証して返す。
 * 範囲外の場合は0を返す。
 *
 * @param int   $step_index リクエストから受け取ったステップ番号。
 * @param array $steps      解決済みのステップ配列。
 * @return int
 */
function hxfe_validate_step_index( int $step_index, array $steps ) {
	$max = count( $steps ) - 1;
	return max( 0, min( $step_index, $max ) );
}

/**
 * プログレスバーのHTMLを返す。
 *
 * @param array $steps       解決済みのステップ配列。
 * @param int   $current     現在のステップインデックス (0始まり)。
 * @param string $form_id   フォームID。
 * @return string HTML。
 */
function hxfe_render_progress( array $steps, int $current, string $form_id ) {
	$total   = count( $steps );
	$percent = $total > 1 ? round( ( $current / ( $total - 1 ) ) * 100 ) : 100;

	ob_start();
	?>
	<div class="hxfe-progress" role="progressbar"
		aria-valuenow="<?php echo esc_attr( $current + 1 ); ?>"
		aria-valuemin="1"
		aria-valuemax="<?php echo esc_attr( $total ); ?>"
		aria-label="<?php
			// translators: 1: current step number, 2: total steps
			printf( esc_attr__( 'Step %1$d of %2$d', 'hxfe-code-first-forms' ), (int) ( $current + 1 ), (int) $total );
			?>">

		<div class="hxfe-progress-labels">
			<?php foreach ( $steps as $i => $step ) : ?>
				<span class="hxfe-progress-label <?php echo $i === $current ? 'current' : ( $i < $current ? 'done' : '' ); ?>">
					<?php if ( $i < $current ) : ?>
						<span class="hxfe-step-check" aria-hidden="true">✓</span>
					<?php else : ?>
						<span class="hxfe-step-num" aria-hidden="true"><?php echo esc_html( $i + 1 ); ?></span>
					<?php endif; ?>
					<?php if ( ! empty( $step['label'] ) ) : ?>
						<span class="hxfe-step-text"><?php echo esc_html( $step['label'] ); ?></span>
					<?php endif; ?>
				</span>
			<?php endforeach; ?>
		</div>

		<div class="hxfe-progress-bar-track">
			<div class="hxfe-progress-bar-fill" style="width:<?php echo esc_attr( $percent ); ?>%"></div>
		</div>

		<p class="hxfe-progress-counter">
			<?php
			// translators: 1: current step number, 2: total steps
			// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment
			printf( esc_html__( 'Step %1$d / %2$d', 'hxfe-code-first-forms' ), (int) ( $current + 1 ), (int) $total );
			?>
		</p>
	</div>
	<?php
	return ob_get_clean();
}
