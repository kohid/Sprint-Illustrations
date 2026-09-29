<?php
/**
 * Where a pasted reference image comes from: an image address or an inline data URL.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Library;

/**
 * Pure checks made before anything is downloaded or decoded. The image itself is validated and
 * re-encoded afterwards, exactly like an upload.
 */
final class ReferenceSource {

	public const MAX_URL = 2000;

	/**
	 * A plain http(s) address, or null. Credentials in the address and other schemes are refused.
	 *
	 * @param string $text Pasted text.
	 * @return string|null
	 */
	public static function url( string $text ): ?string {
		$text = trim( $text );
		if ( '' === $text || strlen( $text ) > self::MAX_URL || preg_match( '/\s/', $text ) ) {
			return null;
		}

		$parts = parse_url( $text ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Pure namespace; no WordPress functions.
		if ( ! is_array( $parts ) || ! in_array( strtolower( (string) ( $parts['scheme'] ?? '' ) ), [ 'http', 'https' ], true ) || '' === (string) ( $parts['host'] ?? '' ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return null;
		}

		return $text;
	}

	/**
	 * The bytes of an inline "data:image/png;base64,…" image, or null when it isn't one or is too big.
	 *
	 * @param string $text Pasted text.
	 * @return string|null
	 */
	public static function data( string $text ): ?string {
		$text = trim( $text );
		if ( ! preg_match( '#^data:image/(?:png|jpeg|jpg|webp);base64,([A-Za-z0-9+/=\s]+)$#D', $text, $match ) ) {
			return null;
		}

		$bytes = base64_decode( (string) preg_replace( '/\s+/', '', $match[1] ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decodes an image the user pasted; it is validated and re-encoded before use.

		return false === $bytes || '' === $bytes || strlen( $bytes ) > ReferenceImage::MAX_BYTES ? null : $bytes;
	}

	/**
	 * File extension for a detected image type.
	 *
	 * @param string $mime MIME type.
	 * @return string
	 */
	public static function extension( string $mime ): string {
		return match ( $mime ) {
			'image/jpeg' => 'jpg',
			'image/webp' => 'webp',
			default      => 'png',
		};
	}
}
