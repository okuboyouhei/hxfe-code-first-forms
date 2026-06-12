<?php
/**
 * フィールドタイプ別レンダリング関数
 *
 * renderer.php から分離。フィールドタイプごとのHTML生成を担当。
 * 新しいフィールドタイプを追加する場合はこのファイルに追記する。
 *
 * @package HtmxFormEngine
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ---------------------------------------------------------------------------
 * 新フィールドタイプのレンダリング関数
 * ------------------------------------------------------------------------- */

/**
 * radio フィールドを描画する。
 */
function hxfe_render_radio_field( array $field, string $error, array $values ) {
	$key     = esc_attr( $field['key'] );
	$label   = esc_html( $field['label'] ?? '' );
	$options = $field['options'] ?? [];
	$current = $values[ $field['key'] ] ?? ( $field['value'] ?? '' );

	ob_start();
	?>
	<div class="hxfe-field hxfe-field--radio <?php echo $error ? 'hxfe-field--error' : ''; ?>"
		<?php echo ! empty( $field['show_if'] ) ? 'data-hxfe-show-if="' . esc_attr( wp_json_encode( $field['show_if'] ) ) . '"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<fieldset>
			<legend class="hxfe-label"><?php echo esc_html( $label ); ?></legend>
			<div class="hxfe-radio-group" <?php echo ! empty( $field['clearable'] ) ? 'data-clearable="true"' : ''; ?>>
				<?php foreach ( $options as $option ) :
					$val     = esc_attr( $option['value'] );
					$opt_lbl = esc_html( $option['label'] );
					$checked = checked( $current, $option['value'], false );
					?>
					<label class="hxfe-radio-label">
						<input type="radio"
							name="<?php echo esc_attr( $key ); ?>"
							value="<?php echo esc_attr( $val ); ?>"
							class="hxfe-radio"
							<?php echo $checked; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php echo ! empty( $field['required'] ) ? 'required' : ''; ?>>
						<?php echo esc_html( $opt_lbl ); ?>
					</label>
				<?php endforeach; ?>
				<?php if ( ! empty( $field['clearable'] ) ) : ?>
					<button type="button" class="hxfe-radio-clear hxfe-btn-text">
						<?php echo esc_html( $field['clear_label'] ?? __( '選択を解除', 'hxfe-code-first-forms' ) ); ?>
					</button>
				<?php endif; ?>
			</div>
			<?php if ( $error ) : ?>
				<span class="hxfe-error-msg" role="alert"><?php echo esc_html( $error ); ?></span>
			<?php endif; ?>
		</fieldset>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * checkbox_group フィールドを描画する。
 */
function hxfe_render_checkbox_group_field( array $field, string $error, array $values ) {
	$key     = esc_attr( $field['key'] );
	$label   = esc_html( $field['label'] ?? '' );
	$options = $field['options'] ?? [];
	$current = isset( $values[ $field['key'] ] ) && '' !== $values[ $field['key'] ]
		? explode( ',', $values[ $field['key'] ] )
		: ( isset( $field['value'] ) ? (array) $field['value'] : [] );

	ob_start();
	?>
	<div class="hxfe-field hxfe-field--checkbox-group <?php echo $error ? 'hxfe-field--error' : ''; ?>"
		<?php echo ! empty( $field['show_if'] ) ? 'data-hxfe-show-if="' . esc_attr( wp_json_encode( $field['show_if'] ) ) . '"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<fieldset>
			<legend class="hxfe-label"><?php echo esc_html( $label ); ?>
				<?php if ( ! empty( $field['min'] ) || ! empty( $field['max'] ) ) : ?>
					<span class="hxfe-field-hint">
						<?php
						if ( ! empty( $field['min'] ) && ! empty( $field['max'] ) ) {
							// translators: 1: min, 2: max
							printf( esc_html__( '(%1$d〜%2$d items)', 'hxfe-code-first-forms' ), (int) $field['min'], (int) $field['max'] );
						} elseif ( ! empty( $field['min'] ) ) {
							// translators: %d: minimum
							printf( esc_html__( '(select at least %d)', 'hxfe-code-first-forms' ), (int) $field['min'] );
						}
						?>
					</span>
				<?php endif; ?>
			</legend>
			<div class="hxfe-checkbox-group">
				<?php foreach ( $options as $option ) :
					$val     = esc_attr( $option['value'] );
					$opt_lbl = esc_html( $option['label'] );
					$checked = in_array( $option['value'], $current, true ) ? 'checked' : '';
					?>
					<label class="hxfe-checkbox-group-label">
						<input type="checkbox"
							name="<?php echo esc_attr( $key ); ?>[]"
							value="<?php echo esc_attr( $val ); ?>"
							class="hxfe-checkbox"
							<?php echo $checked; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
						<?php echo esc_html( $opt_lbl ); ?>
					</label>
				<?php endforeach; ?>
			</div>
			<?php if ( $error ) : ?>
				<span class="hxfe-error-msg" role="alert"><?php echo esc_html( $error ); ?></span>
			<?php endif; ?>
		</fieldset>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * number フィールドを描画する。
 */
function hxfe_render_number_field( array $field, string $error, array $values ) {
	$key   = esc_attr( $field['key'] );
	$label = esc_html( $field['label'] ?? '' );
	$value = esc_attr( $values[ $field['key'] ] ?? ( $field['value'] ?? '' ) );

	ob_start();
	?>
	<div class="hxfe-field <?php echo $error ? 'hxfe-field--error' : ''; ?>"
		<?php echo ! empty( $field['show_if'] ) ? 'data-hxfe-show-if="' . esc_attr( wp_json_encode( $field['show_if'] ) ) . '"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<label for="hxfe-field-<?php echo esc_attr( $key ); ?>" class="hxfe-label"><?php echo esc_html( $label ); ?></label>
		<input type="number"
			id="hxfe-field-<?php echo esc_attr( $key ); ?>"
			name="<?php echo esc_attr( $key ); ?>"
			class="hxfe-input"
			value="<?php echo esc_attr( $value ); ?>"
			<?php echo ! empty( $field['min'] )  ? 'min="' . esc_attr( $field['min'] ) . '"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php echo ! empty( $field['max'] )  ? 'max="' . esc_attr( $field['max'] ) . '"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php echo ! empty( $field['step'] ) ? 'step="' . esc_attr( $field['step'] ) . '"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php echo ! empty( $field['required'] ) ? 'required' : ''; ?>>
		<?php if ( $error ) : ?>
			<span class="hxfe-error-msg" role="alert"><?php echo esc_html( $error ); ?></span>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * date フィールドを描画する。
 */
function hxfe_render_date_field( array $field, string $error, array $values ) {
	$key   = esc_attr( $field['key'] );
	$label = esc_html( $field['label'] ?? '' );
	$value = esc_attr( $values[ $field['key'] ] ?? ( $field['value'] ?? '' ) );

	ob_start();
	?>
	<div class="hxfe-field <?php echo $error ? 'hxfe-field--error' : ''; ?>"
		<?php echo ! empty( $field['show_if'] ) ? 'data-hxfe-show-if="' . esc_attr( wp_json_encode( $field['show_if'] ) ) . '"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<label for="hxfe-field-<?php echo esc_attr( $key ); ?>" class="hxfe-label"><?php echo esc_html( $label ); ?></label>
		<input type="date"
			id="hxfe-field-<?php echo esc_attr( $key ); ?>"
			name="<?php echo esc_attr( $key ); ?>"
			class="hxfe-input"
			value="<?php echo esc_attr( $value ); ?>"
			<?php echo ! empty( $field['min_date'] ) ? 'min="' . esc_attr( $field['min_date'] ) . '"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php echo ! empty( $field['max_date'] ) ? 'max="' . esc_attr( $field['max_date'] ) . '"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php echo ! empty( $field['required'] ) ? 'required' : ''; ?>>
		<?php if ( $error ) : ?>
			<span class="hxfe-error-msg" role="alert"><?php echo esc_html( $error ); ?></span>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * file フィールドを描画する。
 *
 * アップロードされたファイルはメールに添付される。
 * accept: 許可する拡張子（例: '.pdf,.doc,image/*'）
 * mime_types: サーバー側のMIMEホワイトリスト（推奨）
 * max_size_mb: 最大ファイルサイズ（MB、デフォルト5）
 */
function hxfe_render_file_field( array $field, string $error, array $values ) {
	$key    = esc_attr( $field['key'] );
	$label  = esc_html( $field['label'] ?? '' );
	$accept = ! empty( $field['accept'] ) ? esc_attr( $field['accept'] ) : '';
	// accept の例: '.pdf,.doc,.docx' / 'image/*'

	ob_start();
	?>
	<div class="hxfe-field <?php echo $error ? 'hxfe-field--error' : ''; ?>"
		<?php echo ! empty( $field['show_if'] ) ? 'data-hxfe-show-if="' . esc_attr( wp_json_encode( $field['show_if'] ) ) . '"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<label for="hxfe-field-<?php echo esc_attr( $key ); ?>" class="hxfe-label"><?php echo esc_html( $label ); ?></label>
		<input type="file"
			id="hxfe-field-<?php echo esc_attr( $key ); ?>"
			name="<?php echo esc_attr( $key ); ?>"
			class="hxfe-input hxfe-file-input"
			<?php echo $accept ? 'accept="' . $accept . '"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php echo ! empty( $field['required'] ) ? 'required' : ''; ?>>
		<?php if ( ! empty( $field['max_size_mb'] ) ) : ?>
			<span class="hxfe-field-hint">
				<?php
				// translators: %s: maximum file size in MB
				printf( esc_html__( 'Max file size: %sMB', 'hxfe-code-first-forms' ), esc_html( $field['max_size_mb'] ) );
				?>
			</span>
		<?php endif; ?>
		<?php if ( $error ) : ?>
			<span class="hxfe-error-msg" role="alert"><?php echo esc_html( $error ); ?></span>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * リダイレクト用HTMLを生成する。
 * htmxのAJAXレスポンス内からJavaScriptでリダイレクトする。
 *
 * @param string $form_id フォームID（エスケープ済み）。
 * @param string $url     リダイレクト先URL（エスケープ済み）。
 * @return string HTML。
 */
function hxfe_render_redirect_html( string $form_id, string $url ) {
	ob_start();
	?>
	<div id="hxfe-<?php echo esc_attr( $form_id ); ?>" class="hxfe-wrap">
		<div class="hxfe-complete hxfe-complete--redirect" role="status">
			<?php esc_html_e( 'Redirecting...', 'hxfe-code-first-forms' ); ?>
		</div>
	</div>
	<?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- This <script> is part of an htmx AJAX response fragment (outerHTML swap). It is injected into the DOM by htmx after form submission. wp_enqueue_script() and wp_add_inline_script() only work during page load and CANNOT be used for dynamically injected htmx responses. ?>
	<script>
	( function() {
		// htmxのAJAXレスポンス内でも確実にリダイレクトする
		var url = <?php echo wp_json_encode( $url ); ?>;
		if ( window.location.href !== url ) {
			window.location.href = url;
		}
	} )();
	</script>
	<?php
	return ob_get_clean();
}

/**
 * 確認画面でのフィールド表示値を生成する。
 * タイプに応じて value を人間が読みやすい形式に変換する。
 *
 * @param array  $field フィールド定義。
 * @param string $val   入力値。
 * @return string エスケープ済みHTML文字列。
 */
function hxfe_confirm_display_value( array $field, string $val ) {
	$type = $field['type'] ?? 'text';

	switch ( $type ) {
		// checkbox: Yes/No
		case 'checkbox':
			return $val
				? esc_html( __( 'Yes', 'hxfe-code-first-forms' ) )
				: esc_html( __( 'No', 'hxfe-code-first-forms' ) );

		// privacy: 同意済みを明示
		case 'privacy':
			return $val
				? '✓ ' . esc_html( __( 'Agreed', 'hxfe-code-first-forms' ) )
				: esc_html( __( 'Not agreed', 'hxfe-code-first-forms' ) );

		// radio / select: value → label に変換
		case 'radio':
		case 'select':
			if ( ! empty( $field['options'] ) ) {
				foreach ( $field['options'] as $opt ) {
					if ( (string) ( $opt['value'] ?? '' ) === $val ) {
						return esc_html( $opt['label'] ?? $val );
					}
				}
			}
			return esc_html( $val );

		// checkbox_group: "design,dev" → "デザイン, 開発"
		case 'checkbox_group':
			if ( '' === $val ) { return '—'; }
			$selected_vals   = array_map( 'trim', explode( ',', $val ) );
			$option_map      = [];
			foreach ( $field['options'] ?? [] as $opt ) {
				$option_map[ (string) $opt['value'] ] = $opt['label'] ?? $opt['value'];
			}
			$labels = array_map(
				fn( $v ) => esc_html( $option_map[ $v ] ?? $v ),
				$selected_vals
			);
			return implode( ', ', $labels );

		// date: Y-m-d → 読みやすい形式（ロケール考慮）
		case 'date':
			if ( '' === $val ) { return '—'; }
			$dt = DateTimeImmutable::createFromFormat( 'Y-m-d', $val );
			return $dt ? esc_html( $dt->format( get_option( 'date_format', 'Y-m-d' ) ) ) : esc_html( $val );

		// file: ファイル名を表示（値はファイル名が入る想定）
		case 'file':
			return '' !== $val
				? esc_html( basename( $val ) )
				: esc_html( __( '(No file)', 'hxfe-code-first-forms' ) );

		// textarea: 改行を保持
		case 'textarea':
			return nl2br( esc_html( $val ) );

		// その他（text/email/number/tel/url等）
		default:
			return esc_html( $val );
	}
}
