<?php
/**
 * Cheap PNG sanity checks before WordPress handles an upload.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Media;

/**
 * Signature, IHDR dimensions and size limits.
 */
final class PngCheck {

	public const MAX_BYTES = 5242880;

	public const MAX_SIDE = 8000;

	private const SIGNATURE = "\x89PNG\r\n\x1a\n";

	private const IEND = "\0\0\0\0IEND\xAE\x42\x60\x82";

	/**
	 * What is wrong with the file, or null when it is acceptable.
	 *
	 * @param string      $head  At least the first 24 bytes.
	 * @param int         $bytes File size.
	 * @param string|null $tail  The last 12 bytes, when available.
	 * @return string|null
	 */
	public static function problem( string $head, int $bytes, ?string $tail = null ): ?string {
		if ( strlen( $head ) < 24 || ! str_starts_with( $head, self::SIGNATURE ) || 'IHDR' !== substr( $head, 12, 4 ) ) {
			return 'The file is not a PNG image.';
		}

		if ( $bytes > self::MAX_BYTES ) {
			return 'The PNG is larger than 5 MB.';
		}

		$size = unpack( 'Nwidth/Nheight', substr( $head, 16, 8 ) );
		if ( ! is_array( $size ) || $size['width'] < 1 || $size['height'] < 1 || $size['width'] > self::MAX_SIDE || $size['height'] > self::MAX_SIDE ) {
			return sprintf( 'The PNG dimensions must be between 1 and %d pixels.', self::MAX_SIDE );
		}

		if ( null !== $tail && self::IEND !== substr( $tail, -12 ) ) {
			return 'The PNG file is incomplete.';
		}

		return null;
	}
}
