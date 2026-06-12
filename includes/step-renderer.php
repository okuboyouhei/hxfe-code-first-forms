<?php
/**
 * Step renderer — renders a single step of a multi-step form.
 *
 * htmx の動作:
 *   各ステップの「次へ」ボタンが hx-post を発火
 *   → サーバーがバリデーションして次のステップHTMLを返す
 *   → htmx が #hxfe-{form_id} を outerHTML で差し替える
 *
 * 値の保持:
 *   前のステップの値は hidden フィールドに JSON エンコードして保持する。
 *   HXFE の confirm 画面と同じアプローチ。
 *
 * @package HtmxFormEngine
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * 指定ステップの入力フォームをレンダリングする。
 *
 * @param array  $schema      フォームスキーマ。
 * @param array  $steps       解決済みのステップ配列。
 * @param int    $step_index  現在のステップインデックス (0始まり)。
 * @param array  $errors      現在のステップのフィールドエラー。
 * @param array  $values      全ステップの入力値 (key => value)。
 * @return string HTML。
 */
function hxfe_render_step( array $schema, array $steps, int $step_index,
							array $errors = [], array $values = [] ) {
	$form_id     = esc_attr( $schema['id'] );
	$ajax_url    = esc_url( admin_url( 'admin-ajax.php' ) );
	$total       = count( $steps );
	$step        = $steps[ $step_index ];
	$is_last     = ( $step_index === $total - 1 );
	$nonce_action = $is_last
		? 'hxfe_step_submit_' . $schema['id']
		: 'hxfe_step_next_' . $schema['id'];
	$ajax_action  = $is_last ? 'hxfe_step_submit' : 'hxfe_step_next';
	$back_nonce   = wp_create_nonce( 'hxfe_step_back_' . $schema['id'] );

	ob_start();
	?>
	<div id="hxfe-<?php echo esc_attr( $form_id ); ?>" class="hxfe-wrap hxfe-step-wrap">

		<?php echo hxfe_render_progress( $steps, $step_index, $form_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

		<?php if ( ! empty( $errors ) ) : ?>
		<div class="hxfe-error-summary" role="alert">
			<p><?php esc_html_e( 'Please correct the errors below.', 'hxfe-code-first-forms' ); ?></p>
		</div>
		<?php endif; ?>

		<form
			hx-post="<?php echo esc_url( $ajax_url ); ?>"
			hx-target="#hxfe-<?php echo esc_attr( $form_id ); ?>"
			hx-swap="outerHTML"
			hx-vals='{"action":"<?php echo esc_js( $ajax_action ); ?>","hxfe_form_id":"<?php echo esc_js( $schema['id'] ); ?>","hxfe_step_index":"<?php echo esc_js( (string) $step_index ); ?>"}'
			hx-indicator="#hxfe-spinner-<?php echo esc_attr( $form_id ); ?>"
			class="hxfe-form hxfe-step-form"
			novalidate>

			<?php wp_nonce_field( $nonce_action, 'hxfe_nonce' ); ?>
			<input type="hidden" name="hxfe_form_id"    value="<?php echo esc_attr( $schema['id'] ); ?>">
			<input type="hidden" name="hxfe_step_index" value="<?php echo esc_attr( (string) $step_index ); ?>">

			<!-- 前ステップまでの値を hidden フィールドで保持 -->
			<input type="hidden" name="hxfe_prev_values"
				value="<?php echo esc_attr( wp_json_encode( $values ) ); ?>">

			<!-- 現在のステップのフィールドを描画 -->
			<?php foreach ( $step['fields'] as $field ) : ?>
				<?php echo hxfe_render_field( $field, $errors, $values ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endforeach; ?>

			<div class="hxfe-step-actions">
				<?php if ( $step_index > 0 ) : ?>
					<button
						type="button"
						hx-post="<?php echo esc_url( $ajax_url ); ?>"
						hx-target="#hxfe-<?php echo esc_attr( $form_id ); ?>"
						hx-swap="outerHTML"
						hx-vals='{"action":"hxfe_step_back","hxfe_form_id":"<?php echo esc_js( $schema['id'] ); ?>","hxfe_step_index":"<?php echo esc_js( (string) $step_index ); ?>","hxfe_nonce":"<?php echo esc_js( $back_nonce ); ?>","hxfe_prev_values":<?php echo esc_attr( wp_json_encode( $values ) ); ?>}'
						class="hxfe-btn hxfe-btn-back">
						<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo esc_html( $schema['back_label'] ?? __( '← Back', 'hxfe-code-first-forms' ) ); ?>
					</button>
				<?php endif; ?>

				<button type="submit" class="hxfe-btn hxfe-btn-submit">
					<?php if ( $is_last ) : ?>
						<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo esc_html( $schema['submit_label'] ?? __( 'Submit', 'hxfe-code-first-forms' ) ); ?>
					<?php else : ?>
						<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo esc_html( $schema['next_label'] ?? __( 'Next →', 'hxfe-code-first-forms' ) ); ?>
					<?php endif; ?>
				</button>
				<span id="hxfe-spinner-<?php echo esc_attr( $form_id ); ?>"
					class="hxfe-spinner htmx-indicator" aria-hidden="true"></span>
			</div>
		</form>
	</div>
	<?php
	return ob_get_clean();
}
