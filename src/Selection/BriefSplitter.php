<?php
/**
 * Splits a long, numbered brief into one short piece request per layer.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Selection;

use SprintIllustrations\Library\PieceRequest;

/**
 * "(1) a tablet …, (2) some pins …" becomes one request per numbered layer, each followed by as
 * many of the brief's shared style notes (palette, line work, background) as fit in the 300
 * character limit. Notes about animation, canvas size and composition are left out because they
 * are not part of a piece, and hex codes are dropped because pieces only use palette colours.
 */
final class BriefSplitter {

	/**
	 * Sentences that describe the whole scene rather than a piece.
	 */
	private const NOT_A_PIECE = '/animation|aspect|composition|hero|landscape|portrait|canvas|pixel|\d+\s*x\s*\d+|slides? in|fades? in|zooms? in|drops? in/i';

	/**
	 * Split a brief.
	 *
	 * @param string $brief Brief.
	 * @return array{layers: array<string>, style: string, skipped: array<string>}
	 */
	public static function split( string $brief ): array {
		$brief = trim( str_replace( "\r", '', $brief ) );
		$found = self::markers( $brief );
		if ( count( $found ) < 2 ) {
			return [
				'layers'  => [],
				'style'   => '',
				'skipped' => [],
			];
		}

		$intro  = trim( substr( $brief, 0, $found[0]['start'] ) );
		$chunks = [];
		foreach ( $found as $index => $marker ) {
			$end      = $found[ $index + 1 ]['start'] ?? strlen( $brief );
			$chunks[] = trim( substr( $brief, $marker['end'], $end - $marker['end'] ) );
		}

		// The last layer runs on into the brief's closing notes: they start at its first full stop.
		$tail     = '';
		$last     = array_pop( $chunks );
		$cut      = preg_split( '/(?<=[.!?])[ \t]+(?=[A-Z])|\n+/', (string) $last, 2 );
		$chunks[] = (string) $cut[0];
		$tail     = (string) ( $cut[1] ?? '' );

		$layers  = [];
		$skipped = [];
		$shadow  = false;
		foreach ( $chunks as $chunk ) {
			$text = self::layer( $chunk );
			if ( '' === $text ) {
				continue;
			}
			if ( preg_match( '/\bshadows?\b/i', $text ) ) {
				$shadow    = true;
				$skipped[] = $text;
				continue;
			}
			$layers[] = $text;
		}

		$style   = self::style( $intro, $tail, $shadow );
		$results = [];
		foreach ( array_slice( $layers, 0, 12 ) as $layer ) {
			$results[] = self::request( $layer, $style );
		}

		return [
			'layers'  => $results,
			'style'   => implode( ' ', $style ),
			'skipped' => $skipped,
		];
	}

	/**
	 * Layer markers "(1)" or "1." / "1)" at the start of a line, kept while they count 1, 2, 3 …
	 *
	 * @param string $brief Brief.
	 * @return array<int, array{start: int, end: int}>
	 */
	private static function markers( string $brief ): array {
		preg_match_all( '/\(\s*(\d{1,2})\s*\)\s*|(?:^|\n)[ \t]*(\d{1,2})[.)][ \t]+/', $brief, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );

		$found = [];
		foreach ( $matches as $match ) {
			$number = (int) ( '' !== $match[1][0] ? $match[1][0] : ( $match[2][0] ?? 0 ) );
			if ( count( $found ) + 1 !== $number ) {
				continue;
			}
			$found[] = [
				'start' => (int) $match[0][1],
				'end'   => (int) $match[0][1] + strlen( $match[0][0] ),
			];
		}

		return $found;
	}

	/**
	 * One layer's wording: tidy ends, capital first letter, a full stop.
	 *
	 * @param string $chunk Text after the marker.
	 * @return string
	 */
	private static function layer( string $chunk ): string {
		$text = trim( (string) preg_replace( '/\s+/', ' ', $chunk ) );
		$text = trim( (string) preg_replace( '/\s+(?:and|or)\s*$/i', '', rtrim( $text, " \t,;." ) ) );
		$text = rtrim( $text, " \t,;." );
		if ( '' === $text ) {
			return '';
		}

		return ucfirst( $text ) . '.';
	}

	/**
	 * Shared style sentences in priority order.
	 *
	 * @param string $intro  Text before the first layer.
	 * @param string $tail   Text after the last layer.
	 * @param bool   $shadow A shadow layer was folded into the style.
	 * @return array<string>
	 */
	private static function style( string $intro, string $tail, bool $shadow ): array {
		$style = [];

		$words = [];
		foreach ( [ 'isometric', 'flat', 'minimal', 'geometric' ] as $word ) {
			if ( preg_match( '/\b' . $word . '\b/i', $intro ) ) {
				$words[] = $word;
			}
		}
		if ( $words ) {
			$style[] = 'Style: ' . implode( ' ', $words ) . '.';
		}

		foreach ( (array) preg_split( '/(?<=[.!?])\s+(?=[A-Z])/', $tail ) as $sentence ) {
			$sentence = trim( (string) preg_replace( '/\s+/', ' ', (string) $sentence ) );
			$sentence = trim( (string) preg_replace( '/\s+([,.;:])/', '$1', (string) preg_replace( '/\s*\(?#[0-9a-f]{3,8}\)?/i', '', $sentence ) ) );
			if ( '' === $sentence || preg_match( self::NOT_A_PIECE, $sentence ) ) {
				continue;
			}
			$style[] = rtrim( $sentence, '.' ) . '.';
		}

		if ( $shadow ) {
			$style[] = 'Soft shadow underneath.';
		}

		return $style;
	}

	/**
	 * A layer plus as many style sentences as fit in a request, in priority order.
	 *
	 * @param string        $layer Layer wording.
	 * @param array<string> $style Style sentences.
	 * @return string
	 */
	private static function request( string $layer, array $style ): string {
		$text = $layer;
		foreach ( $style as $sentence ) {
			if ( mb_strlen( $text . ' ' . $sentence ) <= PieceRequest::MAX_LENGTH ) {
				$text .= ' ' . $sentence;
			}
		}

		if ( mb_strlen( $text ) > PieceRequest::MAX_LENGTH ) {
			$text = rtrim( mb_substr( $text, 0, PieceRequest::MAX_LENGTH - 1 ), " \t,;" );
			$text = (string) preg_replace( '/\s+\S*$/u', '', $text ) . '…';
		}

		return $text;
	}
}
