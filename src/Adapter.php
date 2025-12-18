<?php
/**
 * Adapter class.
 *
 * @package aysnc/wordpress-dynamic-media
 */

declare( strict_types = 1 );

namespace Aysnc\WordPress\DynamicMedia;

use Aysnc\WordPress\DynamicMedia\Adapters\MediaAdapter;

class Adapter {
	/**
	 * All registered adapters.
	 *
	 * @var array<string, MediaAdapter> $adapters Adapters.
	 */
	protected static array $adapters = [];

	/**
	 * Current adapter.
	 *
	 * @var MediaAdapter|null $current_adapter Current adapter.
	 */
	protected static ?MediaAdapter $current_adapter = null;

	/**
	 * Register an adapter.
	 *
	 * @param string       $name    Adapter name.
	 * @param MediaAdapter $adapter Adapter instance.
	 *
	 * @return void
	 */
	public static function register( string $name, MediaAdapter $adapter ): void {
		self::$adapters[ $name ] = $adapter;
	}

	/**
	 * Set the current adapter.
	 *
	 * @param string $name Adapter name.
	 *
	 * @return void
	 */
	public static function set( string $name ): void {
		if ( isset( self::$adapters[ $name ] ) ) {
			self::$current_adapter = self::$adapters[ $name ];
		} else {
			self::$current_adapter = null;
		}
	}

	/**
	 * Get the current adapter.
	 *
	 * @return MediaAdapter|null
	 */
	public static function get(): ?MediaAdapter {
		return self::$current_adapter;
	}
}
