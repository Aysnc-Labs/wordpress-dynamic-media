<?php
/**
 * Main plugin class.
 *
 * @package aysnc/wordpress-dynamic-media
 */

declare( strict_types = 1 );

namespace Aysnc\WordPress\DynamicMedia;

use Aysnc\WordPress\DynamicMedia\Adapters\Cloudinary;
use WP_HTML_Tag_Processor;

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
		add_filter( 'wp_calculate_image_srcset', [ __CLASS__, 'filter_wp_calculate_image_srcset' ], 999, 5 );
		add_filter( 'the_content', [ __CLASS__, 'update_content_images' ], 999 );
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
			return $downsize;
		}

		$dynamic_image_url = Media::get_dynamic_url( $id, $dimensions );
		if ( empty( $dynamic_image_url ) ) {
			return $downsize;
		}

		return [
			$dynamic_image_url,
			$dimensions['width'],
			$dimensions['height'],
		];
	}

	/**
	 * Filter wp_calculate_image_srcset.
	 *
	 * @param mixed[] $sources       Original sources.
	 * @param mixed[] $size_array    Size array.
	 * @param string  $image_src     Image source.
	 * @param mixed[] $image_meta    Image meta.
	 * @param int     $attachment_id Attachment ID.
	 *
	 * @return mixed[]
	 */
	public static function filter_wp_calculate_image_srcset( array $sources, array $size_array, string $image_src, array $image_meta, int $attachment_id ): array {
		if ( self::$paused || empty( $sources ) ) {
			return $sources;
		}

		$sizes = Media::get_all_image_sizes();

		foreach ( $sources as $key => $source ) {
			$source     = (array) $source;
			$dimensions = self::get_srcset_dimensions( $image_meta, $source, $sizes );

			if ( empty( $dimensions ) ) {
				continue;
			}

			$dimensions  = (array) apply_filters( 'aysnc_wordpress_dynamic_media_srcset_dimensions', $dimensions, $attachment_id, $image_meta, $image_src );
			$dynamic_url = Media::get_dynamic_url( $attachment_id, $dimensions );

			if ( ! empty( $dynamic_url ) && is_array( $sources[ $key ] ) ) {
				$sources[ $key ]['url'] = $dynamic_url;
			}
		}

		return $sources;
	}

	/**
	 * Get dimensions from image meta which matches a descriptor.
	 *
	 * @param mixed[] $image_meta       Image metadata.
	 * @param mixed[] $source           Source descriptor array.
	 * @param mixed[] $registered_sizes Registered image sizes with crop settings.
	 *
	 * @return mixed[]
	 */
	public static function get_srcset_dimensions( array $image_meta = [], array $source = [], array $registered_sizes = [] ): array {
		$dimension = 'w' === $source['descriptor'] ? 'width' : 'height';

		foreach ( (array) $image_meta['sizes'] as $size ) {
			if ( is_array( $size ) && $size[ $dimension ] === $source['value'] ) {
				$dimensions = [
					'width'  => $size['width'],
					'height' => $size['height'],
				];

				// Determine crop mode by matching to registered sizes.
				if ( ! empty( $registered_sizes ) ) {
					foreach ( $registered_sizes as $registered_size ) {
						if (
							is_array( $registered_size )
							&& $dimensions['width'] === $registered_size['width']
							&& $dimensions['height'] === $registered_size['height']
						) {
							$dimensions['hard_crop'] = (bool) $registered_size['crop'];
							break;
						}
					}
				}

				return $dimensions;
			}
		}

		return [
			$dimension => $source['value'],
		];
	}

	/**
	 * Parse content and update images to use dynamic media URLs.
	 *
	 * @param string $content Content to process.
	 *
	 * @return string Processed content.
	 */
	public static function update_content_images( string $content = '' ): string {
		if ( self::$paused || empty( $content ) ) {
			return $content;
		}

		$processor = new WP_HTML_Tag_Processor( $content );

		while ( $processor->next_tag( 'img' ) ) {
			$class = $processor->get_attribute( 'class' );
			if ( ! is_string( $class ) || ! preg_match( '/wp-image-([0-9]+)/i', $class, $class_id ) ) {
				continue;
			}

			$attachment_id = absint( $class_id[1] );
			if ( empty( $attachment_id ) ) {
				continue;
			}

			$src    = $processor->get_attribute( 'src' );
			$width  = $processor->get_attribute( 'width' );
			$height = $processor->get_attribute( 'height' );

			// Extract size name from the class.
			$size = '';
			if ( preg_match( '/size-([a-zA-Z0-9-_]+)/', $class, $match_size ) ) {
				$size = $match_size[1];
			}

			// Allow filters to customize the URL.
			$filtered_src = apply_filters(
				'aysnc_wordpress_dynamic_media_content_image_src',
				null,
				$attachment_id,
				$src,
				$width,
				$height,
				$size,
			);

			if ( is_string( $filtered_src ) && ! empty( $filtered_src ) ) {
				$processor->set_attribute( 'src', $filtered_src );
				continue;
			}

			// Generate dynamic URL.
			if ( empty( $src ) || ! is_string( $src ) ) {
				continue;
			}

			$dimensions = [];
			if ( ! empty( $width ) && ! empty( $height ) ) {
				$dimensions = [
					'width'  => (int) $width,
					'height' => (int) $height,
				];

				// Determine crop mode from size name.
				if ( ! empty( $size ) ) {
					$size_dimensions = Media::get_image_size_by_name( $size );
					if ( ! empty( $size_dimensions ) ) {
						$dimensions['hard_crop'] = (bool) $size_dimensions['crop'];
					}
				}
			}

			$dynamic_url = Media::get_dynamic_url( $attachment_id, $dimensions );
			if ( ! empty( $dynamic_url ) ) {
				$processor->set_attribute( 'src', $dynamic_url );
			}
		}

		return $processor->get_updated_html();
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
