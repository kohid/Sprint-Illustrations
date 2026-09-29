<?php
/**
 * Reference images attached to piece requests (uploads/sprint-illustrations/references/).
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Storage;

use SprintIllustrations\Library\ReferenceImage;
use SprintIllustrations\Plugin;

/**
 * Every upload is decoded and re-encoded by WordPress's image editor (GD here), so only real images
 * are stored, without metadata, at most 1600 px on the long side.
 */
final class ReferenceImages {

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( private Plugin $plugin ) {}

	/**
	 * Store an uploaded file.
	 *
	 * @param array<string, mixed> $file One entry of $_FILES.
	 * @return string|\WP_Error Stored name.
	 */
	public function store( array $file ): string|\WP_Error {
		$tmp = (string) ( $file['tmp_name'] ?? '' );
		if ( UPLOAD_ERR_OK !== (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) || '' === $tmp || ! is_uploaded_file( $tmp ) ) {
			return new \WP_Error( 'sprint_illustrations_reference', __( 'The image didn’t upload. Try again with a PNG, JPEG or WebP up to 5 MB.', 'sprint-illustrations' ) );
		}

		$check = wp_check_filetype_and_ext( $tmp, sanitize_file_name( (string) ( $file['name'] ?? '' ) ) );
		$size  = getimagesize( $tmp );
		$mime  = (string) ( $check['type'] ? $check['type'] : ( $size['mime'] ?? '' ) );
		$error = ReferenceImage::validate( (int) filesize( $tmp ), $mime, (int) ( $size[0] ?? 0 ), (int) ( $size[1] ?? 0 ) );
		if ( '' !== $error || false === $size || $mime !== $size['mime'] ) {
			return new \WP_Error( 'sprint_illustrations_reference', '' !== $error ? $error : __( 'Use a PNG, JPEG or WebP image.', 'sprint-illustrations' ) );
		}

		$editor = wp_get_image_editor( $tmp );
		if ( is_wp_error( $editor ) ) {
			return new \WP_Error( 'sprint_illustrations_reference', __( 'This image couldn’t be read. Try a PNG or JPEG.', 'sprint-illustrations' ) );
		}

		[ $w, $h ] = ReferenceImage::fit( (int) $size[0], (int) $size[1] );
		if ( $w < (int) $size[0] ) {
			$editor->resize( $w, $h, false );
		}

		$alpha = 'image/jpeg' !== $mime;
		$name  = ReferenceImage::name( strtolower( wp_generate_password( 16, false ) ), $alpha );
		wp_mkdir_p( $this->dir() );
		$saved = $editor->save( $this->path( $name ), $alpha ? 'image/png' : 'image/jpeg' );

		return is_wp_error( $saved )
			? new \WP_Error( 'sprint_illustrations_reference', __( 'The image couldn’t be saved.', 'sprint-illustrations' ) )
			: $name;
	}

	/**
	 * Absolute path of a stored image ('' for a malformed name).
	 *
	 * @param string $name Stored name.
	 * @return string
	 */
	public function path( string $name ): string {
		return ReferenceImage::is_name( $name ) ? $this->dir() . '/' . $name : '';
	}

	/**
	 * Public URL of a stored image ('' when missing).
	 *
	 * @param string $name Stored name.
	 * @return string
	 */
	public function url( string $name ): string {
		$path = $this->path( $name );

		return '' !== $path && is_file( $path )
			? trailingslashit( wp_upload_dir( null, false )['baseurl'] ) . 'sprint-illustrations/references/' . $name
			: '';
	}

	/**
	 * Delete a stored image.
	 *
	 * @param string $name Stored name.
	 */
	public function delete( string $name ): void {
		$path = $this->path( $name );
		if ( '' !== $path && is_file( $path ) ) {
			wp_delete_file( $path );
		}
	}

	/**
	 * Folder.
	 *
	 * @return string
	 */
	private function dir(): string {
		return $this->plugin->user_library_dir() . '/references';
	}
}
