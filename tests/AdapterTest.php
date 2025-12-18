<?php
/**
 * Tests for the Adapter class.
 *
 * @package aysnc/wordpress-dynamic-media
 */

declare( strict_types = 1 );

namespace Aysnc\WordPress\DynamicMedia\Tests;

use Aysnc\WordPress\DynamicMedia\Adapter;
use Aysnc\WordPress\DynamicMedia\Adapters\MediaAdapter;
use ReflectionClass;
use WP_UnitTestCase;

/**
 * Adapter test case.
 */
class AdapterTest extends WP_UnitTestCase {
	/**
	 * Mock adapter for testing.
	 *
	 * @var MediaAdapter
	 */
	protected MediaAdapter $mock_adapter;

	/**
	 * Setup test environment.
	 */
	public function setUp(): void {
		parent::setUp();

		// Create a mock adapter.
		$this->mock_adapter = new class () implements MediaAdapter {
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

		// Reset adapter state using reflection to clear static properties.
		$reflection        = new ReflectionClass( Adapter::class );
		$adapters_property = $reflection->getProperty( 'adapters' );
		$adapters_property->setAccessible( true );
		$adapters_property->setValue( null, [] );

		$current_property = $reflection->getProperty( 'current_adapter' );
		$current_property->setAccessible( true );
		$current_property->setValue( null, null );
	}

	/**
	 * Test adapter registration.
	 */
	public function test_adapter_registration(): void {
		// Register the mock adapter.
		Adapter::register( 'test-adapter', $this->mock_adapter );

		// Set it as the current adapter.
		Adapter::set( 'test-adapter' );

		// Get the adapter and verify it's the same instance.
		$adapter = Adapter::get();
		$this->assertInstanceOf( MediaAdapter::class, $adapter );
		$this->assertSame( $this->mock_adapter, $adapter );
	}

	/**
	 * Test multiple adapter registration.
	 */
	public function test_multiple_adapters_registration(): void {
		// Create a second mock adapter.
		$mock_adapter2 = new class () implements MediaAdapter {
			/**
			 * Get dynamic URL.
			 *
			 * @param int     $attachment_id Attachment ID.
			 * @param mixed[] $args          Transformation arguments.
			 *
			 * @return string
			 */
			public static function get_dynamic_url( int $attachment_id, array $args = [] ): string {
				return 'https://another.com/image-' . $attachment_id . '.jpg';
			}
		};

		// Register both adapters.
		Adapter::register( 'adapter-one', $this->mock_adapter );
		Adapter::register( 'adapter-two', $mock_adapter2 );

		// Set the first adapter.
		Adapter::set( 'adapter-one' );
		$this->assertSame( $this->mock_adapter, Adapter::get() );

		// Switch to the second adapter.
		Adapter::set( 'adapter-two' );
		$this->assertSame( $mock_adapter2, Adapter::get() );
	}

	/**
	 * Test setting a non-existent adapter.
	 */
	public function test_set_nonexistent_adapter(): void {
		// Register an adapter.
		Adapter::register( 'test-adapter', $this->mock_adapter );
		Adapter::set( 'test-adapter' );

		// Verify it's set.
		$this->assertInstanceOf( MediaAdapter::class, Adapter::get() );

		// Try to set a non-existent adapter.
		Adapter::set( 'nonexistent-adapter' );

		// Should return null.
		$this->assertNull( Adapter::get() );
	}

	/**
	 * Test getting adapter when none is set.
	 */
	public function test_get_adapter_when_none_set(): void {
		// Without setting any adapter, should return null.
		$this->assertNull( Adapter::get() );
	}

	/**
	 * Test adapter overwriting.
	 */
	public function test_adapter_overwriting(): void {
		$mock_adapter2 = new class () implements MediaAdapter {
			/**
			 * Get dynamic URL.
			 *
			 * @param int     $attachment_id Attachment ID.
			 * @param mixed[] $args          Transformation arguments.
			 *
			 * @return string
			 */
			public static function get_dynamic_url( int $attachment_id, array $args = [] ): string {
				return 'https://new.com/image.jpg';
			}
		};

		// Register an adapter with a name.
		Adapter::register( 'test', $this->mock_adapter );
		Adapter::set( 'test' );

		// Register a different adapter with the same name.
		Adapter::register( 'test', $mock_adapter2 );
		Adapter::set( 'test' );

		// Should get the new adapter.
		$this->assertSame( $mock_adapter2, Adapter::get() );
	}
}
