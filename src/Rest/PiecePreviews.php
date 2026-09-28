<?php
/**
 * Cached piece thumbnails for the Builder.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Rest;

use SprintIllustrations\Compose\PieceLoader;
use SprintIllustrations\Compose\PiecePreview;
use SprintIllustrations\Library\Piece;
use SprintIllustrations\Palette\Palette;
use SprintIllustrations\Security\Sanitizer;

/**
 * Transient-backed PiecePreview; keyed by piece hash + palette hash, so it invalidates itself.
 */
final class PiecePreviews {

	/**
	 * Constructor.
	 *
	 * @param PieceLoader $loader    Loader.
	 * @param Sanitizer   $sanitizer Sanitizer.
	 */
	public function __construct( private PieceLoader $loader, private Sanitizer $sanitizer ) {}

	/**
	 * Preview markup.
	 *
	 * @param Piece   $piece   Piece.
	 * @param Palette $palette Palette.
	 * @return string
	 */
	public function svg( Piece $piece, Palette $palette ): string {
		$key    = 'si_piece_preview_' . substr( sha1( $piece->id . '|' . $piece->hash . '|' . $palette->hash() ), 0, 32 );
		$cached = get_transient( $key );

		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}

		$markup = PiecePreview::render( $piece, $palette, $this->loader, $this->sanitizer );
		set_transient( $key, $markup, 30 * DAY_IN_SECONDS );

		return $markup;
	}
}
