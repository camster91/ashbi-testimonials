<?php
/** Compare real v1.0.0 data through an in-place update and binary rollback. */
if ( ! defined( 'ABSPATH' ) || '1' !== getenv( 'ABT_DISPOSABLE_TEST' ) ) { exit( 1 ); }
$phase = getenv( 'ABT_UPGRADE_PHASE' );
$snapshot = static function( $ids, $collections ) {
 $out = array( 'reviews' => array(), 'collections' => array(), 'settings' => get_option( ABT_SETTINGS_OPTION ) );
 foreach ( $ids as $id ) { $p = get_post( $id ); if ( ! $p ) { throw new RuntimeException( 'Upgrade removed a fixture review.' ); } $terms = wp_get_object_terms( $id, ABT_Data::TAXONOMY, array( 'fields' => 'ids' ) ); sort( $terms ); $out['reviews'][ $id ] = array( 'title' => $p->post_title, 'content' => $p->post_content, 'status' => $p->post_status, 'date' => $p->post_date, 'meta' => get_post_meta( $id ), 'collections' => $terms, 'revisions' => count( wp_get_post_revisions( $id ) ) ); }
 foreach ( $collections as $id ) { $t = get_term( $id, ABT_Data::TAXONOMY ); $out['collections'][ $id ] = array( 'name' => $t->name, 'slug' => $t->slug, 'meta' => get_term_meta( $id ) ); }
 return $out;
};
if ( 'seed' === $phase ) {
 if ( '1.0.0' !== ABT_VERSION ) { throw new RuntimeException( 'Seed must run with the actual v1.0.0 plugin loaded.' ); }
 $data = ABT_Data::instance(); $a = $data->save_collection( array( 'name' => 'Upgrade product reviews', 'type' => 'product', 'layout' => 'list', 'accent' => '#234567' ) ); $b = $data->save_collection( array( 'name' => 'Upgrade service reviews', 'type' => 'service' ) );
 update_option( ABT_SETTINGS_OPTION, array( 'accent' => '#234567', 'schema' => '0', 'woo_tab' => '0', 'notify_email' => 'upgrade@example.test', 'date_format' => 'Y-m-d' ), false );
 $ids = array();
 foreach ( array( 'publish', 'pending', 'draft', 'trash' ) as $status ) {
  $id = $data->save( array( 'author' => 'Upgrade ' . $status, 'content' => '<p>Original paragraphs.</p><p>Safe <strong>formatting</strong>.</p>', 'email' => $status . '@example.test', 'rating' => 4, 'featured' => 1, 'verified' => 1, 'company' => 'Fictional Studio', 'date' => '2025-05-01', 'status' => 'trash' === $status ? 'publish' : $status, 'collections' => array( $a['id'], $b['id'] ) ) );
  if ( is_wp_error( $id ) ) { throw new RuntimeException( $id->get_error_message() ); }
  wp_save_post_revision( $id ); if ( 'trash' === $status ) { wp_trash_post( $id ); } $ids[] = $id;
 }
 update_option( 'abt_upgrade_fixture', array( 'ids' => $ids, 'collections' => array( $a['id'], $b['id'] ), 'snapshot' => $snapshot( $ids, array( $a['id'], $b['id'] ) ) ), false );
} else {
 $fixture = get_option( 'abt_upgrade_fixture' );
 if ( ! is_array( $fixture ) || $snapshot( $fixture['ids'], $fixture['collections'] ) !== $fixture['snapshot'] ) { throw new RuntimeException( 'Upgrade/rollback changed existing reviews, metadata, revisions, assignments, collections or stored settings.' ); }
}
fwrite( STDOUT, 'OK: real v1.0.0 upgrade fixture ' . $phase . ' under plugin ' . ABT_VERSION . "\n" );
