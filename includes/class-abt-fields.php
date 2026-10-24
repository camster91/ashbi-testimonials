<?php
/** Bounded collection fields and private value snapshots.
 * @package Ashbi_Testimonials
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
class ABT_Fields {
	const TYPES = array( 'text', 'textarea', 'select', 'checkbox', 'url' );
	private static function error() { return new WP_Error( 'abt_fields', __( 'Check custom fields: use at most ten named fields, supported types and valid values.', 'ashbi-testimonials' ) ); }
	private static function text( $value, $max ) {
		return is_string( $value ) && strlen( $value ) <= $max * 4 && wp_check_invalid_utf8( $value, true ) === $value && preg_match_all( '/./us', $value ) <= $max;
	}
	/** Validate the entire set before writing. */
	public static function definitions( $input ) {
		if ( ! is_array( $input ) || count( $input ) > 10 ) { return self::error(); }
		$out = array();
		foreach ( $input as $field ) {
			if ( ! is_array( $field ) || array_diff( array_keys( $field ), array( 'key', 'label', 'type', 'required', 'public', 'options' ) ) ) { return self::error(); }
			$key = $field['key'] ?? '';
			if ( ! is_string( $key ) || ! preg_match( '/^[a-z][a-z0-9_]{0,39}$/D', $key ) || isset( $out[ $key ] ) || ! self::text( $field['label'] ?? null, 80 ) || '' === trim( $field['label'] ) || ! in_array( $field['type'] ?? '', self::TYPES, true ) || ! is_bool( $field['required'] ?? null ) || ! is_bool( $field['public'] ?? null ) ) { return self::error(); }
			$options = $field['options'] ?? array();
			if ( ! is_array( $options ) || count( $options ) > 20 || ( 'select' === $field['type'] && ! $options ) ) { return self::error(); }
			foreach ( $options as $option ) { if ( ! self::text( $option, 80 ) || '' === trim( $option ) || sanitize_text_field( $option ) !== $option ) { return self::error(); } }
			if ( count( array_unique( $options ) ) !== count( $options ) ) { return self::error(); }
			$out[ $key ] = array( 'key' => $key, 'label' => sanitize_text_field( $field['label'] ), 'type' => $field['type'], 'required' => $field['required'], 'public' => $field['public'], 'options' => 'select' === $field['type'] ? array_values( $options ) : array() );
		}
		return $out;
	}
	public static function for_collection( $id ) {
		$fields = get_term_meta( $id, 'abt_fields', true );
		$fields = self::definitions( is_array( $fields ) ? $fields : array() );
		return is_wp_error( $fields ) ? array() : $fields;
	}
	private static function value( $value, $type ) {
		if ( ! self::text( $value, 'textarea' === $type ? 2000 : 500 ) ) { return self::error(); }
		if ( 'checkbox' === $type && ! in_array( $value, array( '', '0', '1' ), true ) ) { return self::error(); }
		if ( 'url' === $type && '' !== $value && ( ! filter_var( $value, FILTER_VALIDATE_URL ) || ! preg_match( '#^https?://#i', $value ) ) ) { return self::error(); }
		return 'textarea' === $type ? sanitize_textarea_field( $value ) : ( 'url' === $type ? esc_url_raw( $value, array( 'http', 'https' ) ) : sanitize_text_field( $value ) );
	}
	/** Privacy flags and labels come from server definitions. */
	public static function submission( $collection, $input ) {
		$defs = self::for_collection( $collection );
		if ( ! is_array( $input ) || array_diff( array_keys( $input ), array_keys( $defs ) ) ) { return self::error(); }
		$out = array();
		foreach ( $defs as $key => $field ) {
			$value = self::value( $input[ $key ] ?? '', $field['type'] );
			if ( is_wp_error( $value ) || ( $field['required'] && ( '' === $value || ( 'checkbox' === $field['type'] && '1' !== $value ) ) ) || ( 'select' === $field['type'] && '' !== $value && ! in_array( $value, $field['options'], true ) ) ) { return self::error(); }
			$out[ $key ] = array( 'label' => $field['label'], 'type' => $field['type'], 'public' => $field['public'], 'value' => $value );
		}
		return $out;
	}
	public static function snapshots( $input ) {
		if ( ! is_array( $input ) || count( $input ) > 10 ) { return self::error(); }
		$out = array();
		foreach ( $input as $key => $field ) {
			if ( ! is_string( $key ) || ! preg_match( '/^[a-z][a-z0-9_]{0,39}$/D', $key ) || ! is_array( $field ) || array_diff( array_keys( $field ), array( 'label', 'type', 'public', 'value' ) ) || ! self::text( $field['label'] ?? null, 80 ) || ! in_array( $field['type'] ?? '', self::TYPES, true ) || ! is_bool( $field['public'] ?? null ) ) { return self::error(); }
			$value = self::value( $field['value'] ?? null, $field['type'] );
			if ( is_wp_error( $value ) ) { return $value; }
			$out[ $key ] = array( 'label' => sanitize_text_field( $field['label'] ), 'type' => $field['type'], 'public' => $field['public'], 'value' => $value );
		}
		return $out;
	}
	/** Recheck current visibility so a private/deleted definition hides historic values. */
	public static function card( array $item ) {
		$html = '';
		foreach ( $item['custom_fields'] ?? array() as $key => $field ) {
			$allowed = false;
			foreach ( $item['collections'] ?? array() as $collection ) {
				$defs = self::for_collection( $collection );
				if ( isset( $defs[ $key ] ) ) {
					if ( ! $defs[ $key ]['public'] || $defs[ $key ]['type'] !== $field['type'] ) { $allowed = false; break; }
					$allowed = true;
				}
			}
			if ( ! $allowed || ! $field['public'] || '' === $field['value'] ) { continue; }
			$value = 'checkbox' === $field['type'] ? ( '1' === $field['value'] ? __( 'Yes', 'ashbi-testimonials' ) : __( 'No', 'ashbi-testimonials' ) ) : $field['value'];
			$html .= '<div><dt>' . esc_html( $field['label'] ) . '</dt><dd>' . esc_html( $value ) . '</dd></div>';
		}
		return $html ? '<dl class="abt-card__fields">' . $html . '</dl>' : '';
	}
	public static function form( $collection ) {
		$html = '';
		foreach ( self::for_collection( $collection ) as $key => $field ) {
			$name = 'abt_custom[' . $key . ']';
			$required = $field['required'] ? ' required' : '';
			$html .= '<p><label>' . esc_html( $field['label'] ) . ' (' . esc_html( $field['public'] ? __( 'public', 'ashbi-testimonials' ) : __( 'not published', 'ashbi-testimonials' ) ) . ')';
			if ( 'select' === $field['type'] ) {
				$html .= '<select name="' . esc_attr( $name ) . '"' . $required . '><option value="">' . esc_html__( 'Choose…', 'ashbi-testimonials' ) . '</option>';
				foreach ( $field['options'] as $option ) { $html .= '<option value="' . esc_attr( $option ) . '">' . esc_html( $option ) . '</option>'; }
				$html .= '</select>';
			} elseif ( 'textarea' === $field['type'] ) {
				$html .= '<textarea name="' . esc_attr( $name ) . '" maxlength="2000" rows="3"' . $required . '></textarea>';
			} else {
				$type = 'checkbox' === $field['type'] ? 'checkbox' : ( 'url' === $field['type'] ? 'url' : 'text' );
				$html .= '<input type="' . esc_attr( $type ) . '" name="' . esc_attr( $name ) . '"' . ( 'checkbox' === $type ? ' value="1"' : ' maxlength="500"' ) . $required . ' />';
			}
			$html .= '</label></p>';
		}
		return $html;
	}
}
