<?php

/**
 * Add the options page to the admin menu
 *
 * @since Automatic Updates 0.1
 */
function pushly_admin_menu() {
	add_options_page( __( 'Automatic Updates Options' ), __( 'Automatic Updates' ), 'manage_options', 'pushly-options', 'pushly_options_page' );
}
add_action( 'admin_menu', 'pushly_admin_menu' );

/**
 * Whitelist the plugin options
 *
 * @since Automatic Updates 0.1
 */
function pushly_options_init() {
	register_setting( 'pushly_settings', 'pushly_email', 'pushly_email_validate' );
}
add_action( 'admin_init', 'pushly_options_init' );

/**
 * Options page content
 *
 * @since Automatic Updates 0.1
 */
function pushly_options_page() {
	$token = pushly_get_token();
	if ( is_wp_error( $token ) )
		echo '<div class="error"><p><strong>' . $token->get_error_message() . '</strong></p></div>';

	$email = get_option( 'pushly_email' );
?>
	<div class="wrap">
		<h2><?php _e( 'Automatic Updates API options' ); ?></h2>

		<form method="post" action="options.php">
			<?php settings_fields( 'pushly_settings' ); ?>

			<table class="form-table">
				<tr valign="top">
					<th scope="row"><?php _e( 'Email' ); ?></th>
					<td>
						<input type="text" name="pushly_email" value="<?php echo $email; ?>" />
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
 * @since Automatic Updates 0.1
 */
function pushly_email_validate( $email ) {
	$email = sanitize_email( $email );

	if ( empty( $email ) ) {
		add_settings_error( 'pushly_email', 'invalid_email', __( 'Please enter a valid email.' ) );
		return '';
	}

	pushly_register_site();

	return $email;
}

/**
 * Add link to options page as plugin action link
 *
 * @since Automatic Updates 0.1
 */
function pushly_plugin_action_links( $links, $file ) {
	if ( plugin_basename( dirname( __FILE__ ) . '/automatic-updates.php' ) == $file )
		$links[] = '<a href="admin.php?page=pushly-options">' . __( 'Settings' ) . '</a>';

	return $links;
}
add_filter( 'plugin_action_links', 'pushly_plugin_action_links', 10, 2 );

/**
 * Show admin notice when the plugin options are empty
 *
 * @since Automatic Updates 0.1
 */
function pushly_admin_notices() {
	global $plugin_page;

	if ( 'pushly-options' == $plugin_page || get_option( 'pushly_email' ) != false )
		return;
?>
	<div id="pushly-error" class="error fade">
	<p><strong>
<?php printf(
	__( 'To receive updates for your themes and plugins, you need to enter your email address on the <a href="%s">settings page</a>.' ),
	admin_url( 'admin.php?page=pushly-options' )
) ; ?>
	</strong></p>
</div>
<?php
}
add_action( 'admin_notices', 'pushly_admin_notices' );

