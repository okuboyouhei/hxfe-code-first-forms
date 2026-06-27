<?php
/**
 * HTML rendering for all three form states: input, confirm, complete.
 *
 * All functions are pure: they receive data and return an HTML string.
 * No database reads, no global side-effects.
 *
 * The outermost wrapper element carries id="hxfe-{form_id}" so htmx can
 * target it with hx-target="#hxfe-{form_id}" and hx-swap="outerHTML".
 *
 * @package HtmxFormEngine
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ---------------------------------------------------------------------------
 * Public entry points
 * ------------------------------------------------------------------------- */

/**
 * Renders the initial input form.
 *
 * @param array $schema Form schema.
 * @param array $errors Per-field error messages (empty on first render).
 * @param array $values Previously submitted values to repopulate on error.
 * @return string HTML.
 */
function hxfe_render_input( array $schema, array $errors = [], array $values = [] ) {
	$form_id  = esc_attr( $schema['id'] );
	$ajax_url = esc_url( admin_url( 'admin-ajax.php' ) );
	$nonce    = wp_create_nonce( 'hxfe_validate_' . $schema['id'] );

	ob_start();
	?>
	<?php
	// スキーマの wrapper_class / form_class をマージ
	$wrapper_class = 'hxfe-wrap';
	if ( ! empty( $schema['wrapper_class'] ) ) {
		$wrapper_class .= ' ' . sanitize_html_class( $schema['wrapper_class'] );
	}
	$has_file = ! empty( array_filter( $schema['fields'] ?? [], fn( $f ) => ( $f['type'] ?? '' ) === 'file' ) );
	?>
	<div id="hxfe-<?php echo esc_attr( $form_id ); ?>" class="<?php echo esc_attr( $wrapper_class ); ?>">

		<?php if ( ! empty( $errors ) ) : ?>
		<div class="hxfe-error-summary" role="alert">
			<p><?php echo esc_html( $schema['error_message'] ?? __( 'Please correct the errors below.', 'hxfe-code-first-forms' ) ); ?></p>
		</div>
		<?php endif; ?>

		<form
			hx-post="<?php echo esc_url( $ajax_url ); ?>"
			hx-target="#hxfe-<?php echo esc_attr( $form_id ); ?>"
			hx-swap="outerHTML"
			hx-vals='{"action":"hxfe_validate","hxfe_form_id":"<?php echo esc_attr( $form_id ); ?>"}'
			hx-indicator="#hxfe-spinner-<?php echo esc_attr( $form_id ); ?>"
			<?php if ( $has_file ) : ?>hx-encoding="multipart/form-data"<?php endif; ?>
			class="hxfe-form"
			novalidate>

			<?php wp_nonce_field( 'hxfe_validate_' . $schema['id'], 'hxfe_nonce' ); ?>
			<input type="hidden" name="hxfe_form_id" value="<?php echo esc_attr( $form_id ); ?>">
			<?php if ( ! empty( $schema['_context'] ) ) : ?>
			<input type="hidden" name="hxfe_context" value="<?php echo esc_attr( $schema['_context'] ); ?>">
			<?php endif; ?>

			<?php foreach ( $schema['fields'] as $field ) : ?>
				<?php echo hxfe_render_field( $field, $errors, $values ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML escaped internally ?>
			<?php endforeach; ?>

			<div class="hxfe-actions">
				<button type="submit" class="hxfe-btn hxfe-btn-submit">
					<?php
					// confirm:false なら「送信する」、通常は「確認する →」
					$_hxfe_skip_confirm = isset( $schema['confirm'] ) && false === $schema['confirm'];
					if ( $_hxfe_skip_confirm ) {
						echo esc_html( $schema['submit_label'] ?? __( 'Submit', 'hxfe-code-first-forms' ) );
					} else {
						echo esc_html( $schema['confirm_label'] ?? __( 'Confirm →', 'hxfe-code-first-forms' ) );
					}
					?>
				</button>
				<span id="hxfe-spinner-<?php echo esc_attr( $form_id ); ?>" class="hxfe-spinner htmx-indicator" aria-hidden="true"></span>
			</div>
		</form>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Renders the confirmation screen.
 *
 * @param array $schema Form schema.
 * @param array $values Validated field values.
 * @return string HTML.
 */
function hxfe_render_confirm( array $schema, array $values ) {
	$form_id  = esc_attr( $schema['id'] );
	$ajax_url = esc_url( admin_url( 'admin-ajax.php' ) );

	ob_start();
	?>
	<div id="hxfe-<?php echo esc_attr( $form_id ); ?>" class="hxfe-wrap">
		<div class="hxfe-confirm">

			<p class="hxfe-confirm-heading">
				<?php echo esc_html( $schema['confirm_heading'] ?? __( 'Please review your submission', 'hxfe-code-first-forms' ) ); ?>
			</p>

			<dl class="hxfe-confirm-list">
			<?php
			// アップロード済みファイルパスを hidden フィールドとして埋め込む（submit 時に使用）
			$_hxfe_file_paths_json = esc_attr( $values['__file_paths'] ?? '{}' );
			$_hxfe_file_names_json = esc_attr( $values['__file_names'] ?? '{}' );
			?>
			<input type="hidden" name="hxfe_file_paths" value="<?php echo $_hxfe_file_paths_json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_attr applied above ?>">
			<input type="hidden" name="hxfe_file_names" value="<?php echo $_hxfe_file_names_json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_attr applied above ?>">
			<?php
			// 非表示フィールドを除外して表示（条件分岐で hidden のフィールドは出さない）
			$skip_types = [ 'honeypot', 'recaptcha', 'turnstile' ];
			foreach ( $schema['fields'] as $field ) :
				$type  = $field['type'] ?? 'text';
				if ( in_array( $type, $skip_types, true ) ) { continue; }

				$key   = $field['key'];
				$val   = $values[ $key ] ?? '';

				// 条件分岐で非表示のフィールドは確認画面にも表示しない
				if ( ! hxfe_field_is_visible( $field, $values ) ) { continue; }

				$label = $field['label'] ?? $key;

				// fileフィールドはfile_namesから表示名を取得
				if ( ( $field['type'] ?? '' ) === 'file' ) {
					$file_names = json_decode( $values['__file_names'] ?? '{}', true ) ?: [];
					$display    = isset( $file_names[ $key ] )
						? esc_html( $file_names[ $key ] )
						: esc_html( __( '(No file)', 'hxfe-code-first-forms' ) );
				} else {
					// タイプ別の表示値を生成
					$display = hxfe_confirm_display_value( $field, $val );
				}
			?>
				<dt class="hxfe-confirm-label"><?php echo esc_html( $label ); ?></dt>
				<dd class="hxfe-confirm-value"><?php echo wp_kses_post( $display ); ?></dd>
			<?php endforeach; ?>
			</dl>

			<?php
			// Re-encode values as hidden fields for the final submit.
			$encoded     = esc_attr( wp_json_encode( $values ) );
			$nonce       = wp_create_nonce( 'hxfe_submit_' . $schema['id'] );
			$nonce_back  = wp_create_nonce( 'hxfe_validate_' . $schema['id'] );
			?>

			<div class="hxfe-actions">
				<button
					type="button"
					hx-post="<?php echo esc_url( $ajax_url ); ?>"
					hx-target="#hxfe-<?php echo esc_attr( $form_id ); ?>"
					hx-swap="outerHTML"
					hx-vals='{"action":"hxfe_back","hxfe_form_id":"<?php echo esc_attr( $form_id ); ?>","hxfe_nonce":"<?php echo esc_js( $nonce_back ); ?>","hxfe_values":<?php echo esc_attr( wp_json_encode( $values ) ); ?>}'
					class="hxfe-btn hxfe-btn-back">
					<?php echo esc_html( $schema['back_label'] ?? __( '← Back', 'hxfe-code-first-forms' ) ); ?>
				</button>

				<button
					type="button"
					hx-post="<?php echo esc_url( $ajax_url ); ?>"
					hx-target="#hxfe-<?php echo esc_attr( $form_id ); ?>"
					hx-swap="outerHTML"
					hx-vals='{"action":"hxfe_submit","hxfe_form_id":"<?php echo esc_attr( $form_id ); ?>","hxfe_nonce":"<?php echo esc_js( $nonce ); ?>","hxfe_file_paths":<?php echo esc_attr( $values['__file_paths'] ?? '{}' ); ?>,"hxfe_file_names":<?php echo esc_attr( $values['__file_names'] ?? '{}' ); ?>,"hxfe_values":<?php echo esc_attr( wp_json_encode( $values ) ); ?>}'
					class="hxfe-btn hxfe-btn-submit">
					<?php echo esc_html( $schema['submit_label'] ?? __( 'Submit', 'hxfe-code-first-forms' ) ); ?>
				</button>
			</div>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Renders the completion screen.
 *
 * @param array $schema Form schema.
 * @return string HTML.
 */
function hxfe_render_complete( array $schema, array $values = [] ) {
	$form_id = esc_attr( $schema['id'] );

	// ── ③ redirect_rules: 送信データに応じてリダイレクト先を切り替え ──
	if ( ! empty( $schema['complete_redirect_rules'] ) ) {
		foreach ( $schema['complete_redirect_rules'] as $rule ) {
			$when = $rule['when'] ?? null;
			$url  = $rule['to']   ?? '';
			if ( 'default' === $when || ( $when && hxfe_eval_condition( $when, $values ) ) ) {
				if ( $url ) {
					return hxfe_render_redirect_html( $form_id, esc_url( $url ) );
				}
				break;
			}
		}
	}

	// ── ④ complete_html_rules: 回答に応じて完了HTMLを切り替え ──
	if ( ! empty( $schema['complete_html_rules'] ) ) {
		foreach ( $schema['complete_html_rules'] as $rule ) {
			$when = $rule['when'] ?? null;
			$html = $rule['html'] ?? '';
			if ( 'default' === $when || ( $when && hxfe_eval_condition( $when, $values ) ) ) {
				// {field_key} 補間
				$html = hxfe_interpolate( $html, $values );
				ob_start();
				?>
				<div id="hxfe-<?php echo esc_attr( $form_id ); ?>" class="hxfe-wrap">
					<div class="hxfe-complete hxfe-complete--custom" role="status">
						<?php echo wp_kses_post( $html ); ?>
					</div>
				</div>
				<?php
				return ob_get_clean();
			}
		}
	}

	// ── ① complete_redirect: 完了後にURLへリダイレクト ──
	if ( ! empty( $schema['complete_redirect'] ) ) {
		return hxfe_render_redirect_html( $form_id, esc_url( $schema['complete_redirect'] ) );
	}

	// ── download: 完了後にダウンロードボタンを表示 ──
	if ( ! empty( $schema['download_url'] ) ) {
		$message  = $schema['complete_message'] ?? __( 'Thank you! Your message has been sent.', 'hxfe-code-first-forms' );
		$dl_url   = esc_url( $schema['download_url'] );
		$dl_label = $schema['download_label'] ?? __( 'Download', 'hxfe-code-first-forms' );
		ob_start();
		?>
		<div id="hxfe-<?php echo esc_attr( $form_id ); ?>" class="hxfe-wrap">
			<div class="hxfe-complete" role="status">
				<?php echo wp_kses_post( $message ); ?>
				<div class="hxfe-download-wrap">
					<a href="<?php echo esc_url( $dl_url ); ?>" class="hxfe-btn hxfe-btn-download" download>
						<?php echo esc_html( $dl_label ); ?>
					</a>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	// ── ② complete_html: カスタムHTMLの完了画面 ──
	if ( ! empty( $schema['complete_html'] ) ) {
		ob_start();
		?>
		<div id="hxfe-<?php echo esc_attr( $form_id ); ?>" class="hxfe-wrap">
			<div class="hxfe-complete hxfe-complete--custom" role="status">
				<?php echo wp_kses_post( $schema['complete_html'] ); ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	// ── デフォルト: complete_message テキスト ──
	$message = $schema['complete_message'] ?? __( 'Thank you! Your message has been sent.', 'hxfe-code-first-forms' );

	ob_start();
	?>
	<div id="hxfe-<?php echo esc_attr( $form_id ); ?>" class="hxfe-wrap">
		<div class="hxfe-complete" role="status">
			<?php echo wp_kses_post( $message ); ?>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

/* ---------------------------------------------------------------------------
 * Field rendering
 * ------------------------------------------------------------------------- */

/**
 * Renders a single field including its label and error message.
 *
 * @param array $field  Field definition.
 * @param array $errors Per-field errors.
 * @param array $values Previously submitted values.
 * @return string HTML.
 */
function hxfe_render_field( array $field, array $errors, array $values ) {
	$type  = $field['type'] ?? 'text';
	$error = $errors[ $field['key'] ] ?? '';

	// reCAPTCHA フィールド
	// ── tel / url（textと同じ描画、type属性のみ異なる）──────────────────
	if ( 'tel' === $type || 'url' === $type ) {
		// textフィールドと同じHTMLを使い、input type のみ変える
		$field['_input_type'] = $type; // 内部フラグ
		// 通常のtextフィールドとして描画（フォールスルー）
	}

	// ── radio ──────────────────────────────────────────────────────────
	if ( 'radio' === $type ) {
		return hxfe_render_radio_field( $field, $error, $values );
	}

	// ── checkbox_group ──────────────────────────────────────────────────
	if ( 'checkbox_group' === $type ) {
		return hxfe_render_checkbox_group_field( $field, $error, $values );
	}

	// ── number ──────────────────────────────────────────────────────────
	if ( 'number' === $type ) {
		return hxfe_render_number_field( $field, $error, $values );
	}

	// ── date ────────────────────────────────────────────────────────────
	if ( 'date' === $type ) {
		return hxfe_render_date_field( $field, $error, $values );
	}

	// ── file ────────────────────────────────────────────────────────────
	if ( 'file' === $type ) {
		return hxfe_render_file_field( $field, $error, $values );
	}

	if ( 'recaptcha' === $type ) {
		$error = $errors[ $field['key'] ] ?? '';
		return hxfe_render_recaptcha_field( $field, $error );
	}

	if ( 'turnstile' === $type ) {
		$error = $errors[ $field['key'] ] ?? '';
		return hxfe_render_turnstile_field( $field, $error );
	}

	// プライバシーポリシー同意フィールド
	if ( 'privacy' === $type ) {
		$error   = $errors[ $field['key'] ] ?? '';
		$checked = ! empty( $values[ $field['key'] ] );
		return hxfe_render_privacy_field( $field, $error, $checked );
	}

	// Honeypot: hidden from humans, visible to bots.
	if ( 'honeypot' === $type ) {
		$key = esc_attr( $field['key'] );
		return '<div class="hxfe-honeypot" aria-hidden="true" style="display:none!important">'
			. '<label for="hxfe-field-' . $key . '">Leave this blank</label>'
			. '<input type="text" id="hxfe-field-' . $key . '" name="' . $key . '" value="" tabindex="-1" autocomplete="off">'
			. '</div>';
	}

	$key         = esc_attr( $field['key'] );
	$label       = esc_html( $field['label'] ?? $key );
	$required    = ! empty( $field['required'] );
	$error       = $errors[ $field['key'] ] ?? '';
	$value       = $values[ $field['key'] ] ?? ( $field['value'] ?? '' ); // デフォルト値サポート
	$placeholder = esc_attr( $field['placeholder'] ?? '' );
	$aria_desc   = $error ? ' aria-describedby="hxfe-err-' . $key . '"' : '';
	$aria_inv    = $error ? ' aria-invalid="true"' : '';

	ob_start();
	?>
	<?php
	$field_class = 'hxfe-field';
	if ( $error ) { $field_class .= ' hxfe-field--error'; }
	if ( ! empty( $field['field_class'] ) ) {
		$field_class .= ' ' . sanitize_html_class( $field['field_class'] );
	}
	$input_class = 'hxfe-input';
	if ( ! empty( $field['input_class'] ) ) {
		$input_class .= ' ' . sanitize_html_class( $field['input_class'] );
	}
	$label_class = 'hxfe-label';
	if ( ! empty( $field['label_class'] ) ) {
		$label_class .= ' ' . sanitize_html_class( $field['label_class'] );
	}

	// 条件分岐: show_if / hide_if をdata属性としてHTMLに埋め込む
	// JS側でリアルタイムに表示/非表示を切り替える
	$condition_attrs = '';
	if ( ! empty( $field['show_if'] ) ) {
		$condition_attrs .= ' data-hxfe-show-if="' . esc_attr( wp_json_encode( $field['show_if'] ) ) . '"';
	}
	// hide_if は v1.3.5 で廃止。後方互換のため data-hxfe-hide-if として埋め込む。
	if ( ! empty( $field['hide_if'] ) ) {
		$condition_attrs .= ' data-hxfe-hide-if="' . esc_attr( wp_json_encode( $field['hide_if'] ) ) . '"';
	}

	// 初期表示: PHPで条件を評価して hidden にするか決める
	$is_visible = hxfe_field_is_visible( $field, $values );
	if ( ! $is_visible ) {
		$field_class .= ' hxfe-field--hidden';
	}
	?>
	<?php if ( ! empty( $field['before_html'] ) ) : ?>
		<?php echo wp_kses_post( $field['before_html'] ); ?>
	<?php endif; ?>

	<div class="<?php echo esc_attr( $field_class ); ?>"<?php echo $condition_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<label for="hxfe-field-<?php echo esc_attr( $key ); ?>" class="<?php echo esc_attr( $label_class ); ?>">
			<?php echo esc_html( $label ); ?>
			<?php if ( $required ) : ?>
				<span class="hxfe-required" aria-label="required">*</span>
			<?php endif; ?>
		</label>

		<?php if ( 'textarea' === $type ) : ?>
			<textarea
				id="hxfe-field-<?php echo esc_attr( $key ); ?>"
				name="<?php echo esc_attr( $key ); ?>"
				class="<?php echo esc_attr( str_replace('hxfe-input','hxfe-textarea',$input_class) ); ?>"
				rows="5"
				placeholder="<?php echo esc_attr( $placeholder ); ?>"
				<?php echo $required ? 'required' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static string ?>
				<?php echo $aria_desc . $aria_inv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped attribute strings ?>
			><?php echo esc_textarea( (string) $value ); ?></textarea>

		<?php elseif ( 'select' === $type ) : ?>
			<select
				id="hxfe-field-<?php echo esc_attr( $key ); ?>"
				name="<?php echo esc_attr( $key ); ?>"
				class="<?php echo esc_attr( str_replace('hxfe-input','hxfe-select',$input_class) ); ?>"
				<?php echo $required ? 'required' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static string ?>
				<?php echo $aria_desc . $aria_inv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped attribute strings ?>
				<?php if ( ! empty( $field['cascade_from'] ) ) : ?>
					data-hxfe-cascade-from="<?php echo esc_attr( $field['cascade_from'] ); ?>"
					data-hxfe-cascade-options="<?php echo esc_attr( wp_json_encode( $field['cascade_options'] ?? [] ) ); ?>"
					data-hxfe-placeholder="<?php echo esc_attr( $field['placeholder'] ?? '-- Select --' ); ?>"
				<?php endif; ?>>
				<option value=""><?php esc_html_e( '-- Select --', 'hxfe-code-first-forms' ); ?></option>
				<?php foreach ( $field['options'] as $opt ) : ?>
					<option value="<?php echo esc_attr( $opt['value'] ); ?>"
						<?php selected( $value, $opt['value'] ); ?>>
						<?php echo esc_html( $opt['label'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>

		<?php elseif ( 'checkbox' === $type ) : ?>
			<label class="hxfe-checkbox-label">
				<input
					type="checkbox"
					id="hxfe-field-<?php echo esc_attr( $key ); ?>"
					name="<?php echo esc_attr( $key ); ?>"
					value="1"
					class="<?php echo esc_attr( str_replace('hxfe-input','hxfe-checkbox',$input_class) ); ?>"
					<?php checked( (bool) $value ); ?>
					<?php echo $required ? 'required' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static string ?>
					<?php echo $aria_desc . $aria_inv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped attribute strings ?>>
				<?php echo esc_html( $field['checkbox_label'] ?? $label ); ?>
			</label>

		<?php else : ?>
			<input
				type="<?php echo esc_attr( $type ); ?>"
				id="hxfe-field-<?php echo esc_attr( $key ); ?>"
				name="<?php echo esc_attr( $key ); ?>"
				value="<?php echo esc_attr( (string) $value ); ?>"
				class="<?php echo esc_attr( $input_class ); ?>"
				placeholder="<?php echo esc_attr( $placeholder ); ?>"
				<?php if ( ! empty( $field['maxlength'] ) ) echo 'maxlength="' . (int) $field['maxlength'] . '"'; ?>
				<?php if ( ! empty( $field['minlength'] ) ) echo 'minlength="' . (int) $field['minlength'] . '"'; ?>
				<?php if ( ! empty( $field['pattern'] ) )   echo 'pattern="' . esc_attr( $field['pattern'] ) . '"'; ?>
				<?php echo $required ? 'required' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static string ?>
				<?php echo $aria_desc . $aria_inv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped attribute strings ?>>
		<?php endif; ?>

		<?php if ( $error ) : ?>
			<span id="hxfe-err-<?php echo esc_attr( $key ); ?>" class="hxfe-error-msg" role="alert">
				<?php echo esc_html( $error ); ?>
			</span>
		<?php endif; ?>
	</div>

	<?php if ( ! empty( $field['after_html'] ) ) : ?>
		<?php echo wp_kses_post( $field['after_html'] ); ?>
	<?php endif; ?>
	<?php
	return ob_get_clean();
}

// フィールドタイプ別レンダリング関数（分離ファイル）
require_once __DIR__ . '/fields/field-renderers.php';
