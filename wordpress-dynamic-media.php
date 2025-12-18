<?php
/**
 * Plugin Name: WordPress Dynamic Media
 * Description: Dynamic media for WordPress.
 * Version: 1.0.0
 * Text Domain: wordpress-dynamic-media
 * Author: Aysnc
 * Author URI: https://aysnc.dev
 *
 * @package aysnc/wordpress-dynamic-media
 */

namespace Aysnc\WordPress\DynamicMedia;

// Composer autoloader.
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

// Bootstrap the plugin.
add_action( 'plugins_loaded', [ Plugin::class, 'bootstrap' ] );
