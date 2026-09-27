<?php
/**
 * Tokenizes content and expands tokens with synonyms.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Selection;

/**
 * Simple, deterministic keyword handling.
 */
final class Keywords {

	private const STOPWORDS = [
		'a',
		'an',
		'and',
		'are',
		'as',
		'at',
		'be',
		'best',
		'by',
		'can',
		'for',
		'from',
		'get',
		'guide',
		'how',
		'in',
		'is',
		'it',
		'its',
		'my',
		'new',
		'of',
		'on',
		'or',
		'our',
		'the',
		'this',
		'that',
		'to',
		'top',
		'we',
		'what',
		'why',
		'will',
		'with',
		'you',
		'your',
	];

	/**
	 * Constructor.
	 *
	 * @param array<string, array<string>> $synonyms Token => related tokens.
	 */
	public function __construct( private array $synonyms = [] ) {}

	/**
	 * Load synonyms from a JSON file ({"team": ["people", "collaboration"], ...}). Missing file → none.
	 *
	 * @param string $path JSON path.
	 * @return self
	 */
	public static function from_file( string $path ): self {
		$data = is_readable( $path ) ? json_decode( (string) file_get_contents( $path ), true ) : null;

		$synonyms = [];
		foreach ( is_array( $data ) ? $data : [] as $token => $related ) {
			if ( is_string( $token ) && is_array( $related ) ) {
				$synonyms[ strtolower( $token ) ] = array_values( array_map( 'strtolower', array_filter( $related, 'is_string' ) ) );
			}
		}

		return new self( $synonyms );
	}

	/**
	 * Lower-case, strip markup and stopwords, crude singularization, de-duplicate.
	 *
	 * @param string $text Text.
	 * @return array<string>
	 */
	public function tokenize( string $text ): array {
		$words  = preg_split( '/[^a-z0-9]+/', strtolower( strip_tags( $text ) ), -1, PREG_SPLIT_NO_EMPTY );
		$tokens = [];

		foreach ( $words as $word ) {
			if ( strlen( $word ) < 2 || in_array( $word, self::STOPWORDS, true ) ) {
				continue;
			}
			$tokens[] = $this->singular( $word );
		}

		return array_values( array_unique( $tokens ) );
	}

	/**
	 * Add synonyms of each token.
	 *
	 * @param array<string> $tokens Tokens.
	 * @return array<string>
	 */
	public function expand( array $tokens ): array {
		$expanded = $tokens;

		foreach ( $tokens as $token ) {
			foreach ( $this->synonyms[ $token ] ?? [] as $related ) {
				$expanded[] = $related;
			}
		}

		return array_values( array_unique( $expanded ) );
	}

	/**
	 * Crude English singularization.
	 *
	 * @param string $word Word.
	 * @return string
	 */
	private function singular( string $word ): string {
		if ( strlen( $word ) > 4 && str_ends_with( $word, 'ies' ) ) {
			return substr( $word, 0, -3 ) . 'y';
		}

		if ( strlen( $word ) > 3 && str_ends_with( $word, 's' ) && ! str_ends_with( $word, 'ss' ) && ! str_ends_with( $word, 'us' ) ) {
			return substr( $word, 0, -1 );
		}

		return $word;
	}
}
