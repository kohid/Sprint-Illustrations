<?php
/**
 * Filtering for the Library browser.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Library;

/**
 * Category plus search: every search word must appear in the label or a tag (case-insensitive).
 */
final class LibraryFilter {

	/**
	 * Pieces in a category matching a search.
	 *
	 * @param array<Piece> $pieces   Pieces.
	 * @param string       $category Category.
	 * @param string       $search   Search text.
	 * @return array<Piece>
	 */
	public static function pieces( array $pieces, string $category, string $search ): array {
		return array_values(
			array_filter(
				$pieces,
				static fn( Piece $piece ): bool => $piece->category === $category && self::matches( $piece->label, $piece->tags, $search )
			)
		);
	}

	/**
	 * Templates matching a search.
	 *
	 * @param array<Template> $templates Templates.
	 * @param string          $search    Search text.
	 * @return array<Template>
	 */
	public static function templates( array $templates, string $search ): array {
		return array_values( array_filter( $templates, static fn( Template $template ): bool => self::matches( $template->label, $template->tags, $search ) ) );
	}

	/**
	 * Whether every word of the search appears in the label or tags.
	 *
	 * @param string        $label  Label.
	 * @param array<string> $tags   Tags.
	 * @param string        $search Search.
	 * @return bool
	 */
	private static function matches( string $label, array $tags, string $search ): bool {
		$haystack = strtolower( $label . ' ' . implode( ' ', $tags ) );

		foreach ( preg_split( '/\s+/', strtolower( trim( $search ) ), -1, PREG_SPLIT_NO_EMPTY ) as $word ) {
			if ( ! str_contains( $haystack, $word ) ) {
				return false;
			}
		}

		return true;
	}
}
