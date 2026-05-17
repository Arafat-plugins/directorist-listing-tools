<?php
/**
 * Directorist preview image no-crop.
 *
 * Keeps Directorist archive cards on the generated preview image size while
 * preventing WordPress from hard-cropping wide logos during thumbnail creation.
 *
 * @package DirectoristListingTools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'DLT_AF_LOADED_DIRECTORIST_PREVIEW_IMAGE_NO_CROP' ) ) {
	return;
}

define( 'DLT_AF_LOADED_DIRECTORIST_PREVIEW_IMAGE_NO_CROP', true );

add_filter( 'directorist_default_preview_size', 'dlt_directorist_preview_image_no_crop', 20 );
add_filter( 'image_downsize', 'dlt_directorist_preview_image_no_crop_existing_metadata_fallback', 20, 3 );

/**
 * Disable hard crop for Directorist's default preview image size.
 *
 * @param array $size Existing Directorist preview size config.
 * @return array
 */
function dlt_directorist_preview_image_no_crop( $size ) {
	$width  = ! empty( $size['width'] ) ? absint( $size['width'] ) : 640;
	$height = ! empty( $size['height'] ) ? absint( $size['height'] ) : 360;

	return array(
		'width'  => $width > 0 ? $width : 640,
		'height' => $height > 0 ? $height : 360,
		'crop'   => false,
	);
}

/**
 * Avoid serving an already-generated hard-cropped directorist_preview file.
 *
 * Regeneration tools can skip existing sizes, leaving metadata pointed at the
 * old 640x360 cropped file. For Directorist archive cards, use the closest
 * proportional intermediate image already available instead of the cropped
 * preview. This keeps the fix immediate without loading full-size uploads.
 *
 * @param bool|array   $downsize Existing filtered response.
 * @param int          $attachment_id Attachment ID.
 * @param string|array $size Requested image size.
 * @return bool|array
 */
function dlt_directorist_preview_image_no_crop_existing_metadata_fallback( $downsize, $attachment_id, $size ) {
	if ( false !== $downsize || 'directorist_preview' !== $size ) {
		return $downsize;
	}

	$metadata = wp_get_attachment_metadata( $attachment_id );
	if ( empty( $metadata['width'] ) || empty( $metadata['height'] ) || empty( $metadata['sizes'] ) || ! is_array( $metadata['sizes'] ) ) {
		return $downsize;
	}

	$original_width  = absint( $metadata['width'] );
	$original_height = absint( $metadata['height'] );
	if ( $original_width <= 0 || $original_height <= 0 ) {
		return $downsize;
	}

	$preview = isset( $metadata['sizes']['directorist_preview'] ) && is_array( $metadata['sizes']['directorist_preview'] )
		? $metadata['sizes']['directorist_preview']
		: array();

	if ( empty( $preview['width'] ) || empty( $preview['height'] ) ) {
		return $downsize;
	}

	$original_ratio = $original_width / $original_height;
	$preview_ratio  = absint( $preview['width'] ) / max( 1, absint( $preview['height'] ) );

	if ( abs( $original_ratio - $preview_ratio ) < 0.03 ) {
		return $downsize;
	}

	$fallback = dlt_directorist_preview_image_no_crop_best_intermediate( $metadata, $original_ratio );
	if ( empty( $fallback ) ) {
		return $downsize;
	}

	$url = dlt_directorist_preview_image_no_crop_intermediate_url( $metadata, $fallback['file'] );
	if ( ! $url ) {
		return $downsize;
	}

	return array(
		$url,
		absint( $fallback['width'] ),
		absint( $fallback['height'] ),
		true,
	);
}

/**
 * Choose the closest proportional intermediate image for listing cards.
 *
 * @param array $metadata Attachment metadata.
 * @param float $original_ratio Original image aspect ratio.
 * @return array
 */
function dlt_directorist_preview_image_no_crop_best_intermediate( $metadata, $original_ratio ) {
	$best       = array();
	$best_score = PHP_INT_MAX;

	foreach ( $metadata['sizes'] as $size_name => $candidate ) {
		if ( 'directorist_preview' === $size_name || empty( $candidate['file'] ) || empty( $candidate['width'] ) || empty( $candidate['height'] ) ) {
			continue;
		}

		$width  = absint( $candidate['width'] );
		$height = absint( $candidate['height'] );
		if ( $width <= 0 || $height <= 0 ) {
			continue;
		}

		$ratio_delta = abs( $original_ratio - ( $width / $height ) );
		if ( $ratio_delta > 0.03 ) {
			continue;
		}

		$target_width = 640;
		$size_penalty = $width >= $target_width ? abs( $width - $target_width ) : ( $target_width - $width ) + 200;
		$score        = (int) round( $ratio_delta * 100000 ) + $size_penalty;

		if ( $score < $best_score ) {
			$best       = $candidate;
			$best_score = $score;
		}
	}

	return $best;
}

/**
 * Build an intermediate image URL from attachment metadata.
 *
 * @param array  $metadata Attachment metadata.
 * @param string $file Intermediate file name.
 * @return string
 */
function dlt_directorist_preview_image_no_crop_intermediate_url( $metadata, $file ) {
	$uploads = wp_get_upload_dir();
	if ( empty( $uploads['baseurl'] ) || empty( $metadata['file'] ) ) {
		return '';
	}

	$subdir = trailingslashit( dirname( $metadata['file'] ) );
	if ( './' === $subdir || '.\\' === $subdir ) {
		$subdir = '';
	}

	return trailingslashit( $uploads['baseurl'] ) . $subdir . $file;
}
