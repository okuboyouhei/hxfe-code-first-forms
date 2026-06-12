<?php
/**
 * Honeypot spam protection.
 *
 * Renders a hidden field that bots fill in but humans leave empty.
 * Completely JS-free. No third-party API required.
 *
 * The honeypot field name is derived from the form ID so it differs
 * between forms, making trivial pattern-matching harder.
 *
 * @package HtmxFormEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns the honeypot field name for a form.
 *
 * @param string $form_id Form identifier.
 * @return string
 */
function hxfe_honeypot_field_name( $form_id ) {
	return 'hxfe_hp_' . sanitize_key( $form_id );
}

/**
 * Renders the honeypot field HTML.
 * The wrapper div is visually hidden via inline CSS.
 * Screen readers are excluded via aria-hidden.
 *
 * @param string $form_id Form identifier.
 * @return string HTML string.
 */
function hxfe_honeypot_field_html( $form_id ) {
	$name = esc_attr( hxfe_honeypot_field_name( $form_id ) );
	return '<div aria-hidden="true" style="position:absolute;left:-9999px;top:-9999px;width:1px;height:1px;overflow:hidden;">'
		. '<label for="' . $name . '">' . esc_html__( 'この欄は入力しないでください', 'hxfe-code-first-forms' ) . '</label>'
		. '<input type="text" id="' . $name . '" name="' . $name . '" value="" tabindex="-1" autocomplete="off">'
		. '</div>';
}

/**
 * Returns true if the honeypot field was left empty (legitimate user).
 * Returns false if it was filled (bot detected).
 *
 * @param string $form_id  Form identifier.
 * @param array  $raw_post Raw $_POST data.
 * @return bool
 */
function hxfe_honeypot_is_clean( $form_id, array $raw_post ) {
	$field_name = hxfe_honeypot_field_name( $form_id );
	$value      = isset( $raw_post[ $field_name ] )
		? trim( wp_unslash( (string) $raw_post[ $field_name ] ) )
		: '';
	return '' === $value;
}
