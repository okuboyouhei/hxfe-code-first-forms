<?php
/**
 * reCAPTCHA v2 / v3 integration.
 *
 * スキーマへの追加方法:
 *
 *   // v2 (チェックボックス型)
 *   [ 'key' => 'recaptcha', 'type' => 'recaptcha', 'version' => 'v2' ]
 *
 *   // v3 (スコア型・非表示)
 *   [ 'key' => 'recaptcha', 'type' => 'recaptcha', 'version' => 'v3',
 *     'action' => 'submit', 'threshold' => 0.5 ]
 *
 * サイトキー / シークレットキーは Settings → HXFE で設定する。
 * スキーマに直接書くこともできる:
 *   [ 'type' => 'recaptcha', 'site_key' => '...', 'secret_key' => '...' ]
 *
 * @package HtmxFormEngine
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ---------------------------------------------------------------------------
 * 設定取得
 * ------------------------------------------------------------------------- */

/**
 * reCAPTCHA の設定を返す。
 * スキーマのフィールド定義 > wp_options の順で優先する。
 *
 * @param array $field フィールド定義。
 * @return array{ version: string, site_key: string, secret_key: string, threshold: float, action: string }
 */
function hxfe_recaptcha_config( array $field ) {
	$opts    = get_option( 'hxfe_recaptcha', [] );
	$version = $field['version'] ?? $opts['version'] ?? 'v2';

	if ( 'v3' === $version ) {
		return [
			'version'    => 'v3',
			'site_key'   => $field['site_key']   ?? $opts['v3_site_key']   ?? '',
			'secret_key' => $field['secret_key'] ?? $opts['v3_secret_key'] ?? '',
			'threshold'  => (float) ( $field['threshold'] ?? $opts['v3_threshold'] ?? 0.5 ),
			'action'     => $field['action'] ?? 'hxfe_submit',
		];
	}

	return [
		'version'    => 'v2',
		'site_key'   => $field['site_key']   ?? $opts['v2_site_key']   ?? '',
		'secret_key' => $field['secret_key'] ?? $opts['v2_secret_key'] ?? '',
		'threshold'  => 0.5,
		'action'     => '',
	];
}

/* ---------------------------------------------------------------------------
 * HTML 出力
 * ------------------------------------------------------------------------- */

/**
 * reCAPTCHA フィールドの HTML を返す。
 *
 * v2: Google のチェックボックス Widget を描画する。
 * v3: 非表示の hidden フィールド。JS がトークンを自動注入する。
 *
 * @param array  $field  フィールド定義。
 * @param string $error  エラーメッセージ（あれば）。
 * @return string HTML。
 */
function hxfe_render_recaptcha_field( array $field, string $error = '' ) {
	$cfg      = hxfe_recaptcha_config( $field );
	$site_key = $cfg['site_key'];

	if ( '' === $site_key ) {
		return '<p style="color:#d63638">[HXFE] reCAPTCHA site key is not configured.</p>';
	}

	ob_start();

	if ( 'v3' === $cfg['version'] ) {
		// v3: 非表示フィールド。JS がページロード時にトークンをセットする。
		?>
		<div class="hxfe-field hxfe-recaptcha-v3">
			<input type="hidden"
				id="hxfe_recaptcha_token"
				name="hxfe_recaptcha_token"
				value="">
			<input type="hidden" name="hxfe_recaptcha_version" value="v3">
			<input type="hidden" name="hxfe_recaptcha_action"  value="<?php echo esc_attr( $cfg['action'] ); ?>">
		</div>
		<?php
	} else {
		// v2: チェックボックス Widget
		?>
		<div class="hxfe-field hxfe-recaptcha-v2 <?php echo $error ? 'hxfe-field--error' : ''; ?>">
			<div class="g-recaptcha" data-sitekey="<?php echo esc_attr( $site_key ); ?>"></div>
			<input type="hidden" name="hxfe_recaptcha_version" value="v2">
			<?php if ( $error ) : ?>
				<span class="hxfe-error-msg" role="alert"><?php echo esc_html( $error ); ?></span>
			<?php endif; ?>
		</div>
		<?php
	}

	return ob_get_clean();
}

/**
 * reCAPTCHA 用 JS を enqueue する。
 * shortcode.php の hxfe_enqueue_assets() から呼ばれる。
 *
 * @param array $schema フォームスキーマ。
 */
function hxfe_enqueue_recaptcha_scripts( array $schema ) {
	// recaptcha フィールドを含むか確認
	$recaptcha_field = null;
	foreach ( $schema['fields'] as $field ) {
		if ( ( $field['type'] ?? '' ) === 'recaptcha' ) {
			$recaptcha_field = $field;
			break;
		}
	}
	if ( null === $recaptcha_field ) { return; }

	$cfg = hxfe_recaptcha_config( $recaptcha_field );
	if ( '' === $cfg['site_key'] ) { return; }

	if ( 'v3' === $cfg['version'] ) {
		// v3: grecaptcha.execute() でトークンを取得してhiddenにセット
		wp_enqueue_script(
			'google-recaptcha-v3', // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- external Google CDN, version controlled by Google
			'https://www.google.com/recaptcha/api.js?render=' . rawurlencode( $cfg['site_key'] ),
			[],
			HXFE_VERSION,
			true
		);
		wp_add_inline_script( 'google-recaptcha-v3', sprintf( // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- external Google CDN, version controlled by Google
			'grecaptcha.ready(function(){
				grecaptcha.execute(%s,{action:%s}).then(function(token){
					var el=document.getElementById("hxfe_recaptcha_token");
					if(el){el.value=token;}
				});
			});',
			wp_json_encode( $cfg['site_key'] ),
			wp_json_encode( $cfg['action'] )
		) );
	} else {
		// v2: api.js を読み込むだけで Widget は自動描画
		wp_enqueue_script(
			'google-recaptcha-v2', // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- external Google CDN, version controlled by Google
			'https://www.google.com/recaptcha/api.js',
			[],
			HXFE_VERSION,
			true
		);
	}
}

/* ---------------------------------------------------------------------------
 * サーバーサイド検証
 * ------------------------------------------------------------------------- */

/**
 * reCAPTCHA トークンをサーバーサイドで検証する。
 *
 * @param array  $field  フィールド定義。
 * @param string $token  クライアントから送られたトークン。
 * @return array{ value: string, error: string }
 */
function hxfe_validate_recaptcha( array $field, string $token ) {
	$cfg = hxfe_recaptcha_config( $field );

	if ( '' === $cfg['secret_key'] ) {
		// シークレットキー未設定。
		// 開発環境（WP_DEBUG有効）では検証をスキップして開発を妨げない。
		// 本番環境では fail-closed にして「保護しているつもりで素通し」を防ぐ。
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			return [ 'value' => 'skipped', 'error' => '' ];
		}
		return [
			'value' => '',
			'error' => __( 'Spam protection is not configured. Please contact the site administrator.', 'hxfe-code-first-forms' ),
		];
	}

	if ( '' === $token ) {
		return [
			'value' => '',
			'error' => __( 'Please complete the reCAPTCHA verification.', 'hxfe-code-first-forms' ),
		];
	}

	// Google の検証APIにリクエスト
	$response = wp_remote_post( 'https://www.google.com/recaptcha/api/siteverify', [
		'body' => [
			'secret'   => $cfg['secret_key'],
			'response' => $token,
			'remoteip' => sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) ) ?? '',
		],
		'timeout' => 10,
	] );

	if ( is_wp_error( $response ) ) {
		return [
			'value' => '',
			'error' => __( 'reCAPTCHA verification failed. Please try again.', 'hxfe-code-first-forms' ),
		];
	}

	$body   = json_decode( wp_remote_retrieve_body( $response ), true );
	$success = ! empty( $body['success'] );

	// v3: スコアのしきい値チェック
	if ( 'v3' === $cfg['version'] && $success ) {
		$score = (float) ( $body['score'] ?? 0 );
		if ( $score < $cfg['threshold'] ) {
			return [
				'value' => '',
				'error' => __( 'reCAPTCHA score too low. Please try again.', 'hxfe-code-first-forms' ),
			];
		}
	}

	if ( ! $success ) {
		return [
			'value' => '',
			'error' => __( 'reCAPTCHA verification failed. Please try again.', 'hxfe-code-first-forms' ),
		];
	}

	return [ 'value' => 'verified', 'error' => '' ];
}

/* ---------------------------------------------------------------------------
 * 設定ページ（既存の Settings API に統合）
 * ------------------------------------------------------------------------- */

add_action( 'admin_init', 'hxfe_recaptcha_register_settings' );

function hxfe_recaptcha_register_settings() {
	register_setting( 'hxfe_recaptcha_group', 'hxfe_recaptcha', [
		'sanitize_callback' => 'hxfe_recaptcha_sanitize',
	] );
}

function hxfe_recaptcha_sanitize( $input ) {
	if ( ! is_array( $input ) ) { return []; }
	return [
		'version'       => in_array( $input['version'] ?? 'v2', [ 'v2', 'v3' ], true )
			? $input['version'] : 'v2',
		'v2_site_key'   => sanitize_text_field( $input['v2_site_key']   ?? '' ),
		'v2_secret_key' => sanitize_text_field( $input['v2_secret_key'] ?? '' ),
		'v3_site_key'   => sanitize_text_field( $input['v3_site_key']   ?? '' ),
		'v3_secret_key' => sanitize_text_field( $input['v3_secret_key'] ?? '' ),
		'v3_threshold'  => max( 0.1, min( 1.0, (float) ( $input['v3_threshold'] ?? 0.5 ) ) ),
	];
}
