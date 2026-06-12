/**
 * htmx Form Engine — front-end companion script.
 *
 * @package HtmxFormEngine
 */
( function () {
	'use strict';

	// htmx swap後にスクロール・フォーカス
	document.body.addEventListener( 'htmx:afterSettle', function ( evt ) {
		var id = evt.detail.target && evt.detail.target.id;
		if ( ! id ) { return; }

		setTimeout( function() {
			var wrap = document.getElementById( id );
			if ( ! wrap || ! wrap.classList.contains( 'hxfe-wrap' ) ) { return; }

			// フェードイン
			wrap.classList.add( 'hxfe-wrap--fadein' );

			wrap.scrollIntoView( { block: 'start' } );

			var focusable = wrap.querySelector(
				'input:not([type="hidden"]):not([type="submit"]), textarea, select, [tabindex="0"]'
			);
			if ( focusable ) {
				focusable.focus( { preventScroll: true } );
			}
		}, 200 );
	} );

	// ボタンのローディング状態
	document.body.addEventListener( 'htmx:beforeRequest', function ( evt ) {
		var trigger = evt.detail.elt;
		if ( trigger && trigger.tagName === 'BUTTON' ) {
			trigger.setAttribute( 'data-original-text', trigger.textContent );
			trigger.textContent = trigger.getAttribute( 'data-loading-text' ) || '…';
			trigger.disabled = true;
		}
	} );

	// リクエスト失敗時にボタンを戻す
	document.body.addEventListener( 'htmx:requestError', function ( evt ) {
		var trigger = evt.detail.elt;
		if ( trigger && trigger.tagName === 'BUTTON' ) {
			var original = trigger.getAttribute( 'data-original-text' );
			if ( original ) { trigger.textContent = original; }
			trigger.disabled = false;
		}
	} );

	// ラジオ clearable
	document.body.addEventListener( 'click', function( evt ) {
		var btn = evt.target.closest( '.hxfe-radio-clear' );
		if ( ! btn ) { return; }
		var group = btn.closest( '.hxfe-radio-group' );
		if ( ! group ) { return; }
		group.querySelectorAll( 'input[type="radio"]' ).forEach( function( r ) {
			r.checked = false;
		} );
	} );

} )();
