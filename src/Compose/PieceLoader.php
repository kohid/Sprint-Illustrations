<?php
/**
 * Loads, sanitizes and memoizes piece SVGs.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Compose;

use SprintIllustrations\Library\Piece;
use SprintIllustrations\Security\SanitizationException;
use SprintIllustrations\Security\Sanitizer;
use SprintIllustrations\Svg\SvgDom;

/**
 * Per-request piece cache.
 */
final class PieceLoader {

	/**
	 * Parsed documents keyed by piece ID.
	 *
	 * @var array<string, \DOMDocument>
	 */
	private array $cache = [];

	/**
	 * Constructor.
	 *
	 * @param Sanitizer $sanitizer Sanitizer.
	 */
	public function __construct( private Sanitizer $sanitizer ) {}

	/**
	 * Parsed, sanitized document for a piece. Do not mutate the returned document; import nodes instead.
	 *
	 * @param Piece $piece Piece.
	 * @return \DOMDocument
	 * @throws CompositionException When the file is missing or invalid.
	 */
	public function load( Piece $piece ): \DOMDocument {
		if ( isset( $this->cache[ $piece->id ] ) ) {
			return $this->cache[ $piece->id ];
		}

		if ( ! is_readable( $piece->path ) ) {
			throw new CompositionException( sprintf( 'Piece file for "%s" is missing.', $piece->id ) );
		}

		try {
			$doc = SvgDom::parse( $this->sanitizer->sanitize( (string) file_get_contents( $piece->path ) ) );
		} catch ( SanitizationException $e ) {
			throw new CompositionException( sprintf( 'Piece "%s" is not a valid SVG.', $piece->id ), 0, $e );
		}

		$this->cache[ $piece->id ] = $doc;

		return $doc;
	}
}
