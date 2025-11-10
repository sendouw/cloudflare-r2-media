<?php
/**
 * Plugin Name: Cloudflare R2 Media Offload
 * Plugin URI: https://github.com/sendouw/serverlesswpblog/tree/main/wp/wp-content/plugins/cloudflare-r2-media
 * Description: Stores WordPress uploads in a Cloudflare R2 bucket and rewrites media URLs to serve from object storage.
 * Version: 0.1.0
 * Author: Andy Sendouw
 * License: MIT
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'cloudflare_r2_bootstrap' ) ) {

	/**
	 * Conditional debug helper controlled by the R2_DEBUG env var.
	 *
	 * @param string $message Message to send to the error log.
	 */
	function cloudflare_r2_debug( $message ) {
		static $enabled = null;

		if ( null === $enabled ) {
			$enabled = ! empty( cloudflare_r2_env( 'R2_DEBUG' ) );
		}

		if ( $enabled ) {
			error_log( '[Cloudflare R2] ' . $message ); // phpcs:ignore
		}
	}

	/**
	 * Fetch environment variables safely.
	 *
	 * @param string $key Environment variable name.
	 *
	 * @return string|null
	 */
	function cloudflare_r2_env( $key ) {
		if ( isset( $_ENV[ $key ] ) ) {
			return trim( (string) $_ENV[ $key ] );
		}

		if ( isset( $_SERVER[ $key ] ) ) {
			return trim( (string) $_SERVER[ $key ] );
		}

		$value = getenv( $key ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.env_getenv

		return false !== $value ? trim( (string) $value ) : null;
	}

	/**
	 * Determine if integration is fully configured.
	 *
	 * @return bool
	 */
	function cloudflare_r2_is_enabled() {
		static $enabled = null;

		if ( null !== $enabled ) {
			return $enabled;
		}

		$missing = array();

		$required = array(
			'R2_BUCKET'            => cloudflare_r2_env( 'R2_BUCKET' ),
			'R2_ACCOUNT_ID'        => cloudflare_r2_env( 'R2_ACCOUNT_ID' ),
			'R2_ACCESS_KEY_ID'     => cloudflare_r2_env( 'R2_ACCESS_KEY_ID' ),
			'R2_SECRET_ACCESS_KEY' => cloudflare_r2_env( 'R2_SECRET_ACCESS_KEY' ),
		);

		foreach ( $required as $name => $value ) {
			if ( empty( $value ) ) {
				$missing[] = strtolower( str_replace( 'R2_', '', $name ) );
			}
		}

		if ( ! empty( $missing ) ) {
			cloudflare_r2_debug( 'is_enabled=no missing=' . implode( ',', $missing ) );
			$enabled = false;
		} else {
			cloudflare_r2_debug( 'is_enabled=yes' );
			$enabled = true;
		}

		return $enabled;
	}

	/**
	 * Resolve an autoloader that provides an S3-compatible SDK.
	 *
	 * @return array|false
	 */
	function cloudflare_r2_resolve_sdk() {
		static $resolved = null;

		if ( null !== $resolved ) {
			return $resolved;
		}

		$autoloaders = array(
			__DIR__ . '/vendor/autoload.php',
		);

		if ( defined( 'WP_PLUGIN_DIR' ) ) {
			$autoloaders[] = WP_PLUGIN_DIR . '/amazon-s3-and-cloudfront/vendor/autoload.php';
			$autoloaders[] = WP_PLUGIN_DIR . '/amazon-s3-and-cloudfront/vendor/Aws3/aws-autoloader.php';
		}

		foreach ( $autoloaders as $autoload ) {
			if ( file_exists( $autoload ) ) {
				require_once $autoload;
			}
		}

		if ( class_exists( '\Aws\S3\S3Client' ) ) {
			$resolved = array(
				'client' => '\Aws\S3\S3Client',
			);

			return $resolved;
		}

		if ( class_exists( '\DeliciousBrains\WP_Offload_Media\Aws3\Aws\S3\S3Client' ) ) {
			$resolved = array(
				'client' => '\DeliciousBrains\WP_Offload_Media\Aws3\Aws\S3\S3Client',
			);

			return $resolved;
		}

		$resolved = false;

		return $resolved;
	}

	/**
	 * Provide a shared S3 client configured for R2.
	 *
	 * @return object|false
	 */
	function cloudflare_r2_client() {
		static $client = null;

		if ( null !== $client ) {
			return $client;
		}

		$sdk = cloudflare_r2_resolve_sdk();

		if ( ! $sdk ) {
			error_log( 'Cloudflare R2: AWS SDK not found. Run composer install or install WP Offload Media Lite.', 0 );
			$client = false;

			return $client;
		}

		$args = array(
			'version'                 => 'latest',
			'region'                  => cloudflare_r2_env( 'R2_REGION' ) ? cloudflare_r2_env( 'R2_REGION' ) : 'auto',
			'endpoint'                => cloudflare_r2_endpoint(),
			'use_path_style_endpoint' => true,
			'credentials'             => array(
				'key'    => cloudflare_r2_env( 'R2_ACCESS_KEY_ID' ),
				'secret' => cloudflare_r2_env( 'R2_SECRET_ACCESS_KEY' ),
			),
		);

		$client_class = $sdk['client'];

		try {
			$client = new $client_class( $args );
			cloudflare_r2_debug( 'client_init=success' );
		} catch ( \Throwable $e ) {
			error_log( 'Cloudflare R2: failed to initialise client - ' . $e->getMessage(), 0 );
			$client = false;
		}

		return $client;
	}

	/**
	 * R2 API endpoint.
	 *
	 * @return string
	 */
	function cloudflare_r2_endpoint() {
		$endpoint = cloudflare_r2_env( 'R2_ENDPOINT' );

		if ( ! empty( $endpoint ) ) {
			return rtrim( $endpoint, '/' );
		}

		$account = cloudflare_r2_env( 'R2_ACCOUNT_ID' );

		return sprintf( 'https://%s.r2.cloudflarestorage.com', $account );
	}

	/**
	 * Bucket name helper.
	 *
	 * @return string
	 */
	function cloudflare_r2_bucket() {
		return cloudflare_r2_env( 'R2_BUCKET' );
	}

	/**
	 * Optional object prefix helper.
	 *
	 * @return string
	 */
	function cloudflare_r2_object_prefix() {
		$prefix = cloudflare_r2_env( 'R2_OBJECT_PREFIX' );

		if ( empty( $prefix ) ) {
			return '';
		}

		return trim( str_replace( '\\', '/', $prefix ), '/' );
	}

	/**
	 * Compute the public base URL for uploaded objects.
	 *
	 * @return string
	 */
	function cloudflare_r2_base_url() {
		static $base_url = null;

		if ( null !== $base_url ) {
			return $base_url;
		}

		$custom = cloudflare_r2_env( 'R2_PUBLIC_BASE_URL' );

		if ( ! empty( $custom ) ) {
			$base_url = rtrim( $custom, '/' );
		} else {
			$base_url = cloudflare_r2_endpoint() . '/' . cloudflare_r2_bucket();
		}

		return $base_url;
	}

	/**
	 * Base URL including object prefix.
	 *
	 * @return string
	 */
	function cloudflare_r2_prefixed_base_url() {
		$base   = cloudflare_r2_base_url();
		$prefix = cloudflare_r2_object_prefix();

		if ( empty( $prefix ) ) {
			cloudflare_r2_debug( 'prefixed_base_url=' . $base . ' (no prefix)' );
			return $base;
		}

		$base   = rtrim( $base, '/' );
		$prefix = trim( str_replace( '\\', '/', $prefix ), '/' );

		// If the configured public base URL already contains the prefix path,
		// avoid appending it again to prevent duplicated segments.
		$base_path = wp_parse_url( $base, PHP_URL_PATH );
		$base_path = $base_path ? rtrim( $base_path, '/' ) : '';

		if ( $base_path ) {
			$needle = '/' . $prefix;
			if ( substr( $base_path, -strlen( $needle ) ) === $needle || trim( $base_path, '/' ) === $prefix ) {
				cloudflare_r2_debug( 'duplicate_prefix_detected base=' . $base . ' prefix=' . $prefix . ' (skipped append)' );
				return $base; // Prefix is already present in base URL.
			}
		}

		$final_url = $base . '/' . $prefix;
		cloudflare_r2_debug( 'prefixed_base_url=' . $final_url );
		return $final_url;
	}

	/**
	 * Create an object key, applying any prefix and normalising separators.
	 *
	 * @param string $key Relative key.
	 *
	 * @return string
	 */
	function cloudflare_r2_object_key( $key ) {
		$key    = ltrim( str_replace( '\\', '/', $key ), '/' );
		$prefix = cloudflare_r2_object_prefix();

		if ( ! empty( $prefix ) ) {
			$key = $prefix . '/' . $key;
		}

		return $key;
	}

	/**
	 * Translate an uploads directory path into an R2 key.
	 *
	 * @param string $file_path Absolute filesystem path.
	 *
	 * @return string|false
	 */
	function cloudflare_r2_key_from_path( $file_path ) {
		$uploads = wp_get_upload_dir();

		if ( empty( $uploads['basedir'] ) ) {
			return false;
		}

		$normalized_base = str_replace( '\\', '/', $uploads['basedir'] );
		$normalized_path = str_replace( '\\', '/', $file_path );

		if ( strpos( $normalized_path, $normalized_base ) !== 0 ) {
			return false;
		}

		return ltrim( substr( $normalized_path, strlen( $normalized_base ) ), '/' );
	}

	/**
	 * Build the public object URL from a relative key.
	 *
	 * @param string $key Relative key.
	 *
	 * @return string
	 */
	function cloudflare_r2_object_url( $key ) {
		$key = ltrim( str_replace( '\\', '/', $key ), '/' );

		return rtrim( cloudflare_r2_prefixed_base_url(), '/' ) . '/' . $key;
	}

	/**
	 * Lightweight MIME detection.
	 *
	 * @param string $path Filesystem path.
	 *
	 * @return string
	 */
	function cloudflare_r2_mime_type( $path ) {
		$type = wp_check_filetype( $path );

		if ( ! empty( $type['type'] ) ) {
			return $type['type'];
		}

		return 'application/octet-stream';
	}

	/**
	 * Upload a file to R2.
	 *
	 * @param string $absolute_path Filesystem path.
	 * @param string $key           Relative key.
	 * @param string $mime          Optional MIME type.
	 *
	 * @return bool
	 */
	function cloudflare_r2_upload_file( $absolute_path, $key, $mime = '' ) {
		static $uploaded = array();

		if ( ! cloudflare_r2_is_enabled() ) {
			return false;
		}

		if ( empty( $absolute_path ) || empty( $key ) || ! file_exists( $absolute_path ) ) {
			return false;
		}

		$key = cloudflare_r2_object_key( $key );

		if ( isset( $uploaded[ $key ] ) ) {
			return true;
		}

		$client = cloudflare_r2_client();

		if ( ! $client ) {
			return false;
		}

		$args = array(
			'Bucket'      => cloudflare_r2_bucket(),
			'Key'         => $key,
			'SourceFile'  => $absolute_path,
			'ContentType' => $mime ? $mime : cloudflare_r2_mime_type( $absolute_path ),
			'Metadata'    => array(
				'origin' => 'cloudflare-r2-media',
			),
		);

		try {
			$client->putObject( $args );
			$uploaded[ $key ] = true;
			cloudflare_r2_debug( 'uploaded=' . $key );

			return true;
		} catch ( \Throwable $e ) {
			$message = method_exists( $e, 'getAwsErrorMessage' ) ? $e->getAwsErrorMessage() : $e->getMessage();
			error_log( 'Cloudflare R2: failed to upload ' . $key . ' - ' . $message, 0 );
		}

		return false;
	}

	/**
	 * Upload original files as soon as WordPress handles them.
	 *
	 * @param array  $upload  Upload data.
	 * @param string $context Context.
	 *
	 * @return array
	 */
	function cloudflare_r2_handle_upload( $upload, $context ) {
		if ( ! cloudflare_r2_is_enabled() ) {
			return $upload;
		}

		if ( empty( $upload['file'] ) ) {
			return $upload;
		}

		$relative = cloudflare_r2_key_from_path( $upload['file'] );

		if ( ! $relative ) {
			return $upload;
		}

		$uploaded = cloudflare_r2_upload_file( $upload['file'], $relative, isset( $upload['type'] ) ? $upload['type'] : '' );

		if ( $uploaded ) {
			$upload['url'] = cloudflare_r2_object_url( $relative );
		}

		return $upload;
	}

	/**
	 * Upload generated thumbnails and replacement files.
	 *
	 * @param array $metadata Attachment metadata.
	 *
	 * @return array
	 */
	function cloudflare_r2_capture_metadata( $metadata ) {
		if ( ! cloudflare_r2_is_enabled() || empty( $metadata ) || empty( $metadata['file'] ) ) {
			return $metadata;
		}

		$uploads   = wp_get_upload_dir();
		$base_path = trailingslashit( $uploads['basedir'] );
		$original  = $base_path . $metadata['file'];

		cloudflare_r2_upload_file( $original, $metadata['file'] );

		if ( ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
			foreach ( $metadata['sizes'] as $size ) {
				if ( empty( $size['file'] ) ) {
					continue;
				}

				$size_path = path_join( dirname( $metadata['file'] ), $size['file'] );
				$absolute  = $base_path . $size_path;

				cloudflare_r2_upload_file( $absolute, $size_path );
			}
		}

		return $metadata;
	}

	/**
	 * Delete objects from R2 when attachments are removed.
	 *
	 * @param int $post_id Attachment ID.
	 */
	function cloudflare_r2_delete_objects( $post_id ) {
		if ( ! cloudflare_r2_is_enabled() ) {
			return;
		}

		$client = cloudflare_r2_client();

		if ( ! $client ) {
			return;
		}

		$keys = array();

		$file_meta = get_post_meta( $post_id, '_wp_attached_file', true );

		if ( ! empty( $file_meta ) ) {
			$keys[] = cloudflare_r2_object_key( $file_meta );
		}

		$metadata = wp_get_attachment_metadata( $post_id );

		if ( ! empty( $metadata['file'] ) ) {
			$keys[] = cloudflare_r2_object_key( $metadata['file'] );

			if ( ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
				foreach ( $metadata['sizes'] as $size ) {
					if ( empty( $size['file'] ) ) {
						continue;
					}

					$keys[] = cloudflare_r2_object_key( path_join( dirname( $metadata['file'] ), $size['file'] ) );
				}
			}
		}

		$keys = array_unique( $keys );

		if ( empty( $keys ) ) {
			return;
		}

		$objects = array();

		foreach ( $keys as $key ) {
			$objects[] = array( 'Key' => $key );
		}

		try {
			$client->deleteObjects(
				array(
					'Bucket' => cloudflare_r2_bucket(),
					'Delete' => array(
						'Objects' => $objects,
					),
				)
			);
		} catch ( \Throwable $e ) {
			$message = method_exists( $e, 'getAwsErrorMessage' ) ? $e->getAwsErrorMessage() : $e->getMessage();
			error_log( 'Cloudflare R2: failed to delete ' . count( $objects ) . ' objects - ' . $message, 0 );
		}
	}

	/**
	 * Override the base URL returned for uploads.
	 *
	 * @param array $uploads Upload directory data.
	 *
	 * @return array
	 */
	function cloudflare_r2_filter_upload_dir( $uploads ) {
		if ( ! cloudflare_r2_is_enabled() ) {
			return $uploads;
		}

		$sub      = isset( $uploads['subdir'] ) ? $uploads['subdir'] : '';
		$prefixed = cloudflare_r2_prefixed_base_url();

		$uploads['baseurl'] = $prefixed;
		$uploads['url']     = rtrim( $prefixed, '/' ) . $sub;

		return $uploads;
	}

	/**
	 * Replace attachment URLs with the R2 version.
	 *
	 * @param string $url     Original URL.
	 * @param int    $post_id Attachment ID.
	 *
	 * @return string
	 */
	function cloudflare_r2_get_attachment_url( $url, $post_id ) {
		if ( ! cloudflare_r2_is_enabled() ) {
			return $url;
		}

		$meta = wp_get_attachment_metadata( $post_id );

		if ( ! empty( $meta['file'] ) ) {
			return cloudflare_r2_object_url( $meta['file'] );
		}

		$relative = cloudflare_r2_normalize_relative_from_url( $url );

		if ( ! empty( $relative ) ) {
			return cloudflare_r2_object_url( $relative );
		}

		return $url;
	}

	/**
	 * Ensure responsive image sources point at R2 resources.
	 *
	 * @param array $sources Image source set.
	 *
	 * @return array
	 */
	function cloudflare_r2_filter_srcset( $sources ) {
		if ( ! cloudflare_r2_is_enabled() ) {
			return $sources;
		}

		foreach ( $sources as &$source ) {
			if ( ! empty( $source['url'] ) ) {
				$source['url'] = cloudflare_r2_object_url( cloudflare_r2_normalize_relative_from_url( $source['url'] ) );
			}
		}

		return $sources;
	}

	/**
	 * Prepare media items for the WordPress media library JS layer.
	 *
	 * @param array   $response  Prepared response.
	 * @param WP_Post $attachment Attachment object.
	 * @param array   $meta      Metadata.
	 *
	 * @return array
	 */
	function cloudflare_r2_prepare_attachment_for_js( $response, $attachment, $meta ) {
		if ( ! cloudflare_r2_is_enabled() ) {
			return $response;
		}

		if ( ! empty( $response['url'] ) ) {
			$response['url'] = cloudflare_r2_object_url( cloudflare_r2_normalize_relative_from_url( $response['url'] ) );
			cloudflare_r2_debug( 'prepare_js url=' . $response['url'] );
		}

		if ( ! empty( $response['sizes'] ) && is_array( $response['sizes'] ) ) {
			foreach ( $response['sizes'] as $name => $size ) {
				if ( empty( $size['url'] ) ) {
					continue;
				}

				$response['sizes'][ $name ]['url'] = cloudflare_r2_object_url( cloudflare_r2_normalize_relative_from_url( $size['url'] ) );
			}
		}

		return $response;
	}

	/**
	 * Extract relative path from a URL for srcset rewriting.
	 *
	 * @param string $url URL.
	 *
	 * @return string
	 */
	function cloudflare_r2_normalize_relative_from_url( $url ) {
		if ( empty( $url ) ) {
			return '';
		}

		$path = wp_parse_url( $url, PHP_URL_PATH );
		$path = $path ? ltrim( $path, '/' ) : ltrim( $url, '/' );

		$r2_base_path = wp_parse_url( cloudflare_r2_base_url(), PHP_URL_PATH );
		$r2_base_path = $r2_base_path ? trim( $r2_base_path, '/' ) : '';

		if ( ! empty( $r2_base_path ) && strpos( $path, $r2_base_path ) === 0 ) {
			$path = ltrim( substr( $path, strlen( $r2_base_path ) ), '/' );
		}

		$uploads_base = wp_parse_url( content_url( 'uploads/' ), PHP_URL_PATH );
		$uploads_base = $uploads_base ? trim( $uploads_base, '/' ) : 'wp-content/uploads';

		if ( strpos( $path, $uploads_base . '/' ) === 0 ) {
			$path = substr( $path, strlen( $uploads_base . '/' ) );
		}

		if ( strpos( $path, 'wp-content/uploads/' ) === 0 ) {
			$path = substr( $path, strlen( 'wp-content/uploads/' ) );
		}

		return cloudflare_r2_strip_prefix( $path );
	}

	/**
	 * Remove the configured prefix from a path.
	 *
	 * @param string $path Relative path.
	 *
	 * @return string
	 */
	function cloudflare_r2_strip_prefix( $path ) {
		$path   = ltrim( str_replace( '\\', '/', $path ), '/' );
		$prefix = cloudflare_r2_object_prefix();

		if ( empty( $prefix ) ) {
			return $path;
		}

		$prefix = trim( $prefix, '/' ) . '/';

		if ( strpos( $path, $prefix ) === 0 ) {
			return substr( $path, strlen( $prefix ) );
		}

		return $path;
	}

	/**
	 * Register plugin hooks.
	 */
	function cloudflare_r2_register_hooks() {
		add_filter( 'wp_handle_upload', 'cloudflare_r2_handle_upload', 20, 2 );
		add_filter( 'wp_update_attachment_metadata', 'cloudflare_r2_capture_metadata', 20, 1 );
		add_action( 'delete_attachment', 'cloudflare_r2_delete_objects' );
		add_filter( 'upload_dir', 'cloudflare_r2_filter_upload_dir' );
		add_filter( 'wp_get_attachment_url', 'cloudflare_r2_get_attachment_url', 20, 2 );
		add_filter( 'wp_calculate_image_srcset', 'cloudflare_r2_filter_srcset' );
		add_filter( 'wp_prepare_attachment_for_js', 'cloudflare_r2_prepare_attachment_for_js', 20, 3 );
	}

	/**
	 * Bootstrap the plugin once.
	 */
	function cloudflare_r2_bootstrap() {
		static $bootstrapped = false;

		if ( $bootstrapped ) {
			return;
		}

		$bootstrapped = true;

		cloudflare_r2_register_hooks();
	}

	if ( function_exists( 'add_action' ) ) {
		add_action( 'plugins_loaded', 'cloudflare_r2_bootstrap' );
	}
	cloudflare_r2_bootstrap();
}
