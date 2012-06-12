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
	register_setting( 'thememy_settings', 'thememy_email', 'thememy_email_validate' );
}
add_action( 'admin_init', 'thememy_options_init' );

/**
 * Options page content
 *
 * @since ThemeMY! 0.1
 */
function thememy_options_page() {
	$token = thememy_get_token();
	if ( is_wp_error( $token ) )
		echo '<div class="error"><p><strong>' . $token->get_error_message() . '</strong></p></div>';
	else
		thememy_delete_token( $token );

	$email = get_option( 'thememy_email' );
?>
	<div class="wrap">
		<h2><?php _e( 'ThemeMY! API options' ); ?></h2>

		<form method="post" action="options.php">
			<?php settings_fields( 'thememy_settings' ); ?>

			<table class="form-table">
				<tr valign="top">
					<th scope="row"><?php _e( 'Email' ); ?></th>
					<td>
						<input type="text" name="thememy_email" value="<?php echo $email; ?>" />
						<span class="description"><?php _e( 'The email address you used to purchase your theme' ); ?></span>
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
function thememy_email_validate( $email ) {
	$email = sanitize_email( $email );

	if ( empty( $email ) ) {
		add_settings_error( 'thememy_email', 'invalid_email', __( 'Please enter a valid email.' ) );
		return '';
	}

	return $email;
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
	global $plugin_page;

	if ( 'thememy-options' == $plugin_page || get_option( 'thememy_email' ) != false )
		return;
?>
	<div id="thememy-error" class="error fade">
	<p><strong>
<?php printf(
	__( 'To receive updates for your purchased themes, you need to enter your email address on the <a href="%s">settings page</a>.' ),
	admin_url( 'admin.php?page=thememy-options' )
) ; ?>
	</strong></p>
</div>
<?php
}
add_action( 'admin_notices', 'thememy_admin_notices' );

