<?php
/**
 * Migrate Existing Media to Cloudflare R2
 *
 * This script uploads existing WordPress media files to R2 and updates URLs in posts.
 *
 * Usage:
 * 1. Upload this file to your WordPress installation
 * 2. Run via WP-CLI: wp eval-file migrate-existing-media.php
 * 3. Or access via browser (temporarily): https://yoursite.com/wp-content/plugins/cloudflare-r2-media/migrate-existing-media.php
 */

// Load WordPress if accessed directly via browser
if ( ! defined( 'ABSPATH' ) ) {
	require_once __DIR__ . '/../../../../wp-load.php';

	// Security check - only allow in development or with special key
	if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
		wp_die( 'This migration script can only be run in development mode. Use WP-CLI instead: wp eval-file migrate-existing-media.php' );
	}
}

/**
 * Migrate existing media to R2
 */
function cloudflare_r2_migrate_existing_media() {
	if ( ! function_exists( 'cloudflare_r2_is_enabled' ) ) {
		return new WP_Error( 'r2_not_loaded', 'Cloudflare R2 plugin is not loaded.' );
	}

	if ( ! cloudflare_r2_is_enabled() ) {
		return new WP_Error( 'r2_not_configured', 'R2 is not configured. Please set environment variables.' );
	}

	echo "=== Cloudflare R2 Media Migration ===\n\n";

	$args = array(
		'post_type'      => 'attachment',
		'post_status'    => 'inherit',
		'posts_per_page' => -1,
		'post_mime_type' => 'image',
	);

	$attachments = get_posts( $args );
	$total       = count( $attachments );
	$uploaded    = 0;
	$skipped     = 0;
	$failed      = 0;

	echo "Found {$total} image attachments to migrate.\n\n";

	foreach ( $attachments as $index => $attachment ) {
		$num = $index + 1;
		echo "[{$num}/{$total}] Processing: {$attachment->post_title} (ID: {$attachment->ID})... ";

		$file = get_attached_file( $attachment->ID );

		if ( ! $file || ! file_exists( $file ) ) {
			echo "SKIPPED (file not found)\n";
			$skipped++;
			continue;
		}

		$metadata = wp_get_attachment_metadata( $attachment->ID );

		// Upload main file
		$relative = cloudflare_r2_key_from_path( $file );
		if ( $relative ) {
			$result = cloudflare_r2_upload_file( $file, $relative );

			if ( $result ) {
				echo "✓ ";
				$uploaded++;
			} else {
				echo "✗ ";
				$failed++;
				continue;
			}
		}

		// Upload thumbnails
		if ( ! empty( $metadata['file'] ) && ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
			$upload_dir = wp_get_upload_dir();
			$base_path  = trailingslashit( $upload_dir['basedir'] );
			$thumb_count = 0;

			foreach ( $metadata['sizes'] as $size_name => $size_data ) {
				if ( empty( $size_data['file'] ) ) {
					continue;
				}

				$size_path = path_join( dirname( $metadata['file'] ), $size_data['file'] );
				$absolute  = $base_path . $size_path;

				if ( file_exists( $absolute ) ) {
					cloudflare_r2_upload_file( $absolute, $size_path );
					$thumb_count++;
				}
			}

			echo "({$thumb_count} thumbnails) ";
		}

		echo "OK\n";
	}

	echo "\n=== Migration Complete ===\n";
	echo "Total attachments: {$total}\n";
	echo "Uploaded: {$uploaded}\n";
	echo "Skipped: {$skipped}\n";
	echo "Failed: {$failed}\n\n";

	// Update post content URLs
	echo "=== Updating Post Content URLs ===\n\n";
	cloudflare_r2_update_post_content_urls();

	return array(
		'total'    => $total,
		'uploaded' => $uploaded,
		'skipped'  => $skipped,
		'failed'   => $failed,
	);
}

/**
 * Update URLs in post content
 */
function cloudflare_r2_update_post_content_urls() {
	global $wpdb;

	$upload_dir = wp_get_upload_dir();
	$old_base   = $upload_dir['baseurl'];
	$new_base   = cloudflare_r2_prefixed_base_url();

	// Find posts with media URLs
	$posts = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT ID, post_content FROM {$wpdb->posts}
			WHERE post_content LIKE %s
			AND post_status IN ('publish', 'draft', 'private', 'pending')",
			'%' . $wpdb->esc_like( $old_base ) . '%'
		)
	);

	$updated = 0;

	foreach ( $posts as $post ) {
		$new_content = str_replace( $old_base, $new_base, $post->post_content );

		if ( $new_content !== $post->post_content ) {
			$wpdb->update(
				$wpdb->posts,
				array( 'post_content' => $new_content ),
				array( 'ID' => $post->ID ),
				array( '%s' ),
				array( '%d' )
			);
			$updated++;
			echo "Updated post ID {$post->ID}\n";
		}
	}

	echo "\nUpdated {$updated} posts with new R2 URLs.\n";

	// Clear caches
	if ( function_exists( 'wp_cache_flush' ) ) {
		wp_cache_flush();
	}

	return $updated;
}

/**
 * Rollback URLs back to local (if needed)
 */
function cloudflare_r2_rollback_urls() {
	global $wpdb;

	$upload_dir = wp_get_upload_dir();
	$local_base = $upload_dir['baseurl'];
	$r2_base    = cloudflare_r2_prefixed_base_url();

	$posts = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT ID, post_content FROM {$wpdb->posts}
			WHERE post_content LIKE %s
			AND post_status IN ('publish', 'draft', 'private', 'pending')",
			'%' . $wpdb->esc_like( $r2_base ) . '%'
		)
	);

	$updated = 0;

	foreach ( $posts as $post ) {
		$new_content = str_replace( $r2_base, $local_base, $post->post_content );

		if ( $new_content !== $post->post_content ) {
			$wpdb->update(
				$wpdb->posts,
				array( 'post_content' => $new_content ),
				array( 'ID' => $post->ID ),
				array( '%s' ),
				array( '%d' )
			);
			$updated++;
		}
	}

	echo "Rolled back {$updated} posts to local URLs.\n";

	return $updated;
}

// Run migration if accessed directly
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	// Only run if WP_DEBUG is enabled (safety measure)
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		$result = cloudflare_r2_migrate_existing_media();

		if ( is_wp_error( $result ) ) {
			echo 'Error: ' . $result->get_error_message();
		}
	}
}

// WP-CLI command
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * Migrate existing media to Cloudflare R2
	 *
	 * ## EXAMPLES
	 *
	 *     wp r2 migrate
	 *
	 * @when after_wp_load
	 */
	WP_CLI::add_command( 'r2 migrate', function() {
		$result = cloudflare_r2_migrate_existing_media();

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		} else {
			WP_CLI::success( "Migration complete! Uploaded {$result['uploaded']} files." );
		}
	});

	/**
	 * Update post content URLs to R2
	 *
	 * ## EXAMPLES
	 *
	 *     wp r2 update-urls
	 *
	 * @when after_wp_load
	 */
	WP_CLI::add_command( 'r2 update-urls', function() {
		$updated = cloudflare_r2_update_post_content_urls();
		WP_CLI::success( "Updated {$updated} posts." );
	});

	/**
	 * Rollback URLs to local storage
	 *
	 * ## EXAMPLES
	 *
	 *     wp r2 rollback-urls
	 *
	 * @when after_wp_load
	 */
	WP_CLI::add_command( 'r2 rollback-urls', function() {
		$updated = cloudflare_r2_rollback_urls();
		WP_CLI::success( "Rolled back {$updated} posts." );
	});
}
