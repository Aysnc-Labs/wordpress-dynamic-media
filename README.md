# WordPress Dynamic Media

![GitHub Actions](https://github.com/Aysnc-Labs/wordpress-dynamic-media/actions/workflows/test.yml/badge.svg)
![Maintenance](https://img.shields.io/badge/Actively%20Maintained-yes-green.svg)

Automatically transform WordPress media URLs into dynamic, optimized URLs powered by image transformation services like
Cloudinary.

## Requirements

- PHP 8.3+
- WordPress 6.2+
- A Cloudinary account
  with [auto-upload mapping](https://cloudinary.com/documentation/fetch_remote_images#auto_upload_remote_resources)
  configured

## Installation

```bash
composer require aysnc/wordpress-dynamic-media
```

The plugin auto-activates through Composer's `wordpress-plugin` type. If your setup doesn't support that, activate it
manually in wp-admin.

## What It Does

When you upload an image to WordPress, it gets stored at a URL like this:

```
https://example.com/wp-content/uploads/2024/03/hero-image.jpg
```

With this plugin active, that same image is served through Cloudinary with on-the-fly transformations:

```
https://res.cloudinary.com/your-cloud/images/w_800,h_600,c_fill/my-site/2024/03/hero-image/hero-image.jpg
```

The plugin hooks into WordPress's image handling at multiple levels, so this happens automatically - no need to change
your templates or content.

**Here's what gets transformed:**

- `wp_get_attachment_image()` and related functions
- Responsive `srcset` attributes
- Images embedded in post/page content
- Any code using `image_downsize()`
- REST API media endpoints (`/wp/v2/media`)

## REST API Support

The plugin transforms URLs in REST API responses by default. When you fetch media via `/wp/v2/media`, both `source_url`
and all size URLs in `media_details.sizes` are transformed.

This is enabled by default. To disable it globally:

```php
add_filter( 'aysnc_wordpress_dynamic_media_config', function () {
    return [
        'rest_api_enabled' => false,
    ];
} );
```

Or disable it per-request (useful for admin/editor contexts):

```php
add_filter( 'aysnc_wordpress_dynamic_media_rest_enabled', function ( bool $enabled, WP_REST_Request $request, WP_Post $attachment ): bool {
    // Disable for authenticated requests (likely editor)
    if ( is_user_logged_in() ) {
        return false;
    }
    return $enabled;
}, 10, 3 );
```

## Generating URLs

The real power is in generating dynamic URLs on demand. Given any attachment ID, you can build a URL with whatever
transformations you need.

### The Basics

These parameters work across all adapters:

```php
use Aysnc\WordPress\DynamicMedia\Media;

// Basic resize
$url = Media::get_dynamic_url( $attachment_id, [
    'width'  => 800,
    'height' => 600,
] );

// Hard crop (fills the exact dimensions)
$url = Media::get_dynamic_url( $attachment_id, [
    'width'     => 400,
    'height'    => 400,
    'hard_crop' => true,
] );
```

### Adapter-Specific Transforms

Need more than dimensions? The `transform` array lets you pass parameters directly to your adapter:

```php
// Cloudinary: auto-optimize with face detection
$url = Media::get_dynamic_url( $attachment_id, [
    'width'     => 400,
    'height'    => 400,
    'hard_crop' => true,
    'transform' => [
        'quality'      => 'auto',
        'fetch_format' => 'auto',
        'gravity'      => 'face',
    ],
] );

// Cloudinary: low-quality placeholder for lazy loading
$placeholder = Media::get_dynamic_url( $attachment_id, [
    'width'     => 100,
    'transform' => [
        'effect'  => 'blur:1000',
        'quality' => 30,
    ],
] );
```

**On portability:** `width`, `height`, and `hard_crop` work across all adapters - the plugin translates these for each
service. The `transform` array does not. It's passed directly to your adapter, untouched.

This is intentional. Image services have different capabilities - Cloudinary's `gravity: 'face'` has no equivalent in
every provider. Rather than maintain a leaky abstraction, we give you direct access. The trade-off: if you switch
adapters, any code using `transform` needs to be updated.

**Best practice:** Don't call `Media::get_dynamic_url()` with `transform` scattered throughout your codebase. Wrap it in
your own function:

```php
function get_image_url( int $id, array $args = [] ): string {
    return Media::get_dynamic_url( $id, $args );
}
```

That way if you change adapters - or just want to tweak your transforms - you have one place to update instead of
hunting through templates.

## Configuration

The plugin needs to know your Cloudinary details. Add this filter to your theme or a mu-plugin:

```php
add_filter( 'aysnc_wordpress_cloudinary_config', function () {
    return [
        'cloud_name'          => 'your-cloud-name',
        'auto_mapping_folder' => 'your-auto-upload-folder',
    ];
} );
```

That's the minimum config. The `auto_mapping_folder` should match the folder name you set up in Cloudinary's auto-upload
settings.

### Full Configuration Options

| Option              | Default            | Description                                               |
|---------------------|--------------------|-----------------------------------------------------------|
| cloud_name          | (required)         | Your Cloudinary cloud name                                |
| auto_mapping_folder | (required)         | The folder configured in Cloudinary's auto-upload mapping |
| domain              | res.cloudinary.com | Custom domain if you're using a CNAME                     |
| default_hard_crop   | fill               | Cloudinary crop mode for hard-cropped images              |
| default_soft_crop   | fit                | Cloudinary crop mode for proportionally-scaled images     |

### Using Environment Variables

Since configuration happens through a filter, you can pull values from wherever makes sense for your setup:

```php
add_filter( 'aysnc_wordpress_cloudinary_config', function () {
    return [
        'cloud_name'          => getenv( 'CLOUDINARY_CLOUD_NAME' ),
        'auto_mapping_folder' => getenv( 'CLOUDINARY_FOLDER' ),
        'domain'              => getenv( 'CLOUDINARY_DOMAIN' ) ?: 'res.cloudinary.com',
    ];
} );
```

## Hooks & Filters

### `aysnc_wordpress_dynamic_media_config`

Global plugin configuration.

```php
add_filter( 'aysnc_wordpress_dynamic_media_config', function (): array {
    return [
        'rest_api_enabled' => true, // Enable REST API transformation (default: true)
    ];
} );
```

---

### `aysnc_wordpress_dynamic_media_rest_enabled`

Control REST API transformation on a per-request basis. Receives the request and attachment objects for context.

```php
add_filter( 'aysnc_wordpress_dynamic_media_rest_enabled', function ( bool $enabled, WP_REST_Request $request, WP_Post $attachment ): bool {
    // Skip transformation for specific attachments
    if ( get_post_meta( $attachment->ID, '_skip_dynamic_media', true ) ) {
        return false;
    }
    return $enabled;
}, 10, 3 );
```

**Parameters:**

- `$enabled` - Whether REST API transformation is enabled (default: `true`)
- `$request` - The REST request object
- `$attachment` - The attachment post object

---

### `aysnc_wordpress_cloudinary_config`

Configure the Cloudinary adapter. See [Configuration](#configuration) above.

---

### `aysnc_wordpress_cloudinary_args`

Modify transformation arguments before the Cloudinary URL is built. Use this for site-wide settings like
auto-optimization:

```php
add_filter( 'aysnc_wordpress_cloudinary_args', function ( array $args, int $attachment_id ): array {
    // Apply auto quality and format to all images
    $args['transform']['quality']      = 'auto';
    $args['transform']['fetch_format'] = 'auto';

    return $args;
}, 10, 2 );
```

This filter is Cloudinary-specific. If you switch adapters, you'll replace this with the equivalent for your new
service.

**Supported transformation parameters:**

| Parameter    | Cloudinary     | Parameter            | Cloudinary |
|--------------|----------------|----------------------|------------|
| width        | w              | height               | h          |
| crop         | c              | gravity              | g          |
| quality      | q              | fetch_format         | f          |
| effect       | e              | opacity              | o          |
| radius       | r              | angle                | a          |
| background   | b              | border               | bo         |
| overlay      | l              | underlay             | u          |
| dpr          | dpr            | zoom                 | z          |
| aspect_ratio | ar             | flags                | fl         |
| progressive  | fl_progressive | named_transformation | t          |

See [Cloudinary's transformation reference](https://cloudinary.com/documentation/transformation_reference) for the
complete list.

---

### `aysnc_wordpress_dynamic_media_url`

Modify the final dynamic URL before it's returned. Works with any adapter.

```php
add_filter( 'aysnc_wordpress_dynamic_media_url', function ( string $url, int $attachment_id, array $args ): string {
    // Log all generated URLs
    error_log( "Dynamic URL for {$attachment_id}: {$url}" );
    return $url;
}, 10, 3 );
```

**Parameters:**

- `$url` - The generated URL
- `$attachment_id` - WordPress attachment ID
- `$args` - Transformation arguments (width, height, hard_crop, etc.)

---

### `aysnc_wordpress_dynamic_media_srcset_dimensions`

Adjust dimensions for individual srcset entries.

```php
add_filter( 'aysnc_wordpress_dynamic_media_srcset_dimensions', function ( array $dimensions, int $attachment_id, array $image_meta, string $image_src ): array {
    // Force soft crop for all srcset images
    $dimensions['hard_crop'] = false;
    return $dimensions;
}, 10, 4 );
```

**Parameters:**

- `$dimensions` - Array with `width`, `height`, and optionally `hard_crop`
- `$attachment_id` - WordPress attachment ID
- `$image_meta` - WordPress attachment metadata
- `$image_src` - Original image source URL

---

### `aysnc_wordpress_dynamic_media_content_image_src`

Override the URL for images in post content. Return a string to use your custom URL, or `null` to let the plugin
generate one.

```php
add_filter( 'aysnc_wordpress_dynamic_media_content_image_src', function ( ?string $src, int $attachment_id, ?string $original_src, $width, $height, string $size ): ?string {
    // Skip transformation for full-size images
    if ( $size === 'full' ) {
        return $original_src;
    }
    return null; // Let the plugin handle it
}, 10, 6 );
```

**Parameters:**

- `$src` - Current source (null on first pass)
- `$attachment_id` - WordPress attachment ID
- `$original_src` - The original `src` attribute value
- `$width` - Image width attribute
- `$height` - Image height attribute
- `$size` - Image size name extracted from class (e.g., `large`, `thumbnail`)

---

### `aysnc_wordpress_cloudinary_upload_url`

Override the base upload URL used to determine the path within Cloudinary. Useful for multisite or custom upload
configurations.

```php
add_filter( 'aysnc_wordpress_cloudinary_upload_url', function ( string $upload_url ): string {
    // Use a consistent URL for multisite
    return 'https://example.com/wp-content/uploads';
} );
```

## Custom Adapters

The plugin uses an adapter pattern, so you can add support for other image services:

```php
use Aysnc\WordPress\DynamicMedia\Adapter;
use Aysnc\WordPress\DynamicMedia\Adapters\MediaAdapter;

class ImgixAdapter implements MediaAdapter {
    public static function get_dynamic_url( int $id, array $args ): string {
        $original_url = wp_get_attachment_url( $id );

        // Build your Imgix URL here
        $imgix_url = 'https://your-source.imgix.net/' . basename( $original_url );

        if ( ! empty( $args['width'] ) ) {
            $imgix_url .= '?w=' . $args['width'];
        }

        // Handle $args['transform'] for Imgix-specific params

        return $imgix_url;
    }
}

// Register and activate your adapter
add_action( 'after_setup_theme', function () {
    Adapter::register( 'imgix', new ImgixAdapter() );
    Adapter::set( 'imgix' );
}, 20 ); // Priority 20 to run after default registration
```

### Switching Adapters

If you have multiple adapters registered, you can switch between them:

```php
Adapter::set( 'cloudinary' );
Adapter::set( 'imgix' );
```

### Pausing the Plugin

Need to temporarily disable transformations? Maybe for debugging or a specific request:

```php
use Aysnc\WordPress\DynamicMedia\Plugin;

Plugin::pause();        // Disable transformations
// ... do something with original URLs ...
Plugin::pause( false ); // Re-enable
```

## Development

### Setup

```bash
composer install
npm install
```

### Running Tests

```bash
npm run test:php
```

### Code Quality

```bash
composer lint            # PHP CodeSniffer
composer format          # PHP CS Fixer
composer static-analysis # PHPStan (level max)
```

### Full Test Suite

```bash
npm run lint:test        # Runs lint, tests, and static analysis
```
