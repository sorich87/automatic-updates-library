<?php
/**
 * @package Automatic_Updates
 * @version 0.1
 */

// Define Push.ly API URL
if ( ! defined( 'PUSHLY_API_URL' ) )
	define( 'PUSHLY_API_URL', 'app.push.ly/api/v1' );

// Secret key for encryption
if ( ! defined( 'PUSHLY_SECRET' ) )
	define( 'PUSHLY_SECRET', 'set in wp-content.php for the best security' );

// $pushly_version should be an array
global $pushly_version;
if ( ! is_array( $pushly_version ) )
  $pushly_version = array();

// Load version number of this instance of the library
include( dirname( __FILE__ ) . '/version.php' );

// Save the version details
$dir = dirname( __FILE__ );
$pushly_version[$dir] = $version;

/**
 * Run after theme setup to load the most recent version of the library
 *
 * @since Automatic Updates 0.1
 */
if ( ! function_exists( 'pushly_init' ) ) {
  function pushly_init() {
		global $pushly_version;
		reset( $pushly_version );
		arsort( $pushly_version );
		$recent_version_dir = key( $pushly_version );

		include( $recent_version_dir . '/updates.php' );
  }
  add_action( 'after_setup_theme', 'pushly_init' );
}

