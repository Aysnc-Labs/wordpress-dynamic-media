<?php
/**
 * Main plugin class.
 *
 * @package aysnc/wordpress-dynamic-media
 */

declare( strict_types = 1 );

namespace Aysnc\WordPress\DynamicMedia;

use Aysnc\WordPress\DynamicMedia\Adapters\Cloudinary;

class Plugin {
	/**
	 * Whether the plugin is paused or not.
	 *
	 * @var bool $paused Paused or not.
	 */
	protected static bool $paused = false;

	/**
	 * Bootstrap.
	 *
	 * @return void
	 */
	public static function bootstrap(): void {
		add_action( 'after_setup_theme', [ __CLASS__, 'register_adapters' ] );
		add_filter( 'image_downsize', [ __CLASS__, 'filter_image_downsize' ], 999, 3 );
	}

	/**
	 * Register adapters.
	 *
	 * @return void
	 */
	public static function register_adapters(): void {
		// Register adapters.
		Adapter::register( 'cloudinary', new Cloudinary() );

		// Set default adapter.
		Adapter::set( 'cloudinary' );
	}

	/**
	 * Filters whether to preempt the output of image_downsize().
	 *
	 * @param bool|array{string, int, int} $downsize Whether to short-circuit the image downsize.
	 * @param int                          $id       Image attachment ID.
	 * @param string|int[]                 $size     Requested image size. Can be any registered image size name.
	 *                                               or an array of width and height values in pixels (in that order).
	 *
	 * @return bool|array{string, int, int} False to short-circuit the image downsize, or an array with the URL, width, and height.
	 */
	public static function filter_image_downsize( bool|array $downsize, int $id, string|array $size ): bool|array {
		if ( self::$paused ) {
			return $downsize;
		}

		$dimensions = [];

		if ( is_array( $size ) ) {
			if ( 2 === count( $size ) ) {
				$dimensions = [
					'width'     => $size[0],
					'height'    => $size[1],
					'hard_crop' => true,
				];
			}
		} elseif ( 'full' === $size ) {
			$meta = wp_get_attachment_metadata( $id );
			if ( isset( $meta['width'] ) && isset( $meta['height'] ) ) {
				$dimensions = [
					'width'  => absint( $meta['width'] ),
					'height' => absint( $meta['height'] ),
				];
			}
		} else {
			$dimensions = Media::get_image_size_by_name( $size );

			// Soft crop dimensions.
			if ( isset( $dimensions['crop'] ) && false === $dimensions['crop'] ) {
				$meta = wp_get_attachment_metadata( $id );

				if ( isset( $meta['width'] ) && isset( $meta['height'] ) ) {
					$new_dimensions = wp_constrain_dimensions(
						absint( $meta['width'] ),
						absint( $meta['height'] ),
						absint( $dimensions['width'] ),
						absint( $dimensions['height'] ),
					);

					if ( ! empty( $new_dimensions ) ) {
						$dimensions['width']     = absint( $new_dimensions[0] );
						$dimensions['height']    = absint( $new_dimensions[1] );
						$dimensions['hard_crop'] = false;
					}
				}
			}
		}

		if ( empty( $dimensions ) ) {
			return false;
		}

		$dynamic_image_url = Media::get_dynamic_url( $id, $dimensions );
		if ( empty( $dynamic_image_url ) ) {
			return false;
		}

		return [
			$dynamic_image_url,
			$dimensions['width'],
			$dimensions['height'],
		];
	}

	/**
	 * Pause the plugin.
	 *
	 * @param bool $paused Whether to pause or not.
	 *
	 * @return void
	 */
	public static function pause( bool $paused = true ): void {
		self::$paused = $paused;
	}
}
