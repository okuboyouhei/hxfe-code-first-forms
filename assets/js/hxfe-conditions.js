/**
 * HXFE Conditions — クライアント側リアルタイム条件分岐
 *
 * show_if / hide_if 属性を持つフィールドを監視して
 * 他のフィールドの値が変わったときに表示/非表示を切り替える。
 */
( function () {
	'use strict';

	/**
	 * 単一条件を評価する。
	 * @param {Array} condition [fieldKey, operator, value]
	 * @param {Object} values { fieldKey: value, ... }
	 * @returns {boolean}
	 */
	function evalSingle( condition, values ) {
		if ( ! Array.isArray( condition ) || condition.length < 2 ) { return true; }
		var fieldKey = condition[0];
		var operator = condition[1];
		var expected = condition[2] !== undefined ? String( condition[2] ) : '';
		var actual   = values[ fieldKey ] !== undefined ? String( values[ fieldKey ] ) : '';

		switch ( operator ) {
			case '==':          return actual === expected;
			case '!=':          return actual !== expected;
			case '>':           return parseFloat( actual ) > parseFloat( expected );
			case '>=':          return parseFloat( actual ) >= parseFloat( expected );
			case '<':           return parseFloat( actual ) < parseFloat( expected );
			case '<=':          return parseFloat( actual ) <= parseFloat( expected );
			case 'contains':    return actual.indexOf( expected ) !== -1;
			case 'not_contains':return actual.indexOf( expected ) === -1;
			case 'in':          return expected.split( ',' ).map( s => s.trim() ).indexOf( actual ) !== -1;
			case 'not_in':      return expected.split( ',' ).map( s => s.trim() ).indexOf( actual ) === -1;
			case 'empty':       return actual === '';
			case 'not_empty':   return actual !== '';
			default:            return true;
		}
	}

	/**
	 * 条件（シンプル/AND/OR）を評価する。
	 */
	function evalCondition( condition, values ) {
		if ( ! condition ) { return true; }
		if ( Array.isArray( condition ) && condition[0] === 'and' && Array.isArray( condition[1] ) ) {
			return condition[1].every( function( c ) { return evalSingle( c, values ); } );
		}
		if ( Array.isArray( condition ) && condition[0] === 'or' && Array.isArray( condition[1] ) ) {
			return condition[1].some( function( c ) { return evalSingle( c, values ); } );
		}
		return evalSingle( condition, values );
	}

	/**
	 * フォーム内の全フィールドの現在値を取得する。
	 */
	function collectValues( form ) {
		var values = {};
		var inputs = form.querySelectorAll( 'input, select, textarea' );
		inputs.forEach( function( el ) {
			if ( ! el.name ) { return; }
			if ( el.type === 'checkbox' ) {
				values[ el.name ] = el.checked ? el.value || '1' : '';
			} else if ( el.type === 'radio' ) {
				if ( el.checked ) { values[ el.name ] = el.value; }
			} else {
				values[ el.name ] = el.value;
			}
		} );
		return values;
	}

	/**
	 * 全条件フィールドの表示/非表示を更新する。
	 */
	function updateVisibility( form ) {
		var values = collectValues( form );
		var fields = form.querySelectorAll( '[data-hxfe-show-if], [data-hxfe-hide-if]' );

		fields.forEach( function( el ) {
			var showIf = el.getAttribute( 'data-hxfe-show-if' );
			var hideIf = el.getAttribute( 'data-hxfe-hide-if' );
			var visible = true;

			try {
				if ( showIf ) {
					visible = evalCondition( JSON.parse( showIf ), values );
				} else if ( hideIf ) {
					visible = ! evalCondition( JSON.parse( hideIf ), values );
				}
			} catch ( e ) {}

			el.classList.toggle( 'hxfe-field--hidden', ! visible );

			// 非表示のフィールドは disabled にしてフォーム送信から除外
			var inputs = el.querySelectorAll( 'input, select, textarea' );
			inputs.forEach( function( input ) {
				input.disabled = ! visible;
			} );
		} );
	}

	/**
	 * フォームにイベントリスナーを設定する。
	 */
	function initForm( form ) {
		// 初期評価
		updateVisibility( form );

		// 値が変わるたびに評価
		form.addEventListener( 'change', function() { updateVisibility( form ); } );
		form.addEventListener( 'input',  function() { updateVisibility( form ); } );
	}

	// DOM 読み込み後に初期化
	function init() {
		document.querySelectorAll( '.hxfe-form' ).forEach( initForm );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

	// htmx の画面切り替え後も再初期化
	document.addEventListener( 'htmx:afterSwap', function( e ) {
		var form = e.target && e.target.querySelector( '.hxfe-form' );
		if ( form ) { initForm( form ); }
	} );

} )();

/* ---------------------------------------------------------------------------
 * カスケードセレクト（cascade_from）
 *
 * 親セレクトの選択値に応じて子セレクトの選択肢を動的に切り替える。
 *
 * スキーマの書き方:
 *   [ 'key' => 'pref', 'type' => 'select', 'label' => '都道府県', 'options' => [...] ]
 *   [ 'key' => 'city', 'type' => 'select', 'label' => '市区町村',
 *     'cascade_from' => 'pref',   ← 親フィールドのキー
 *     'cascade_options' => [      ← 親の値 → 子の選択肢 のマッピング
 *         'tokyo' => [
 *             [ 'value' => 'shinjuku', 'label' => '新宿区' ],
 *             [ 'value' => 'shibuya',  'label' => '渋谷区' ],
 *         ],
 *         'osaka' => [
 *             [ 'value' => 'namba',    'label' => '難波' ],
 *             [ 'value' => 'umeda',    'label' => '梅田' ],
 *         ],
 *     ],
 *   ]
 * ------------------------------------------------------------------------- */
( function() {
	'use strict';

	function initCascade( form ) {
		var selects = form.querySelectorAll( '[data-hxfe-cascade-from]' );
		selects.forEach( function( child ) {
			var parentKey   = child.getAttribute( 'data-hxfe-cascade-from' );
			var optionsJson = child.getAttribute( 'data-hxfe-cascade-options' );
			if ( ! parentKey || ! optionsJson ) { return; }

			var cascadeMap;
			try { cascadeMap = JSON.parse( optionsJson ); } catch(e) { return; }

			var parent = form.querySelector( '[name="' + parentKey + '"]' );
			if ( ! parent ) { return; }

			function updateChild() {
				var val     = parent.value;
				var options = cascadeMap[ val ] || [];

				// 子セレクトの選択肢を入れ替え
				child.innerHTML = '';
				var placeholder = document.createElement( 'option' );
				placeholder.value = '';
				placeholder.textContent = child.getAttribute( 'data-hxfe-placeholder' ) || '--- 選択 ---';
				child.appendChild( placeholder );

				options.forEach( function( opt ) {
					var el   = document.createElement( 'option' );
					el.value = opt.value || '';
					el.textContent = opt.label || opt.value;
					child.appendChild( el );
				} );

				// 選択肢が0件なら非表示
				var wrap = child.closest( '.hxfe-field' );
				if ( wrap ) {
					wrap.style.display = options.length === 0 ? 'none' : '';
				}
			}

			parent.addEventListener( 'change', updateChild );
			updateChild(); // 初期化
		} );
	}

	function init() {
		document.querySelectorAll( '.hxfe-form' ).forEach( initCascade );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

	document.addEventListener( 'htmx:afterSwap', function( e ) {
		var form = e.target && e.target.querySelector( '.hxfe-form' );
		if ( form ) { initCascade( form ); }
	} );
} )();
