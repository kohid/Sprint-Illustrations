<?php
/**
 * Rules for reference images attached to piece requests.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Library;

/**
 * PNG, JPEG or WebP up to 5 MB and 8000 px; stored re-encoded, at most 1600 px on the long side.
 */
final class ReferenceImage {

	public const MAX_BYTES = 5242880;
	public const MIN_EDGE  = 16;
	public const MAX_EDGE  = 8000;
	public const FIT_EDGE  = 1600;
	public const TYPES     = [ 'image/png', 'image/jpeg', 'image/webp' ];

	/**
	 * Why an upload can't be used, or '' when it can.
	 *
	 * @param int    $bytes File size.
	 * @param string $mime  Detected MIME type.
	 * @param int    $w     Width in px.
	 * @param int    $h     Height in px.
	 * @return string
	 */
	public static function validate( int $bytes, string $mime, int $w, int $h ): string {
		if ( ! in_array( $mime, self::TYPES, true ) ) {
			return 'Use a PNG, JPEG or WebP image.';
		}
		if ( $bytes <= 0 || $bytes > self::MAX_BYTES ) {
			return 'The image must be 5 MB or smaller.';
		}
		if ( min( $w, $h ) < self::MIN_EDGE || max( $w, $h ) > self::MAX_EDGE ) {
			return sprintf( 'The image must be between %1$d and %2$d pixels on each side.', self::MIN_EDGE, self::MAX_EDGE );
		}

		return '';
	}

	/**
	 * Stored size: the long side at most FIT_EDGE, never enlarged.
	 *
	 * @param int $w Width.
	 * @param int $h Height.
	 * @return array{0: int, 1: int}
	 */
	public static function fit( int $w, int $h ): array {
		$k = min( 1.0, self::FIT_EDGE / max( $w, $h ) );

		return [ max( 1, (int) round( $w * $k ) ), max( 1, (int) round( $h * $k ) ) ];
	}

	/**
	 * Stored file name.
	 *
	 * @param string $random 16 lower-case letters or digits.
	 * @param bool   $alpha  Whether the image keeps transparency (PNG) or not (JPEG).
	 * @return string
	 */
	public static function name( string $random, bool $alpha ): string {
		return 'ref-' . $random . ( $alpha ? '.png' : '.jpg' );
	}

	/**
	 * Whether a stored name is well-formed (checked before any file operation).
	 *
	 * @param string $name Name.
	 * @return bool
	 */
	public static function is_name( string $name ): bool {
		return (bool) preg_match( '/^ref-[a-z0-9]{16}\.(?:png|jpg)$/D', $name );
	}
}
