<?php
/**
 * Cloudinary adapter implementation.
 *
 * @package aysnc/wordpress-dynamic-media
 */

declare( strict_types = 1 );

namespace Aysnc\WordPress\DynamicMedia\Adapters;

class Cloudinary implements MediaAdapter {
	/**
	 * Cloudinary configuration.
	 *
	 * @var array{
	 *    cloud_name: string,
	 *    auto_mapping_folder: string,
	 *    domain: string,
	 *    default_hard_crop: string,
	 *    default_soft_crop: string,
	 *  }|array{} $config Cloudinary configuration.
	 */
	protected static array $config = [];

	/**
	 * Get the Cloudinary configuration.
	 *
	 * @return array{
	 *    cloud_name: string,
	 *    auto_mapping_folder: string,
	 *    domain: string,
	 *    default_hard_crop: string,
	 *    default_soft_crop: string,
	 * }
	 */
	public static function get_config(): array {
		if ( ! empty( self::$config ) ) {
			return self::$config;
		}

		self::$config = (array) apply_filters( 'aysnc_wordpress_cloudinary_config', [] );
		self::$config = [
			'cloud_name'          => isset( self::$config['cloud_name'] ) && is_string( self::$config['cloud_name'] ) ? self::$config['cloud_name'] : '',
			'auto_mapping_folder' => isset( self::$config['auto_mapping_folder'] ) && is_string( self::$config['auto_mapping_folder'] ) ? self::$config['auto_mapping_folder'] : '',
			'domain'              => isset( self::$config['domain'] ) && is_string( self::$config['domain'] ) ? self::$config['domain'] : 'res.cloudinary.com',
			'default_hard_crop'   => isset( self::$config['default_hard_crop'] ) && is_string( self::$config['default_hard_crop'] ) ? self::$config['default_hard_crop'] : 'fill',
			'default_soft_crop'   => isset( self::$config['default_soft_crop'] ) && is_string( self::$config['default_soft_crop'] ) ? self::$config['default_soft_crop'] : 'fit',
		];

		return self::$config;
	}

	/**
	 * Get the Cloudinary domain URL.
	 *
	 * @return string Domain URL with protocol.
	 */
	public static function get_domain(): string {
		$config = self::get_config();
		$domain = $config['domain'];

		// Ensure protocol is present.
		if ( ! str_starts_with( $domain, 'http://' ) && ! str_starts_with( $domain, 'https://' ) ) {
			$domain = 'https://' . $domain;
		}

		// Remove trailing slash.
		return rtrim( $domain, '/' );
	}

	/**
	 * Get the URL for an image with transformations.
	 *
	 * @param int     $id   Attachment ID.
	 * @param mixed[] $args Transformation arguments.
	 *
	 * @return string Image URL.
	 */
	public static function get_dynamic_url( int $id, array $args ): string {
		// Get the original URL.
		$original_url = wp_get_attachment_url( $id );
		if ( ! is_string( $original_url ) || empty( $original_url ) ) {
			return '';
		}

		// Get config.
		$config = self::get_config();

		// If the plugin isn't set up correctly, default to the original URL.
		if ( empty( $config['cloud_name'] ) || empty( $config['auto_mapping_folder'] ) ) {
			return $original_url;
		}

		// Get upload URL.
		$upload_dir = wp_upload_dir();
		$upload_url = apply_filters( 'aysnc_wordpress_cloudinary_upload_url', $upload_dir['baseurl'] );

		// Validate URL - ensure it's from the uploads' directory.
		if ( ! is_string( $upload_url ) || ! str_starts_with( $original_url, $upload_url ) ) {
			return $original_url;
		}

		// Build transform array, preserving any passed transforms.
		$transform = isset( $args['transform'] ) && is_array( $args['transform'] ) ? $args['transform'] : [];
		if ( ! empty( $args['width'] ) ) {
			$transform['width'] = $args['width'];
		}
		if ( ! empty( $args['height'] ) ) {
			$transform['height'] = $args['height'];
		}
		$args['transform'] = $transform;

		if ( isset( $args['hard_crop'] ) ) {
			if ( true === $args['hard_crop'] ) {
				$args['transform']['crop'] = $config['default_hard_crop'];
			} else {
				$args['transform']['crop'] = $config['default_soft_crop'];
			}
		}

		// Filter args.
		$args = (array) apply_filters( 'aysnc_wordpress_cloudinary_args', $args, $id );

		// Start building the URL.
		$url = self::get_domain() . '/' . $config['cloud_name'];

		// If the file name is present, add the "images" prefix.
		if ( ! empty( $args['file_name'] ) && ( ! isset( $args['file_type'] ) || 'video' !== $args['file_type'] ) ) {
			$url .= '/images';
		}

		// Add support for the video file type.
		if ( ! empty( $args['file_type'] ) && 'video' === $args['file_type'] ) {
			$url .= '/video/upload';
		}

		// Transformations.
		if ( ! empty( $args['transform'] ) && is_array( $args['transform'] ) ) {
			$transformations_slug = self::build_transformation_slug( $args['transform'] );
			if ( ! empty( $transformations_slug ) ) {
				$url .= '/' . $transformations_slug;
			}
		}

		// Finish building the URL.
		$url .= '/' . $config['auto_mapping_folder'] . str_replace( $upload_url, '', $original_url );

		// Modify the last bit of the URL if the file name is present.
		if ( ! empty( $args['file_name'] ) && is_string( $args['file_name'] ) ) {
			$path_info = pathinfo( $url );
			$url       = str_replace( $path_info['filename'], $path_info['filename'] . '/' . $args['file_name'], $url );
		}

		// All done, let's return it.
		return $url;
	}

	/**
	 * Build a Cloudinary transformation slug from arguments.
	 *
	 * @param mixed[] $args Transformation arguments.
	 *
	 * @return string Transformation slug.
	 */
	protected static function build_transformation_slug( array $args ): string {
		if ( empty( $args ) ) {
			return '';
		}

		$cloudinary_params = [
			'angle'                => 'a',
			'aspect_ratio'         => 'ar',
			'background'           => 'b',
			'border'               => 'bo',
			'crop'                 => 'c',
			'color'                => 'co',
			'dpr'                  => 'dpr',
			'duration'             => 'du',
			'effect'               => 'e',
			'end_offset'           => 'eo',
			'flags'                => 'fl',
			'height'               => 'h',
			'overlay'              => 'l',
			'opacity'              => 'o',
			'quality'              => 'q',
			'radius'               => 'r',
			'start_offset'         => 'so',
			'named_transformation' => 't',
			'underlay'             => 'u',
			'video_codec'          => 'vc',
			'width'                => 'w',
			'x'                    => 'x',
			'y'                    => 'y',
			'zoom'                 => 'z',
			'audio_codec'          => 'ac',
			'audio_frequency'      => 'af',
			'bit_rate'             => 'br',
			'color_space'          => 'cs',
			'default_image'        => 'd',
			'delay'                => 'dl',
			'density'              => 'dn',
			'fetch_format'         => 'f',
			'gravity'              => 'g',
			'prefix'               => 'p',
			'page'                 => 'pg',
			'video_sampling'       => 'vs',
			'progressive'          => 'fl_progressive',
		];

		$slug = [];
		foreach ( $args as $key => $value ) {
			if (
				array_key_exists( $key, $cloudinary_params )
				&& self::valid_value(
					$cloudinary_params[ $key ],
					$value,
				)
			) {
				switch ( $key ) {
					case 'progressive':
						if ( true === $value ) {
							$slug[] = $cloudinary_params[ $key ];
						} elseif ( is_string( $value ) || is_numeric( $value ) ) {
							$slug[] = $cloudinary_params[ $key ] . ':' . $value;
						}
						break;
					default:
						if ( is_scalar( $value ) || ( is_object( $value ) && method_exists( $value, '__toString' ) ) ) {
							$slug[] = $cloudinary_params[ $key ] . '_' . $value;
						}
				}
			}
		}
		return implode( ',', $slug );
	}

	/**
	 * Check if the value is valid.
	 *
	 * @param string $key   Parameter key.
	 * @param mixed  $value Parameter value.
	 *
	 * @return bool True if valid.
	 */
	protected static function valid_value( string $key, mixed $value ): bool {
		if ( ( 'w' === $key || 'h' === $key ) && empty( $value ) ) {
			return false;
		}
		return true;
	}
}
