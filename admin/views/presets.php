<?php
/** Administrator design preset controls. @package Ashbi_Testimonials */
if ( ! defined( 'ABSPATH' ) || ! current_user_can( 'manage_options' ) ) { return; }
$abt_presets = ABT_Presets::all();
$abt_key = isset( $_GET['preset'] ) && is_string( $_GET['preset'] ) ? sanitize_key( wp_unslash( $_GET['preset'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only selection.
$abt_design = $abt_presets[ $abt_key ] ?? array( 'name' => '', 'layout' => 'grid', 'columns' => '3', 'theme' => 'light', 'accent' => '#2457ff' );
?>
<details class="abt-panel abt-settings" id="abt-design-presets">
 <summary><?php esc_html_e( 'Reusable designs', 'ashbi-testimonials' ); ?></summary>
 <p><?php esc_html_e( 'Save a design once, select it in blocks or Elementor, or use its preset key in a shortcode. Presets override layout, columns, theme and accent controls until you clear the preset selection.', 'ashbi-testimonials' ); ?></p>
 <table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Name', 'ashbi-testimonials' ); ?></th><th><?php esc_html_e( 'Preset key', 'ashbi-testimonials' ); ?></th><th><?php esc_html_e( 'Actions', 'ashbi-testimonials' ); ?></th></tr></thead><tbody>
 <?php foreach ( $abt_presets as $abt_slug => $abt_preset ) : ?>
 <tr><td><?php echo esc_html( $abt_preset['name'] ); ?></td><td><code><?php echo esc_html( $abt_slug ); ?></code></td><td>
  <a class="button" href="<?php echo esc_url( add_query_arg( array( 'page' => 'ashbi-testimonials', 'preset' => $abt_slug ), admin_url( 'admin.php' ) ) . '#abt-design-presets' ); ?>"><?php esc_html_e( 'Edit', 'ashbi-testimonials' ); ?></a>
  <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
   <?php wp_nonce_field( 'abt_presets' ); ?><input type="hidden" name="action" value="abt_presets" /><input type="hidden" name="operation" value="delete" /><input type="hidden" name="preset_key" value="<?php echo esc_attr( $abt_slug ); ?>" />
   <button class="button" type="submit"><?php esc_html_e( 'Delete preset', 'ashbi-testimonials' ); ?></button>
  </form>
 </td></tr>
 <?php endforeach; ?>
 </tbody></table>
 <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="abt-settings__form">
  <?php wp_nonce_field( 'abt_presets' ); ?><input type="hidden" name="action" value="abt_presets" /><input type="hidden" name="operation" value="save" />
  <p><label><?php esc_html_e( 'Design name', 'ashbi-testimonials' ); ?><br /><input name="name" required maxlength="80" value="<?php echo esc_attr( $abt_design['name'] ); ?>" /></label></p>
  <p><label><?php esc_html_e( 'Preset key (optional for a new design)', 'ashbi-testimonials' ); ?><br /><input name="preset_key" maxlength="40" value="<?php echo esc_attr( $abt_key ); ?>" /></label></p>
  <p><label><?php esc_html_e( 'Layout', 'ashbi-testimonials' ); ?> <select name="layout"><?php foreach ( ABT_Data::layouts() as $abt_value => $abt_label ) : ?><option value="<?php echo esc_attr( $abt_value ); ?>" <?php selected( $abt_design['layout'], $abt_value ); ?>><?php echo esc_html( $abt_label ); ?></option><?php endforeach; ?></select></label></p>
  <p><label><?php esc_html_e( 'Columns', 'ashbi-testimonials' ); ?> <input name="columns" type="number" min="1" max="4" value="<?php echo esc_attr( $abt_design['columns'] ); ?>" /></label></p>
  <p><label><?php esc_html_e( 'Theme', 'ashbi-testimonials' ); ?> <select name="theme"><?php foreach ( array( 'light' => __( 'Light', 'ashbi-testimonials' ), 'dark' => __( 'Dark', 'ashbi-testimonials' ), 'auto' => __( 'Automatic', 'ashbi-testimonials' ) ) as $abt_value => $abt_label ) : ?><option value="<?php echo esc_attr( $abt_value ); ?>" <?php selected( $abt_design['theme'], $abt_value ); ?>><?php echo esc_html( $abt_label ); ?></option><?php endforeach; ?></select></label></p>
  <p><label><?php esc_html_e( 'Accent colour', 'ashbi-testimonials' ); ?> <input name="accent" type="color" value="<?php echo esc_attr( $abt_design['accent'] ?: '#2457ff' ); ?>" /></label></p>
  <p><button class="button button-primary" type="submit"><?php esc_html_e( 'Save design', 'ashbi-testimonials' ); ?></button></p>
 </form>
 <h3><?php esc_html_e( 'Move designs between sites', 'ashbi-testimonials' ); ?></h3>
 <p><?php esc_html_e( 'Transfers contain design values only. Importing adds designs and updates matching preset keys. Export a backup first. Reviews, notification addresses and site settings are not transferred.', 'ashbi-testimonials' ); ?></p>
 <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
  <?php wp_nonce_field( 'abt_presets' ); ?><input type="hidden" name="action" value="abt_presets" /><input type="hidden" name="operation" value="export" /><button class="button" type="submit"><?php esc_html_e( 'Export designs', 'ashbi-testimonials' ); ?></button>
 </form>
 <form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
  <?php wp_nonce_field( 'abt_presets' ); ?><input type="hidden" name="action" value="abt_presets" /><input type="hidden" name="operation" value="import" />
  <p><label><?php esc_html_e( 'Design file (JSON, up to 64 KB)', 'ashbi-testimonials' ); ?> <input type="file" name="preset_file" accept="application/json,.json" required /></label> <button class="button" type="submit"><?php esc_html_e( 'Import designs', 'ashbi-testimonials' ); ?></button></p>
 </form>
</details>
