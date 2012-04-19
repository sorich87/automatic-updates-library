<?php

/**
 * Add the options page to the admin menu
 *
 * @since ThemeMY! 0.1
 */
function thememy_admin_menu() {
	add_options_page( __( 'ThemeMY! Options' ), __( 'ThemeMY!' ), 'manage_options', 'thememy-options', 'thememy_options_page' );
}
add_action( 'admin_menu', 'thememy_admin_menu' );

/**
 * Whitelist the plugin options
 *
 * @since ThemeMY! 0.1
 */
function thememy_options_init() {
	register_setting( 'thememy_settings', 'thememy_options', 'thememy_options_validate' );
}
add_action( 'admin_init', 'thememy_options_init' );

/**
 * Options page content
 *
 * @since ThemeMY! 0.1
 */
function thememy_options_page() {
	$options = get_option( 'thememy_options' );
?>
	<div class="wrap">
		<h2><?php _e( 'ThemeMY! API options' ); ?></h2>

		<form method="post" action="options.php">
			<?php settings_fields( 'thememy_settings' ); ?>

			<table class="form-table">
				<tr valign="top">
					<th scope="row"><?php _e( 'Email' ); ?></th>
					<td>
						<input type="text" name="thememy_options[email]" value="<?php echo $options['email']; ?>" />
						<span class="description"><?php _e( 'The email address you used to purchase your theme' ); ?></span>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php _e( 'API Key' ); ?></th>
					<td>
						<input type="password" name="thememy_options[api_key]" value="<?php echo $options['api_key']; ?>" />
						<span class="description"><?php _e( 'Your API key provided on the theme download page' ); ?></span>
					</td>
				</tr>
			</table>
			<p class="submit">
				<input type="submit" class="button-primary" value="<?php _e( 'Save Changes' ); ?>" />
			</p>
		</form>
	</div>
<?php	
}

/**
 * Validate options page input
 *
 * @since ThemeMY! 0.1
 */
function thememy_options_validate( $input ) {
	$input['email'] =  wp_filter_nohtml_kses( $input['email'] );
	$input['api_key'] =  wp_filter_nohtml_kses( $input['api_key'] );

	return $input;
}

/**
 * Add link to options page as plugin action link
 *
 * @since ThemeMY! 0.1
 */
function thememy_plugin_action_links( $links, $file ) {
	if ( plugin_basename( dirname( __FILE__ ) . '/thememy.php' ) == $file )
		$links[] = '<a href="admin.php?page=thememy-options">' . __( 'Settings' ) . '</a>';

	return $links;
}
add_filter( 'plugin_action_links', 'thememy_plugin_action_links', 10, 2 );

/**
 * Show admin notice when the plugin options are empty
 *
 * @since ThemeMY! 0.1
 */
function thememy_admin_notices() {
	if ( get_option( 'thememy_options' ) != false )
		return;
?>
	<div id="thememy-error" class="error fade">
	<p><strong>
		<?php printf(
			__( 'To receive updates for your purchased themes, you need to enter your API credentials on the <a href="%s">settings page</a>.' ),
			admin_url( 'admin.php?page=thememy-options' )
		) ; ?>
	</strong></p>
</div>
<?php
}
add_action( 'admin_notices', 'thememy_admin_notices' );

