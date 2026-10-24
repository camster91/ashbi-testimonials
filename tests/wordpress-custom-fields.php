<?php
/** Custom field validation, public/private rendering and portability. */
if ( ! defined( 'ABSPATH' ) ) { exit( 1 ); }
$check = static function ( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( 'Custom fields: ' . $message ); } };
$check( class_exists( 'ABT_Fields' ), 'field provider exists' );
$definitions = array(
 array( 'key' => 'project', 'label' => 'Project', 'type' => 'text', 'required' => true, 'public' => true ),
 array( 'key' => 'private_note', 'label' => 'Private note', 'type' => 'textarea', 'required' => false, 'public' => false ),
 array( 'key' => 'tier', 'label' => 'Plan', 'type' => 'select', 'required' => true, 'public' => true, 'options' => array( 'Basic', 'Advanced' ) ),
);
$invalid_label = $definitions; $invalid_label[0]['label'] = '<b></b>';
$check( is_wp_error( ABT_Fields::definitions( $invalid_label ) ), 'labels must remain nonempty after sanitization' );
$data = ABT_Data::instance();
$col = $data->save_collection( array( 'name' => 'Portable custom fields', 'fields' => $definitions ) );
$check( ! is_wp_error( $col ) && 3 === count( $col['fields'] ), 'collection retains its field definitions' );
$check( is_wp_error( ABT_Fields::submission( $col['id'], array() ) ), 'required fields are checked on the server' );
$check( is_wp_error( ABT_Fields::submission( $col['id'], array( 'project' => array( 'bad' ) ) ) ), 'nested field values are rejected' );
$check( is_wp_error( ABT_Fields::submission( $col['id'], array( 'project' => 'Valid', 'tier' => 'forged' ) ) ), 'select values must be defined options' );
$values = ABT_Fields::submission( $col['id'], array( 'project' => 'Public project', 'tier' => 'Advanced', 'private_note' => 'PRIVATE CUSTOM DETAIL' ) );
$check( ! is_wp_error( $values ), 'valid public/private values are accepted' );
$id = $data->save( array( 'author' => 'Custom reviewer', 'email' => 'custom@example.test', 'rating' => 5, 'collections' => array( $col['id'] ), 'custom_fields' => $values ) );
$html = ABT_Render::instance()->card( $data->get( $id ) );
$check( false !== strpos( $html, 'Public project' ) && false === strpos( $html, 'PRIVATE CUSTOM DETAIL' ), 'cards render only opted-in public custom details' );
$exporters = apply_filters( 'wp_privacy_personal_data_exporters', array() );
$privacy = call_user_func( $exporters['ashbi-testimonials']['callback'], 'custom@example.test', 1 );
$check( false !== strpos( wp_json_encode( $privacy ), 'PRIVATE CUSTOM DETAIL' ), 'privacy export includes private custom values' );
$admin = ABT_Admin::instance();
$row = array_combine( ABT_Admin::CSV_COLUMNS, $admin->export_row( $data->get( $id ) ) );
$data->delete_collection( $col['id'] );
$restored = $admin->import_row( $row );
$check( ! is_wp_error( $restored ) && 'PRIVATE CUSTOM DETAIL' === $data->get( $restored )['custom_fields']['private_note']['value'], 'CSV preserves custom value snapshots' );
$new_col = get_term_by( 'slug', 'portable-custom-fields', ABT_Data::TAXONOMY );
$check( 3 === count( $data->collection_to_array( $new_col )['fields'] ), 'CSV restores missing collection definitions' );
$bad = $values; $bad['project']['type'] = 'script';
$check( is_wp_error( $data->save( array( 'author' => 'Bad', 'rating' => 5, 'custom_fields' => $bad ) ) ), 'unsupported custom types cannot be stored' );
$f = $data->collection_to_array( $new_col ); $f['fields']['project']['public'] = false;
$data->save_collection( $f, $new_col->term_id );
$check( false === strpos( ABT_Render::instance()->card( $data->get( $restored ) ), 'Public project' ), 'making a definition private hides historic public values' );
fwrite( STDOUT, "OK: custom field validation, privacy and CSV portability passed\n" );

// WordPress metadata APIs strip slashes; preserve legitimate text across writes and CSV.
$slash_values = $values; $slash_values['project']['value'] = 'C:\project\notes';
$slash_id = $data->save( array( 'author' => 'Portable text reviewer', 'rating' => 5, 'custom_fields' => $slash_values ) );
$check( 'C:\project\notes' === $data->get( $slash_id )['custom_fields']['project']['value'], 'literal backslashes survive metadata storage' );
$wide = array();
foreach ( range( 1, 10 ) as $i ) { $wide[ 'wide_' . $i ] = array( 'label' => 'Unicode answer', 'type' => 'textarea', 'public' => false, 'value' => str_repeat( '🌿', 2000 ) ); }
$wide_id = $data->save( array( 'author' => 'Unicode reviewer', 'rating' => 5, 'custom_fields' => $wide ) );
$wide_row = array_combine( ABT_Admin::CSV_COLUMNS, $admin->export_row( $data->get( $wide_id ) ) );
$wide_restored = $admin->import_row( $wide_row );
$check( ! is_wp_error( $wide_restored ) && $wide === $data->get( $wide_restored )['custom_fields'], 'maximum supported Unicode answers survive CSV export and import' );
fwrite( STDOUT, "OK: literal text and maximum Unicode answer portability passed\n" );
