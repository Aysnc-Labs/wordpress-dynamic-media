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
	 * Get the URL for an image with transformations.
	 *
	 * @param int     $id   Attachment ID.
	 * @param mixed[] $args Transformation arguments.
	 *
	 * @return string Image URL.
	 */
	public static function get_dynamic_url( int $id, array $args ): string {
		return wp_get_attachment_url( $id );
	}
}
