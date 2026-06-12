<?php
/**
 * Privacy policy field — renders a checkbox + link.
 *
 * スキーマへの追加方法:
 *
 *   // URLリンク
 *   [ 'key' => 'privacy', 'type' => 'privacy',
 *     'label' => 'プライバシーポリシーに同意する',
 *     'policy_url' => 'https://example.com/privacy',
 *     'policy_label' => 'プライバシーポリシー',
 *     'required' => true ]
 *
 *   // PDFアップロード (メディアライブラリのURL)
 *   [ 'key' => 'privacy', 'type' => 'privacy',
 *     'label' => 'プライバシーポリシーに同意する',
 *     'policy_url' => 'https://example.com/wp-content/uploads/privacy.pdf',
 *     'policy_label' => 'プライバシーポリシー(PDF)',
 *     'required' => true ]
 *
 * policy_url は Settings → HXFE → Privacy でサイト共通として設定することもできる。
 * フィールド定義の policy_url がある場合はそちらを優先する。
 *
 * @package HtmxFormEngine
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * privacy フィールドの HTML を返す。
 *
 * @param array  $field  フィールド定義。
 * @param string $error  エラーメッセージ（あれば）。
 * @param bool   $checked 既にチェック済みか（値の復元時）。
 * @return string HTML。
 */
function hxfe_render_privacy_field( array $field, string $error = '', bool $checked = false ) {
	$key        = esc_attr( $field['key'] );
	$required   = ! empty( $field['required'] );

	// policy_url: フィールド定義 > wp_options のサイト設定
	$opts       = get_option( 'hxfe_privacy', [] );
	$policy_url = $field['policy_url'] ?? $opts['policy_url'] ?? '';

	// policy_label のデフォルト
	$policy_label = $field['policy_label']
		?? $opts['policy_label']
		?? __( 'Privacy Policy', 'hxfe-code-first-forms' );

	// フィールドのラベルテキスト（リンクを含むHTMLを組み立て）
	$label_text = $field['label'] ?? __( 'I agree to the privacy policy.', 'hxfe-code-first-forms' );

	ob_start();
	?>
	<div class="hxfe-field hxfe-privacy-field <?php echo $error ? 'hxfe-field--error' : ''; ?>">
		<label class="hxfe-checkbox-label">
			<input
				type="checkbox"
				id="hxfe-field-<?php echo esc_attr( $key ); ?>"
				name="<?php echo esc_attr( $key ); ?>"
				value="1"
				class="hxfe-checkbox"
				<?php checked( $checked ); ?>
				<?php echo $required ? 'required' : ''; ?>
				<?php
			if ( $error ) {
				echo 'aria-invalid="true" aria-describedby="hxfe-err-' . esc_attr( $key ) . '"'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_attr applied to $key
			}
			?>>

			<span class="hxfe-privacy-label-text">
				<?php echo esc_html( $label_text ); ?>
				<?php if ( '' !== $policy_url ) : ?>
					<a
						href="<?php echo esc_url( $policy_url ); ?>"
						target="_blank"
						rel="noopener noreferrer"
						class="hxfe-privacy-link">
						<?php echo esc_html( $policy_label ); ?>
					</a>
				<?php endif; ?>
				<?php if ( $required ) : ?>
					<span class="hxfe-required" aria-label="required">*</span>
				<?php endif; ?>
			</span>
		</label>

		<?php if ( $error ) : ?>
			<span id="hxfe-err-<?php echo esc_attr( $key ); ?>" class="hxfe-error-msg" role="alert">
				<?php echo esc_html( $error ); ?>
			</span>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

/* ---------------------------------------------------------------------------
 * サイト共通のプライバシー設定
 * ------------------------------------------------------------------------- */

add_action( 'admin_init', 'hxfe_privacy_register_settings' );

function hxfe_privacy_register_settings() {
	register_setting( 'hxfe_privacy_group', 'hxfe_privacy', [
		'sanitize_callback' => 'hxfe_privacy_sanitize',
	] );
}

function hxfe_privacy_sanitize( $input ) {
	if ( ! is_array( $input ) ) { return []; }
	return [
		'policy_url'   => esc_url_raw( $input['policy_url']   ?? '' ),
		'policy_label' => sanitize_text_field( $input['policy_label'] ?? '' ),
	];
}
