<?php
/**
 * Tests for the Media class.
 *
 * @package aysnc/wordpress-dynamic-media
 */

declare( strict_types = 1 );

namespace Aysnc\WordPress\DynamicMedia\Tests;

use Aysnc\WordPress\DynamicMedia\Adapter;
use Aysnc\WordPress\DynamicMedia\Adapters\MediaAdapter;
use Aysnc\WordPress\DynamicMedia\Media;
use ReflectionClass;
use WP_UnitTestCase;

/**
 * Media test case.
 */
class MediaTest extends WP_UnitTestCase {
	/**
	 * Setup test environment.
	 */
	public function setUp(): void {
		parent::setUp();

		// Reset Media static cache using reflection.
		$reflection           = new ReflectionClass( Media::class );
		$image_sizes_property = $reflection->getProperty( 'image_sizes' );
		$image_sizes_property->setAccessible( true );
		$image_sizes_property->setValue( null, [] );

		// Reset Adapter state.
		$adapter_reflection = new ReflectionClass( Adapter::class );
		$adapters_property  = $adapter_reflection->getProperty( 'adapters' );
		$adapters_property->setAccessible( true );
		$adapters_property->setValue( null, [] );

		$current_property = $adapter_reflection->getProperty( 'current_adapter' );
		$current_property->setAccessible( true );
		$current_property->setValue( null, null );
	}

	/**
	 * Test getting all image sizes includes WordPress defaults.
	 */
	public function test_get_all_image_sizes_includes_defaults(): void {
		$sizes = Media::get_all_image_sizes();

		// Should contain WordPress default sizes.
		$this->assertArrayHasKey( 'thumbnail', $sizes );
		$this->assertArrayHasKey( 'medium', $sizes );
		$this->assertArrayHasKey( 'large', $sizes );

		// Each size should have width, height, and crop keys.
		foreach ( [ 'thumbnail', 'medium', 'large' ] as $size ) {
			$this->assertArrayHasKey( 'width', $sizes[ $size ] );
			$this->assertArrayHasKey( 'height', $sizes[ $size ] );
			$this->assertArrayHasKey( 'crop', $sizes[ $size ] );
			$this->assertIsInt( $sizes[ $size ]['width'] ); // @phpstan-ignore method.alreadyNarrowedType
			$this->assertIsInt( $sizes[ $size ]['height'] ); // @phpstan-ignore method.alreadyNarrowedType
			$this->assertIsBool( $sizes[ $size ]['crop'] ); // @phpstan-ignore method.alreadyNarrowedType
		}
	}

	/**
	 * Test getting all image sizes caches results.
	 */
	public function test_get_all_image_sizes_caches_results(): void {
		// First call.
		$sizes1 = Media::get_all_image_sizes();

		// Second call should return cached results.
		$sizes2 = Media::get_all_image_sizes();

		$this->assertSame( $sizes1, $sizes2 );
	}

	/**
	 * Test getting all image sizes includes custom sizes.
	 */
	public function test_get_all_image_sizes_includes_custom_sizes(): void {
		// Add a custom image size.
		add_image_size( 'custom-size', 300, 200, true );

		// Clear the cache to force re-reading.
		$reflection           = new ReflectionClass( Media::class );
		$image_sizes_property = $reflection->getProperty( 'image_sizes' );
		$image_sizes_property->setAccessible( true );
		$image_sizes_property->setValue( null, [] );

		$sizes = Media::get_all_image_sizes();

		// Should include the custom size.
		$this->assertArrayHasKey( 'custom-size', $sizes );
		$this->assertSame( 300, $sizes['custom-size']['width'] );
		$this->assertSame( 200, $sizes['custom-size']['height'] );
		$this->assertTrue( $sizes['custom-size']['crop'] );

		// Clean up.
		remove_image_size( 'custom-size' );
	}

	/**
	 * Test getting image size by name returns correct size.
	 */
	public function test_get_image_size_by_name_returns_correct_size(): void {
		$size = Media::get_image_size_by_name( 'thumbnail' );

		$this->assertArrayHasKey( 'width', $size );
		$this->assertArrayHasKey( 'height', $size );
		$this->assertArrayHasKey( 'crop', $size );
		$this->assertIsInt( $size['width'] ); // @phpstan-ignore offsetAccess.notFound, method.alreadyNarrowedType
		$this->assertIsInt( $size['height'] ); // @phpstan-ignore method.alreadyNarrowedType
		$this->assertIsBool( $size['crop'] ); // @phpstan-ignore method.alreadyNarrowedType
	}

	/**
	 * Test getting image size by name with invalid size returns empty array.
	 */
	public function test_get_image_size_by_name_with_invalid_size(): void {
		$size = Media::get_image_size_by_name( 'nonexistent-size' );

		$this->assertIsArray( $size ); // @phpstan-ignore method.alreadyNarrowedType
		$this->assertEmpty( $size );
	}

	/**
	 * Test getting image size by name for custom size.
	 */
	public function test_get_image_size_by_name_for_custom_size(): void {
		// Add a custom image size.
		add_image_size( 'test-custom', 400, 300, false );

		// Clear the cache.
		$reflection           = new ReflectionClass( Media::class );
		$image_sizes_property = $reflection->getProperty( 'image_sizes' );
		$image_sizes_property->setAccessible( true );
		$image_sizes_property->setValue( null, [] );

		$size = Media::get_image_size_by_name( 'test-custom' );

		$this->assertSame( 400, $size['width'] ); // @phpstan-ignore offsetAccess.notFound
		$this->assertSame( 300, $size['height'] );
		$this->assertFalse( $size['crop'] );

		// Clean up.
		remove_image_size( 'test-custom' );
	}

	/**
	 * Test getting dynamic URL without adapter returns empty string.
	 */
	public function test_get_dynamic_url_without_adapter(): void {
		$url = Media::get_dynamic_url( 123 );

		$this->assertSame( '', $url );
	}

	/**
	 * Test getting dynamic URL with adapter.
	 */
	public function test_get_dynamic_url_with_adapter(): void {
		// Create and register a mock adapter.
		$mock_adapter = new class () implements MediaAdapter {
			/**
			 * Get dynamic URL.
			 *
			 * @param int     $attachment_id Attachment ID.
			 * @param mixed[] $args          Transformation arguments.
			 *
			 * @return string
			 */
			public static function get_dynamic_url( int $attachment_id, array $args = [] ): string {
				return 'https://example.com/image-' . $attachment_id . '.jpg';
			}
		};

		Adapter::register( 'test', $mock_adapter );
		Adapter::set( 'test' );

		$url = Media::get_dynamic_url( 456 );

		$this->assertSame( 'https://example.com/image-456.jpg', $url );
	}

	/**
	 * Test getting dynamic URL applies filter.
	 */
	public function test_get_dynamic_url_applies_filter(): void {
		// Create and register a mock adapter.
		$mock_adapter = new class () implements MediaAdapter {
			/**
			 * Get dynamic URL.
			 *
			 * @param int     $attachment_id Attachment ID.
			 * @param mixed[] $args          Transformation arguments.
			 *
			 * @return string
			 */
			public static function get_dynamic_url( int $attachment_id, array $args = [] ): string {
				return 'https://example.com/image.jpg';
			}
		};

		Adapter::register( 'test', $mock_adapter );
		Adapter::set( 'test' );

		// Add a filter to modify the URL.
		add_filter(
			'aysnc_wordpress_dynamic_media_url',
			function ( $url, $attachment_id, $args ) {
				/** @var int $attachment_id */
				return 'https://filtered.com/image-' . $attachment_id . '.jpg';
			},
			10,
			3,
		);

		$url = Media::get_dynamic_url( 789, [ 'width' => 100 ] );

		$this->assertSame( 'https://filtered.com/image-789.jpg', $url );

		// Clean up filter.
		remove_all_filters( 'aysnc_wordpress_dynamic_media_url' );
	}

	/**
	 * Test getting dynamic URL when filter returns non-string.
	 */
	public function test_get_dynamic_url_when_filter_returns_non_string(): void {
		// Create and register a mock adapter.
		$mock_adapter = new class () implements MediaAdapter {
			/**
			 * Get dynamic URL.
			 *
			 * @param int     $attachment_id Attachment ID.
			 * @param mixed[] $args          Transformation arguments.
			 *
			 * @return string
			 */
			public static function get_dynamic_url( int $attachment_id, array $args = [] ): string {
				return 'https://example.com/image.jpg';
			}
		};

		Adapter::register( 'test', $mock_adapter );
		Adapter::set( 'test' );

		// Add a filter that returns a non-string.
		add_filter(
			'aysnc_wordpress_dynamic_media_url',
			function () {
				return 123; // Return integer instead of string.
			},
		);

		$url = Media::get_dynamic_url( 123 );

		// Should return empty string when filter returns non-string.
		$this->assertSame( '', $url );

		// Clean up filter.
		remove_all_filters( 'aysnc_wordpress_dynamic_media_url' );
	}
}
