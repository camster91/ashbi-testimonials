<?php
/** Fictional local/CI browser fixture only. */
if ( ! defined( 'ABSPATH' ) || '1' !== getenv( 'ABT_DISPOSABLE_TEST' ) ) { exit( 1 ); }
$admin = get_users( array( 'role' => 'administrator', 'number' => 1 ) )[0];
wp_set_password( 'abt-ci-only-password', $admin->ID );
$data = ABT_Data::instance();
$col = $data->save_collection( array( 'name' => 'Fictional browser demo', 'fields' => array( array( 'key' => 'project', 'label' => 'Project', 'type' => 'text', 'required' => true, 'public' => true ), array( 'key' => 'private_note', 'label' => 'Private note', 'type' => 'textarea', 'required' => false, 'public' => false ) ) ) );
foreach ( range( 1, 8 ) as $i ) {
	$data->save( array( 'author' => 'Demo reviewer ' . $i, 'content' => 'Fictional demonstration feedback. ' . str_repeat( 'Readable review content for testing layouts. ', 15 ), 'company' => 'Example Studio', 'rating' => $i % 2 ? 5 : 4, 'origin' => 'direct', 'collections' => array( $col['id'] ) ) );
}
$content = '<!-- wp:paragraph --><p>Fictional demonstration reviews for automated testing.</p><!-- /wp:paragraph -->';
foreach ( ABT_Data::layouts() as $layout => $label ) {
	$content .= '<!-- wp:heading --><h2>' . esc_html( $label ) . '</h2><!-- /wp:heading -->';
	$content .= serialize_block( array( 'blockName' => 'ashbi/testimonials', 'attrs' => array( 'collection' => (string) $col['id'], 'layout' => $layout, 'limit' => 'slider' === $layout ? '8' : '3', 'summary' => '1', 'load_more' => '1', 'schema' => '0' ), 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() ) );
}
foreach ( range( 1, 2 ) as $form ) {
	$content .= serialize_block( array( 'blockName' => 'ashbi/review-form', 'attrs' => array( 'collection' => (string) $col['id'] ), 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() ) );
}
$page = wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'Ashbi review browser fixture', 'post_status' => 'publish', 'post_content' => $content ) );
update_option( ABT_SETTINGS_OPTION, array( 'notify_email' => '' ), false );
fwrite( STDOUT, 'ABT_BROWSER_FIXTURE=' . wp_json_encode( array( 'url' => get_permalink( $page ), 'login' => wp_login_url(), 'admin' => admin_url( 'admin.php?page=ashbi-testimonials&collection=' . $col['id'] ), 'username' => $admin->user_login ) ) . "\n" );
