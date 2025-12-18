<?php
/**
 * Tests for the Plugin class.
 *
 * @package aysnc/wordpress-dynamic-media
 */

declare( strict_types = 1 );

namespace Aysnc\WordPress\DynamicMedia\Tests;

use Aysnc\WordPress\DynamicMedia\Plugin;
use WP_UnitTestCase;

/**
 * Plugin test case.
 */
class PluginTest extends WP_UnitTestCase {
	/**
	 * Test that the plugin registers hooks.
	 */
	public function test_plugin_hooks_registered(): void {
		// Bootstrap the plugin.
		Plugin::bootstrap();

		// Check that the after_setup_theme action is registered.
		$this->assertNotFalse( has_action( 'after_setup_theme', [ Plugin::class, 'register_adapters' ] ) );

		// Check that the image_downsize filter is registered.
		$this->assertNotFalse( has_filter( 'image_downsize', [ Plugin::class, 'filter_image_downsize' ] ) );
	}

	/**
	 * Test that the pause method works.
	 */
	public function test_plugin_pause_functionality(): void {
		// Test pausing.
		Plugin::pause( true );

		// When paused, the filter should return the original value.
		$result = Plugin::filter_image_downsize( false, 1, 'thumbnail' );
		$this->assertFalse( $result );

		// Test unpausing.
		Plugin::pause( false );
	}

	/**
	 * Test that the plugin requires valid dimensions.
	 */
	public function test_filter_image_downsize_requires_dimensions(): void {
		Plugin::pause( false );

		// With an invalid size, it should return false.
		$result = Plugin::filter_image_downsize( false, 999999, 'invalid-size' );
		$this->assertFalse( $result );
	}
}
