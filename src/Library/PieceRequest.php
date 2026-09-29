<?php
/**
 * Rules for piece requests made on the Library page.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Library;

/**
 * A known category and a 3–300 character plain-text description.
 */
final class PieceRequest {

	public const STATES = [ 'queued', 'drawing', 'review', 'done', 'discarded', 'declined' ];

	/**
	 * Allowed moves: from => [to, …].
	 */
	private const MOVES = [
		'queued'    => [ 'drawing', 'review', 'declined' ],
		'drawing'   => [ 'review', 'declined', 'queued' ],
		'review'    => [ 'done', 'discarded' ],
		'discarded' => [ 'queued' ],
		'declined'  => [ 'queued' ],
		'done'      => [],
	];

	/**
	 * Where a finished draft is shown: template and slot per category.
	 */
	private const SAMPLES = [
		'characters'  => [ 'hero-left-character', 'subject' ],
		'objects'     => [ 'centered-object-with-decor', 'hero' ],
		'backgrounds' => [ 'centered-object-with-decor', 'bg' ],
		'decor'       => [ 'centered-object-with-decor', 'decor' ],
	];

	/**
	 * Whether a request may move between two states.
	 *
	 * @param string $from Current state.
	 * @param string $to   Target state.
	 * @return bool
	 */
	public static function can_move( string $from, string $to ): bool {
		return in_array( $to, self::MOVES[ $from ] ?? [], true );
	}

	/**
	 * Sample scene for reviewing a draft piece.
	 *
	 * @param string $category Piece category.
	 * @param string $piece_id Draft piece ID.
	 * @return array{template: string, picks: array<string, string|array<string>>}
	 */
	public static function sample( string $category, string $piece_id ): array {
		[ $template, $slot ] = self::SAMPLES[ $category ] ?? self::SAMPLES['objects'];

		return [
			'template' => $template,
			'picks'    => [ $slot => 'decor' === $category ? [ $piece_id ] : $piece_id ],
		];
	}

	public const MIN_LENGTH = 3;

	public const MAX_LENGTH = 300;

	/**
	 * Error message, or '' when the request is valid.
	 *
	 * @param string $category    Piece category.
	 * @param string $description What to draw.
	 * @return string
	 */
	public static function validate( string $category, string $description ): string {
		if ( ! in_array( $category, Piece::CATEGORIES, true ) ) {
			return sprintf( 'Choose a category: %s.', implode( ', ', Piece::CATEGORIES ) );
		}

		$length = mb_strlen( self::clean( $description ) );
		if ( $length < self::MIN_LENGTH ) {
			return sprintf( 'Describe the piece in at least %d characters.', self::MIN_LENGTH );
		}
		if ( $length > self::MAX_LENGTH ) {
			return sprintf( 'Keep the description to %d characters or fewer.', self::MAX_LENGTH );
		}

		return '';
	}

	/**
	 * Plain, single-line text.
	 *
	 * @param string $description Raw description.
	 * @return string
	 */
	public static function clean( string $description ): string {
		return trim( (string) preg_replace( '/\s+/u', ' ', strip_tags( $description ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Pure namespace; no WordPress functions.
	}
}
