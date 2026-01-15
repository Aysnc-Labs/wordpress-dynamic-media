<?php
/**
 * Tests for the Plugin class.
 *
 * @package aysnc/wordpress-dynamic-media
 */

declare( strict_types = 1 );

namespace Aysnc\WordPress\DynamicMedia\Tests;

use Aysnc\WordPress\DynamicMedia\Adapter;
use Aysnc\WordPress\DynamicMedia\Adapters;
use Aysnc\WordPress\DynamicMedia\Media;
use Aysnc\WordPress\DynamicMedia\Plugin;
use ReflectionClass;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;
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

	/**
	 * Test that register_adapters registers cloudinary adapter.
	 */
	public function test_register_adapters(): void {
		// Reset adapter state.
		$reflection        = new ReflectionClass( Adapter::class );
		$adapters_property = $reflection->getProperty( 'adapters' );
		$adapters_property->setAccessible( true );
		$adapters_property->setValue( null, [] );

		$current_property = $reflection->getProperty( 'current_adapter' );
		$current_property->setAccessible( true );
		$current_property->setValue( null, null );

		// Register adapters.
		Plugin::register_adapters();

		// Check that an adapter was set.
		$adapter = Adapter::get();
		$this->assertNotNull( $adapter );
		$this->assertInstanceOf( Adapters\MediaAdapter::class, $adapter );
		$this->assertInstanceOf( Adapters\Cloudinary::class, $adapter );
	}

	/**
	 * Test filter_image_downsize with array size.
	 */
	public function test_filter_image_downsize_with_array_size(): void {
		// Set up the adapter and config.
		$this->setup_cloudinary_adapter();

		// Create a test attachment.
		$attachment_id = $this->factory->attachment->create_upload_object( __DIR__ . '/fixtures/test-image.jpg' );
		$this->assertIsInt( $attachment_id );

		Plugin::pause( false );

		// Test with array size [width, height].
		$result = Plugin::filter_image_downsize( false, $attachment_id, [ 300, 200 ] );

		$this->assertIsArray( $result );
		$this->assertCount( 3, $result );
		$this->assertSame( 300, $result[1] );
		$this->assertSame( 200, $result[2] );
		$this->assertStringContainsString( 'cloudinary.com', $result[0] );
	}

	/**
	 * Test filter_image_downsize with full size.
	 */
	public function test_filter_image_downsize_with_full_size(): void {
		// Set up adapter and config.
		$this->setup_cloudinary_adapter();

		// Create a test attachment.
		$attachment_id = $this->factory->attachment->create_upload_object( __DIR__ . '/fixtures/test-image.jpg' );
		$this->assertIsInt( $attachment_id );

		Plugin::pause( false );

		// Test with 'full' size.
		$result = Plugin::filter_image_downsize( false, $attachment_id, 'full' );

		$this->assertIsArray( $result );
		$this->assertCount( 3, $result );
		$this->assertStringContainsString( 'cloudinary.com', $result[0] );
	}

	/**
	 * Test filter_image_downsize with named size.
	 */
	public function test_filter_image_downsize_with_named_size(): void {
		// Set up the adapter and config.
		$this->setup_cloudinary_adapter();

		// Create a test attachment.
		$attachment_id = $this->factory->attachment->create_upload_object( __DIR__ . '/fixtures/test-image.jpg' );
		$this->assertIsInt( $attachment_id );

		Plugin::pause( false );

		// Test with named size.
		$result = Plugin::filter_image_downsize( false, $attachment_id, 'thumbnail' );

		$this->assertIsArray( $result );
		$this->assertCount( 3, $result );
		$this->assertStringContainsString( 'cloudinary.com', $result[0] );
	}

	/**
	 * Test filter_image_downsize with soft crop size.
	 */
	public function test_filter_image_downsize_with_soft_crop(): void {
		// Set up adapter and config.
		$this->setup_cloudinary_adapter();

		// Add a soft crop image size.
		add_image_size( 'test-soft-crop', 400, 300, false );

		// Create a test attachment.
		$attachment_id = $this->factory->attachment->create_upload_object( __DIR__ . '/fixtures/test-image.jpg' );
		$this->assertIsInt( $attachment_id );

		// Clear Media cache.
		$reflection           = new ReflectionClass( Media::class );
		$image_sizes_property = $reflection->getProperty( 'image_sizes' );
		$image_sizes_property->setAccessible( true );
		$image_sizes_property->setValue( null, [] );

		Plugin::pause( false );

		// Test with soft crop size.
		$result = Plugin::filter_image_downsize( false, $attachment_id, 'test-soft-crop' );

		$this->assertIsArray( $result );
		$this->assertCount( 3, $result );
		$this->assertStringContainsString( 'c_fit', $result[0] );

		// Clean up.
		remove_image_size( 'test-soft-crop' );
	}

	/**
	 * Test filter_wp_calculate_image_srcset.
	 */
	public function test_filter_wp_calculate_image_srcset(): void {
		// Set up adapter and config.
		$this->setup_cloudinary_adapter();

		// Create a test attachment.
		$attachment_id = $this->factory->attachment->create_upload_object( __DIR__ . '/fixtures/test-image.jpg' );
		$this->assertIsInt( $attachment_id );
		$meta = wp_get_attachment_metadata( $attachment_id );
		$this->assertIsArray( $meta );

		Plugin::pause( false );

		// Mock srcset sources.
		$sources = [
			'300' => [
				'url'        => 'http://example.com/image-300x200.jpg',
				'descriptor' => 'w',
				'value'      => 300,
			],
		];

		$result = Plugin::filter_wp_calculate_image_srcset(
			$sources,
			[ 300, 200 ],
			'http://example.com/image.jpg',
			$meta,
			$attachment_id,
		);

		$this->assertArrayHasKey( '300', $result );
		$this->assertIsArray( $result['300'] );
		$this->assertArrayHasKey( 'url', $result['300'] );
		$this->assertIsString( $result['300']['url'] );
		$this->assertStringContainsString( 'cloudinary.com', $result['300']['url'] );
	}

	/**
	 * Test filter_wp_calculate_image_srcset returns original when paused.
	 */
	public function test_filter_wp_calculate_image_srcset_when_paused(): void {
		Plugin::pause( true );

		$sources = [
			'300' => [
				'url'        => 'http://example.com/image-300x200.jpg',
				'descriptor' => 'w',
				'value'      => 300,
			],
		];

		$result = Plugin::filter_wp_calculate_image_srcset( $sources, [ 300, 200 ], 'http://example.com/image.jpg', [], 123 );

		$this->assertSame( $sources, $result );

		Plugin::pause( false );
	}

	/**
	 * Test get_srcset_dimensions.
	 */
	public function test_get_srcset_dimensions(): void {
		$image_meta = [
			'sizes' => [
				'thumbnail' => [
					'width'  => 150,
					'height' => 150,
				],
				'medium' => [
					'width'  => 300,
					'height' => 200,
				],
			],
		];

		$source = [
			'descriptor' => 'w',
			'value'      => 300,
		];

		$registered_sizes = [
			'medium' => [
				'width'  => 300,
				'height' => 200,
				'crop'   => true,
			],
		];

		$dimensions = Plugin::get_srcset_dimensions( $image_meta, $source, $registered_sizes );

		$this->assertIsArray( $dimensions ); // @phpstan-ignore method.alreadyNarrowedType.
		$this->assertSame( 300, $dimensions['width'] );
		$this->assertSame( 200, $dimensions['height'] );
		$this->assertTrue( $dimensions['hard_crop'] );
	}

	/**
	 * Test get_srcset_dimensions with no match returns dimension from source.
	 */
	public function test_get_srcset_dimensions_no_match(): void {
		$image_meta = [
			'sizes' => [
				'thumbnail' => [
					'width'  => 150,
					'height' => 150,
				],
			],
		];

		$source = [
			'descriptor' => 'w',
			'value'      => 300,
		];

		$dimensions = Plugin::get_srcset_dimensions( $image_meta, $source, [] );

		$this->assertIsArray( $dimensions ); // @phpstan-ignore method.alreadyNarrowedType.
		$this->assertSame( 300, $dimensions['width'] );
		$this->assertArrayNotHasKey( 'height', $dimensions );
	}

	/**
	 * Test update_content_images.
	 */
	public function test_update_content_images(): void {
		// Set up adapter and config.
		$this->setup_cloudinary_adapter();

		// Create a test attachment.
		$attachment_id = $this->factory->attachment->create_upload_object( __DIR__ . '/fixtures/test-image.jpg' );
		$this->assertIsInt( $attachment_id );

		Plugin::pause( false );

		// Create test content with an image.
		$content = sprintf(
			'<p>Test content</p><img src="http://example.com/image.jpg" class="wp-image-%d size-medium" width="300" height="200" />',
			$attachment_id,
		);

		$result = Plugin::update_content_images( $content );

		$this->assertStringContainsString( 'cloudinary.com', $result );
		$this->assertStringContainsString( 'wp-image-' . $attachment_id, $result );
	}

	/**
	 * Test update_content_images returns original when paused.
	 */
	public function test_update_content_images_when_paused(): void {
		Plugin::pause( true );

		$content = '<p>Test content</p><img src="http://example.com/image.jpg" class="wp-image-123" />';
		$result  = Plugin::update_content_images( $content );

		$this->assertSame( $content, $result );

		Plugin::pause( false );
	}

	/**
	 * Test update_content_images with empty content.
	 */
	public function test_update_content_images_with_empty_content(): void {
		Plugin::pause( false );

		$result = Plugin::update_content_images( '' );

		$this->assertSame( '', $result );
	}

	/**
	 * Test update_content_images skips images without wp-image class.
	 */
	public function test_update_content_images_skips_non_wp_images(): void {
		Plugin::pause( false );

		$content = '<p>Test content</p><img src="http://example.com/image.jpg" class="custom-class" />';
		$result  = Plugin::update_content_images( $content );

		// Should not modify images without wp-image class.
		$this->assertSame( $content, $result );
	}

	/**
	 * Test update_content_images with custom filter.
	 */
	public function test_update_content_images_with_custom_filter(): void {
		// Set up adapter and config.
		$this->setup_cloudinary_adapter();

		// Create a test attachment.
		$attachment_id = $this->factory->attachment->create_upload_object( __DIR__ . '/fixtures/test-image.jpg' );
		$this->assertIsInt( $attachment_id );

		Plugin::pause( false );

		// Add a custom filter.
		add_filter(
			'aysnc_wordpress_dynamic_media_content_image_src',
			function ( $src, $id ) use ( $attachment_id ) {
				if ( $id === $attachment_id ) {
					return 'https://custom.com/custom-image.jpg';
				}
				return $src;
			},
			10,
			2,
		);

		$content = sprintf(
			'<img src="http://example.com/image.jpg" class="wp-image-%d" width="300" height="200" />',
			$attachment_id,
		);

		$result = Plugin::update_content_images( $content );

		$this->assertStringContainsString( 'https://custom.com/custom-image.jpg', $result );

		// Clean up.
		remove_all_filters( 'aysnc_wordpress_dynamic_media_content_image_src' );
	}

	/**
	 * Test get_config returns default values.
	 */
	public function test_get_config_default_values(): void {
		// Reset config.
		$this->reset_plugin_config();

		$config = Plugin::get_config();

		$this->assertTrue( $config['rest_api_enabled'] );
	}

	/**
	 * Test get_config applies filter.
	 */
	public function test_get_config_applies_filter(): void {
		// Reset config.
		$this->reset_plugin_config();

		add_filter(
			'aysnc_wordpress_dynamic_media_config',
			function () {
				return [
					'rest_api_enabled' => false,
				];
			},
		);

		$config = Plugin::get_config();

		$this->assertFalse( $config['rest_api_enabled'] );

		// Clean up.
		remove_all_filters( 'aysnc_wordpress_dynamic_media_config' );
	}

	/**
	 * Test filter_rest_prepare_attachment transforms URLs.
	 */
	public function test_filter_rest_prepare_attachment(): void {
		// Reset config and set up adapter.
		$this->reset_plugin_config();
		$this->setup_cloudinary_adapter();

		// Create a test attachment.
		$attachment_id = $this->factory->attachment->create_upload_object( __DIR__ . '/fixtures/test-image.jpg' );
		$this->assertIsInt( $attachment_id );

		Plugin::pause( false );

		// Create mock response data.
		$attachment = get_post( $attachment_id );
		$this->assertInstanceOf( WP_Post::class, $attachment );

		$response = new WP_REST_Response(
			[
				'source_url'    => 'http://example.com/wp-content/uploads/test.jpg',
				'media_details' => [
					'sizes' => [
						'thumbnail' => [
							'source_url' => 'http://example.com/wp-content/uploads/test-150x150.jpg',
							'width'      => 150,
							'height'     => 150,
						],
						'medium' => [
							'source_url' => 'http://example.com/wp-content/uploads/test-300x200.jpg',
							'width'      => 300,
							'height'     => 200,
						],
					],
				],
			],
		);

		$request = new WP_REST_Request( 'GET', '/wp/v2/media/' . $attachment_id );

		$result = Plugin::filter_rest_prepare_attachment( $response, $attachment, $request );
		$data   = $result->get_data();

		// Check source_url is transformed.
		$this->assertTrue( is_array( $data ) && ! empty( $data['source_url'] ) );
		$this->assertIsString( $data['source_url'] );
		$this->assertStringContainsString( 'cloudinary.com', $data['source_url'] );

		// Check sizes are transformed.
		$this->assertTrue( is_array( $data['media_details'] ) );
		$this->assertTrue( is_array( $data['media_details']['sizes'] ) );
		$this->assertTrue( is_array( $data['media_details']['sizes']['thumbnail'] ) );
		$this->assertIsString( $data['media_details']['sizes']['thumbnail']['source_url'] );
		$this->assertStringContainsString( 'cloudinary.com', $data['media_details']['sizes']['thumbnail']['source_url'] );
		$this->assertTrue( is_array( $data['media_details']['sizes']['medium'] ) );
		$this->assertIsString( $data['media_details']['sizes']['medium']['source_url'] );
		$this->assertStringContainsString( 'cloudinary.com', $data['media_details']['sizes']['medium']['source_url'] );
	}

	/**
	 * Test filter_rest_prepare_attachment respects the paused state.
	 */
	public function test_filter_rest_prepare_attachment_when_paused(): void {
		Plugin::pause( true );

		$attachment_id = $this->factory->attachment->create_upload_object( __DIR__ . '/fixtures/test-image.jpg' );
		$this->assertIsInt( $attachment_id );

		$attachment = get_post( $attachment_id );
		$this->assertInstanceOf( WP_Post::class, $attachment );

		$original_url = 'http://example.com/wp-content/uploads/test.jpg';
		$response     = new WP_REST_Response( [ 'source_url' => $original_url ] );
		$request      = new WP_REST_Request( 'GET', '/wp/v2/media/' . $attachment_id );

		$result = Plugin::filter_rest_prepare_attachment( $response, $attachment, $request );
		$data   = $result->get_data();

		// Should not be transformed.
		$this->assertTrue( is_array( $data ) && ! empty( $data['source_url'] ) );
		$this->assertSame( $original_url, $data['source_url'] );

		Plugin::pause( false );
	}

	/**
	 * Test filter_rest_prepare_attachment respects config.
	 */
	public function test_filter_rest_prepare_attachment_respects_config(): void {
		// Reset config and disable REST API.
		$this->reset_plugin_config();

		add_filter(
			'aysnc_wordpress_dynamic_media_config',
			function () {
				return [ 'rest_api_enabled' => false ];
			},
		);

		$this->setup_cloudinary_adapter();
		Plugin::pause( false );

		$attachment_id = $this->factory->attachment->create_upload_object( __DIR__ . '/fixtures/test-image.jpg' );
		$this->assertIsInt( $attachment_id );

		$attachment = get_post( $attachment_id );
		$this->assertInstanceOf( WP_Post::class, $attachment );

		$original_url = 'http://example.com/wp-content/uploads/test.jpg';
		$response     = new WP_REST_Response( [ 'source_url' => $original_url ] );
		$request      = new WP_REST_Request( 'GET', '/wp/v2/media/' . $attachment_id );

		$result = Plugin::filter_rest_prepare_attachment( $response, $attachment, $request );
		$data   = $result->get_data();

		// Should not be transformed.
		$this->assertTrue( is_array( $data ) && ! empty( $data['source_url'] ) );
		$this->assertSame( $original_url, $data['source_url'] );

		// Clean up.
		remove_all_filters( 'aysnc_wordpress_dynamic_media_config' );
	}

	/**
	 * Test filter_rest_prepare_attachment respects per-request filter.
	 */
	public function test_filter_rest_prepare_attachment_respects_per_request_filter(): void {
		// Reset config and set up an adapter.
		$this->reset_plugin_config();
		$this->setup_cloudinary_adapter();
		Plugin::pause( false );

		$attachment_id = $this->factory->attachment->create_upload_object( __DIR__ . '/fixtures/test-image.jpg' );
		$this->assertIsInt( $attachment_id );

		$attachment = get_post( $attachment_id );
		$this->assertInstanceOf( WP_Post::class, $attachment );

		// Disable via per-request filter.
		add_filter( 'aysnc_wordpress_dynamic_media_rest_enabled', '__return_false' );

		$original_url = 'http://example.com/wp-content/uploads/test.jpg';
		$response     = new WP_REST_Response( [ 'source_url' => $original_url ] );
		$request      = new WP_REST_Request( 'GET', '/wp/v2/media/' . $attachment_id );

		$result = Plugin::filter_rest_prepare_attachment( $response, $attachment, $request );
		$data   = $result->get_data();

		// Should not be transformed.
		$this->assertTrue( is_array( $data ) && ! empty( $data['source_url'] ) );
		$this->assertSame( $original_url, $data['source_url'] );

		// Clean up.
		remove_all_filters( 'aysnc_wordpress_dynamic_media_rest_enabled' );
	}

	/**
	 * Test that the rest_prepare_attachment hook is registered.
	 */
	public function test_rest_prepare_attachment_hook_registered(): void {
		Plugin::bootstrap();

		$this->assertNotFalse( has_filter( 'rest_prepare_attachment', [ Plugin::class, 'filter_rest_prepare_attachment' ] ) );
	}

	/**
	 * Helper method to set up Cloudinary adapter for tests.
	 */
	protected function setup_cloudinary_adapter(): void {
		// Set up Cloudinary config.
		add_filter(
			'aysnc_wordpress_cloudinary_config',
			function () {
				return [
					'cloud_name'          => 'test-cloud',
					'auto_mapping_folder' => 'wp-content',
				];
			},
		);

		// Register adapter.
		Plugin::register_adapters();
	}

	/**
	 * Helper method to reset plugin config.
	 */
	protected function reset_plugin_config(): void {
		$reflection      = new ReflectionClass( Plugin::class );
		$config_property = $reflection->getProperty( 'config' );
		$config_property->setAccessible( true );
		$config_property->setValue( null, [] );
	}
}
