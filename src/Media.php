<?php
/**
 * Media class.
 *
 * @package aysnc/wordpress-dynamic-media
 */

declare( strict_types = 1 );

namespace Aysnc\WordPress\DynamicMedia;

class Media {
	/**
	 * Save image sizes for performance.
	 *
	 * @var array<string, array{
	 *      width: int,
	 *      height: int,
	 *      crop: bool,
	 *  }> $image_sizes Image sizes.
	 */
	protected static array $image_sizes = [];

	/**
	 * Default WordPress image sizes.
	 *
	 * @var string[] $wordpress_image_sizes WordPress image sizes.
	 */
	protected static array $wordpress_image_sizes = [ 'thumbnail', 'medium', 'medium_large', 'large' ];

	/**
	 * Get all image sizes.
	 *
	 * @return array{
	 *     width: int,
	 *     height: int,
	 *     crop: bool,
	 * }[]
	 */
	public static function get_all_image_sizes(): array {
		if ( ! empty( self::$image_sizes ) ) {
			return self::$image_sizes;
		}

		/**
		 * Additional image sizes registered using add_image_size().
		 *
		 * @var array{
		 *     array-key: array{
		 *         width: int,
		 *         height: int,
		 *         crop: bool,
		 *     }
		 * } $_wp_additional_image_sizes
		 */
		global $_wp_additional_image_sizes;

		foreach ( get_intermediate_image_sizes() as $size ) {
			if ( in_array( $size, self::$wordpress_image_sizes, true ) ) {
				self::$image_sizes[ $size ] = [
					'width'  => absint( get_option( "{$size}_size_w" ) ),
					'height' => absint( get_option( "{$size}_size_h" ) ),
					'crop'   => (bool) get_option( "{$size}_crop" ),
				];
			} elseif ( isset( $_wp_additional_image_sizes[ $size ] ) ) {
				self::$image_sizes[ $size ] = [
					'width'  => absint( $_wp_additional_image_sizes[ $size ]['width'] ),
					'height' => absint( $_wp_additional_image_sizes[ $size ]['height'] ),
					'crop'   => (bool) $_wp_additional_image_sizes[ $size ]['crop'],
				];
			}
		}

		return self::$image_sizes;
	}

	/**
	 * Get size information for a specific image size.
	 *
	 * @param string $size The image size for which to retrieve data.
	 *
	 * @return array{}|array{
	 *      width: int,
	 *      height: int,
	 *      crop: bool,
	 *  }
	 */
	public static function get_image_size_by_name( string $size ): array {
		$sizes = self::get_all_image_sizes();

		if ( ! empty( $sizes ) && isset( $sizes[ $size ] ) ) {
			return $sizes[ $size ];
		}

		return [];
	}

	/**
	 * Get dynamic URL for an attachment.
	 *
	 * @param int     $attachment_id Attachment ID.
	 * @param mixed[] $args          Transformation arguments.
	 *
	 * @return string Dynamic image URL.
	 */
	public static function get_dynamic_url( int $attachment_id, array $args = [] ): string {
		$adapter = Adapter::get();
		if ( ! $adapter ) {
			return '';
		}

		$dynamic_image_url = apply_filters(
			'aysnc_wordpress_dynamic_media_url',
			$adapter::get_dynamic_url( $attachment_id, $args ),
			$attachment_id,
			$args,
		);

		if ( ! is_string( $dynamic_image_url ) ) {
			return '';
		}

		return $dynamic_image_url;
	}
}
