/**
 * HXFE Chatbot — チャットbot形式のフォームUI
 *
 * 動作の流れ:
 *   1. ページ読み込み → タイピングアニメーション → 最初のBotメッセージ表示
 *   2. ユーザーが回答を入力 → Sendボタン（またはEnter）で送信
 *   3. ユーザーバブルをチャットログに追加
 *   4. fetch で hxfe_chatbot_next を呼ぶ
 *   5. レスポンスのHTMLを入力エリアに注入 → タイピングアニメーション → Botメッセージ表示
 *   6. complete になったら入力エリアを非表示
 */
( function () {
	'use strict';

	var TYPING_DELAY = 800; // タイピングアニメーションの表示時間（ms）

	/**
	 * チャットbotを初期化する。
	 * @param {HTMLElement} wrap
	 */
	function initChatbot( wrap ) {
		var formId   = wrap.getAttribute( 'data-form-id' );
		var ajaxUrl  = wrap.getAttribute( 'data-ajax-url' );
		var nonce    = wrap.getAttribute( 'data-nonce' );
		var log      = wrap.querySelector( '#hxfe-chatbot-log-' + formId );
		var inputArea= wrap.querySelector( '#hxfe-chatbot-input-' + formId );
		var valInput = wrap.querySelector( '#hxfe-chatbot-values-' + formId );
		var stepInput= wrap.querySelector( '#hxfe-chatbot-step-' + formId );

		if ( ! log || ! inputArea ) { return; }

		// 最初のフィールドのBotメッセージをタイピングアニメーション付きで表示
		showTypingThenMessage( inputArea, log );

		// イベント委譲: Sendボタン
		wrap.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '.hxfe-chatbot-send-btn' );
			if ( ! btn ) { return; }
			e.preventDefault();
			submitField( wrap, formId, ajaxUrl, nonce, log, inputArea, valInput, stepInput );
		} );

		// イベント委譲: ラジオ/selectのボタン型選択肢
		wrap.addEventListener( 'click', function ( e ) {
			var choiceBtn = e.target.closest( '.hxfe-chatbot-choice-btn' );
			if ( ! choiceBtn ) { return; }
			e.preventDefault();

			// 選択状態を更新
			var allBtns = wrap.querySelectorAll( '.hxfe-chatbot-choice-btn' );
			allBtns.forEach( function ( b ) { b.classList.remove( 'is-selected' ); } );
			choiceBtn.classList.add( 'is-selected' );

			// 少し待ってから送信（選択視覚フィードバックのため）
			setTimeout( function () {
				submitField( wrap, formId, ajaxUrl, nonce, log, inputArea, valInput, stepInput, {
					value: choiceBtn.getAttribute( 'data-value' ),
					label: choiceBtn.getAttribute( 'data-label' ),
				} );
			}, 250 );
		} );

		// イベント委譲: Enterキーで送信（textarea以外）
		wrap.addEventListener( 'keydown', function ( e ) {
			if ( e.key !== 'Enter' ) { return; }
			var input = e.target.closest( '.hxfe-chatbot-input' );
			if ( ! input ) { return; }
			e.preventDefault();
			submitField( wrap, formId, ajaxUrl, nonce, log, inputArea, valInput, stepInput );
		} );
	}

	/**
	 * タイピングアニメーションを表示してからBotメッセージに切り替える。
	 * @param {HTMLElement} inputArea
	 * @param {HTMLElement} log
	 */
	function showTypingThenMessage( inputArea, log ) {
		var typing = inputArea.querySelector( '[id^="hxfe-typing-"]' );
		var msg    = inputArea.querySelector( '[id^="hxfe-bot-msg-"]' );
		if ( ! typing || ! msg ) { return; }

		// タイピングを入力エリアに表示 → ログに移動してBotメッセージに切り替え
		typing.style.display = '';
		setTimeout( function () {
			typing.style.display = 'none';
			msg.style.display    = '';
			// ログへ移動
			log.appendChild( typing.cloneNode( false ) ); // アニメーション用のクローンは不要
			log.appendChild( msg );
			scrollToBottom( log );
		}, TYPING_DELAY );
	}

	/**
	 * 現在のフィールドの回答を送信する。
	 */
	function submitField( wrap, formId, ajaxUrl, nonce, log, inputArea, valInput, stepInput, override ) {
		var fieldWrap = inputArea.querySelector( '.hxfe-chatbot-field' );
		if ( ! fieldWrap ) { return; }

		var fieldKey  = fieldWrap.getAttribute( 'data-field-key' );
		var values    = JSON.parse( valInput.value || '{}' );
		var stepIndex = parseInt( stepInput.value || '0', 10 );

		// 入力値を取得
		var value, userLabel;
		if ( override ) {
			value     = override.value;
			userLabel = override.label || override.value;
		} else {
			var inputEl = inputArea.querySelector(
				'[name="' + fieldKey + '"]'
			);
			if ( ! inputEl ) { return; }

			if ( inputEl.type === 'checkbox' ) {
				value     = inputEl.checked ? '1' : '';
				userLabel = inputEl.checked ? '✓' : '';
			} else {
				value     = inputEl.value;
				userLabel = value;
			}
		}

		// バリデーション: required かつ空の場合
		var fieldDef = inputArea.querySelector( '[required]' );
		if ( fieldDef && ! value ) {
			// ブラウザのHTML5バリデーションに任せる（送信ボタン経由でない場合）
			if ( fieldDef.reportValidity ) { fieldDef.reportValidity(); }
			return;
		}

		// ユーザーバブルをログに追加
		if ( userLabel ) {
			var now = new Date();
			var timeStr = now.getHours() + ':' + String( now.getMinutes() ).padStart( 2, '0' );
			var userRow = document.createElement( 'div' );
			userRow.className = 'hxfe-chatbot-row hxfe-chatbot-row--user';
			userRow.innerHTML = '<div class="hxfe-chatbot-bubble-wrap">'
				+ '<div class="hxfe-chatbot-bubble hxfe-chatbot-bubble--user">'
				+ escapeHtml( userLabel ) + '</div>'
				+ '<div class="hxfe-chatbot-time">' + timeStr + '</div>'
				+ '</div>';
			log.appendChild( userRow );
			scrollToBottom( log );
		}

		// 入力エリアを一時的に無効化
		setInputDisabled( inputArea, true );

		// fetch で次のフィールドを取得
		var params = new FormData();
		params.append( 'action',             'hxfe_chatbot_next' );
		params.append( 'hxfe_form_id',       formId );
		params.append( 'hxfe_nonce',         nonce );
		params.append( 'hxfe_chatbot_step',  String( stepIndex ) );
		params.append( 'hxfe_chatbot_values', JSON.stringify( values ) );
		params.append( fieldKey, value );

		fetch( ajaxUrl, { method: 'POST', body: params } )
			.then( function ( res ) { return res.json(); } )
			.then( function ( json ) {
				if ( ! json.success ) {
					setInputDisabled( inputArea, false );
					return;
				}

				var data = json.data;

				// values と step を更新
				valInput.value  = JSON.stringify( data.values || {} );
				stepInput.value = String( data.step || 0 );

				if ( data.status === 'error' ) {
					// バリデーションエラー: 入力エリアを再描画
					inputArea.innerHTML = data.html;
					showTypingThenMessage( inputArea, log );
					return;
				}

				if ( data.status === 'complete' ) {
					// 完了: 入力エリアを完了メッセージに置き換え
					inputArea.innerHTML = data.html;
					log.appendChild( inputArea.querySelector( '.hxfe-chatbot-row--complete' ) );
					inputArea.style.display = 'none';
					scrollToBottom( log );
					return;
				}

				// 次のフィールドを注入 → タイピングアニメーション
				inputArea.innerHTML = data.html;
				showTypingThenMessage( inputArea, log );
			} )
			.catch( function () {
				setInputDisabled( inputArea, false );
			} );
	}

	function setInputDisabled( inputArea, disabled ) {
		var els = inputArea.querySelectorAll( 'input, textarea, button, select' );
		els.forEach( function ( el ) { el.disabled = disabled; } );
	}

	function scrollToBottom( el ) {
		el.scrollTop = el.scrollHeight;
	}

	function escapeHtml( str ) {
		return String( str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' );
	}

	// 初期化
	function init() {
		document.querySelectorAll( '.hxfe-chatbot-wrap' ).forEach( initChatbot );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
