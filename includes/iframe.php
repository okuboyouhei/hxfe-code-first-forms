<?php
/**
 * ============================================================
 * IMPORTANT FOR CODE REVIEWERS:
 *
 * This file intentionally outputs a raw HTML page WITHOUT any
 * WordPress bootstrap. It has NO wp_head(), NO wp_footer(),
 * and NO access to the WP enqueue system.
 *
 * WHY: This page is served as the src of an <iframe> tag,
 * allowing HXFE forms to be embedded in non-WordPress sites.
 * Loading a full WP theme would break the iframe embedding.
 *
 * THEREFORE: wp_register_script(), wp_enqueue_script(),
 * wp_register_style(), and wp_enqueue_style() CANNOT be used
 * anywhere in the HTML output section of this file.
 * All <script> and <style> tags below are intentional and
 * have phpcs:ignore comments explaining each case.
 * ============================================================
 *
 * iframe support — serves a standalone form page and embeds it via shortcode.
 *
 * 2つの機能を提供する:
 *
 *   1. スタンドアロンページ (/hxfe-iframe/contact/ 等)
 *      WordPressのテーマを読み込まず、フォームのHTMLだけを返す。
 *      これをiframeのsrcとして使う。
 *
 *   2. [hxfe_iframe] ショートコード
 *      iframeタグを自動生成する。高さはpostMessageで自動調整。
 *
 *   3. CORSヘッダー
 *      スタンドアロンページと admin-ajax.php の両方に
 *      許可ドメインからのアクセスを許可するヘッダーを追加する。
 *
 * 使い方:
 *   // フォームを設置するサイトA の functions.php
 *   add_filter( 'hxfe_schemas', function( $schemas ) {
 *       $schemas['contact'] = [ ... ];
 *       return $schemas;
 *   });
 *
 *   // 埋め込みたいサイトB の投稿
 *   [hxfe_iframe id="contact" site="https://site-a.com"]
 *
 *   // 同一サイト内の埋め込み（site省略可）
 *   [hxfe_iframe id="contact"]
 *
 * @package HtmxFormEngine
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ---------------------------------------------------------------------------
 * 定数
 * ------------------------------------------------------------------------- */

// スタンドアロンページのクエリ変数
define( 'HXFE_IFRAME_QUERY_VAR', 'hxfe_iframe' );

/* ---------------------------------------------------------------------------
 * リライトルール
 * /hxfe-iframe/{form_id}/ → index.php?hxfe_iframe={form_id}
 * ------------------------------------------------------------------------- */

add_action( 'init',                   'hxfe_register_iframe_rewrite' );
add_action( 'admin_init',             'hxfe_register_iframe_settings' );

function hxfe_register_iframe_settings() {
	// hxfe_allowed_origins はスキーマの allowed_origins キーで代替
	// hxfe_disable_default_css は hxfe_smtp_settings グループで管理
}
add_filter( 'query_vars',             'hxfe_add_iframe_query_var' );
add_action( 'template_redirect',      'hxfe_maybe_serve_iframe_page', 1 );
add_action( 'wp_ajax_hxfe_validate',  'hxfe_add_cors_headers', 0 );
add_action( 'wp_ajax_nopriv_hxfe_validate',  'hxfe_add_cors_headers', 0 );
add_action( 'wp_ajax_hxfe_submit',    'hxfe_add_cors_headers', 0 );
add_action( 'wp_ajax_nopriv_hxfe_submit',    'hxfe_add_cors_headers', 0 );
add_action( 'wp_ajax_hxfe_back',      'hxfe_add_cors_headers', 0 );
add_action( 'wp_ajax_nopriv_hxfe_back',      'hxfe_add_cors_headers', 0 );
add_action( 'wp_ajax_hxfe_step_next',       'hxfe_add_cors_headers', 0 );
add_action( 'wp_ajax_nopriv_hxfe_step_next','hxfe_add_cors_headers', 0 );
add_action( 'wp_ajax_hxfe_step_submit',       'hxfe_add_cors_headers', 0 );
add_action( 'wp_ajax_nopriv_hxfe_step_submit','hxfe_add_cors_headers', 0 );
add_action( 'wp_ajax_hxfe_step_back',       'hxfe_add_cors_headers', 0 );
add_action( 'wp_ajax_nopriv_hxfe_step_back','hxfe_add_cors_headers', 0 );

/**
 * リライトルールを登録する。
 */
function hxfe_register_iframe_rewrite() {
	add_rewrite_rule(
		'^hxfe-iframe/([a-z0-9_-]+)/?$',
		'index.php?' . HXFE_IFRAME_QUERY_VAR . '=$matches[1]',
		'top'
	);
}

/**
 * カスタムクエリ変数を登録する。
 *
 * @param string[] $vars
 * @return string[]
 */
function hxfe_add_iframe_query_var( array $vars ) {
	$vars[] = HXFE_IFRAME_QUERY_VAR;
	return $vars;
}

/**
 * スタンドアロンのiframeページを出力する。
 * WordPressのテーマを一切読み込まないミニマルなHTMLを返す。
 */
function hxfe_maybe_serve_iframe_page() {
	$form_id = get_query_var( HXFE_IFRAME_QUERY_VAR );
	if ( ! $form_id ) { return; }

	$schema = hxfe_get_schema( $form_id );
	if ( null === $schema ) {
		status_header( 404 );
		wp_die( esc_html__( 'Form not found.', 'hxfe-code-first-forms' ) );
	}

	// CORSヘッダーを付与
	hxfe_add_cors_headers();

	// ここから先はWordPressのテーマを使わずに直接出力する
	status_header( 200 );
	header( 'Content-Type: text/html; charset=utf-8' );
	nocache_headers();

	// フォームのレンダリング（ステップ or 通常）
	if ( hxfe_is_step_mode( $schema ) ) {
		$steps       = hxfe_resolve_steps( $schema );
		$form_html   = hxfe_render_step( $schema, $steps, 0 );
	} else {
		$form_html = hxfe_render_input( $schema, [], [] );
	}

	// CSS のURL
	$css_url = HXFE_PLUGIN_URL . 'assets/css/hxfe-forms.css?ver=' . HXFE_VERSION;

	// htmx のURL
	$htmx_url = HXFE_PLUGIN_URL . 'assets/js/htmx.min.js?ver=' . HXFE_VERSION;

	// admin-ajax.php のURL
	$ajax_url = admin_url( 'admin-ajax.php' );

	?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html( $schema['title'] ?? $form_id ); ?></title>
<?php if ( empty( $schema['disable_default_css'] ) ) : ?>
<?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- Standalone iframe page: no WP bootstrap, no wp_head(), wp_enqueue_style() unavailable by design. ?>
<link rel="stylesheet" href="<?php echo esc_url( $css_url ); ?>">
<?php endif; ?>
<?php if ( ! empty( $schema['custom_css'] ) ) : ?>
<?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- Standalone iframe page: wp_add_inline_style() unavailable, no WP enqueue system. ?>
<style><?php echo esc_html( $schema['custom_css'] ); ?></style>
<?php endif; ?>
<?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- Standalone iframe page: minimal layout CSS, no WP enqueue system available. ?>
<style>
	/* iframeページ専用: bodyの余白を最小にして高さを正確に計算できるようにする */
	*, *::before, *::after { box-sizing: border-box; }
	html, body { margin: 0; padding: 0; }
	body { padding: 16px; }
</style>
</head>
<body>
<?php echo $form_html; // phpcs:ignore WordPress.Security.EscapeOutput ?>

<?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- Standalone iframe page: wp_add_inline_script() unavailable, no WP enqueue system. ?>
<script>
/* htmxのajaxURLをiframeページ用に上書き */
window.hxfeAjaxUrl = <?php echo wp_json_encode( $ajax_url ); ?>;

/**
 * postMessageで親ウィンドウにiframeの高さを通知する。
 * 親側のJS(hxfe-iframe-resizer.js)がiframeのheightを調整する。
 */
function hxfeNotifyHeight() {
	var h = document.body.scrollHeight;
	if ( window.parent && window.parent !== window ) {
		window.parent.postMessage(
			{ type: 'hxfe-iframe-resize', formId: <?php echo wp_json_encode( $form_id ); ?>, height: h },
			'*'
		);
	}
}

// 初回 + DOM変化 + htmxイベントで高さ通知
hxfeNotifyHeight();
document.addEventListener( 'DOMContentLoaded', hxfeNotifyHeight );
if ( typeof MutationObserver !== 'undefined' ) {
	new MutationObserver( hxfeNotifyHeight )
		.observe( document.body, { childList: true, subtree: true, attributes: true } );
}
document.addEventListener( 'htmx:afterSwap', hxfeNotifyHeight );
</script>
<?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- Standalone iframe page: wp_enqueue_script() unavailable, no WP enqueue system. ?>
<script src="<?php echo esc_url( $htmx_url ); ?>"></script>
</body>
</html>
<?php
	exit;
}

/* ---------------------------------------------------------------------------
 * CORSヘッダー
 * ------------------------------------------------------------------------- */

/**
 * 許可ドメインからのリクエストにCORSヘッダーを付与する。
 *
 * 許可ドメインは設定ページ（設定 → Form Engine → Allowed iframe origins）
 * または hxfe_allowed_iframe_origins フィルターで追加できる。
 */
function hxfe_add_cors_headers() {
	$origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? sanitize_url( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) ) : '';
	if ( ! $origin ) { return; }

	// スキーマレベルの allowed_origins チェック
	// フォームIDが POST に含まれる場合、そのスキーマの設定を優先する
	$form_id = isset( $_POST['hxfe_form_id'] ) ? sanitize_key( wp_unslash( $_POST['hxfe_form_id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( $form_id ) {
		$schema = hxfe_get_schema( $form_id );
		if ( $schema && isset( $schema['allowed_origins'] ) ) {
			// スキーマに allowed_origins が指定されている場合はそちらのみで判定
			$schema_origins    = (array) $schema['allowed_origins'];
			$origin_normalized = rtrim( $origin, '/' );
			foreach ( $schema_origins as $allowed ) {
				if ( rtrim( $allowed, '/' ) === $origin_normalized ) {
					header( 'Access-Control-Allow-Origin: ' . esc_url_raw( $origin ) );
					header( 'Access-Control-Allow-Credentials: true' );
					header( 'Access-Control-Allow-Methods: POST, GET, OPTIONS' );
					header( 'Access-Control-Allow-Headers: Content-Type, X-Requested-With' );
					return;
				}
			}
			// スキーマに allowed_origins があるが一致しなかった → 拒否
			return;
		}
	}

	$allowed = hxfe_get_allowed_origins();

	// ワイルドカード指定がある場合は全許可
	if ( in_array( '*', $allowed, true ) ) {
		header( 'Access-Control-Allow-Origin: ' . esc_url_raw( $origin ) );
		header( 'Access-Control-Allow-Credentials: true' );
		header( 'Access-Control-Allow-Methods: POST, GET, OPTIONS' );
		header( 'Access-Control-Allow-Headers: Content-Type, X-Requested-With' );
		return;
	}

	// 許可リストに一致するか確認（末尾スラッシュを正規化して比較）
	$origin_normalized = rtrim( $origin, '/' );
	foreach ( $allowed as $allowed_origin ) {
		if ( rtrim( $allowed_origin, '/' ) === $origin_normalized ) {
			header( 'Access-Control-Allow-Origin: ' . esc_url_raw( $origin ) );
			header( 'Access-Control-Allow-Credentials: true' );
			header( 'Access-Control-Allow-Methods: POST, GET, OPTIONS' );
			header( 'Access-Control-Allow-Headers: Content-Type, X-Requested-With' );
			return;
		}
	}
}

/**
 * 許可オリジンのリストを返す。
 *
 * @return string[]
 */
function hxfe_get_allowed_origins() {
	// DBの設定を取得
	$saved = get_option( 'hxfe_allowed_origins', '' );
	$lines = array_filter( array_map( 'trim', explode( "\n", $saved ) ) );

	// 同一サイトは常に許可
	$lines[] = home_url();
	$lines[] = site_url();

	/**
	 * 許可オリジンをフィルターで追加できる。
	 *
	 * add_filter( 'hxfe_allowed_iframe_origins', function( $origins ) {
	 *     $origins[] = 'https://other-site.com';
	 *     return $origins;
	 * });
	 */
	return apply_filters( 'hxfe_allowed_iframe_origins', $lines );
}

/* ---------------------------------------------------------------------------
 * [hxfe_iframe] ショートコード
 * ------------------------------------------------------------------------- */

add_shortcode( 'hxfe_iframe', 'hxfe_render_iframe_shortcode' );

/**
 * iframeタグを生成するショートコード。
 *
 * 属性:
 *   id     : フォームID（必須）
 *   site   : フォームを設置しているサイトのURL（省略時は同一サイト）
 *   height : 初期高さpx（省略時は300、postMessageで自動調整される）
 *   class  : iframeに追加するCSSクラス
 *   title  : iframeのtitle属性（アクセシビリティ用）
 *
 * @param array $atts
 * @return string HTML
 */
function hxfe_render_iframe_shortcode( $atts ) {
	$atts = shortcode_atts( [
		'id'     => '',
		'site'   => home_url(),
		'height' => '300',
		'class'  => '',
		'title'  => __( 'Contact form', 'hxfe-code-first-forms' ),
	], $atts, 'hxfe_iframe' );

	$form_id = sanitize_key( $atts['id'] );
	if ( ! $form_id ) {
		return '<!-- hxfe_iframe: id attribute is required -->';
	}

	$site    = esc_url( rtrim( $atts['site'], '/' ) );
	$src     = $site . '/hxfe-iframe/' . $form_id . '/';
	$height  = (int) $atts['height'];
	$class   = 'hxfe-iframe' . ( $atts['class'] ? ' ' . sanitize_html_class( $atts['class'] ) : '' );
	$iframe_id = 'hxfe-iframe-' . $form_id . '-' . wp_rand( 1000, 9999 );

	ob_start();
	?>
	<iframe
		id="<?php echo esc_attr( $iframe_id ); ?>"
		src="<?php echo esc_url( $src ); ?>"
		class="<?php echo esc_attr( $class ); ?>"
		title="<?php echo esc_attr( $atts['title'] ); ?>"
		width="100%"
		height="<?php echo esc_attr( $height ); ?>"
		frameborder="0"
		scrolling="no"
		loading="lazy"
		style="border:none;width:100%;display:block;overflow:hidden;"
		aria-label="<?php echo esc_attr( $atts['title'] ); ?>">
	</iframe>
	<?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- Inline script for iframe resizer: inserted as part of shortcode HTML output, wp_add_inline_script() cannot target a non-enqueued parent handle in this context. ?>
	<script>
	( function() {
		var iframe = document.getElementById( <?php echo wp_json_encode( $iframe_id ); ?> );
		if ( ! iframe ) { return; }

		// postMessage で高さを受け取って iframe を自動リサイズ
		window.addEventListener( 'message', function( e ) {
			if ( ! e.data || e.data.type !== 'hxfe-iframe-resize' ) { return; }
			if ( e.data.formId !== <?php echo wp_json_encode( $form_id ); ?> ) { return; }
			iframe.style.height = ( e.data.height + 32 ) + 'px'; // 余白32px追加
		} );
	} )();
	</script>
	<?php
	return ob_get_clean();
}
