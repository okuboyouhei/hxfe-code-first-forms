<?php
/**
 * Chatbot renderer — チャットbot風のフォームUI
 *
 * step_mode: 'chatbot' のときに使用される。
 * 1問1答型のステップをチャットバブル形式で表示する。
 *
 * スキーマの書き方:
 *
 *   $schemas['support'] = [
 *       'id'              => 'support',
 *       'to'              => 'admin@example.com',
 *       'step_mode'       => 'chatbot',
 *       'bot_name'        => 'サポートBot',
 *       'bot_icon'        => '🤖',           // 絵文字またはhttps://...の画像URL
 *       'greeting'        => 'こんにちは！いくつか質問させてください。',
 *       'complete_message' => 'ありがとうございました！担当者よりご連絡いたします。',
 *       'fields' => [
 *           [
 *               'key'         => 'name',
 *               'type'        => 'text',
 *               'bot_message' => 'まず、お名前を教えていただけますか？',
 *               'required'    => true,
 *           ],
 *           [
 *               'key'         => 'email',
 *               'type'        => 'email',
 *               'bot_message' => '{name}様、メールアドレスをどうぞ。',
 *               'required'    => true,
 *           ],
 *           [
 *               'key'         => 'category',
 *               'type'        => 'radio',
 *               'bot_message' => 'どのようなご用件でしょうか？',
 *               'options'     => [
 *                   [ 'value' => 'product', 'label' => '製品について' ],
 *                   [ 'value' => 'support', 'label' => 'サポート' ],
 *                   [ 'value' => 'other',   'label' => 'その他' ],
 *               ],
 *           ],
 *           [
 *               'key'         => 'body',
 *               'type'        => 'textarea',
 *               'bot_message' => 'ご用件の詳細を教えてください。',
 *               'required'    => true,
 *           ],
 *           [ 'key' => 'hp', 'type' => 'honeypot' ],
 *       ],
 *   ];
 *
 * @package HtmxFormEngine
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * step_mode が 'chatbot' かどうかを返す。
 *
 * @param array $schema
 * @return bool
 */
function hxfe_is_chatbot_mode( array $schema ) {
	return ( $schema['step_mode'] ?? '' ) === 'chatbot';
}

/**
 * チャットbotフォームの初期画面を描画する。
 * ページ読み込み時に呼ばれる。
 *
 * @param array $schema フォームスキーマ。
 * @return string HTML。
 */
function hxfe_render_chatbot( array $schema ) {
	$form_id  = esc_attr( $schema['id'] );
	$bot_name = esc_html( $schema['bot_name'] ?? 'Bot' );
	$bot_icon = $schema['bot_icon'] ?? '🤖';
	$greeting = $schema['greeting'] ?? '';

	// honeypot以外の最初のフィールドを取得（show_ifを考慮）
	$fields  = array_values( array_filter(
		$schema['fields'] ?? [],
		fn( $f ) => ( $f['type'] ?? '' ) !== 'honeypot'
	) );

	// 初期値は空なので、show_ifの条件を満たす最初のフィールドを探す
	$first_index = 0;
	foreach ( $fields as $i => $f ) {
		if ( hxfe_field_is_visible( $f, [] ) ) {
			$first_index = $i;
			break;
		}
	}
	$first   = $fields[ $first_index ] ?? null;
	$nonce   = wp_create_nonce( 'hxfe_chatbot_' . $schema['id'] );

	ob_start();
	?>
	<div id="hxfe-<?php echo esc_attr( $form_id ); ?>" class="hxfe-wrap hxfe-chatbot-wrap"
		data-form-id="<?php echo esc_attr( $form_id ); ?>"
		data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
		data-nonce="<?php echo esc_attr( $nonce ); ?>">

		<?php if ( $bot_name ) : ?>
		<!-- ヘッダー -->
		<div class="hxfe-chatbot-header">
			<div class="hxfe-chatbot-header-avatar">
				<?php echo hxfe_render_bot_icon( $bot_icon, $bot_name ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
			<div>
				<div class="hxfe-chatbot-header-name"><?php echo esc_html( $bot_name ); ?></div>
				<div class="hxfe-chatbot-header-status">● <?php esc_html_e( 'Online', 'hxfe-code-first-forms' ); ?></div>
			</div>
		</div>
		<?php endif; ?>

		<!-- チャットログ -->
		<div class="hxfe-chatbot-log" id="hxfe-chatbot-log-<?php echo esc_attr( $form_id ); ?>" aria-live="polite">

			<?php if ( $greeting ) : ?>
			<!-- グリーティングバブル -->
			<div class="hxfe-chatbot-row hxfe-chatbot-row--bot hxfe-chatbot-row--greeting">
				<?php if ( ! $bot_name ) : ?>
				<div class="hxfe-chatbot-avatar">
					<?php echo hxfe_render_bot_icon( $bot_icon, $bot_name ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
				<?php endif; ?>
				<div class="hxfe-chatbot-bubble-wrap">
					<div class="hxfe-chatbot-bubble hxfe-chatbot-bubble--bot">
						<?php echo esc_html( $greeting ); ?>
					</div>
				</div>
			</div>
			<?php endif; ?>

		</div><!-- /.hxfe-chatbot-log -->

		<!-- 入力エリア -->
		<div class="hxfe-chatbot-input-area" id="hxfe-chatbot-input-<?php echo esc_attr( $form_id ); ?>">
			<?php if ( $first ) :
				echo hxfe_render_chatbot_field_input( $schema, $first, [], [] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			endif; ?>
		</div>

		<!-- 隠しフィールド: 送信済み回答（JSON）-->
		<input type="hidden"
			id="hxfe-chatbot-values-<?php echo esc_attr( $form_id ); ?>"
			name="hxfe_chatbot_values"
			value="{}">
		<input type="hidden"
			id="hxfe-chatbot-step-<?php echo esc_attr( $form_id ); ?>"
			name="hxfe_chatbot_step"
			value="<?php echo (int) $first_index; ?>">
	</div>
	<?php
	return ob_get_clean();
}

/**
 * ボットアイコンを描画する。
 * 絵文字の場合はspanで、URLの場合はimgで描画する。
 *
 * @param string $icon     絵文字またはURL。
 * @param string $bot_name ボット名（altテキスト用）。
 * @return string HTML。
 */
function hxfe_render_bot_icon( string $icon, string $bot_name ) {
	if ( filter_var( $icon, FILTER_VALIDATE_URL ) ) {
		return '<img src="' . esc_url( $icon ) . '" alt="' . esc_attr( $bot_name ) . '" class="hxfe-chatbot-avatar-img">';
	}
	return '<span class="hxfe-chatbot-avatar-emoji" aria-hidden="true">' . esc_html( $icon ) . '</span>';
}

/**
 * 現在のステップの入力UIを描画する。
 * AJAXレスポンスとして返される。
 *
 * @param array  $schema     フォームスキーマ。
 * @param array  $field      現在のフィールド定義。
 * @param array  $errors     エラー配列。
 * @param array  $values     入力済みの値。
 * @return string HTML。
 */
function hxfe_render_chatbot_field_input(
	array $schema,
	array $field,
	array $errors,
	array $values
) {
	$form_id  = esc_attr( $schema['id'] );
	$key      = $field['key'];
	$type     = $field['type'] ?? 'text';
	$error    = $errors[ $key ] ?? '';
	$bot_icon = $schema['bot_icon'] ?? '🤖';
	$bot_name = esc_html( $schema['bot_name'] ?? 'Bot' );

	// bot_message の {placeholder} を置換
	$bot_msg = hxfe_interpolate( $field['bot_message'] ?? $field['label'] ?? '', $values );

	ob_start();
	?>
	<div class="hxfe-chatbot-field" data-field-key="<?php echo esc_attr( $key ); ?>">

		<!-- Botの発言バブル（タイピングアニメーション後に表示） -->
		<div class="hxfe-chatbot-row hxfe-chatbot-row--bot hxfe-chatbot-row--typing"
			id="hxfe-typing-<?php echo esc_attr( $form_id ); ?>">
			<div class="hxfe-chatbot-avatar">
				<?php echo hxfe_render_bot_icon( $bot_icon, $bot_name ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
			<div class="hxfe-chatbot-typing">
				<span class="hxfe-chatbot-dot"></span>
				<span class="hxfe-chatbot-dot"></span>
				<span class="hxfe-chatbot-dot"></span>
			</div>
		</div>

		<!-- 実際のBotメッセージ（タイピング後に置き換わる） -->
		<div class="hxfe-chatbot-row hxfe-chatbot-row--bot hxfe-chatbot-row--message"
			id="hxfe-bot-msg-<?php echo esc_attr( $form_id ); ?>"
			style="display:none">
			<div class="hxfe-chatbot-avatar">
				<?php echo hxfe_render_bot_icon( $bot_icon, $bot_name ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
			<div class="hxfe-chatbot-bubble-wrap">
				<div class="hxfe-chatbot-bubble hxfe-chatbot-bubble--bot">
					<?php echo esc_html( $bot_msg ); ?>
				</div>
			</div>
		</div>

		<!-- エラーメッセージ -->
		<?php if ( $error ) : ?>
		<div class="hxfe-chatbot-row hxfe-chatbot-row--bot hxfe-chatbot-row--error">
			<div class="hxfe-chatbot-avatar">
				<?php echo hxfe_render_bot_icon( $bot_icon, $bot_name ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
			<div class="hxfe-chatbot-bubble-wrap">
				<div class="hxfe-chatbot-bubble hxfe-chatbot-bubble--error">
					<?php echo esc_html( $error ); ?>
				</div>
			</div>
		</div>
		<?php endif; ?>

		<!-- ユーザーの入力欄 -->
		<div class="hxfe-chatbot-user-input">
			<?php echo hxfe_render_chatbot_input_widget( $field, $error ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>

	</div><!-- /.hxfe-chatbot-field -->
	<?php
	return ob_get_clean();
}

/**
 * フィールドタイプに応じた入力ウィジェットを描画する。
 *
 * @param array  $field フィールド定義。
 * @param string $error エラーメッセージ。
 * @return string HTML。
 */
function hxfe_render_chatbot_input_widget( array $field, string $error ) {
	$key  = esc_attr( $field['key'] );
	$type = $field['type'] ?? 'text';

	ob_start();

	// radio / select の場合はボタン型の選択肢を表示
	if ( in_array( $type, [ 'radio', 'select' ], true ) && ! empty( $field['options'] ) ) : ?>
		<div class="hxfe-chatbot-choices">
			<?php foreach ( $field['options'] as $opt ) :
				if ( '' === ( $opt['value'] ?? '' ) ) continue; ?>
				<button type="button"
					class="hxfe-chatbot-choice-btn"
					data-value="<?php echo esc_attr( $opt['value'] ); ?>"
					data-label="<?php echo esc_attr( $opt['label'] ); ?>">
					<?php echo esc_html( $opt['label'] ); ?>
				</button>
			<?php endforeach; ?>
		</div>

	<?php elseif ( 'textarea' === $type ) : ?>
		<div class="hxfe-chatbot-text-input">
			<textarea
				name="<?php echo esc_attr( $key ); ?>"
				id="hxfe-chatbot-input-field-<?php echo esc_attr( $key ); ?>"
				class="hxfe-chatbot-textarea"
				rows="3"
				placeholder="<?php echo esc_attr( $field['placeholder'] ?? __( 'Type your answer...', 'hxfe-code-first-forms' ) ); ?>"
				<?php echo ! empty( $field['required'] ) ? 'required' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>></textarea>
			<button type="button" class="hxfe-chatbot-send-btn" data-field="<?php echo esc_attr( $key ); ?>">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
			</button>
		</div>

	<?php elseif ( 'checkbox' === $type || 'privacy' === $type ) : ?>
		<div class="hxfe-chatbot-checkbox-input">
			<label class="hxfe-chatbot-agree-label">
				<input type="checkbox"
					name="<?php echo esc_attr( $key ); ?>"
					id="hxfe-chatbot-input-field-<?php echo esc_attr( $key ); ?>"
					value="1"
					class="hxfe-chatbot-checkbox">
				<?php echo esc_html( $field['label'] ?? '' ); ?>
				<?php if ( ! empty( $field['policy_url'] ) ) : ?>
					<a href="<?php echo esc_url( $field['policy_url'] ); ?>" target="_blank" rel="noopener">
						<?php echo esc_html( $field['policy_label'] ?? __( 'Privacy Policy', 'hxfe-code-first-forms' ) ); ?>
					</a>
				<?php endif; ?>
			</label>
			<button type="button" class="hxfe-chatbot-send-btn" data-field="<?php echo esc_attr( $key ); ?>">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
			</button>
		</div>

	<?php else : // text / email / number / date など ?>
		<div class="hxfe-chatbot-text-input">
			<input
				type="<?php echo esc_attr( $type ); ?>"
				name="<?php echo esc_attr( $key ); ?>"
				id="hxfe-chatbot-input-field-<?php echo esc_attr( $key ); ?>"
				class="hxfe-chatbot-input"
				placeholder="<?php echo esc_attr( $field['placeholder'] ?? __( 'Type your answer...', 'hxfe-code-first-forms' ) ); ?>"
				<?php echo ! empty( $field['min'] )      ? 'min="'  . esc_attr( $field['min'] ) . '"'  : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php echo ! empty( $field['max'] )      ? 'max="'  . esc_attr( $field['max'] ) . '"'  : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php echo ! empty( $field['min_date'] ) ? 'min="'  . esc_attr( $field['min_date'] ) . '"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php echo ! empty( $field['max_date'] ) ? 'max="'  . esc_attr( $field['max_date'] ) . '"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php echo ! empty( $field['maxlength'] ) ? 'maxlength="' . esc_attr( $field['maxlength'] ) . '"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php echo ! empty( $field['required'] ) ? 'required' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<button type="button" class="hxfe-chatbot-send-btn" data-field="<?php echo esc_attr( $key ); ?>">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
			</button>
		</div>
	<?php endif; ?>
	<?php
	return ob_get_clean();
}

/**
 * チャットbot完了画面を描画する。
 *
 * @param array $schema フォームスキーマ。
 * @return string HTML。
 */
function hxfe_render_chatbot_complete( array $schema, array $values = [] ) {
	$form_id  = esc_attr( $schema['id'] );
	$bot_icon = $schema['bot_icon'] ?? '🤖';
	$bot_name = esc_html( $schema['bot_name'] ?? 'Bot' );
	// redirect_rules / complete_redirect / complete_html_rules がある場合はhxfe_render_completeに委譲
	if ( ! empty( $schema['complete_redirect_rules'] ) || ! empty( $schema['complete_redirect'] ) || ! empty( $schema['complete_html_rules'] ) ) {
		return hxfe_render_complete( $schema, $values );
	}

	$complete = esc_html( $schema['complete_message'] ?? __( 'Thank you! Your message has been sent.', 'hxfe-code-first-forms' ) );

	ob_start();
	?>
	<div class="hxfe-chatbot-row hxfe-chatbot-row--bot hxfe-chatbot-row--complete">
		<div class="hxfe-chatbot-avatar">
			<?php echo hxfe_render_bot_icon( $bot_icon, $bot_name ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
		<div class="hxfe-chatbot-bubble hxfe-chatbot-bubble--complete">
			✅ <?php echo $complete; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

/* ---------------------------------------------------------------------------
 * AJAXエンドポイント
 * ユーザーが1フィールド回答するごとに呼ばれる。
 * ------------------------------------------------------------------------- */

add_action( 'wp_ajax_hxfe_chatbot_next',        'hxfe_handle_chatbot_next' );
add_action( 'wp_ajax_nopriv_hxfe_chatbot_next', 'hxfe_handle_chatbot_next' );

/**
 * 1フィールドの回答を受け取り、バリデーションして次のフィールドを返す。
 *
 * POSTパラメータ:
 *   hxfe_form_id       : フォームID
 *   hxfe_nonce         : nonce
 *   hxfe_chatbot_step  : 現在のステップインデックス
 *   hxfe_chatbot_values: これまでの回答（JSON）
 *   {field_key}        : 現在のフィールドの入力値
 *
 * レスポンス（JSON）:
 *   status    : 'next' | 'complete' | 'error'
 *   user_label: ユーザーバブルに表示するテキスト
 *   html      : 次のフィールドのHTML | 完了メッセージのHTML
 */
function hxfe_handle_chatbot_next() {
	$form_id = sanitize_key( wp_unslash( $_POST['hxfe_form_id'] ?? '' ) );

	if ( ! wp_verify_nonce(
		sanitize_text_field( wp_unslash( $_POST['hxfe_nonce'] ?? '' ) ),
		'hxfe_chatbot_' . $form_id
	) ) {
		wp_send_json_error( [ 'message' => 'Invalid nonce.' ], 403 );
	}

	$schema = hxfe_get_schema( $form_id );
	if ( null === $schema ) {
		wp_send_json_error( [ 'message' => 'Form not found.' ], 400 );
	}

	// これまでの回答を取得
	$prev_json = sanitize_text_field( wp_unslash( $_POST['hxfe_chatbot_values'] ?? '{}' ) );
	$values    = json_decode( $prev_json, true );
	$values    = is_array( $values ) ? $values : [];

	// 現在のステップ
	$step_index = absint( wp_unslash( $_POST['hxfe_chatbot_step'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

	// honeypot以外のフィールドのみ対象
	$fields = array_values( array_filter(
		$schema['fields'] ?? [],
		fn( $f ) => ( $f['type'] ?? '' ) !== 'honeypot'
	) );

	if ( $step_index >= count( $fields ) ) {
		wp_send_json_error( [ 'message' => 'Invalid step.' ], 400 );
	}

	$field = $fields[ $step_index ];
	$key   = $field['key'];
	$type  = $field['type'] ?? 'text';

	// --- 現在のフィールドをバリデーション ---
	// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
	$raw    = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$result = hxfe_validate_field( $field, $raw );
	$values[ $key ] = $result['value'];

	if ( '' !== $result['error'] ) {
		// バリデーションエラー: 同じフィールドをエラー付きで返す
		wp_send_json_success( [
			'status'     => 'error',
			'user_label' => '',
			'html'       => hxfe_render_chatbot_field_input( $schema, $field, [ $key => $result['error'] ], $values ),
			'values'     => $values,
			'step'       => $step_index,
		] );
	}

	// ユーザーバブルに表示するラベルを決定
	$user_label = hxfe_chatbot_user_label( $field, $values[ $key ] );

	// --- 次のフィールドを探す（条件分岐・スキップ対応）---
	$next_index = $step_index + 1;
	while ( $next_index < count( $fields ) ) {
		$next_field = $fields[ $next_index ];
		if ( hxfe_field_is_visible( $next_field, $values ) ) {
			break;
		}
		$next_index++;
	}

	// --- 全フィールド回答完了 ---
	if ( $next_index >= count( $fields ) ) {
		// honeypotチェック
		foreach ( $schema['fields'] as $f ) {
			if ( ( $f['type'] ?? '' ) === 'honeypot' && ! empty( $_POST[ $f['key'] ] ) ) { // phpcs:ignore
				wp_send_json_success( [
					'status'     => 'complete',
					'user_label' => $user_label,
					'html'       => hxfe_render_chatbot_complete( $schema ),
					'values'     => $values,
					'step'       => $next_index,
				] );
			}
		}

		// メール送信
		hxfe_send_emails( $schema, $values );

		wp_send_json_success( [
			'status'     => 'complete',
			'user_label' => $user_label,
			'html'       => hxfe_render_chatbot_complete( $schema, $values ),
			'values'     => $values,
			'step'       => $next_index,
		] );
	}

	// --- 次のフィールドを返す ---
	wp_send_json_success( [
		'status'     => 'next',
		'user_label' => $user_label,
		'html'       => hxfe_render_chatbot_field_input( $schema, $fields[ $next_index ], [], $values ),
		'values'     => $values,
		'step'       => $next_index,
	] );
}

/**
 * ユーザーのバブルに表示するラベルを生成する。
 * radio/select の場合はラベル、それ以外は入力値をそのまま使う。
 *
 * @param array  $field フィールド定義。
 * @param string $value 入力値。
 * @return string
 */
function hxfe_chatbot_user_label( array $field, string $value ) {
	$type = $field['type'] ?? 'text';

	if ( in_array( $type, [ 'radio', 'select' ], true ) && ! empty( $field['options'] ) ) {
		foreach ( $field['options'] as $opt ) {
			if ( (string) ( $opt['value'] ?? '' ) === $value ) {
				return $opt['label'];
			}
		}
	}

	if ( in_array( $type, [ 'checkbox', 'privacy' ], true ) ) {
		return $value ? __( '✓ Agreed', 'hxfe-code-first-forms' ) : '';
	}

	return $value;
}
