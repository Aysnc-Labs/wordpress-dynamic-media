<?php
/**
 * Tests for the Cloudinary adapter class.
 *
 * @package aysnc/wordpress-dynamic-media
 */

declare( strict_types = 1 );

namespace Aysnc\WordPress\DynamicMedia\Tests;

use Aysnc\WordPress\DynamicMedia\Adapters\Cloudinary;
use ReflectionClass;
use WP_UnitTestCase;

/**
 * Cloudinary adapter test case.
 */
class CloudinaryTest extends WP_UnitTestCase {
	/**
	 * Setup test environment.
	 */
	public function setUp(): void {
		parent::setUp();

		// Reset Cloudinary static config using reflection.
		$reflection      = new ReflectionClass( Cloudinary::class );
		$config_property = $reflection->getProperty( 'config' );
		$config_property->setAccessible( true );
		$config_property->setValue( null, [] );

		// Remove any existing filters.
		remove_all_filters( 'aysnc_wordpress_cloudinary_config' );
		remove_all_filters( 'aysnc_wordpress_cloudinary_upload_url' );
		remove_all_filters( 'aysnc_wordpress_cloudinary_args' );
	}

	/**
	 * Test getting config with defaults.
	 */
	public function test_get_config_defaults(): void {
		$config = Cloudinary::get_config();

		$this->assertArrayHasKey( 'cloud_name', $config );
		$this->assertArrayHasKey( 'auto_mapping_folder', $config );
		$this->assertArrayHasKey( 'domain', $config );
		$this->assertArrayHasKey( 'default_hard_crop', $config );
		$this->assertArrayHasKey( 'default_soft_crop', $config );

		// Check default values.
		$this->assertSame( '', $config['cloud_name'] );
		$this->assertSame( '', $config['auto_mapping_folder'] );
		$this->assertSame( 'res.cloudinary.com', $config['domain'] );
		$this->assertSame( 'fill', $config['default_hard_crop'] );
		$this->assertSame( 'fit', $config['default_soft_crop'] );
	}

	/**
	 * Test getting config via filter.
	 */
	public function test_get_config_via_filter(): void {
		add_filter(
			'aysnc_wordpress_cloudinary_config',
			function () {
				return [
					'cloud_name'          => 'test-cloud',
					'auto_mapping_folder' => 'test-folder',
					'domain'              => 'custom.cloudinary.com',
					'default_hard_crop'   => 'lfill',
					'default_soft_crop'   => 'limit',
				];
			},
		);

		$config = Cloudinary::get_config();

		$this->assertSame( 'test-cloud', $config['cloud_name'] );
		$this->assertSame( 'test-folder', $config['auto_mapping_folder'] );
		$this->assertSame( 'custom.cloudinary.com', $config['domain'] );
		$this->assertSame( 'lfill', $config['default_hard_crop'] );
		$this->assertSame( 'limit', $config['default_soft_crop'] );
	}

	/**
	 * Test config caching.
	 */
	public function test_get_config_caches_result(): void {
		$call_count = 0;
		add_filter(
			'aysnc_wordpress_cloudinary_config',
			function () use ( &$call_count ) {
				++$call_count;
				return [ 'cloud_name' => 'test' ];
			},
		);

		// First call.
		Cloudinary::get_config();
		// Second call.
		Cloudinary::get_config();

		// Filter should only be called once due to caching.
		$this->assertSame( 1, $call_count );
	}

	/**
	 * Test getting domain with default.
	 */
	public function test_get_domain_default(): void {
		$domain = Cloudinary::get_domain();

		$this->assertSame( 'https://res.cloudinary.com', $domain );
	}

	/**
	 * Test getting domain with custom domain.
	 */
	public function test_get_domain_custom(): void {
		add_filter(
			'aysnc_wordpress_cloudinary_config',
			function () {
				return [ 'domain' => 'custom.cloudinary.com' ];
			},
		);

		$domain = Cloudinary::get_domain();

		$this->assertSame( 'https://custom.cloudinary.com', $domain );
	}

	/**
	 * Test getting domain with protocol already present.
	 */
	public function test_get_domain_with_protocol(): void {
		add_filter(
			'aysnc_wordpress_cloudinary_config',
			function () {
				return [ 'domain' => 'https://secure.cloudinary.com' ];
			},
		);

		$domain = Cloudinary::get_domain();

		$this->assertSame( 'https://secure.cloudinary.com', $domain );
	}

	/**
	 * Test getting domain removes trailing slash.
	 */
	public function test_get_domain_removes_trailing_slash(): void {
		add_filter(
			'aysnc_wordpress_cloudinary_config',
			function () {
				return [ 'domain' => 'https://custom.cloudinary.com/' ];
			},
		);

		$domain = Cloudinary::get_domain();

		$this->assertSame( 'https://custom.cloudinary.com', $domain );
	}

	/**
	 * Test getting dynamic URL returns empty string for invalid attachment.
	 */
	public function test_get_dynamic_url_invalid_attachment(): void {
		$url = Cloudinary::get_dynamic_url( 999999, [] );

		$this->assertSame( '', $url );
	}

	/**
	 * Test getting dynamic URL returns original URL when config incomplete.
	 */
	public function test_get_dynamic_url_incomplete_config(): void {
		// Create a test attachment.
		$attachment_id = $this->factory->attachment->create_upload_object( __DIR__ . '/fixtures/test-image.jpg' );
		$this->assertIsInt( $attachment_id );

		$url = Cloudinary::get_dynamic_url( $attachment_id, [] );

		// Should return the original URL when config is incomplete.
		$original_url = wp_get_attachment_url( $attachment_id );
		$this->assertSame( $original_url, $url );
	}

	/**
	 * Test getting dynamic URL with basic config.
	 */
	public function test_get_dynamic_url_basic(): void {
		// Set up config.
		add_filter(
			'aysnc_wordpress_cloudinary_config',
			function () {
				return [
					'cloud_name'          => 'test-cloud',
					'auto_mapping_folder' => 'wp-content',
				];
			},
		);

		// Create a test attachment.
		$attachment_id = $this->factory->attachment->create_upload_object( __DIR__ . '/fixtures/test-image.jpg' );
		$this->assertIsInt( $attachment_id );

		$url = Cloudinary::get_dynamic_url( $attachment_id, [] );

		// Should match full Cloudinary URL pattern.
		$this->assertMatchesRegularExpression( '#^https://res\.cloudinary\.com/test-cloud/wp-content/.+\.jpg$#', $url );
	}

	/**
	 * Test getting dynamic URL with transformations.
	 */
	public function test_get_dynamic_url_with_transformations(): void {
		// Set up config.
		add_filter(
			'aysnc_wordpress_cloudinary_config',
			function () {
				return [
					'cloud_name'          => 'test-cloud',
					'auto_mapping_folder' => 'wp-content',
				];
			},
		);

		// Create a test attachment.
		$attachment_id = $this->factory->attachment->create_upload_object( __DIR__ . '/fixtures/test-image.jpg' );
		$this->assertIsInt( $attachment_id );

		$url = Cloudinary::get_dynamic_url(
			$attachment_id,
			[
				'width'  => 300,
				'height' => 200,
			],
		);

		// Should match full Cloudinary URL with transformations.
		$this->assertMatchesRegularExpression( '#^https://res\.cloudinary\.com/test-cloud/w_300,h_200/wp-content/.+\.jpg$#', $url );
	}

	/**
	 * Test getting dynamic URL with hard crop.
	 */
	public function test_get_dynamic_url_with_hard_crop(): void {
		// Set up config.
		add_filter(
			'aysnc_wordpress_cloudinary_config',
			function () {
				return [
					'cloud_name'          => 'test-cloud',
					'auto_mapping_folder' => 'wp-content',
					'default_hard_crop'   => 'fill',
				];
			},
		);

		// Create a test attachment.
		$attachment_id = $this->factory->attachment->create_upload_object( __DIR__ . '/fixtures/test-image.jpg' );
		$this->assertIsInt( $attachment_id );

		$url = Cloudinary::get_dynamic_url(
			$attachment_id,
			[
				'width'     => 300,
				'height'    => 200,
				'hard_crop' => true,
			],
		);

		// Should match full Cloudinary URL with hard crop.
		$this->assertMatchesRegularExpression( '#^https://res\.cloudinary\.com/test-cloud/w_300,h_200,c_fill/wp-content/.+\.jpg$#', $url );
	}

	/**
	 * Test getting dynamic URL with soft crop.
	 */
	public function test_get_dynamic_url_with_soft_crop(): void {
		// Set up config.
		add_filter(
			'aysnc_wordpress_cloudinary_config',
			function () {
				return [
					'cloud_name'          => 'test-cloud',
					'auto_mapping_folder' => 'wp-content',
					'default_soft_crop'   => 'fit',
				];
			},
		);

		// Create a test attachment.
		$attachment_id = $this->factory->attachment->create_upload_object( __DIR__ . '/fixtures/test-image.jpg' );
		$this->assertIsInt( $attachment_id );

		$url = Cloudinary::get_dynamic_url(
			$attachment_id,
			[
				'width'     => 300,
				'height'    => 200,
				'hard_crop' => false,
			],
		);

		// Should match full Cloudinary URL with soft crop.
		$this->assertMatchesRegularExpression( '#^https://res\.cloudinary\.com/test-cloud/w_300,h_200,c_fit/wp-content/.+\.jpg$#', $url );
	}

	/**
	 * Test build transformation slug with empty args.
	 */
	public function test_build_transformation_slug_empty(): void {
		$reflection = new ReflectionClass( Cloudinary::class );
		$method     = $reflection->getMethod( 'build_transformation_slug' );
		$method->setAccessible( true );

		$slug = $method->invoke( null, [] );
		$this->assertIsString( $slug );

		$this->assertSame( '', $slug );
	}

	/**
	 * Test build transformation slug with basic parameters.
	 */
	public function test_build_transformation_slug_basic(): void {
		$reflection = new ReflectionClass( Cloudinary::class );
		$method     = $reflection->getMethod( 'build_transformation_slug' );
		$method->setAccessible( true );

		$slug = $method->invoke(
			null,
			[
				'width'  => 300,
				'height' => 200,
				'crop'   => 'fill',
			],
		);
		$this->assertIsString( $slug );

		$this->assertStringContainsString( 'w_300', $slug );
		$this->assertStringContainsString( 'h_200', $slug );
		$this->assertStringContainsString( 'c_fill', $slug );
	}

	/**
	 * Test build transformation slug with quality parameter.
	 */
	public function test_build_transformation_slug_with_quality(): void {
		$reflection = new ReflectionClass( Cloudinary::class );
		$method     = $reflection->getMethod( 'build_transformation_slug' );
		$method->setAccessible( true );

		$slug = $method->invoke(
			null,
			[
				'width'   => 300,
				'quality' => 80,
			],
		);
		/** @var string $slug */

		$this->assertStringContainsString( 'w_300', $slug );
		$this->assertStringContainsString( 'q_80', $slug );
	}

	/**
	 * Test build transformation slug with gravity parameter.
	 */
	public function test_build_transformation_slug_with_gravity(): void {
		$reflection = new ReflectionClass( Cloudinary::class );
		$method     = $reflection->getMethod( 'build_transformation_slug' );
		$method->setAccessible( true );

		$slug = $method->invoke(
			null,
			[
				'width'   => 300,
				'gravity' => 'face',
			],
		);
		/** @var string $slug */

		$this->assertStringContainsString( 'w_300', $slug );
		$this->assertStringContainsString( 'g_face', $slug );
	}

	/**
	 * Test build transformation slug with progressive flag.
	 */
	public function test_build_transformation_slug_with_progressive(): void {
		$reflection = new ReflectionClass( Cloudinary::class );
		$method     = $reflection->getMethod( 'build_transformation_slug' );
		$method->setAccessible( true );

		$slug = $method->invoke(
			null,
			[
				'width'       => 300,
				'progressive' => true,
			],
		);
		/** @var string $slug */

		$this->assertStringContainsString( 'w_300', $slug );
		$this->assertStringContainsString( 'fl_progressive', $slug );
	}

	/**
	 * Test build transformation slug ignores unknown parameters.
	 */
	public function test_build_transformation_slug_ignores_unknown(): void {
		$reflection = new ReflectionClass( Cloudinary::class );
		$method     = $reflection->getMethod( 'build_transformation_slug' );
		$method->setAccessible( true );

		$slug = $method->invoke(
			null,
			[
				'width'           => 300,
				'unknown_param'   => 'value',
				'another_unknown' => 123,
			],
		);
		/** @var string $slug */

		$this->assertStringContainsString( 'w_300', $slug );
		$this->assertStringNotContainsString( 'unknown', $slug );
	}

	/**
	 * Test valid_value method allows non-empty width and height.
	 */
	public function test_valid_value_width_height(): void {
		$reflection = new ReflectionClass( Cloudinary::class );
		$method     = $reflection->getMethod( 'valid_value' );
		$method->setAccessible( true );

		// Valid width.
		$this->assertTrue( $method->invoke( null, 'w', 300 ) );

		// Valid height.
		$this->assertTrue( $method->invoke( null, 'h', 200 ) );

		// Empty width should be invalid.
		$this->assertFalse( $method->invoke( null, 'w', 0 ) );
		$this->assertFalse( $method->invoke( null, 'w', '' ) );

		// Empty height should be invalid.
		$this->assertFalse( $method->invoke( null, 'h', 0 ) );
		$this->assertFalse( $method->invoke( null, 'h', '' ) );
	}

	/**
	 * Test valid_value method allows other parameters.
	 */
	public function test_valid_value_other_parameters(): void {
		$reflection = new ReflectionClass( Cloudinary::class );
		$method     = $reflection->getMethod( 'valid_value' );
		$method->setAccessible( true );

		// Other parameters should be valid.
		$this->assertTrue( $method->invoke( null, 'q', 80 ) );
		$this->assertTrue( $method->invoke( null, 'g', 'face' ) );
		$this->assertTrue( $method->invoke( null, 'c', 'fill' ) );
		$this->assertTrue( $method->invoke( null, 'fl_progressive', true ) );
	}

	/**
	 * Test getting dynamic URL with file_name parameter adds images prefix.
	 */
	public function test_get_dynamic_url_with_file_name(): void {
		// Set up config.
		add_filter(
			'aysnc_wordpress_cloudinary_config',
			function () {
				return [
					'cloud_name'          => 'test-cloud',
					'auto_mapping_folder' => 'wp-content',
				];
			},
		);

		// Create a test attachment.
		$attachment_id = $this->factory->attachment->create_upload_object( __DIR__ . '/fixtures/test-image.jpg' );
		$this->assertIsInt( $attachment_id );

		$url = Cloudinary::get_dynamic_url(
			$attachment_id,
			[
				'file_name' => 'custom-name',
			],
		);

		// Should match full Cloudinary URL with images prefix and custom file name.
		$this->assertMatchesRegularExpression( '#^https://res\.cloudinary\.com/test-cloud/images/wp-content/.+/custom-name\.jpg$#', $url );
	}

	/**
	 * Test getting dynamic URL applies filter.
	 */
	public function test_get_dynamic_url_applies_filter(): void {
		// Set up config.
		add_filter(
			'aysnc_wordpress_cloudinary_config',
			function () {
				return [
					'cloud_name'          => 'test-cloud',
					'auto_mapping_folder' => 'wp-content',
				];
			},
		);

		// Create a test attachment.
		$attachment_id = $this->factory->attachment->create_upload_object( __DIR__ . '/fixtures/test-image.jpg' );
		$this->assertIsInt( $attachment_id );

		// Add a filter to modify transformations.
		add_filter(
			'aysnc_wordpress_cloudinary_args',
			function ( $args ) {
				/** @var array<string, mixed> $args */
				/** @phpstan-ignore offsetAccess.nonOffsetAccessible */
				$args['transform']['quality'] = 90;
				return $args;
			},
		);

		$url = Cloudinary::get_dynamic_url(
			$attachment_id,
			[
				'width' => 300,
			],
		);

		// Should match full Cloudinary URL with width and quality parameters.
		$this->assertMatchesRegularExpression( '#^https://res\.cloudinary\.com/test-cloud/w_300,q_90/wp-content/.+\.jpg$#', $url );
	}
}
