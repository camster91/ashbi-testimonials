<?php
/** Administrator design preset controls. @package Ashbi_Testimonials */
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! current_user_can( 'manage_options' ) ) { return; }
$ashbte_presets = ASHBTE_Presets::all();
$ashbte_key = ASHBTE_Admin::nav_arg( 'preset' );
$ashbte_design = $ashbte_presets[ $ashbte_key ] ?? array( 'name' => '', 'layout' => 'grid', 'columns' => '3', 'theme' => 'light', 'accent' => '#2457ff' );
?>
<details class="ashbte-panel ashbte-settings" id="ashbte-design-presets">
 <summary><?php esc_html_e( 'Reusable designs', 'ashbi-testimonials' ); ?></summary>
 <p><?php esc_html_e( 'Save a design once, select it in blocks or Elementor, or use its preset key in a shortcode. Presets override layout, columns, theme and accent controls until you clear the preset selection.', 'ashbi-testimonials' ); ?></p>
 <table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Name', 'ashbi-testimonials' ); ?></th><th><?php esc_html_e( 'Preset key', 'ashbi-testimonials' ); ?></th><th><?php esc_html_e( 'Actions', 'ashbi-testimonials' ); ?></th></tr></thead><tbody>
 <?php foreach ( $ashbte_presets as $ashbte_slug => $ashbte_preset ) : ?>
 <tr><td><?php echo esc_html( $ashbte_preset['name'] ); ?></td><td><code><?php echo esc_html( $ashbte_slug ); ?></code></td><td>
  <a class="button" href="<?php echo esc_url( ASHBTE_Admin::url( array( 'preset' => $ashbte_slug ) ) . '#ashbte-design-presets' ); ?>"><?php esc_html_e( 'Edit', 'ashbi-testimonials' ); ?></a>
  <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
   <?php wp_nonce_field( 'ashbte_presets' ); ?><input type="hidden" name="action" value="ashbte_presets" /><input type="hidden" name="operation" value="delete" /><input type="hidden" name="preset_key" value="<?php echo esc_attr( $ashbte_slug ); ?>" />
   <button class="button" type="submit"><?php esc_html_e( 'Delete preset', 'ashbi-testimonials' ); ?></button>
  </form>
 </td></tr>
 <?php endforeach; ?>
 </tbody></table>
 <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ashbte-settings__form">
  <?php wp_nonce_field( 'ashbte_presets' ); ?><input type="hidden" name="action" value="ashbte_presets" /><input type="hidden" name="operation" value="save" />
  <p><label><?php esc_html_e( 'Design name', 'ashbi-testimonials' ); ?><br /><input name="name" required maxlength="80" value="<?php echo esc_attr( $ashbte_design['name'] ); ?>" /></label></p>
  <p><label><?php esc_html_e( 'Preset key (optional for a new design)', 'ashbi-testimonials' ); ?><br /><input name="preset_key" maxlength="40" value="<?php echo esc_attr( $ashbte_key ); ?>" /></label></p>
  <p><label><?php esc_html_e( 'Layout', 'ashbi-testimonials' ); ?> <select name="layout"><?php foreach ( ASHBTE_Data::layouts() as $ashbte_value => $ashbte_label ) : ?><option value="<?php echo esc_attr( $ashbte_value ); ?>" <?php selected( $ashbte_design['layout'], $ashbte_value ); ?>><?php echo esc_html( $ashbte_label ); ?></option><?php endforeach; ?></select></label></p>
  <p><label><?php esc_html_e( 'Columns', 'ashbi-testimonials' ); ?> <input name="columns" type="number" min="1" max="4" value="<?php echo esc_attr( $ashbte_design['columns'] ); ?>" /></label></p>
  <p><label><?php esc_html_e( 'Theme', 'ashbi-testimonials' ); ?> <select name="theme"><?php foreach ( array( 'light' => __( 'Light', 'ashbi-testimonials' ), 'dark' => __( 'Dark', 'ashbi-testimonials' ), 'auto' => __( 'Automatic', 'ashbi-testimonials' ) ) as $ashbte_value => $ashbte_label ) : ?><option value="<?php echo esc_attr( $ashbte_value ); ?>" <?php selected( $ashbte_design['theme'], $ashbte_value ); ?>><?php echo esc_html( $ashbte_label ); ?></option><?php endforeach; ?></select></label></p>
  <p><label><?php esc_html_e( 'Accent colour', 'ashbi-testimonials' ); ?> <input name="accent" type="color" value="<?php echo esc_attr( $ashbte_design['accent'] ?: '#2457ff' ); ?>" /></label></p>
  <p><button class="button button-primary" type="submit"><?php esc_html_e( 'Save design', 'ashbi-testimonials' ); ?></button></p>
 </form>
 <h3><?php esc_html_e( 'Move designs between sites', 'ashbi-testimonials' ); ?></h3>
 <p><?php esc_html_e( 'Transfers contain design values only. Importing adds designs and updates matching preset keys. Export a backup first. Reviews, notification addresses and site settings are not transferred.', 'ashbi-testimonials' ); ?></p>
 <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
  <?php wp_nonce_field( 'ashbte_presets' ); ?><input type="hidden" name="action" value="ashbte_presets" /><input type="hidden" name="operation" value="export" /><button class="button" type="submit"><?php esc_html_e( 'Export designs', 'ashbi-testimonials' ); ?></button>
 </form>
 <form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
  <?php wp_nonce_field( 'ashbte_presets' ); ?><input type="hidden" name="action" value="ashbte_presets" /><input type="hidden" name="operation" value="import" />
  <p><label><?php esc_html_e( 'Design file (JSON, up to 64 KB)', 'ashbi-testimonials' ); ?> <input type="file" name="preset_file" accept="application/json,.json" required /></label> <button class="button" type="submit"><?php esc_html_e( 'Import designs', 'ashbi-testimonials' ); ?></button></p>
 </form>
</details>
