<?php
/**
 * Access control — IP restriction and form-level password authentication.
 *
 * Two independent layers, both configured in the schema:
 *
 *   allowed_ips       (array)   Whitelist of IPs or CIDR ranges.
 *                               Empty = no restriction.
 *   ip_blocked_html   (string)  HTML shown when IP is blocked.
 *                               Defaults to a generic message.
 *
 *   auth (array)
 *     users (array)   List of [ 'id' => string, 'password' => string ]
 *                     Password may be a wp_hash_password() hash or a
 *                     plain-text constant reference (e.g. HXFE_STAFF_PASS).
 *     login_html (string|null)  Custom login form HTML. null = default.
 *
 * Security notes:
 *   - Session is stored in a short-lived, httponly, samesite=strict cookie.
 *   - Passwords are compared with wp_check_password() — bcrypt-safe.
 *   - Plain-text passwords are accepted for convenience but a warning is
 *     shown in the admin form list lint check.
 *   - Cookie value is a random token stored in a transient, not the password.
 *
 * Recommended wp-config.php pattern (keeps secrets out of Git):
 *   define( 'HXFE_STAFF_PASS', 'your-secret-password' );
 *
 * Schema example:
 *   'allowed_ips'     => [ '192.168.1.0/24', '203.0.113.5' ],
 *   'ip_blocked_html' => '<p>学内ネットワークからのみアクセスできます。</p>',
 *   'auth' => [
 *       'users' => [
 *           [ 'id' => 'staff', 'password' => defined('HXFE_STAFF_PASS') ? HXFE_STAFF_PASS : '' ],
 *       ],
 *       'login_html' => null,
 *   ],
 *
 * @package HtmxFormEngine
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ---------------------------------------------------------------------------
 * Public API
 * ------------------------------------------------------------------------- */

/**
 * Checks all access control layers for a schema.
 * Returns null if access is granted, or an HTML string to display if denied.
 *
 * Call this at the top of hxfe_shortcode_handler() before rendering.
 *
 * @param array $schema Form schema.
 * @return string|null  HTML to display on denial, or null on success.
 */
function hxfe_check_access( array $schema ) {
	// ① IP制限チェック
	if ( ! empty( $schema['allowed_ips'] ) ) {
		$client_ip = hxfe_get_client_ip();
		if ( ! hxfe_ip_is_allowed( $client_ip, $schema['allowed_ips'] ) ) {
			return hxfe_render_ip_blocked( $schema );
		}
	}

	// ② パスワード認証チェック
	if ( ! empty( $schema['auth']['users'] ) ) {
		$form_id = $schema['id'];

		// 既に認証済みかどうか確認
		if ( hxfe_auth_is_authenticated( $form_id ) ) {
			return null; // 認証済み → アクセス許可
		}

		// ログインフォームのPOST処理
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce検証は hxfe_auth_handle_login() 内で wp_verify_nonce() により実施済み
		if ( isset( $_POST['hxfe_auth_form_id'] ) && sanitize_key( wp_unslash( $_POST['hxfe_auth_form_id'] ) ) === $form_id ) {
			$result = hxfe_auth_handle_login( $schema );
			if ( true === $result ) {
				return null; // ログイン成功 → アクセス許可
			}
			// ログイン失敗 → ログイン画面を再表示（エラーあり）
			return hxfe_render_login_form( $schema, $result );
		}

		// 未認証 → ログイン画面を表示
		return hxfe_render_login_form( $schema );
	}

	return null; // アクセス制限なし
}

/* ---------------------------------------------------------------------------
 * IP restriction
 * ------------------------------------------------------------------------- */

/**
 * Returns the client's real IP address.
 * Respects common proxy headers when present.
 *
 * @return string
 */
function hxfe_get_client_ip() {
	$headers = [
		'HTTP_CF_CONNECTING_IP', // Cloudflare
		'HTTP_X_FORWARDED_FOR',
		'HTTP_X_REAL_IP',
		'REMOTE_ADDR',
	];
	foreach ( $headers as $header ) {
		if ( ! empty( $_SERVER[ $header ] ) ) {
			// X-Forwarded-For may contain a comma-separated list; take the first.
			$ip = trim( explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) ) )[0] );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return $ip;
			}
		}
	}
	return '0.0.0.0';
}

/**
 * Returns the direct client IP from REMOTE_ADDR only.
 *
 * Used for rate-limiting keys to prevent spoofing via proxy headers.
 * Unlike hxfe_get_client_ip(), this function intentionally ignores
 * X-Forwarded-For and similar headers that can be set by the client.
 *
 * @return string
 */
function hxfe_get_remote_addr() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
}

/**
 * Returns true if $ip matches any entry in $allowed list.
 * Supports exact IPs and CIDR notation (e.g. 192.168.1.0/24).
 *
 * @param string   $ip      Client IP.
 * @param string[] $allowed List of IPs or CIDR ranges.
 * @return bool
 */
function hxfe_ip_is_allowed( string $ip, array $allowed ) {
	foreach ( $allowed as $entry ) {
		$entry = trim( $entry );
		if ( str_contains( $entry, '/' ) ) {
			if ( hxfe_ip_in_cidr( $ip, $entry ) ) {
				return true;
			}
		} else {
			if ( $ip === $entry ) {
				return true;
			}
		}
	}
	return false;
}

/**
 * Returns true if $ip falls within the $cidr range.
 * Supports both IPv4 and IPv6.
 *
 * @param string $ip   Client IP.
 * @param string $cidr CIDR range (e.g. 192.168.1.0/24).
 * @return bool
 */
function hxfe_ip_in_cidr( string $ip, string $cidr ) {
	[ $range, $prefix ] = explode( '/', $cidr, 2 );
	$prefix = (int) $prefix;

	// IPv6
	if ( str_contains( $ip, ':' ) || str_contains( $range, ':' ) ) {
		$ip_bin    = inet_pton( $ip );
		$range_bin = inet_pton( $range );
		if ( false === $ip_bin || false === $range_bin ) {
			return false;
		}
		$bits      = 128;
		$ip_dec    = gmp_import( $ip_bin );
		$range_dec = gmp_import( $range_bin );
		$mask      = gmp_sub( gmp_pow( 2, $bits ), gmp_pow( 2, $bits - $prefix ) );
		return gmp_cmp( gmp_and( $ip_dec, $mask ), gmp_and( $range_dec, $mask ) ) === 0;
	}

	// IPv4
	$ip_long    = ip2long( $ip );
	$range_long = ip2long( $range );
	if ( false === $ip_long || false === $range_long ) {
		return false;
	}
	$mask = $prefix > 0 ? ( ~0 << ( 32 - $prefix ) ) : 0;
	return ( $ip_long & $mask ) === ( $range_long & $mask );
}

/**
 * Renders the IP blocked message.
 *
 * @param array $schema Form schema.
 * @return string HTML.
 */
function hxfe_render_ip_blocked( array $schema ) {
	if ( ! empty( $schema['ip_blocked_html'] ) ) {
		return wp_kses_post( $schema['ip_blocked_html'] );
	}
	return '<div class="hxfe-wrap hxfe-access-denied">'
		. '<p>' . esc_html__( 'This form is not available from your network.', 'hxfe-code-first-forms' ) . '</p>'
		. '</div>';
}

/* ---------------------------------------------------------------------------
 * Password authentication
 * ------------------------------------------------------------------------- */

/**
 * Cookie name for a given form.
 *
 * @param string $form_id Form ID.
 * @return string
 */
function hxfe_auth_cookie_name( string $form_id ) {
	return 'hxfe_auth_' . sanitize_key( $form_id );
}

/**
 * Returns true if the visitor holds a valid auth token for this form.
 *
 * @param string $form_id Form ID.
 * @return bool
 */
function hxfe_auth_is_authenticated( string $form_id ) {
	$cookie_name = hxfe_auth_cookie_name( $form_id );
	if ( empty( $_COOKIE[ $cookie_name ] ) ) {
		return false;
	}
	$token        = sanitize_text_field( wp_unslash( $_COOKIE[ $cookie_name ] ) );
	$stored_token = get_transient( 'hxfe_auth_' . $form_id . '_' . $token );
	return false !== $stored_token;
}

/**
 * Handles the login form POST.
 * Returns true on success, or an error message string on failure.
 *
 * @param array $schema Form schema.
 * @return true|string
 */
function hxfe_auth_handle_login( array $schema ) {
	// nonce検証
	$nonce = isset( $_POST['hxfe_auth_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['hxfe_auth_nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'hxfe_auth_' . $schema['id'] ) ) {
		return __( 'Invalid request. Please try again.', 'hxfe-code-first-forms' );
	}

	$posted_id   = isset( $_POST['hxfe_auth_id'] )       ? sanitize_text_field( wp_unslash( $_POST['hxfe_auth_id'] ) )       : '';
	$posted_pass = isset( $_POST['hxfe_auth_password'] )  ? sanitize_text_field( wp_unslash( $_POST['hxfe_auth_password'] ) ) : '';

	// ブルートフォース対策: 失敗回数チェック
	// REMOTE_ADDR のみ使用 — proxy ヘッダはクライアントが偽装できるため。
	$attempts_key = 'hxfe_auth_attempts_' . $schema['id'] . '_' . md5( hxfe_get_remote_addr() );
	$attempts     = (int) get_transient( $attempts_key );
	if ( $attempts >= 5 ) {
		return __( 'Too many failed attempts. Please wait 15 minutes and try again.', 'hxfe-code-first-forms' );
	}

	// ユーザー照合
	foreach ( $schema['auth']['users'] as $user ) {
		$user_id   = $user['id']       ?? '';
		$user_pass = $user['password'] ?? '';

		if ( '' === $user_pass ) {
			continue; // パスワード未設定のユーザーはスキップ
		}

		if ( $posted_id !== $user_id ) {
			continue;
		}

		// wp_check_password はハッシュ済みパスワードと平文の両方に対応
		if ( wp_check_password( $posted_pass, $user_pass ) || $posted_pass === $user_pass ) {
			// ログイン成功 — トークン発行
			$token = wp_generate_password( 32, false );
			set_transient( 'hxfe_auth_' . $schema['id'] . '_' . $token, 1, 8 * HOUR_IN_SECONDS );
			delete_transient( $attempts_key );

			// httponly / samesite=strict cookie
			$cookie_name = hxfe_auth_cookie_name( $schema['id'] );
			setcookie(
				$cookie_name,
				$token,
				[
					'expires'  => 0, // ブラウザを閉じるまで
					'path'     => COOKIEPATH,
					'domain'   => COOKIE_DOMAIN,
					'secure'   => is_ssl(),
					'httponly' => true,
					'samesite' => 'Strict',
				]
			);
			return true;
		}
	}

	// 認証失敗
	set_transient( $attempts_key, $attempts + 1, 15 * MINUTE_IN_SECONDS );
	return __( 'Invalid ID or password.', 'hxfe-code-first-forms' );
}

/**
 * Renders the login form.
 *
 * @param array       $schema Form schema.
 * @param string|null $error  Error message to display, or null.
 * @return string HTML.
 */
function hxfe_render_login_form( array $schema, ?string $error = null ) {
	// カスタムHTMLが指定されていればそちらを使う
	if ( ! empty( $schema['auth']['login_html'] ) ) {
		return wp_kses_post( $schema['auth']['login_html'] );
	}

	$form_id   = esc_attr( $schema['id'] );
	$nonce     = wp_create_nonce( 'hxfe_auth_' . $schema['id'] );
	$login_label = esc_html( $schema['auth']['login_label'] ?? __( 'Login required', 'hxfe-code-first-forms' ) );

	ob_start();
	?>
	<div class="hxfe-wrap hxfe-auth-wrap" id="hxfe-auth-<?php echo esc_attr( $form_id ); ?>">
		<h2 class="hxfe-auth-title"><?php echo esc_html( $login_label ); ?></h2>

		<?php if ( $error ) : ?>
			<div class="hxfe-error-summary" role="alert">
				<p><?php echo esc_html( $error ); ?></p>
			</div>
		<?php endif; ?>

		<form method="post" class="hxfe-form hxfe-auth-form">
			<?php wp_nonce_field( 'hxfe_auth_' . $schema['id'], 'hxfe_auth_nonce' ); ?>
			<input type="hidden" name="hxfe_auth_form_id" value="<?php echo esc_attr( $form_id ); ?>">

			<div class="hxfe-field">
				<label for="hxfe-auth-id-<?php echo esc_attr( $form_id ); ?>" class="hxfe-label">
					<?php echo esc_html( $schema['auth']['id_label'] ?? __( 'ID', 'hxfe-code-first-forms' ) ); ?>
				</label>
				<input
					type="text"
					id="hxfe-auth-id-<?php echo esc_attr( $form_id ); ?>"
					name="hxfe_auth_id"
					class="hxfe-input"
					autocomplete="username"
					required>
			</div>

			<div class="hxfe-field">
				<label for="hxfe-auth-pass-<?php echo esc_attr( $form_id ); ?>" class="hxfe-label">
					<?php echo esc_html( $schema['auth']['password_label'] ?? __( 'Password', 'hxfe-code-first-forms' ) ); ?>
				</label>
				<input
					type="password"
					id="hxfe-auth-pass-<?php echo esc_attr( $form_id ); ?>"
					name="hxfe_auth_password"
					class="hxfe-input"
					autocomplete="current-password"
					required>
			</div>

			<div class="hxfe-actions">
				<button type="submit" class="hxfe-btn hxfe-btn-submit">
					<?php echo esc_html( $schema['auth']['submit_label'] ?? __( 'Login', 'hxfe-code-first-forms' ) ); ?>
				</button>
			</div>
		</form>
	</div>
	<?php
	return ob_get_clean();
}

/* ---------------------------------------------------------------------------
 * 公開期間チェック
 * ------------------------------------------------------------------------- */

/**
 * スキーマの available_from / available_until を検証する。
 *
 * @param array $schema スキーマ定義。
 * @return string|null 期間外なら表示用HTML、期間内なら null。
 */
function hxfe_check_availability( array $schema ): ?string {
	$from  = $schema['available_from']  ?? '';
	$until = $schema['available_until'] ?? '';

	if ( '' === $from && '' === $until ) {
		return null; // 制限なし
	}

	$tz  = wp_timezone();
	$now = new DateTimeImmutable( 'now', $tz );

	// 開始日時チェック
	if ( '' !== $from ) {
		try {
			$from_dt = new DateTimeImmutable( $from, $tz );
			if ( $now < $from_dt ) {
				$html = $schema['before_html']
					?? '<p>' . esc_html__( 'This form is not yet available.', 'hxfe-code-first-forms' ) . '</p>';
				return '<div class="hxfe-wrap"><div class="hxfe-availability-notice">'
					. wp_kses_post( $html ) . '</div></div>';
			}
		} catch ( \Exception $e ) {
			// 日時フォーマット不正は無視
		}
	}

	// 終了日時チェック
	if ( '' !== $until ) {
		try {
			$until_dt = new DateTimeImmutable( $until, $tz );
			if ( $now > $until_dt ) {
				$html = $schema['after_html']
					?? '<p>' . esc_html__( 'This form is now closed.', 'hxfe-code-first-forms' ) . '</p>';
				return '<div class="hxfe-wrap"><div class="hxfe-availability-notice">'
					. wp_kses_post( $html ) . '</div></div>';
			}
		} catch ( \Exception $e ) {
			// 日時フォーマット不正は無視
		}
	}

	return null; // 期間内
}
