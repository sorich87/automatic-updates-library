<?php

/**
 * Return Push.ly API URL
 *
 * @since Automatic Updates 0.1
 */
function pushly_api_url( $path = '' ) {
	$scheme = force_ssl_admin() || is_ssl() ? 'https' : 'http';
	return untrailingslashit( $scheme . '://' . PUSHLY_API_URL . '/' . $path );
}

/**
 * Return website email
 *
 * @since Automatic Updates 0.1
 */
function pushly_email() {
	return get_option( 'pushly_email' );
}

/**
 * Return website domain name
 *
 * @since Automatic Updates 0.1
 */
function pushly_domain_name() {
	return parse_url( site_url(), PHP_URL_HOST );
}

/**
 * Return website secret key
 *
 * @since Automatic Updates 0.1
 */
function pushly_secret_key() {
	return wp_hash( PUSHLY_SECRET );
}

/**
 * Return authentication token for Push.ly API
 *
 * @since Automatic Updates 0.1
 */
function pushly_get_token() {
	if ( ! $email = pushly_email() )
		return;

	$token = get_option( 'pushly_auth_token' );

	if ( $token ) {
		if ( time() - $token['time'] < 3600 )
			return $token;

		pushly_delete_token( $token );
		delete_option( 'pushly_auth_token' );
	}

	$domain_name = pushly_domain_name();
	$secret_key = pushly_secret_key();

	$body = compact( 'email', 'domain_name', 'secret_key' );
	$url = pushly_api_url( 'tokens' );

	$args = array(
		'headers' => array(
			'Content-Type' => 'application/json'
		),
		'body' => json_encode( $body ),
		'sslverify' => false
	);
	$response = wp_remote_post( $url, $args );

	if ( is_wp_error( $response ) ) {
		$message = sprintf( __( 'Error contacting the updates server: %s' ), $response->get_error_message() );
		return new WP_Error( 'invalid_request', $message );
	}

	$response_code = wp_remote_retrieve_response_code( $response );
	$body = json_decode( wp_remote_retrieve_body( $response ) );

	switch ( $response_code ) {
		case 400:
			return new WP_Error( 'unconfirmed_domain', __( 'Please check your email for a message to confirm your domain name.' ) );

		case 200:
			update_option( 'pushly_auth_token', $body->token );
			return $body->token;

		default:
			$message = sprintf( __( 'The updates server returned an invalid reponse: %s' ), wp_remote_retrieve_response_message( $response ) );
			return new WP_Error( 'invalid_response', $message );
	}
}

/**
 * Send a non blocking request with small timeout value to delete authentication token
 *
 * @since Automatic Updates 0.1
 */
function pushly_delete_token( $token ) {
	$url = pushly_api_url( "tokens/$token" );

	$args = array(
		'method' => 'DELETE',
		'headers' => array(
			'Content-Type' => 'application/json'
		),
		'timeout' => 0.01,
		'blocking' => false,
		'sslverify' => false
	);
	wp_remote_request( $url, $args );
}

/**
 * Ping Push.ly API and return an error if can't get the auth token
 *
 * @since Automatic Updates 0.1
 */
function pushly_ping( $echo = true ) {
	$token = pushly_get_token();
	if ( ! is_wp_error( $token ) )
		return;

	if ( $echo )
		echo '<div class="error"><p><strong>' . $token->get_error_message() . '</strong></p></div>';
	else
		return $token;
}


/**
 * Register the site with Push.ly
 *
 * @since Automatic Updates 0.1
 */
function pushly_register_site() {
	if ( ! $email = pushly_email() )
		return;

	$domain_name = pushly_domain_name();
	$secret_key = pushly_secret_key();

	$body = compact( 'email', 'domain_name', 'secret_key' );
	$url = pushly_api_url( 'sites' );

	$args = array(
		'headers' => array(
			'Content-Type' => 'application/json'
		),
		'body' => json_encode( $body ),
		'sslverify' => false
	);
	$response = wp_remote_post( $url, $args );

	if ( is_wp_error( $response ) ) {
		$message = sprintf( __( 'Error contacting the updates server: %s' ), $response->get_error_message() );
		return new WP_Error( 'invalid_request', $message );
	}

	$response_code = wp_remote_retrieve_response_code( $response );
	$body = json_decode( wp_remote_retrieve_body( $response ) );

	switch ( $response_code ) {
		case 400:
			if ( 22 != $body->code ) {
				$message = sprintf( __( 'One or more errors occured: %s' ), implode( ', ', $body->errors ) );
				return new WP_Error( 'remote_error', $message );
			}
			break;

		case 200:
			return true;

		default:
			$message = sprintf( __( 'The updates server returned an invalid reponse: %s' ), wp_remote_retrieve_response_message( $response ) );
			return new WP_Error( 'invalid_response', $message );
	}
}

/**
 * Check for plugins updates
 *
 * @since Automatic Updates 0.1
 */
function pushly_update_plugins( $update ) {
	global $wp_version;

	if ( empty( $update->checked ) )
		return $update;

	$auth_token = pushly_get_token();

	if ( is_wp_error( $auth_token ) )
		return;

	$domain_name = pushly_domain_name();

	if ( ! function_exists( 'get_plugins' ) )
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );

	$plugins = get_plugins();
	$active_plugins  = get_option( 'active_plugins', array() );

	$body = compact( 'auth_token', 'domain_name', 'plugins', 'active_plugins' );

	$url = pushly_api_url( 'plugins/update-check' );

	$args = array(
		'headers' => array(
			'Content-Type' => 'application/json'
		),
		'body' => json_encode( $body ),
		'sslverify' => false,
		'timeout' => ( ( defined('DOING_CRON') && DOING_CRON ) ? 30 : 3),
		'user-agent'	=> 'WordPress/' . $wp_version . '; ' . get_bloginfo( 'url' )
	);

	$raw_response = wp_remote_get( $url, $args );

	if ( ! is_wp_error( $raw_response ) && 200 == wp_remote_retrieve_response_code( $raw_response ) )
		$response = (array) json_decode( wp_remote_retrieve_body( $raw_response ) );

	if ( ! empty( $response ) ) {
		$update->response = array_merge( $update->response, $response );
	}

	return $update;
}
add_filter( 'pre_set_site_transient_update_plugins', 'pushly_update_plugins' );

/**
 * Check for themes updates
 *
 * @since Automatic Updates 0.1
 */
function pushly_update_themes( $update ) {
	global $wp_version;

	if ( empty( $update->checked ) )
		return $update;

	$auth_token = pushly_get_token();

	if ( is_wp_error( $auth_token ) )
		return;

	$domain_name = pushly_domain_name();

	$themes = array();
	$current_theme = get_option( 'stylesheet' );
	$installed_themes = wp_get_themes();

	foreach ( (array) $installed_themes as $theme_title => $theme ) {
		$themes[ $theme->get_stylesheet() ] = array(
			'Name'       => $theme->get('Name'),
			'Title'      => $theme->get('Name'),
			'Version'    => $theme->get('Version'),
			'Author'     => $theme->get('Author'),
			'Author URI' => $theme->get('AuthorURI'),
			'Template'   => $theme->get_template(),
			'Stylesheet' => $theme->get_stylesheet(),
		);
	}

	$body = compact( 'auth_token', 'domain_name', 'themes', 'current_theme' );

	$url = pushly_api_url( 'themes/update-check' );

	$args = array(
		'headers' => array(
			'Content-Type' => 'application/json'
		),
		'body' => json_encode( $body ),
		'sslverify' => false,
		'timeout' => ( ( defined('DOING_CRON') && DOING_CRON ) ? 30 : 3),
		'user-agent'	=> 'WordPress/' . $wp_version . '; ' . get_bloginfo( 'url' )
	);

	$raw_response = wp_remote_get( $url, $args );

	if ( ! is_wp_error( $raw_response ) && 200 == wp_remote_retrieve_response_code( $raw_response ) )
		$response = json_decode( wp_remote_retrieve_body( $raw_response ), true );

	if ( ! empty( $response ) )
		$update->response = array_merge( $update->response, $response );

	return $update;
}
add_filter( 'pre_set_site_transient_update_themes', 'pushly_update_themes' );

/**
 * Get plugin information
 *
 * @since Automatic Updates 0.1
 */
function pushly_get_plugin_info( $def, $action, $args ) {
	global $wp_version;

	if ( empty( $args->slug ) )
		return $def;

	$auth_token = pushly_get_token();

	if ( is_wp_error( $auth_token ) )
		return $def;

	$domain_name = pushly_domain_name();

	$body = compact( 'auth_token', 'domain_name' );

	$url = pushly_api_url( "plugins/$args->slug" );

	$args = array(
		'headers' => array(
			'Content-Type' => 'application/json'
		),
		'body' => json_encode( $body ),
		'sslverify' => false,
		'timeout' => 15,
		'user-agent'	=> 'WordPress/' . $wp_version . '; ' . get_bloginfo( 'url' )
	);
	$request = wp_remote_get( $url, $args );

	if ( is_wp_error( $request ) ) {
		$res = new WP_Error( 'plugins_api_failed', __( 'An unexpected error occurred. Something may be wrong with Push.ly or this server&#8217;s configuration.' ), $request->get_error_message() );
	} else {
		$res = json_decode( wp_remote_retrieve_body( $request ), true );
		if ( 200 != wp_remote_retrieve_response_code( $request ) )
			$res = new WP_Error('plugins_api_failed', __( 'An unexpected error occurred. Something may be wrong with Push.ly or this server&#8217;s configuration.' ), $res ? $res->message : '' );
	}

	return empty( $res ) ? $def : (object) $res;
}
add_filter( 'plugins_api', 'pushly_get_plugin_info', 10, 3 );

/**
 * Get theme information
 *
 * @since Automatic Updates 0.1
 */
function pushly_get_theme_info( $def, $action, $args ) {
	global $wp_version;

	if ( empty( $args->slug ) )
		return $def;

	$auth_token = pushly_get_token();

	if ( is_wp_error( $auth_token ) )
		return $def;

	$domain_name = pushly_domain_name();

	$body = compact( 'auth_token', 'domain_name' );

	$url = pushly_api_url( "themes/$args->slug" );

	$args = array(
		'headers' => array(
			'Content-Type' => 'application/json'
		),
		'body' => json_encode( $body ),
		'sslverify' => false,
		'timeout' => 15,
		'user-agent'	=> 'WordPress/' . $wp_version . '; ' . get_bloginfo( 'url' )
	);
	$request = wp_remote_get( $url, $args );

	if ( is_wp_error( $request ) ) {
		$res = new WP_Error( 'themes_api_failed', __( 'An unexpected error occurred. Something may be wrong with Push.ly or this server&#8217;s configuration.' ), $request->get_error_message() );
	} else {
		$res = json_decode( wp_remote_retrieve_body( $request ), true );
		if ( 200 != wp_remote_retrieve_response_code( $request ) )
			$res = new WP_Error('themes_api_failed', __( 'An unexpected error occurred. Something may be wrong with Push.ly or this server&#8217;s configuration.' ), $res );
	}

	return empty( $res ) ? $def : (object) $res;
}
add_filter( 'themes_api', 'pushly_get_theme_info', 10, 3 );

/**
 * When debugging, check for updates on every request
 *
 * @since Automatic Updates 0.1
 */
function pushly_update_debug() {
	if ( ! defined( 'PUSHLY_DEBUG' ) || ! PUSHLY_DEBUG )
		return;

	set_site_transient( 'update_plugins', null );
	set_site_transient( 'update_themes', null );
}
add_action( 'init', 'pushly_update_debug' );

