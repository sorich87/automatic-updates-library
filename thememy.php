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

// Define ThemeMY! API URL
if ( ! defined( 'THEMEMY_API_URL' ) )
	define( 'THEMEMY_API_URL', 'http://thememy.com/api/' );

/**
 * Check for themes updates
 *
 * @since ThemeMY! 0.1
 */
function thememy_update_themes( $update ) {
	if ( empty( $update->checked ) )
		return $update;

	include ABSPATH . WPINC . '/version.php'; // include an unmodified $wp_version

	$settings = get_option( 'thememy_options' );

	$themes = array();
	$current_theme = get_option( 'stylesheet' );
	$installed_themes = wp_get_themes();

	foreach ( (array) $installed_themes as $theme_title => $theme ) {
		$stylesheet = $theme['Stylesheet'];
		$themes[$stylesheet] = array();

		$themes[$stylesheet]['Name']    = $theme['Name'];
		$themes[$stylesheet]['Version'] = $theme['Version'];
		$themes[$stylesheet]['ThemeURI']     = $theme['ThemeURI'];
		$themes[$stylesheet]['Active']  = ( $stylesheet == $current_theme ) ? 'yes' : 'no';
	}

	$args = array(
		'body' => array(
			'action'  => 'theme_update',
			'email'   => $settings['email'],
			'api_key' => $settings['api_key'],
			'themes'  => json_encode( $themes )
		),
		'user-agent' => "WordPress/{$wp_version}; " . get_bloginfo('url')
	);

	$raw_response = wp_remote_post( THEMEMY_API_URL, $args );

	if ( ! is_wp_error( $raw_response ) && 200 == wp_remote_retrieve_response_code( $raw_response ) )
		$response = json_decode( wp_remote_retrieve_body( $raw_response ), true );

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

