<?php
/**
 * @package Automatic_Updates
 * @version 0.1
 */
/*
Plugin Name: Automatic Updates
Plugin URI: http://push.ly/
Description: Automatic updates for your themes and plugins securely delivered by Push.ly automatic updates server
Version: 0.1
Author: Push.ly
Author URI: http://push.ly/
License: GPLv2 or later
 */

/*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

// Define Push.ly API URL
if ( ! defined( 'PUSHLY_API_URL' ) )
	define( 'PUSHLY_API_URL', 'app.push.ly/api/v1' );

if ( ! defined( 'PUSHLY_SECRET' ) )
	define( 'PUSHLY_SECRET', 'set in wp-content.php for the best security' );

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
	$email = pushly_email();
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
		if ( 20 != $body->code ) {
			$register_site = pushly_register_site( $email );
			if ( is_wp_error( $register_site ) )
				return $register_site;
		}

		return new WP_Error( 'unconfirmed_domain', __( 'Please check your email for a message to confirm your domain name.' ) );

	case 200:
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
 * Register the site with Push.ly
 *
 * @since Automatic Updates 0.1
 */
function pushly_register_site() {
	$email = pushly_email();
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
		$message = sprintf( __( 'One or more errors occured: %s' ), implode( ', ', $body->errors ) );
		return new WP_Error( 'remote_error', $message );

	case 200:
		return true;

	default:
		$message = sprintf( __( 'The updates server returned an invalid reponse: %s' ), wp_remote_retrieve_response_message( $response ) );
		return new WP_Error( 'invalid_response', $message );
	}
}

/**
 * Check for themes updates
 *
 * @since Automatic Updates 0.1
 */
function pushly_update_themes( $update ) {
	global $wp_version;

	if ( empty( $update->checked ) )
		return;

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

	$body = compact( 'auth_token', 'domain_name', 'themes' );

	$url = pushly_api_url( 'sites/themes' );

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
	$new_update = new stdClass;

	if ( ! empty( $response ) )
		$update->response = array_merge( $update->response, $response );

	return $update;
}
add_filter( 'pre_set_site_transient_update_themes', 'pushly_update_themes' );

/**
 * When debugging, check for updates on every request
 *
 * @since Automatic Updates 0.1
 */
function pushly_update_debug() {
	if ( defined( 'PUSHLY_DEBUG' ) && PUSHLY_DEBUG )
		set_site_transient( 'update_themes', null );
}
add_action( 'init', 'pushly_update_debug' );

/**
 * Load admin page
 *
 * @since Automatic Updates 0.1
 */
function pushly_admin() {
	if ( is_admin() )
		include plugin_dir_path( __FILE__ ) . 'admin.php';
}
add_action( 'init', 'pushly_admin' );

