<?php
/**
 * Shortcode registration and asset enqueuing.
 *
 * Usage: [hxfe_form id="contact"]
 *
 * The shortcode outputs the initial input form HTML and ensures htmx.min.js
 * is enqueued exactly once per page load.
 *
 * @package HtmxFormEngine
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_shortcode( 'hxfe_form', 'hxfe_shortcode_handler' );

/**
 * Handles [hxfe_form id="..."].
 *
 * @param array $atts Shortcode attributes.
 * @return string HTML output.
 */
function hxfe_shortcode_handler( $atts ) {
	$atts = shortcode_atts( [ 'id' => '' ], $atts, 'hxfe_form' );
	$id   = sanitize_key( $atts['id'] );

	if ( '' === $id ) {
		return '<!-- hxfe_form: missing id attribute -->';
	}

	$schema = hxfe_get_schema( $id );
	if ( null === $schema ) {
		return '<!-- hxfe_form: unknown form id "' . esc_html( $id ) . '" -->';
	}

	// 同一ページに同じIDのショートコードが複数置かれた場合、
	// id="hxfe-{form_id}" が重複してHTMLとして不正になり、htmxのターゲットが壊れる。
	static $rendered_ids = [];
	if ( in_array( $id, $rendered_ids, true ) ) {
		return '<!-- hxfe_form: duplicate id "' . esc_html( $id ) . '" on this page. Each form id must appear only once per page. -->';
	}
	$rendered_ids[] = $id;

	// ページスラッグを context として付与（subject の自動付与に使用）
	$post_slug = get_post_field( 'post_name', get_the_ID() );
	if ( $post_slug && empty( $schema['disable_context'] ) ) {
		$schema['_context'] = sanitize_key( $post_slug );
	}

	// アセットを先にenqueue（ログイン画面でもCSSが当たるように）
	hxfe_enqueue_assets( $schema );

	// アクセス制御チェック（IP制限・パスワード認証）
	$access_result = hxfe_check_access( $schema );
	if ( null !== $access_result ) {
		return $access_result;
	}

	// 公開期間チェック
	$availability = hxfe_check_availability( $schema );
	if ( null !== $availability ) {
		return $availability;
	}

	hxfe_enqueue_assets( $schema );
	hxfe_enqueue_recaptcha_scripts( $schema );

	// chatbotモード
	if ( hxfe_is_chatbot_mode( $schema ) ) {
		return hxfe_render_chatbot( $schema );
	}

	// ステップモードか従来モードかで出力を切り替える
	if ( hxfe_is_step_mode( $schema ) ) {
		$steps = hxfe_resolve_steps( $schema );
		return hxfe_render_step( $schema, $steps, 0 );
	}

	return hxfe_render_input( $schema );
}

/**
 * Enqueues htmx and the plugin stylesheet.
 * Safe to call multiple times — WordPress deduplicates by handle.
 */
function hxfe_enqueue_assets( array $schema = [] ) {
	$form_id = $schema['id'] ?? '';

	wp_enqueue_script(
		'hxfe-htmx',
		HXFE_PLUGIN_URL . 'assets/js/htmx.min.js',
		[],
		HXFE_HTMX_VERSION,
		true
	);

	wp_enqueue_script(
		'hxfe-front',
		HXFE_PLUGIN_URL . 'assets/js/hxfe-front.js',
		[ 'hxfe-htmx' ],
		HXFE_VERSION,
		true
	);

	// chatbot.js はchatbotモードのフォームがあるページのみ読み込む
	if ( hxfe_is_chatbot_mode( $schema ) ) {
		wp_enqueue_script(
			'hxfe-chatbot',
			HXFE_PLUGIN_URL . 'assets/js/hxfe-chatbot.js',
			[ 'hxfe-htmx' ],
			HXFE_VERSION,
			true
		);
	}

	wp_enqueue_script(
		'hxfe-conditions',
		HXFE_PLUGIN_URL . 'assets/js/hxfe-conditions.js',
		[],
		HXFE_VERSION,
		true
	);

	// スキーマまたはグローバル設定でデフォルトCSSを無効化できる
	$disable_css = ! empty( $schema['disable_default_css'] )
		|| (bool) get_option( 'hxfe_disable_default_css', false );

	if ( ! $disable_css ) {
		wp_enqueue_style(
			'hxfe-forms',
			HXFE_PLUGIN_URL . 'assets/css/hxfe-forms.css',
			[],
			HXFE_VERSION
		);
	}

	// スキーマの custom_css をそのページにインラインで注入
	if ( ! empty( $schema['custom_css'] ) ) {
		$handle = 'hxfe-forms';
		if ( $disable_css ) {
			// デフォルトCSS無効時はダミーハンドルを登録してインラインを添付
			wp_register_style( 'hxfe-inline-' . $form_id, false, [], HXFE_VERSION );
			wp_enqueue_style(  'hxfe-inline-' . $form_id );
			$handle = 'hxfe-inline-' . $form_id;
		}
		wp_add_inline_style( $handle, wp_strip_all_tags( $schema['custom_css'] ) );
	}
}
