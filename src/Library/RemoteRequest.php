<?php
/**
 * Checks for what a remote drawer sends to the piece request REST endpoints.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Library;

use SprintIllustrations\Storage\PieceDrafts;

/**
 * Pure: no WordPress calls. The real SVG checks (sanitizing, anchors, colour slots) stay in the
 * manifest build that PieceDrafts runs; this only rejects payloads that can't be a piece at all.
 */
final class RemoteRequest {

	public const MAX_SVG_BYTES = 500000;
	public const MAX_NOTE      = 1000;

	/**
	 * A draft payload: the piece's file name and its SVG source.
	 *
	 * @param mixed $name Piece name, with or without ".svg".
	 * @param mixed $svg  SVG markup.
	 * @return array{name: string, svg: string}|string The clean payload, or why it was refused.
	 */
	public static function draft( mixed $name, mixed $svg ): array|string {
		if ( ! is_string( $name ) || ! is_string( $svg ) ) {
			return 'Send the piece name and the SVG as text.';
		}

		$clean = PieceDrafts::name( $name );
		if ( '' === $clean ) {
			return 'The piece needs a name made of letters, numbers or dashes.';
		}

		$svg = trim( $svg );
		if ( '' === $svg || false === stripos( $svg, '<svg' ) ) {
			return 'That is not an SVG.';
		}
		if ( strlen( $svg ) > self::MAX_SVG_BYTES ) {
			return sprintf( 'The SVG is over %d KB.', (int) ( self::MAX_SVG_BYTES / 1000 ) );
		}

		return [
			'name' => $clean,
			'svg'  => $svg,
		];
	}

	/**
	 * A decline note, or null when it is empty or not text.
	 *
	 * @param mixed $note Note.
	 * @return string|null
	 */
	public static function note( mixed $note ): ?string {
		if ( ! is_string( $note ) ) {
			return null;
		}
		$note = trim( $note );

		return '' === $note ? null : mb_substr( $note, 0, self::MAX_NOTE );
	}
}
