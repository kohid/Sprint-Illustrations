<?php
/**
 * Reference images attached to piece requests (uploads/sprint-illustrations/references/).
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Storage;

use SprintIllustrations\Library\ReferenceImage;
use SprintIllustrations\Library\ReferenceSource;
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
		$tmp = is_string( $file['tmp_name'] ?? null ) ? $file['tmp_name'] : '';
		if ( UPLOAD_ERR_OK !== (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) || '' === $tmp || ! is_uploaded_file( $tmp ) ) {
			return new \WP_Error( 'sprint_illustrations_reference', __( 'The image didn’t upload. Try again with a PNG, JPEG or WebP up to 5 MB.', 'sprint-illustrations' ) );
		}

		return $this->store_file( $tmp, sanitize_file_name( (string) ( $file['name'] ?? '' ) ) );
	}

	/**
	 * Store a pasted screenshot or downloaded image from its bytes.
	 *
	 * @param string $bytes Image bytes.
	 * @return string|\WP_Error Stored name.
	 */
	public function store_bytes( string $bytes ): string|\WP_Error {
		if ( '' === $bytes || strlen( $bytes ) > ReferenceImage::MAX_BYTES ) {
			return new \WP_Error( 'sprint_illustrations_reference', __( 'The image must be 5 MB or smaller.', 'sprint-illustrations' ) );
		}

		// wp_tempnam() is an admin helper that REST requests don't load.
		require_once ABSPATH . 'wp-admin/includes/file.php';

		$tmp = wp_tempnam( 'si-reference' );
		if ( '' === $tmp || false === file_put_contents( $tmp, $bytes ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- A temporary file inside the system temp dir.
			return new \WP_Error( 'sprint_illustrations_reference', __( 'The image couldn’t be saved.', 'sprint-illustrations' ) );
		}

		$size   = getimagesize( $tmp );
		$result = $this->store_file( $tmp, 'pasted.' . ReferenceSource::extension( (string) ( $size['mime'] ?? '' ) ) );
		wp_delete_file( $tmp );

		return $result;
	}

	/**
	 * Download an image address (a plain public http(s) address) and store it.
	 *
	 * @param string $text Pasted address or inline image.
	 * @return string|\WP_Error Stored name.
	 */
	public function store_pasted_text( string $text ): string|\WP_Error {
		$data = ReferenceSource::data( $text );
		if ( null !== $data ) {
			return $this->store_bytes( $data );
		}

		$url = ReferenceSource::url( $text );
		if ( null === $url ) {
			return new \WP_Error( 'sprint_illustrations_reference', __( 'That doesn’t look like an image address. Paste a link that starts with https://.', 'sprint-illustrations' ) );
		}

		// Blocks addresses that point at this server or a private network; redirects are checked too.
		$response = wp_safe_remote_get(
			$url,
			[
				'timeout'             => 10,
				'redirection'         => 3,
				'limit_response_size' => ReferenceImage::MAX_BYTES + 1,
				'headers'             => [ 'Accept' => 'image/png,image/jpeg,image/webp,*/*;q=0.5' ],
			]
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return new \WP_Error( 'sprint_illustrations_reference', __( 'That image address couldn’t be downloaded. Save the image and upload it, or paste a screenshot instead.', 'sprint-illustrations' ) );
		}

		return $this->store_bytes( (string) wp_remote_retrieve_body( $response ) );
	}

	/**
	 * Validate, re-encode and save an image file (shared by uploads, pasted screenshots and addresses).
	 *
	 * @param string $tmp      File to read.
	 * @param string $filename Name used only to help detect the type.
	 * @return string|\WP_Error Stored name.
	 */
	private function store_file( string $tmp, string $filename ): string|\WP_Error {
		$check = wp_check_filetype_and_ext( $tmp, $filename );
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

		// Phone photos: apply the EXIF rotation before the metadata is dropped.
		if ( method_exists( $editor, 'maybe_exif_rotate' ) ) {
			$editor->maybe_exif_rotate();
		}
		$dims      = $editor->get_size();
		[ $w, $h ] = ReferenceImage::fit( (int) $dims['width'], (int) $dims['height'] );
		if ( $w < (int) $dims['width'] && is_wp_error( $editor->resize( $w, $h, false ) ) ) {
			return new \WP_Error( 'sprint_illustrations_reference', __( 'This image couldn’t be resized. Try a smaller PNG or JPEG.', 'sprint-illustrations' ) );
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
	 * Whether a stored name is well-formed and the file exists.
	 *
	 * @param string $name Stored name.
	 * @return bool
	 */
	public function exists( string $name ): bool {
		$path = $this->path( $name );

		return '' !== $path && is_file( $path );
	}

	/**
	 * Delete pasted images that no request ever used (at most once an hour; only files over two days old).
	 */
	public function prune(): void {
		if ( get_transient( 'si_reference_prune' ) ) {
			return;
		}
		set_transient( 'si_reference_prune', 1, HOUR_IN_SECONDS );

		$used = [];
		foreach ( get_posts(
			[
				'post_type'      => PieceRequestPostType::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			]
		) as $id ) {
			$used[ (string) get_post_meta( (int) $id, PieceRequestPostType::META_REFERENCE, true ) ] = true;
		}

		foreach ( (array) glob( $this->dir() . '/ref-*' ) as $file ) {
			$name = basename( (string) $file );
			if ( ! isset( $used[ $name ] ) && ReferenceImage::is_name( $name ) && (int) filemtime( (string) $file ) < time() - 2 * DAY_IN_SECONDS ) {
				wp_delete_file( (string) $file );
			}
		}
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
