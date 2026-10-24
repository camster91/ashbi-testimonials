<?php
/** Site-local, portable design settings. @package Ashbi_Testimonials */
if ( ! defined( 'ABSPATH' ) ) { exit; }
class ABT_Presets {
	const OPTION = 'abt_design_presets';
	public static function init() { add_action( 'admin_post_abt_presets', array( __CLASS__, 'handle' ) ); }
	public static function all() {
		$stored = get_option( self::OPTION, array() );
		return is_array( $stored ) ? $stored : array();
	}
	public static function apply( array $attrs ) {
		$ref = isset( $attrs['preset'] ) && is_scalar( $attrs['preset'] ) ? sanitize_key( (string) $attrs['preset'] ) : '';
		$preset = self::all()[ $ref ] ?? null;
		if ( is_array( $preset ) ) {
			unset( $preset['name'] );
			$attrs = array_merge( $attrs, $preset );
		}
		return $attrs;
	}
	public static function validate( $presets ) {
		if ( ! is_array( $presets ) || count( $presets ) > 30 ) { return new WP_Error( 'abt_presets', __( 'Use no more than 30 design presets.', 'ashbi-testimonials' ) ); }
		$clean = array();
		foreach ( $presets as $key => $preset ) {
			if ( ! is_string( $key ) || ! preg_match( '/^[a-z0-9][a-z0-9-]{0,39}$/', $key ) || ! is_array( $preset ) || array_diff( array_keys( $preset ), array( 'name', 'layout', 'columns', 'theme', 'accent' ) ) ) {
				return new WP_Error( 'abt_presets', __( 'The preset file contains an unsupported field or key.', 'ashbi-testimonials' ) );
			}
			foreach ( $preset as $value ) { if ( ! is_scalar( $value ) || strlen( (string) $value ) > 320 ) { return new WP_Error( 'abt_presets', __( 'Preset values must be short text values.', 'ashbi-testimonials' ) ); } }
			$name = sanitize_text_field( (string) ( $preset['name'] ?? '' ) );
			$layout = $preset['layout'] ?? 'grid';
			$theme = $preset['theme'] ?? 'light';
			$columns = (string) ( $preset['columns'] ?? '3' );
			$accent = (string) ( $preset['accent'] ?? '' );
			if ( '' === $name || ! isset( ABT_Data::layouts()[ $layout ] ) || ! in_array( $theme, array( 'light', 'dark', 'auto' ), true ) || ! preg_match( '/^[1-4]$/', $columns ) || ( '' !== $accent && ! sanitize_hex_color( $accent ) ) ) {
				return new WP_Error( 'abt_presets', __( 'Check the preset name, layout, columns, theme and hex colour.', 'ashbi-testimonials' ) );
			}
			$clean[ $key ] = array( 'name' => $name, 'layout' => $layout, 'columns' => $columns, 'theme' => $theme, 'accent' => $accent );
		}
		return $clean;
	}
	public static function export_json() { return wp_json_encode( array( 'version' => 1, 'presets' => self::all() ), JSON_PRETTY_PRINT ); }
	public static function import_json( $json ) {
		if ( ! is_string( $json ) || strlen( $json ) > 65536 ) { return new WP_Error( 'abt_presets', __( 'Choose a preset file smaller than 64 KB.', 'ashbi-testimonials' ) ); }
		$data = json_decode( $json, true, 8 );
		if ( ! is_array( $data ) || 1 !== ( $data['version'] ?? null ) || ! isset( $data['presets'] ) || array_diff( array_keys( $data ), array( 'version', 'presets' ) ) ) {
			return new WP_Error( 'abt_presets', __( 'This preset file has an unsupported format or version.', 'ashbi-testimonials' ) );
		}
		$validated = self::validate( $data['presets'] );
		if ( is_wp_error( $validated ) ) { return $validated; }
		$merged = self::validate( array_merge( self::all(), $validated ) );
		if ( is_wp_error( $merged ) ) { return $merged; }
		update_option( self::OPTION, $merged, false );
		return true;
	}
	public static function handle() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'You do not have permission to manage design presets.', 'ashbi-testimonials' ) ); }
		check_admin_referer( 'abt_presets' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Checked above.
		$operation = isset( $_POST['operation'] ) && is_string( $_POST['operation'] ) ? sanitize_key( wp_unslash( $_POST['operation'] ) ) : '';
		if ( 'export' === $operation ) {
			nocache_headers(); header( 'Content-Type: application/json; charset=utf-8' ); header( 'Content-Disposition: attachment; filename="ashbi-design-presets.json"' );
			echo self::export_json(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Validated JSON download, not HTML.
			exit;
		}
		if ( 'import' === $operation ) {
			$file = $_FILES['preset_file'] ?? array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Only the verified uploaded file body is read below.
			if ( ! is_array( $file ) || ! isset( $file['tmp_name'], $file['size'], $file['error'] ) || ! is_string( $file['tmp_name'] ) || UPLOAD_ERR_OK !== $file['error'] || (int) $file['size'] > 65536 || ! is_uploaded_file( $file['tmp_name'] ) ) {
				wp_die( esc_html__( 'Choose a valid preset JSON upload smaller than 64 KB.', 'ashbi-testimonials' ) );
			}
			$result = self::import_json( file_get_contents( $file['tmp_name'] ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Validated temporary upload, bounded above.
		} else {
			$presets = self::all();
			$key = isset( $_POST['preset_key'] ) && is_string( $_POST['preset_key'] ) ? sanitize_key( wp_unslash( $_POST['preset_key'] ) ) : '';
			if ( 'delete' === $operation ) { unset( $presets[ $key ] ); $result = $presets; }
			elseif ( 'save' === $operation ) {
				$preset = array();
				foreach ( array( 'name', 'layout', 'columns', 'theme', 'accent' ) as $field ) {
					$preset[ $field ] = isset( $_POST[ $field ] ) && is_string( $_POST[ $field ] ) ? wp_unslash( $_POST[ $field ] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Full allowlist/type/value validation in validate() before any write.
				}
				if ( '' === $key ) { $key = sanitize_title( $preset['name'] ); if ( ! preg_match( '/^[a-z0-9][a-z0-9-]{0,39}$/', $key ) ) { $key = 'preset-' . substr( md5( $preset['name'] ), 0, 12 ); } }
				$presets[ $key ] = $preset;
				$result = self::validate( $presets );
			} else { $result = new WP_Error( 'abt_presets', __( 'Choose a valid preset operation.', 'ashbi-testimonials' ) ); }
			if ( ! is_wp_error( $result ) ) { update_option( self::OPTION, $result, false ); }
		}
		// phpcs:enable
		if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ) ); }
		wp_safe_redirect( admin_url( 'admin.php?page=ashbi-testimonials&presets-updated=1' ) ); exit;
	}
}
