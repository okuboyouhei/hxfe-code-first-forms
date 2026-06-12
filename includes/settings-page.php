<?php
/**
 * Settings page — reCAPTCHA keys and privacy policy URL.
 *
 * 設定 → HXFE Settings でサイト共通の設定を管理する。
 * フィールド定義で上書きすることも可能。
 *
 * @package HtmxFormEngine
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'admin_menu', 'hxfe_add_settings_page' );
add_action( 'admin_enqueue_scripts', 'hxfe_enqueue_admin_assets' );

/**
 * Enqueue admin-only inline scripts and styles for HXFE settings pages.
 *
 * @param string $hook Current admin page hook.
 */
function hxfe_enqueue_admin_assets( $hook ) {
	// SMTP settings page: provider preset switcher.
	if ( 'settings_page_hxfe-settings' === $hook ) {
		$s       = hxfe_get_smtp_settings();
		$presets = [
			'gmail'     => [ 'host' => 'smtp.gmail.com',      'port' => 587, 'enc' => 'tls' ],
			'outlook'   => [ 'host' => 'smtp.office365.com',  'port' => 587, 'enc' => 'tls' ],
			'yahoo'     => [ 'host' => 'smtp.mail.yahoo.com', 'port' => 587, 'enc' => 'tls' ],
			'sendgrid'  => [ 'host' => 'smtp.sendgrid.net',   'port' => 587, 'enc' => 'tls' ],
			'mailgun'   => [ 'host' => 'smtp.mailgun.org',    'port' => 587, 'enc' => 'tls' ],
			'ses'       => [ 'host' => 'email-smtp.us-east-1.amazonaws.com', 'port' => 587, 'enc' => 'tls' ],
			'custom'    => [ 'host' => '',                    'port' => 587, 'enc' => 'tls' ],
		];
		$preset_data = array_map( function( $p ) {
			return [ 'host' => $p['host'], 'port' => $p['port'], 'enc' => $p['enc'] ];
		}, $presets );

		wp_register_script( 'hxfe-smtp-settings', false, [], HXFE_VERSION, true );
		wp_enqueue_script( 'hxfe-smtp-settings' );
		wp_localize_script( 'hxfe-smtp-settings', 'hxfeSmtpPresets', $preset_data );
		wp_add_inline_script(
			'hxfe-smtp-settings',
			'( function() {
				var presets = hxfeSmtpPresets;
				var $provider = document.getElementById( "hxfe-smtp-provider" );
				if ( ! $provider ) { return; }
				$provider.addEventListener( "change", function() {
					var val    = this.value;
					var preset = presets[ val ] || presets.custom;
					var host = document.getElementById( "hxfe-smtp-host" );
					var port = document.getElementById( "hxfe-smtp-port" );
					var enc  = document.querySelector( "[name=\"hxfe_smtp_settings[encryption]\"]" );
					if ( host && preset.host ) { host.value = preset.host; }
					if ( port )                { port.value = preset.port; }
					if ( enc  )                { enc.value  = preset.enc;  }
					document.querySelectorAll( ".hxfe-smtp-note" ).forEach( function( el ) {
						el.style.display = "none";
					} );
					var note = document.getElementById( "hxfe-note-" + val );
					if ( note ) { note.style.display = ""; }
				} );
			} )();'
		);
	}

	// Form list page: copy shortcode button + stat card styles.
	if ( 'settings_page_hxfe-form-list' === $hook ) {
		wp_register_script( 'hxfe-form-list', false, [], HXFE_VERSION, true );
		wp_enqueue_script( 'hxfe-form-list' );
		wp_add_inline_script(
			'hxfe-form-list',
			'function hxfeCopyShortcode( text, btn ) {
				var orig = btn.textContent;
				function onSuccess() {
					btn.textContent = "\u2713 Copied!";
					btn.style.background = "#00a32a";
					setTimeout( function() {
						btn.textContent = orig;
						btn.style.background = "";
					}, 1500 );
				}
				if ( navigator.clipboard && navigator.clipboard.writeText ) {
					navigator.clipboard.writeText( text ).then( onSuccess );
				} else {
					var ta = document.createElement( "textarea" );
					ta.value = text;
					ta.style.cssText = "position:fixed;top:0;left:0;opacity:0";
					document.body.appendChild( ta );
					ta.focus();
					ta.select();
					try { document.execCommand( "copy" ); onSuccess(); } catch(e) {}
					document.body.removeChild( ta );
				}
			}'
		);

		wp_register_style( 'hxfe-form-list', false, [], HXFE_VERSION );
		wp_enqueue_style( 'hxfe-form-list' );
		wp_add_inline_style(
			'hxfe-form-list',
			'.hxfe-stat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:20px}
.hxfe-stat{background:#fff;border:1px solid #e2e8f0;border-radius:6px;padding:12px 16px}
.hxfe-stat__label{font-size:11px;font-weight:500;color:#64748b;text-transform:uppercase;letter-spacing:.06em;margin-bottom:4px}
.hxfe-stat__value{font-size:20px;font-weight:500;color:#0f172a}
.hxfe-stat__value--warn{color:#b45309}
.hxfe-form-list{border-collapse:collapse;width:100%;background:#fff;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;margin-top:0}
.hxfe-form-list thead th{background:#f8fafc;font-size:11px;font-weight:500;color:#64748b;text-transform:uppercase;letter-spacing:.06em;padding:10px 16px;border-bottom:1px solid #e2e8f0;text-align:left;white-space:nowrap}
.hxfe-form-list tbody td{padding:14px 16px;border-bottom:1px solid #e2e8f0;vertical-align:top;font-size:13px;color:#0f172a}
.hxfe-form-list tbody tr:last-child td{border-bottom:none}
.hxfe-form-list tbody tr:hover td{background:#f8fafc}
.hxfe-badge{display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:500;padding:3px 8px;border-radius:20px}
.hxfe-badge--normal{background:#e8f0fe;color:#1a56db}
.hxfe-badge--step{background:#fef3c7;color:#92400e}
.hxfe-badge--one{background:#d1fae5;color:#065f46}
.hxfe-badge--chatbot{background:#ede9fe;color:#5b21b6}
.hxfe-field-list{margin:0;padding:0;list-style:none;display:flex;flex-direction:column;gap:4px}
.hxfe-field-list li{display:flex;align-items:center;gap:6px;font-size:12px;color:#475569}
.hxfe-tag{display:inline-block;font-size:10px;padding:1px 5px;border-radius:3px}
.hxfe-tag--hp{background:#d1fae5;color:#065f46}
.hxfe-tag--warn{background:#fef3c7;color:#92400e}
.hxfe-tag--ok{background:#e8f0fe;color:#1a56db}
.hxfe-field-type{font-family:monospace;font-size:11px;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:3px;padding:1px 5px;color:#0f172a}
.hxfe-warn-list{margin:6px 0 0;padding:0 0 0 14px;font-size:11px;color:#92400e}
.hxfe-warn-list li{margin:2px 0}
.hxfe-shortcode-wrap{display:flex;flex-direction:column;gap:6px}
.hxfe-sc-row{display:flex;align-items:center;gap:6px}
.hxfe-shortcode-code{font-family:monospace;font-size:11px;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:3px;padding:3px 7px;color:#0f172a;white-space:nowrap}
.hxfe-copy-btn{font-size:11px;padding:3px 8px;cursor:pointer;border:1px solid #cbd5e1;border-radius:4px;background:#fff;color:#475569;white-space:nowrap;transition:background .12s,color .12s}
.hxfe-copy-btn:hover{background:#f1f5f9;color:#0f172a;border-color:#94a3b8}
.hxfe-samples{display:flex;flex-direction:column;gap:12px}
.hxfe-sample-block{border:1px solid #e2e8f0;border-radius:6px;overflow:hidden}
.hxfe-sample-header{display:flex;align-items:center;justify-content:space-between;padding:8px 14px;background:#f8fafc;border-bottom:1px solid #e2e8f0}
.hxfe-sample-code{margin:0;padding:12px 16px;font-size:12px;line-height:1.6;overflow-x:auto;background:#fff;white-space:pre;font-family:monospace}'
		);
	}
}

function hxfe_add_settings_page() {
	add_options_page(
		__( 'HXFE Settings', 'hxfe-code-first-forms' ),
		__( 'HXFE Settings', 'hxfe-code-first-forms' ),
		'manage_options',
		'hxfe-settings',
		'hxfe_render_settings_page'
	);
}

function hxfe_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$rc  = get_option( 'hxfe_recaptcha', [] );
	$prv = get_option( 'hxfe_privacy',   [] );
	?>
	<div class="wrap">
	<h1><?php esc_html_e( 'HXFE Settings', 'hxfe-code-first-forms' ); ?></h1>

	<!-- reCAPTCHA -->
	<h2><?php esc_html_e( 'reCAPTCHA', 'hxfe-code-first-forms' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Get your keys at', 'hxfe-code-first-forms' ); ?>
		<a href="https://www.google.com/recaptcha/admin" target="_blank" rel="noopener">
			google.com/recaptcha/admin
		</a>
	</p>
	<form method="post" action="options.php">
		<?php settings_fields( 'hxfe_recaptcha_group' ); ?>
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'v2 Site Key', 'hxfe-code-first-forms' ); ?></th>
				<td><input type="text" name="hxfe_recaptcha[v2_site_key]"
					value="<?php echo esc_attr( $rc['v2_site_key'] ?? '' ); ?>"
					class="regular-text"></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'v2 Secret Key', 'hxfe-code-first-forms' ); ?></th>
				<td><input type="password" name="hxfe_recaptcha[v2_secret_key]"
					value="<?php echo esc_attr( $rc['v2_secret_key'] ?? '' ); ?>"
					class="regular-text"></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'v3 Site Key', 'hxfe-code-first-forms' ); ?></th>
				<td><input type="text" name="hxfe_recaptcha[v3_site_key]"
					value="<?php echo esc_attr( $rc['v3_site_key'] ?? '' ); ?>"
					class="regular-text"></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'v3 Secret Key', 'hxfe-code-first-forms' ); ?></th>
				<td><input type="password" name="hxfe_recaptcha[v3_secret_key]"
					value="<?php echo esc_attr( $rc['v3_secret_key'] ?? '' ); ?>"
					class="regular-text"></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'v3 Score Threshold', 'hxfe-code-first-forms' ); ?></th>
				<td>
					<input type="number" name="hxfe_recaptcha[v3_threshold]"
						value="<?php echo esc_attr( $rc['v3_threshold'] ?? '0.5' ); ?>"
						min="0.1" max="1.0" step="0.1" class="small-text">
					<p class="description">
						<?php esc_html_e( '0.1 (most permissive) – 1.0 (most strict). Default: 0.5', 'hxfe-code-first-forms' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Save reCAPTCHA Settings', 'hxfe-code-first-forms' ) ); ?>
	</form>

	<hr>

	<!-- プライバシーポリシー -->
	<h2><?php esc_html_e( 'Privacy Policy', 'hxfe-code-first-forms' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Site-wide default. Can be overridden per-field in the schema.', 'hxfe-code-first-forms' ); ?>
	</p>
	<form method="post" action="options.php">
		<?php settings_fields( 'hxfe_privacy_group' ); ?>
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Policy URL or PDF URL', 'hxfe-code-first-forms' ); ?></th>
				<td>
					<input type="url" name="hxfe_privacy[policy_url]"
						value="<?php echo esc_attr( $prv['policy_url'] ?? '' ); ?>"
						class="regular-text"
						placeholder="https://example.com/privacy-policy">
					<p class="description">
						<?php esc_html_e( 'Direct URL or media library PDF URL.', 'hxfe-code-first-forms' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Link label', 'hxfe-code-first-forms' ); ?></th>
				<td>
					<input type="text" name="hxfe_privacy[policy_label]"
						value="<?php echo esc_attr( $prv['policy_label'] ?? '' ); ?>"
						class="regular-text"
						placeholder="<?php esc_attr_e( 'Privacy Policy', 'hxfe-code-first-forms' ); ?>">
				</td>
			</tr>
		</table>
		<?php submit_button( __( 'Save Privacy Settings', 'hxfe-code-first-forms' ) ); ?>
	</form>

	<hr>

	<!-- スキーマ記述例 -->
	<h2><?php esc_html_e( 'Schema Examples', 'hxfe-code-first-forms' ); ?></h2>
	<pre style="background:#1d2327;color:#e5e7eb;padding:16px;border-radius:6px;font-size:12px;overflow-x:auto"><?php echo esc_html(
'// ── reCAPTCHA v2 (チェックボックス) ──
[ \'key\' => \'captcha\', \'type\' => \'recaptcha\', \'version\' => \'v2\' ]

// ── reCAPTCHA v3 (非表示スコア型) ──
[ \'key\' => \'captcha\', \'type\' => \'recaptcha\', \'version\' => \'v3\',
  \'action\' => \'contact\', \'threshold\' => 0.5 ]

// ── プライバシーポリシー同意 ──
[ \'key\' => \'privacy\', \'type\' => \'privacy\', \'required\' => true,
  \'label\' => \'プライバシーポリシーに同意します\',
  \'policy_url\' => \'https://example.com/privacy\',
  \'policy_label\' => \'プライバシーポリシー\' ]

// ── 自動返信 (スキーマのトップレベルに追加) ──
\'reply_to_field\'    => \'email\',
\'autoreply_subject\' => \'【{site_name}】お問い合わせを受け付けました\',
\'autoreply_body\'    => "お問い合わせありがとうございます。\n\nお名前: {name}\n\n担当者より折り返しご連絡いたします。",
\'autoreply_from\'    => \'noreply@example.com\',
\'autoreply_from_name\' => \'Example サポート\',

// ── 管理者通知を無効化 ──
\'admin_notify\' => false,

// ── BCC ──
\'bcc\' => \'archive@example.com,backup@example.com\','
	); ?></pre>
	</div>


	<?php
	hxfe_render_smtp_settings();
}

/* ---------------------------------------------------------------------------
 * SMTP 設定ページ
 * ------------------------------------------------------------------------- */

add_action( 'admin_init',   'hxfe_register_smtp_settings' );
add_action( 'admin_post_hxfe_test_mail', 'hxfe_handle_test_mail' );

function hxfe_register_smtp_settings() {
	register_setting( 'hxfe_smtp_group', 'hxfe_smtp_settings', [
		'sanitize_callback' => 'hxfe_sanitize_smtp_settings',
	] );
	register_setting( 'hxfe_smtp_group', 'hxfe_disable_default_css', [
		'sanitize_callback' => 'absint',
	] );
}

function hxfe_sanitize_smtp_settings( $input ) {
	if ( ! is_array( $input ) ) { return []; }
	$c = [];
	$c['enabled']    = ! empty( $input['enabled'] );
	$c['provider']   = sanitize_key( $input['provider'] ?? 'custom' );
	$c['host']       = sanitize_text_field( $input['host'] ?? '' );
	$c['port']       = max( 1, min( 65535, (int) ( $input['port'] ?? 587 ) ) );
	$c['encryption'] = in_array( $input['encryption'] ?? '', [ 'tls', 'ssl', 'none' ], true )
		? $input['encryption'] : 'tls';
	$c['username']   = sanitize_text_field( $input['username'] ?? '' );
	// パスワードはDBに保存（定数推奨だが空でなければ上書き保存）
	// セキュリティ: 入力が空の場合は既存値を保持する
	$existing = get_option( 'hxfe_smtp_settings', [] );
	$c['password'] = ( isset( $input['password'] ) && '' !== $input['password'] )
		? $input['password']
		: ( $existing['password'] ?? '' );
	$c['from_email'] = sanitize_email( $input['from_email'] ?? '' );
	$c['from_name']  = sanitize_text_field( $input['from_name'] ?? '' );
	return $c;
}

function hxfe_handle_test_mail() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( '', 403 ); }
	if ( ! wp_verify_nonce(
		sanitize_text_field( wp_unslash( $_POST['hxfe_nonce'] ?? '' ) ),
		'hxfe_test_mail'
	) ) { wp_die( '', 403 ); }

	$to     = sanitize_email( wp_unslash( $_POST['test_email'] ?? '' ) );
	$result = hxfe_send_test_mail( $to );
	$status = $result['ok'] ? 'ok' : 'fail';
	$msg    = urlencode( $result['message'] );

	wp_safe_redirect( add_query_arg( [
		'page'       => 'hxfe-settings',
		'test_mail'  => $status,
		'test_msg'   => $msg,
	], admin_url( 'options-general.php' ) ) );
	exit;
}

/**
 * SMTP設定ページのHTMLを出力する。
 * hxfe_render_settings_page() の末尾から呼ばれる。
 */
function hxfe_render_smtp_settings() {
	$s          = hxfe_get_smtp_settings();
	$test_mail  = sanitize_key( wp_unslash( $_GET['test_mail'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$test_msg   = sanitize_text_field( urldecode( wp_unslash( $_GET['test_msg'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	// プロバイダープリセット
	$presets = [
		'gmail'     => [ 'host' => 'smtp.gmail.com',      'port' => 587, 'enc' => 'tls',
						  'note' => __( 'Use an App Password (not your Google account password). Enable 2FA first.', 'hxfe-code-first-forms' ) ],
		'gsuite'    => [ 'host' => 'smtp.gmail.com',      'port' => 587, 'enc' => 'tls',
						  'note' => __( 'Same as Gmail. Username = full email address.', 'hxfe-code-first-forms' ) ],
		'sendgrid'  => [ 'host' => 'smtp.sendgrid.net',   'port' => 587, 'enc' => 'tls',
						  'note' => __( 'Username must be "apikey" (literally). Password = your SendGrid API key. Or set HXFE_SMTP_API_KEY in wp-config.php.', 'hxfe-code-first-forms' ) ],
		'mailgun'   => [ 'host' => 'smtp.mailgun.org',    'port' => 587, 'enc' => 'tls',
						  'note' => __( 'Username = postmaster@YOUR_DOMAIN. Password = Mailgun SMTP password.', 'hxfe-code-first-forms' ) ],
		'custom'    => [ 'host' => '',                    'port' => 587, 'enc' => 'tls',
						  'note' => __( 'Enter your SMTP server details manually.', 'hxfe-code-first-forms' ) ],
	];
	?>
	<hr>
	<h2>📧 <?php esc_html_e( 'SMTP Settings', 'hxfe-code-first-forms' ); ?></h2>

	<?php if ( 'ok' === $test_mail ) : ?>
	<div class="notice notice-success is-dismissible"><p>✅ <?php echo esc_html( $test_msg ); ?></p></div>
	<?php elseif ( 'fail' === $test_mail ) : ?>
	<div class="notice notice-error is-dismissible"><p>❌ <?php echo esc_html( $test_msg ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="options.php">
		<?php settings_fields( 'hxfe_smtp_group' ); ?>
		<table class="form-table">

			<tr>
				<th><?php esc_html_e( 'Enable SMTP', 'hxfe-code-first-forms' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="hxfe_smtp_settings[enabled]" value="1"
							<?php checked( $s['enabled'] ); ?>>
						<?php esc_html_e( 'Send all WordPress emails via SMTP', 'hxfe-code-first-forms' ); ?>
					</label>
				</td>
			</tr>

			<tr>
				<th><?php esc_html_e( 'Provider', 'hxfe-code-first-forms' ); ?></th>
				<td>
					<select name="hxfe_smtp_settings[provider]" id="hxfe-smtp-provider">
						<option value="gmail"    <?php selected( $s['provider'], 'gmail' ); ?>>Gmail</option>
						<option value="gsuite"   <?php selected( $s['provider'], 'gsuite' ); ?>>Google Workspace</option>
						<option value="sendgrid" <?php selected( $s['provider'], 'sendgrid' ); ?>>SendGrid</option>
						<option value="mailgun"  <?php selected( $s['provider'], 'mailgun' ); ?>>Mailgun</option>
						<option value="custom"   <?php selected( $s['provider'], 'custom' ); ?>><?php esc_html_e( 'Custom SMTP', 'hxfe-code-first-forms' ); ?></option>
					</select>
					<?php foreach ( $presets as $key => $preset ) : ?>
					<p class="description hxfe-smtp-note" id="hxfe-note-<?php echo esc_attr( $key ); ?>"
						style="<?php echo $s['provider'] === $key ? '' : 'display:none'; ?>">
						💡 <?php echo esc_html( $preset['note'] ); ?>
					</p>
					<?php endforeach; ?>
				</td>
			</tr>

			<tr>
				<th><label for="hxfe-smtp-host"><?php esc_html_e( 'SMTP Host', 'hxfe-code-first-forms' ); ?></label></th>
				<td>
					<input type="text" id="hxfe-smtp-host" name="hxfe_smtp_settings[host]"
						value="<?php echo esc_attr( $s['host'] ); ?>"
						class="regular-text" placeholder="smtp.example.com">
				</td>
			</tr>

			<tr>
				<th><label for="hxfe-smtp-port"><?php esc_html_e( 'Port', 'hxfe-code-first-forms' ); ?></label></th>
				<td>
					<input type="number" id="hxfe-smtp-port" name="hxfe_smtp_settings[port]"
						value="<?php echo esc_attr( $s['port'] ); ?>"
						class="small-text" min="1" max="65535">
					<p class="description">587 = TLS（推奨）/ 465 = SSL / 25 = 暗号化なし</p>
				</td>
			</tr>

			<tr>
				<th><?php esc_html_e( 'Encryption', 'hxfe-code-first-forms' ); ?></th>
				<td>
					<select name="hxfe_smtp_settings[encryption]">
						<option value="tls"  <?php selected( $s['encryption'], 'tls' ); ?>>TLS / STARTTLS（推奨）</option>
						<option value="ssl"  <?php selected( $s['encryption'], 'ssl' ); ?>>SSL</option>
						<option value="none" <?php selected( $s['encryption'], 'none' ); ?>>なし</option>
					</select>
				</td>
			</tr>

			<tr>
				<th><label for="hxfe-smtp-user"><?php esc_html_e( 'Username', 'hxfe-code-first-forms' ); ?></label></th>
				<td>
					<input type="text" id="hxfe-smtp-user" name="hxfe_smtp_settings[username]"
						value="<?php echo esc_attr( $s['username'] ); ?>"
						class="regular-text" autocomplete="off">
				</td>
			</tr>

			<tr>
				<th><label for="hxfe-smtp-pass"><?php esc_html_e( 'Password', 'hxfe-code-first-forms' ); ?></label></th>
				<td>
					<input type="password" id="hxfe-smtp-pass" name="hxfe_smtp_settings[password]"
						value="" class="regular-text" autocomplete="new-password"
						placeholder="<?php esc_attr_e( 'Leave blank to keep current password', 'hxfe-code-first-forms' ); ?>">
					<?php if ( defined( 'HXFE_SMTP_PASSWORD' ) ) : ?>
					<p class="description" style="color:#2271b1">
						🔒 <?php esc_html_e( 'HXFE_SMTP_PASSWORD is defined in wp-config.php and takes priority.', 'hxfe-code-first-forms' ); ?>
					</p>
					<?php elseif ( defined( 'HXFE_SMTP_API_KEY' ) ) : ?>
					<p class="description" style="color:#2271b1">
						🔒 <?php esc_html_e( 'HXFE_SMTP_API_KEY is defined in wp-config.php and takes priority.', 'hxfe-code-first-forms' ); ?>
					</p>
					<?php else : ?>
					<p class="description">
						<?php esc_html_e( 'Recommended: define HXFE_SMTP_PASSWORD in wp-config.php instead of storing here.', 'hxfe-code-first-forms' ); ?>
					</p>
					<?php endif; ?>
				</td>
			</tr>

			<tr>
				<th><label for="hxfe-from-email"><?php esc_html_e( 'From Email', 'hxfe-code-first-forms' ); ?></label></th>
				<td>
					<input type="email" id="hxfe-from-email" name="hxfe_smtp_settings[from_email]"
						value="<?php echo esc_attr( $s['from_email'] ); ?>"
						class="regular-text">
					<p class="description"><?php esc_html_e( 'Must match the authenticated sender domain (DKIM/SPF).', 'hxfe-code-first-forms' ); ?></p>
				</td>
			</tr>

			<tr>
				<th><label for="hxfe-from-name"><?php esc_html_e( 'From Name', 'hxfe-code-first-forms' ); ?></label></th>
				<td>
					<input type="text" id="hxfe-from-name" name="hxfe_smtp_settings[from_name]"
						value="<?php echo esc_attr( $s['from_name'] ); ?>"
						class="regular-text">
				</td>
			</tr>

		</table>

		<h3 style="margin-top:24px"><?php esc_html_e( 'CSS', 'hxfe-code-first-forms' ); ?></h3>
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Disable default CSS globally', 'hxfe-code-first-forms' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="hxfe_disable_default_css" value="1"
							<?php checked( get_option( 'hxfe_disable_default_css', false ) ); ?>>
						<?php esc_html_e( 'Do not load hxfe-forms.css on any form', 'hxfe-code-first-forms' ); ?>
					</label>
				</td>
			</tr>
		</table>

		<h3 style="margin-top:24px"><?php esc_html_e( 'iframe / CORS', 'hxfe-code-first-forms' ); ?></h3>
		<p style="color:#475569;font-size:13px;max-width:600px">
			<?php esc_html_e( 'iframe CORS is configured per-form via the', 'hxfe-code-first-forms' ); ?>
			<code>allowed_origins</code>
			<?php esc_html_e( 'schema key. Use the Schema Examples panel on the Forms page to get started.', 'hxfe-code-first-forms' ); ?>
		</p>
		<p style="color:#475569;font-size:13px;max-width:600px">
			<?php esc_html_e( 'Standalone iframe URL:', 'hxfe-code-first-forms' ); ?>
			<code><?php echo esc_html( home_url( '/hxfe-iframe/{form-id}/' ) ); ?></code>
		</p>

		<?php submit_button( __( 'Save SMTP Settings', 'hxfe-code-first-forms' ) ); ?>
	</form>

	<!-- テスト送信 -->
	<h3><?php esc_html_e( 'Send Test Email', 'hxfe-code-first-forms' ); ?></h3>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'hxfe_test_mail', 'hxfe_nonce' ); ?>
		<input type="hidden" name="action" value="hxfe_test_mail">
		<p>
			<input type="email" name="test_email"
				value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>"
				class="regular-text"
				placeholder="test@example.com">
			<button type="submit" class="button button-secondary">
				📨 <?php esc_html_e( 'Send Test', 'hxfe-code-first-forms' ); ?>
			</button>
		</p>
	</form>

	<?php
}

/* ---------------------------------------------------------------------------
 * フォーム一覧ページ（登録済みスキーマ）
 * 設定 → Form Engine → 登録済みフォーム
 * ------------------------------------------------------------------------- */

add_action( 'admin_menu', 'hxfe_add_form_list_page' );

function hxfe_add_form_list_page() {
	add_submenu_page(
		'options-general.php',
		__( 'Registered Forms', 'hxfe-code-first-forms' ),
		__( 'Form Engine — Forms', 'hxfe-code-first-forms' ),
		'manage_options',
		'hxfe-form-list',
		'hxfe_render_form_list_page'
	);
}

function hxfe_render_form_list_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }

	$schemas = hxfe_get_all_schemas();
	$count   = count( $schemas );

	// WP_DEBUGのlint結果を収集
	$lint_results = [];
	foreach ( $schemas as $id => $schema ) {
		$problems = hxfe_lint_schema( $schema );
		if ( ! empty( $problems ) ) {
			$lint_results[ $id ] = $problems;
		}
	}

	?>
	<div class="wrap">
	<h1>
		<?php esc_html_e( 'Form Engine — Registered Forms', 'hxfe-code-first-forms' ); ?>
		<span style="font-size:13px; font-weight:normal; color:#666; margin-left:8px;">
			<?php
			// translators: %d: number of forms
			printf( esc_html__( '%d form(s) registered', 'hxfe-code-first-forms' ), (int) $count );
			?>
		</span>
	</h1>

	<?php if ( 0 === $count ) : ?>
		<div class="notice notice-warning inline">
			<p>
				<?php esc_html_e( 'No forms registered yet.', 'hxfe-code-first-forms' ); ?>
				<?php esc_html_e( 'Add schemas via the hxfe_schemas filter in functions.php.', 'hxfe-code-first-forms' ); ?>
			</p>
		</div>
		<?php return; ?>
	<?php endif; ?>

	<!-- サマリーバー -->
	<?php
	$type_counts = [ 'normal' => 0, 'step' => 0, 'one_by_one' => 0, 'chatbot' => 0 ];
	$total_fields = 0;
	$has_webhook  = 0;
	$has_lint_warn = count( $lint_results );

	foreach ( $schemas as $schema ) {
		$step_mode = $schema['step_mode'] ?? '';
		if ( 'chatbot' === $step_mode ) { $type_counts['chatbot']++; }
		elseif ( 'one_by_one' === $step_mode ) { $type_counts['one_by_one']++; }
		elseif ( ! empty( $schema['steps'] ) ) { $type_counts['step']++; }
		else { $type_counts['normal']++; }
		$total_fields += count( array_filter( $schema['fields'] ?? [], fn($f) => ( $f['type'] ?? '' ) !== 'honeypot' ) );
		if ( ! empty( $schema['webhooks'] ) ) { $has_webhook++; }
	}
	?>
	<div class="hxfe-stat-grid">
		<div class="hxfe-stat">
			<div class="hxfe-stat__label"><?php esc_html_e( 'Total', 'hxfe-code-first-forms' ); ?></div>
			<div class="hxfe-stat__value"><?php echo (int) $count; ?></div>
		</div>
		<div class="hxfe-stat">
			<div class="hxfe-stat__label">Fields</div>
			<div class="hxfe-stat__value"><?php echo (int) $total_fields; ?></div>
		</div>
		<div class="hxfe-stat">
			<div class="hxfe-stat__label">Webhook</div>
			<div class="hxfe-stat__value"><?php echo (int) $has_webhook; ?></div>
		</div>
		<div class="hxfe-stat">
			<div class="hxfe-stat__label">Lint warns</div>
			<div class="hxfe-stat__value <?php echo $has_lint_warn > 0 ? 'hxfe-stat__value--warn' : ''; ?>"><?php echo (int) $has_lint_warn; ?></div>
		</div>
	</div>

	<!-- フォーム一覧テーブル -->
	<table class="hxfe-form-list widefat">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Form ID', 'hxfe-code-first-forms' ); ?></th>
				<th><?php esc_html_e( 'UI Mode', 'hxfe-code-first-forms' ); ?></th>
				<th><?php esc_html_e( 'Fields', 'hxfe-code-first-forms' ); ?></th>
				<th><?php esc_html_e( 'Mail / Webhook', 'hxfe-code-first-forms' ); ?></th>
				<th><?php esc_html_e( 'Shortcode', 'hxfe-code-first-forms' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $schemas as $id => $schema ) :
			$step_mode  = $schema['step_mode'] ?? '';
			$has_steps  = ! empty( $schema['steps'] );
			$fields     = $schema['fields'] ?? [];
			$non_hp     = array_filter( $fields, fn($f) => ! in_array( $f['type'] ?? '', ['honeypot','recaptcha'], true ) );
			$has_honeypot = (bool) array_filter( $fields, fn($f) => ( $f['type'] ?? '' ) === 'honeypot' );
			$has_recaptcha = (bool) array_filter( $fields, fn($f) => ( $f['type'] ?? '' ) === 'recaptcha' );

			// UIモード
			if ( 'chatbot' === $step_mode ) {
				$mode_label = '🤖 Chatbot';
				$mode_class = 'hxfe-badge--chatbot';
			} elseif ( 'one_by_one' === $step_mode ) {
				$mode_label = '1⃣ One by One';
				$mode_class = 'hxfe-badge--one';
			} elseif ( $has_steps ) {
				$step_count = count( $schema['steps'] );
				// translators: %d: number of steps
				$mode_label = sprintf( '📋 Steps (%d)', $step_count );
				$mode_class = 'hxfe-badge--step';
			} else {
				$mode_label = '📄 Normal';
				$mode_class = 'hxfe-badge--normal';
			}

			// 送信先
			$to_raw = $schema['to'] ?? '';
			if ( is_array( $to_raw ) ) {
				$to_display = implode( ', ', $to_raw );
			} elseif ( ! empty( $schema['to_rules'] ) ) {
				$to_display = '⇄ ' . count( $schema['to_rules'] ) . ' rules';
			} else {
				$to_display = (string) $to_raw;
			}

			// 確認画面
			$confirm = isset( $schema['confirm'] ) && false === $schema['confirm'] ? '⚡ No confirm' : '';

			// Webhook
			$webhook_count = count( $schema['webhooks'] ?? [] );

			// lint警告
			$lints = $lint_results[ $id ] ?? [];

			$shortcode = '[hxfe_form id="' . esc_attr( $id ) . '"]';
		?>
		<tr>
			<!-- Form ID -->
			<td>
				<strong style="font-size:14px"><?php echo esc_html( $id ); ?></strong>
				<?php if ( $confirm ) : ?>
					<br><span class="hxfe-tag hxfe-tag--ok" style="margin-top:4px;display:inline-block"><?php echo esc_html( $confirm ); ?></span>
				<?php endif; ?>
				<?php if ( ! empty( $lints ) ) : ?>
					<br>
					<ul class="hxfe-warn-list">
						<?php foreach ( $lints as $lint ) : ?>
							<li>⚠ <?php echo esc_html( $lint ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</td>

			<!-- UI Mode -->
			<td>
				<span class="hxfe-badge <?php echo esc_attr( $mode_class ); ?>">
					<?php echo esc_html( $mode_label ); ?>
				</span>
			</td>

			<!-- Fields -->
			<td>
				<ul class="hxfe-field-list">
				<?php foreach ( $non_hp as $field ) :
					$ftype  = $field['type'] ?? 'text';
					$flabel = $field['label'] ?? $field['key'];
					$req    = ! empty( $field['required'] ) ? ' <span class="hxfe-tag hxfe-tag--warn">required</span>' : '';
					$cond   = ! empty( $field['show_if'] ) || ! empty( $field['hide_if'] ) ? ' <span class="hxfe-tag hxfe-tag--ok">cond</span>' : '';
				?>
					<li>
						<span class="hxfe-field-type"><?php echo esc_html( $ftype ); ?></span>
						<?php echo esc_html( $flabel ); ?>
						<?php echo wp_kses( $req . $cond, [ 'span' => [ 'class' => [] ] ] ); ?>
					</li>
				<?php endforeach; ?>
				</ul>
				<div style="margin-top:6px; font-size:11px; color:#888;">
					<?php if ( $has_honeypot ) : ?>
						<span class="hxfe-tag hxfe-tag--hp">🍯 honeypot</span>
					<?php else : ?>
						<span class="hxfe-tag hxfe-tag--warn">⚠ no honeypot</span>
					<?php endif; ?>
					<?php if ( $has_recaptcha ) : ?>
						<span class="hxfe-tag hxfe-tag--ok">🔐 reCAPTCHA</span>
					<?php endif; ?>
				</div>
			</td>

			<!-- Mail / Webhook -->
			<td style="font-size:12px">
				<div>📧 <?php echo esc_html( $to_display ); ?></div>
				<?php if ( ! empty( $schema['reply_to_field'] ) ) : ?>
					<div style="color:#666">↩ autoreply</div>
				<?php endif; ?>
				<?php if ( $webhook_count > 0 ) : ?>
					<div>
						🔗 webhook
						<span style="color:#666">(<?php echo (int) $webhook_count; ?>)</span>
					</div>
				<?php endif; ?>
				<?php if ( ! empty( $schema['bcc'] ) ) : ?>
					<div style="color:#666">BCC: <?php echo esc_html( $schema['bcc'] ); ?></div>
				<?php endif; ?>
			</td>

			<!-- Shortcode -->
			<td>
				<div class="hxfe-shortcode-wrap">
					<div class="hxfe-sc-row">
						<code class="hxfe-shortcode-code"><?php echo esc_html( $shortcode ); ?></code>
						<button type="button" class="hxfe-copy-btn"
							onclick="hxfeCopyShortcode( <?php echo esc_attr( wp_json_encode( $shortcode ) ); ?>, this )">
							Form
						</button>
					</div>
					<div class="hxfe-sc-row">
						<?php $iframe_sc = '[hxfe_iframe id="' . esc_attr( $id ) . '"]'; ?>
						<code class="hxfe-shortcode-code"><?php echo esc_html( $iframe_sc ); ?></code>
						<button type="button" class="hxfe-copy-btn"
							onclick="hxfeCopyShortcode( <?php echo esc_attr( wp_json_encode( $iframe_sc ) ); ?>, this )">
							iFrame
						</button>
					</div>
					<div class="hxfe-sc-row">
						<?php
						$iframe_url = esc_url( home_url( '/hxfe-iframe/' . $id . '/' ) );
						$embed_html = '<iframe src="' . $iframe_url . '" style="width:100%;border:none;" loading="lazy"></iframe>';
						?>
						<code class="hxfe-shortcode-code" style="font-size:10px;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">&lt;iframe src="..."&gt;</code>
						<button type="button" class="hxfe-copy-btn"
							onclick="hxfeCopyShortcode( <?php echo esc_attr( wp_json_encode( $embed_html ) ); ?>, this )">
							HTML
						</button>
					</div>
				</div>
			</td>
		</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<p style="margin-top:16px; color:#666; font-size:12px;">
		<?php esc_html_e( 'Forms are defined via the hxfe_schemas filter. Edit in functions.php (or a separate file) and reload this page.', 'hxfe-code-first-forms' ); ?>
	</p>

	<hr style="margin:32px 0 24px">

	<h2 style="font-size:16px; margin-bottom:4px;"><?php esc_html_e( 'Schema Examples', 'hxfe-code-first-forms' ); ?></h2>
	<p style="color:#666; font-size:13px; margin-bottom:16px;"><?php esc_html_e( 'Copy and paste into functions.php (or a separate file). Change the id, to, and field labels as needed.', 'hxfe-code-first-forms' ); ?></p>

	<div class="hxfe-samples">

	<?php
	$samples = [
		[
			'label' => __( '① Basic form', 'hxfe-code-first-forms' ),
			'code'  => "add_filter( 'hxfe_schemas', function( \$schemas ) {\n    \$schemas['contact'] = [\n        'id'      => 'contact',\n        'to'      => 'admin@example.com',\n        'subject' => 'Contact: {name}',\n        'fields'  => [\n            [ 'key' => 'name',  'type' => 'text',     'label' => 'Name',    'required' => true ],\n            [ 'key' => 'email', 'type' => 'email',    'label' => 'Email',   'required' => true ],\n            [ 'key' => 'body',  'type' => 'textarea', 'label' => 'Message', 'required' => true ],\n            [ 'key' => 'hp',    'type' => 'honeypot' ],\n        ],\n    ];\n    return \$schemas;\n} );\n// Shortcode: [hxfe_form id=\"contact\"]",
		],
		[
			'label' => __( '② Step form', 'hxfe-code-first-forms' ),
			'code'  => "add_filter( 'hxfe_schemas', function( \$schemas ) {\n    \$schemas['apply'] = [\n        'id'      => 'apply',\n        'to'      => 'admin@example.com',\n        'subject' => 'Application: {name}',\n        'steps'   => [\n            [ 'label' => 'Basic info', 'fields' => ['name', 'email'] ],\n            [ 'label' => 'Details',    'fields' => ['body'] ],\n        ],\n        'fields'  => [\n            [ 'key' => 'name',  'type' => 'text',     'label' => 'Name',    'required' => true ],\n            [ 'key' => 'email', 'type' => 'email',    'label' => 'Email',   'required' => true ],\n            [ 'key' => 'body',  'type' => 'textarea', 'label' => 'Details', 'required' => true ],\n            [ 'key' => 'hp',    'type' => 'honeypot' ],\n        ],\n    ];\n    return \$schemas;\n} );\n// Shortcode: [hxfe_form id=\"apply\"]",
		],
		[
			'label' => __( '③ One-by-one (survey)', 'hxfe-code-first-forms' ),
			'code'  => "add_filter( 'hxfe_schemas', function( \$schemas ) {\n    \$schemas['survey'] = [\n        'id'        => 'survey',\n        'to'        => 'admin@example.com',\n        'subject'   => 'Survey: {name}',\n        'step_mode' => 'one_by_one',\n        'confirm'   => false,\n        'fields'    => [\n            [ 'key' => 'name',  'type' => 'text',  'label' => 'Your name',  'required' => true ],\n            [ 'key' => 'email', 'type' => 'email', 'label' => 'Your email', 'required' => true ],\n            [ 'key' => 'score', 'type' => 'radio', 'label' => 'Rating',     'required' => true,\n              'options' => [\n                  [ 'value' => '5', 'label' => '⭐⭐⭐⭐⭐' ],\n                  [ 'value' => '4', 'label' => '⭐⭐⭐⭐' ],\n                  [ 'value' => '3', 'label' => '⭐⭐⭐' ],\n              ],\n            ],\n            [ 'key' => 'hp', 'type' => 'honeypot' ],\n        ],\n    ];\n    return \$schemas;\n} );\n// Shortcode: [hxfe_form id=\"survey\"]",
		],
		[
			'label' => __( '④ Chatbot', 'hxfe-code-first-forms' ),
			'code'  => "add_filter( 'hxfe_schemas', function( \$schemas ) {\n    \$schemas['bot'] = [\n        'id'        => 'bot',\n        'to'        => 'admin@example.com',\n        'subject'   => 'Chat: {name}',\n        'step_mode' => 'chatbot',\n        'bot_name'  => 'Support Bot',\n        'bot_icon'  => '🤖',\n        'greeting'  => 'Hi! I have a few questions.',\n        'fields'    => [\n            [ 'key' => 'name',  'type' => 'text',  'label' => 'Name',  'required' => true,\n              'bot_message' => 'What is your name?' ],\n            [ 'key' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => true,\n              'bot_message' => 'Thanks {name}! Your email?' ],\n            [ 'key' => 'body',  'type' => 'textarea', 'label' => 'Message', 'required' => true,\n              'bot_message' => 'What can I help you with?' ],\n            [ 'key' => 'hp', 'type' => 'honeypot' ],\n        ],\n    ];\n    return \$schemas;\n} );\n// Shortcode: [hxfe_form id=\"bot\"]",
		],
		[
			'label' => __( '⑤ Conditional fields', 'hxfe-code-first-forms' ),
			'code'  => "add_filter( 'hxfe_schemas', function( \$schemas ) {\n    \$schemas['inquiry'] = [\n        'id'      => 'inquiry',\n        'to'      => 'admin@example.com',\n        'subject' => 'Inquiry: {name}',\n        'fields'  => [\n            [ 'key' => 'name',  'type' => 'text',  'label' => 'Name',  'required' => true ],\n            [ 'key' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => true ],\n            [ 'key' => 'type',  'type' => 'radio', 'label' => 'Type',  'required' => true,\n              'options' => [\n                  [ 'value' => 'general', 'label' => 'General' ],\n                  [ 'value' => 'support', 'label' => 'Support' ],\n              ],\n            ],\n            // show only when type == support\n            [ 'key' => 'detail', 'type' => 'textarea', 'label' => 'Support details',\n              'show_if' => [ 'type', '==', 'support' ] ],\n            [ 'key' => 'hp', 'type' => 'honeypot' ],\n        ],\n    ];\n    return \$schemas;\n} );\n// Shortcode: [hxfe_form id=\"inquiry\"]",
		],
		[
			'label' => __( '⑥ File upload', 'hxfe-code-first-forms' ),
			'code'  => "add_filter( 'hxfe_schemas', function( \$schemas ) {\n    \$schemas['upload'] = [\n        'id'      => 'upload',\n        'to'      => 'admin@example.com',\n        'subject' => 'File submission: {name}',\n        'fields'  => [\n            [ 'key' => 'name',  'type' => 'text',  'label' => 'Name',  'required' => true ],\n            [ 'key' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => true ],\n            [ 'key' => 'file',  'type' => 'file',  'label' => 'Attachment', 'required' => true,\n              'accept'      => '.pdf,.jpg,.png',\n              'max_size_mb' => 5 ],\n            [ 'key' => 'hp', 'type' => 'honeypot' ],\n        ],\n    ];\n    return \$schemas;\n} );\n// Shortcode: [hxfe_form id=\"upload\"]",
		],
		[
			'label' => __( '⑦ Password-protected form (auth can be added to any form)', 'hxfe-code-first-forms' ),
			'code'  => "// In wp-config.php (keep out of Git):\n// define( 'MY_FORM_PASS', 'your-secret-password' );\n\nadd_filter( 'hxfe_schemas', function( \$schemas ) {\n    \$schemas['internal'] = [\n        'id'     => 'internal',\n        'to'     => 'admin@example.com',\n        'subject'=> 'Internal form: {name}',\n        'auth'   => [\n            'users' => [\n                [ 'id' => 'staff', 'password' => defined('MY_FORM_PASS') ? MY_FORM_PASS : '' ],\n            ],\n        ],\n        'fields' => [\n            [ 'key' => 'name',  'type' => 'text',     'label' => 'Name',    'required' => true ],\n            [ 'key' => 'email', 'type' => 'email',    'label' => 'Email',   'required' => true ],\n            [ 'key' => 'body',  'type' => 'textarea', 'label' => 'Message', 'required' => true ],\n            [ 'key' => 'hp',    'type' => 'honeypot' ],\n        ],\n    ];\n    return \$schemas;\n} );\n// Shortcode: [hxfe_form id=\"internal\"]",
		],
		[
			'label' => __( '⑧ Download after submit (document request)', 'hxfe-code-first-forms' ),
			'code'  => "add_filter( 'hxfe_schemas', function( \$schemas ) {\n    \$schemas['document'] = [\n        'id'               => 'document',\n        'to'               => 'admin@example.com',\n        'subject'          => 'Document request: {name}',\n        'complete_message' => 'Thank you! Please download the document below.',\n        'download_url'     => 'https://example.com/files/document.pdf',\n        'download_label'   => 'Download PDF',\n        'fields'           => [\n            [ 'key' => 'name',  'type' => 'text',  'label' => 'Name',  'required' => true ],\n            [ 'key' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => true ],\n            [ 'key' => 'hp',    'type' => 'honeypot' ],\n        ],\n    ];\n    return \$schemas;\n} );\n// Shortcode: [hxfe_form id=\"document\"]",
		],
		[
			'label' => __( '⑨ Form availability window (open/close date)', 'hxfe-code-first-forms' ),
			'code'  => "add_filter( 'hxfe_schemas', function( \$schemas ) {\n    \$schemas['campaign'] = [\n        'id'               => 'campaign',\n        'to'               => 'admin@example.com',\n        'subject'          => 'Campaign: {name}',\n        'available_from'   => '2026-06-01 00:00:00',\n        'available_until'  => '2026-06-30 23:59:59',\n        'before_html' => '<p>Applications open June 1st.</p>',\n        'after_html' => '<p>Applications are now closed.</p>',\n        'fields'           => [\n            [ 'key' => 'name',  'type' => 'text',  'label' => 'Name',  'required' => true ],\n            [ 'key' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => true ],\n            [ 'key' => 'hp',    'type' => 'honeypot' ],\n        ],\n    ];\n    return \$schemas;\n} );\n// Shortcode: [hxfe_form id=\"campaign\"]\n// Note: datetime follows WordPress timezone setting",
		],
		[
			'label' => __( '⑩ Diagnosis chatbot (no email, result only)', 'hxfe-code-first-forms' ),
			'code'  => "add_filter( 'hxfe_schemas', function( \$schemas ) {\n    \$schemas['diagnosis'] = [\n        'id'        => 'diagnosis',\n        // No 'to' — email is not sent, result is shown instead\n        'step_mode' => 'chatbot',\n        'bot_name'  => 'Diagnosis Bot',\n        'greeting'  => 'Answer a few questions to find your best plan.',\n        'complete_html_rules' => [\n            [ 'when' => ['plan', '==', 'basic'],\n              'html' => '<h3>Basic plan recommended</h3><p>Hi {name}, <a href=\"/basic/\">learn more</a>.</p>' ],\n            [ 'when' => ['plan', '==', 'premium'],\n              'html' => '<h3>Premium plan recommended</h3><p>Our team will contact you, {name}.</p>' ],\n            [ 'when' => 'default',\n              'html' => '<p>Thank you {name}. We will be in touch.</p>' ],\n        ],\n        'fields' => [\n            [ 'key' => 'name', 'type' => 'text',  'label' => 'Name', 'required' => true,\n              'bot_message' => 'What is your name?' ],\n            [ 'key' => 'plan', 'type' => 'radio', 'label' => 'Plan',  'required' => true,\n              'bot_message' => 'Hi {name}! Which best describes you?',\n              'options' => [\n                  [ 'value' => 'basic',   'label' => 'Individual / Small team' ],\n                  [ 'value' => 'premium', 'label' => 'Business / Enterprise' ],\n              ],\n            ],\n            [ 'key' => 'hp', 'type' => 'honeypot' ],\n        ],\n    ];\n    return \$schemas;\n} );\n// Shortcode: [hxfe_form id=\"diagnosis\"]",
		],
		[
			'label' => __( '⑪ Custom validation (pattern / cross-field)', 'hxfe-code-first-forms' ),
			'code'  => "// A) Schema keys — pattern, minlength, maxlength, error_message\nadd_filter( 'hxfe_schemas', function( \$schemas ) {\n    \$schemas['register'] = [\n        'id'      => 'register',\n        'to'      => 'admin@example.com',\n        'subject' => 'Registration: {name}',\n        'fields'  => [\n            [ 'key' => 'name',  'type' => 'text',     'label' => 'Name',     'required' => true ],\n            [ 'key' => 'zip',   'type' => 'text',     'label' => 'Zip code', 'required' => true,\n              'pattern'       => '^[0-9]{3}-?[0-9]{4}\$',\n              'minlength'     => 7,\n              'maxlength'     => 8,\n              'error_message' => 'Invalid zip code format (e.g. 100-0001)' ],\n            [ 'key' => 'email', 'type' => 'email',    'label' => 'Email',    'required' => true ],\n            [ 'key' => 'pass',  'type' => 'text',     'label' => 'Password', 'required' => true ],\n            [ 'key' => 'pass2', 'type' => 'text',     'label' => 'Confirm password', 'required' => true ],\n            [ 'key' => 'hp',    'type' => 'honeypot' ],\n        ],\n    ];\n    return \$schemas;\n} );\n\n// B) Cross-field validation — password confirmation\nadd_filter( 'hxfe_validate_form', function( \$errors, \$values, \$schema ) {\n    if ( ( \$schema['id'] ?? '' ) !== 'register' ) { return \$errors; }\n    if ( ( \$values['pass'] ?? '' ) !== ( \$values['pass2'] ?? '' ) ) {\n        \$errors['pass2'] = 'Passwords do not match.';\n    }\n    return \$errors;\n}, 10, 3 );\n// Shortcode: [hxfe_form id=\"register\"]",
		],
	];
	foreach ( $samples as $sample ) :
	?>
	<div class="hxfe-sample-block">
		<div class="hxfe-sample-header">
			<strong><?php echo esc_html( $sample['label'] ); ?></strong>
			<button type="button" class="hxfe-copy-btn"
				onclick="hxfeCopyShortcode( <?php echo esc_attr( wp_json_encode( $sample['code'] ) ); ?>, this )">
				📋 Copy
			</button>
		</div>
		<pre class="hxfe-sample-code"><?php echo esc_html( $sample['code'] ); ?></pre>
	</div>
	<?php endforeach; ?>

	</div><!-- .hxfe-samples -->

	</div><!-- .wrap -->
	<?php
}

/* ---------------------------------------------------------------------------
 * ログページ
 * ------------------------------------------------------------------------- */

add_action( 'admin_menu', 'hxfe_add_log_page' );

function hxfe_add_log_page() {
	add_submenu_page(
		'options-general.php',
		__( 'Error Logs', 'hxfe-code-first-forms' ),
		__( 'Form Engine — Logs', 'hxfe-code-first-forms' ),
		'manage_options',
		'hxfe-logs',
		'hxfe_render_log_page'
	);
}

function hxfe_render_log_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }

	// ログクリアの処理
	if (
		isset( $_POST['hxfe_clear_logs'] ) &&
		isset( $_POST['hxfe_clear_logs_nonce'] ) &&
		wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hxfe_clear_logs_nonce'] ) ), 'hxfe_clear_logs' )
	) {
		hxfe_clear_all_logs();
		echo '<div class="notice notice-success"><p>' . esc_html__( 'All logs cleared.', 'hxfe-code-first-forms' ) . '</p></div>';
	}

	$logs = hxfe_get_recent_logs( 7 );

	?>
	<div class="wrap">
	<h1><?php esc_html_e( 'Form Engine — Error Logs', 'hxfe-code-first-forms' ); ?></h1>
	<p style="color:#666;">
		<?php esc_html_e( 'Last 7 days of error logs. Logs are automatically deleted after 30 days.', 'hxfe-code-first-forms' ); ?>
	</p>

	<form method="post" style="margin-bottom:16px;">
		<?php wp_nonce_field( 'hxfe_clear_logs', 'hxfe_clear_logs_nonce' ); ?>
		<button type="submit" name="hxfe_clear_logs" class="button button-secondary"
			onclick="return confirm('<?php esc_attr_e( 'Clear all logs? This cannot be undone.', 'hxfe-code-first-forms' ); ?>');">
			<?php esc_html_e( 'Clear All Logs', 'hxfe-code-first-forms' ); ?>
		</button>
	</form>

	<?php if ( empty( $logs ) ) : ?>
		<div class="notice notice-info inline">
			<p><?php esc_html_e( 'No error logs found. Great — everything is working smoothly!', 'hxfe-code-first-forms' ); ?></p>
		</div>
	<?php else : ?>
		<?php foreach ( $logs as $log ) : ?>
		<h2 style="font-size:14px; margin-top:24px;"><?php echo esc_html( $log['date'] ); ?></h2>
		<div style="background:#1e1e1e; color:#d4d4d4; font-family:monospace; font-size:12px; padding:12px 16px; border-radius:4px; overflow-x:auto; max-height:400px; overflow-y:auto;">
			<?php foreach ( $log['lines'] as $line ) : ?>
				<?php
				// エラー種別で色分け
				$color = '#d4d4d4';
				if ( str_contains( $line, 'SMTP_ERROR' ) ) {
					$color = '#f48771';
				} elseif ( str_contains( $line, 'WEBHOOK_ERROR' ) ) {
					$color = '#dcdcaa';
				} elseif ( str_contains( $line, 'RECAPTCHA_ERROR' ) ) {
					$color = '#9cdcfe';
				} elseif ( str_contains( $line, 'FILE_ERROR' ) ) {
					$color = '#ce9178';
				}
				?>
				<div style="color:<?php echo esc_attr( $color ); ?>; padding:2px 0; border-bottom:1px solid #2d2d2d;">
					<?php echo esc_html( $line ); ?>
				</div>
			<?php endforeach; ?>
		</div>
		<?php endforeach; ?>
	<?php endif; ?>

	</div><!-- .wrap -->
	<?php
}
