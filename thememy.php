<?php
/**
 * @package ThemeMY_
 * @version 0.1
 */
/*
Plugin Name: ThemeMY!
Plugin URI: http://thememy.com/
Description: Automatic updates for your themes bought via ThemeMY!
Version: 0.1
Author: ThemeMY!
Author URI: http://thememy.com/
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

define( 'THEMEMY_DEBUG', true );

// Define ThemeMY! API URL
if ( ! defined( 'THEMEMY_API_URL' ) )
	define( 'THEMEMY_API_URL', 'thememy.com/api/v1' );

if ( ! defined( 'THEMEMY_SECRET' ) )
	define( 'THEMEMY_SECRET', 'set in wp-content.php for the best security' );

/**
 * Return ThemeMY! API URL
 *
 * @since ThemeMY! 0.1
 */
function thememy_api_url( $path = '' ) {
	$scheme = force_ssl_admin() || is_ssl() ? 'https' : 'http';
	return untrailingslashit( $scheme . '://' . THEMEMY_API_URL . '/' . $path );
}

/**
 * Return website email
 *
 * @since ThemeMY! 0.1
 */
function thememy_email() {
	return get_option( 'thememy_email' );
}

/**
 * Return website domain name
 *
 * @since ThemeMY! 0.1
 */
function thememy_domain_name() {
	return parse_url( site_url(), PHP_URL_HOST );
}

/**
 * Return website secret key
 *
 * @since ThemeMY! 0.1
 */
function thememy_secret_key() {
	return wp_hash( THEMEMY_SECRET );
}

/**
 * Return authentication token for ThemeMY! API
 *
 * @since ThemeMY! 0.1
 */
function thememy_get_token() {
	$email = thememy_email();
	$domain_name = thememy_domain_name();
	$secret_key = thememy_secret_key();

	$body = compact( 'email', 'domain_name', 'secret_key' );
	$url = thememy_api_url( 'tokens' );

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
				$register_site = thememy_register_site( $email );
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
 * @since ThemeMY! 0.1
 */
function thememy_delete_token( $token ) {
	$url = thememy_api_url( "tokens/$token" );

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
 * Register the site with ThemeMY!
 *
 * @since ThemeMY! 0.1
 */
function thememy_register_site() {
	$email = thememy_email();
	$domain_name = thememy_domain_name();
	$secret_key = thememy_secret_key();

	$body = compact( 'email', 'domain_name', 'secret_key' );
	$url = thememy_api_url( 'sites' );

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
 * @since ThemeMY! 0.1
 */
function thememy_update_themes( $update ) {
	global $wp_version;

	if ( empty( $update->checked ) )
		return;

	$auth_token = thememy_get_token();

	$domain_name = thememy_domain_name();

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

	$url = thememy_api_url( 'sites/themes' );

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
add_filter( 'pre_set_site_transient_update_themes', 'thememy_update_themes' );

/**
 * When debugging, check for updates on every request
 *
 * @since ThemeMY! 0.1
 */
function thememy_update_debug() {
	if ( defined( 'THEMEMY_DEBUG' ) && THEMEMY_DEBUG )
		set_site_transient( 'update_themes', null );
}
add_action( 'init', 'thememy_update_debug' );

/**
 * Load admin page
 *
 * @since ThemeMY! 0.1
 */
function thememy_admin() {
	if ( is_admin() )
		include plugin_dir_path( __FILE__ ) . 'admin.php';
}
add_action( 'init', 'thememy_admin' );

