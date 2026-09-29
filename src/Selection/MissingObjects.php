<?php
/**
 * Words in a description that the library has nothing for.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Selection;

/**
 * A starting list of objects worth drawing: the description's words that match no tag and have no
 * synonyms. It is only a guess (the user edits it), so it favours few, plain nouns over completeness.
 */
final class MissingObjects {

	public const MAX = 6;

	/**
	 * Words that are never an object to draw.
	 */
	private const FILLER = [
		'about',
		'after',
		'all',
		'also',
		'and',
		'any',
		'around',
		'before',
		'between',
		'but',
		'each',
		'every',
		'have',
		'having',
		'here',
		'into',
		'just',
		'like',
		'make',
		'made',
		'more',
		'most',
		'not',
		'page',
		'post',
		'section',
		'show',
		'showing',
		'shows',
		'site',
		'some',
		'such',
		'than',
		'them',
		'then',
		'their',
		'there',
		'these',
		'they',
		'thing',
		'things',
		'those',
		'using',
		'very',
		'was',
		'were',
		'when',
		'where',
		'which',
		'while',
		'who',
		'would',
		'website',
	];

	/**
	 * Candidate objects, in the order they appear.
	 *
	 * @param string        $content    Description.
	 * @param Keywords      $keywords   Tokenizer with synonyms.
	 * @param array<string> $known_tags Every piece and template tag.
	 * @return array<string>
	 */
	public static function find( string $content, Keywords $keywords, array $known_tags ): array {
		$known = array_flip( $known_tags );
		$found = [];

		foreach ( $keywords->tokenize( $content ) as $token ) {
			if ( strlen( $token ) < 3 || isset( $known[ $token ] ) || in_array( $token, self::FILLER, true ) || ctype_digit( $token ) ) {
				continue;
			}
			// A word with synonyms is one the library already understands.
			if ( [ $token ] !== $keywords->expand( [ $token ] ) ) {
				continue;
			}

			$found[] = $token;
			if ( self::MAX <= count( $found ) ) {
				break;
			}
		}

		return $found;
	}
}
