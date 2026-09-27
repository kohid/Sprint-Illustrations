<?php
/**
 * The single input shape for composition.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Compose;

/**
 * Immutable, normalized scene request. from_array() never throws: invalid values fall back to defaults.
 */
final class SceneSpec {

	public const MAX_SEED     = 2147483647;
	public const MAX_KEYWORDS = 20;

	/**
	 * Constructor. Use from_array().
	 *
	 * @param string|null                         $template   Template ID, or null to auto-select.
	 * @param int                                 $seed       Seed 0..MAX_SEED.
	 * @param string|array<string, mixed>         $palette    "site", "default", "preset:<slug>", or inline palette.
	 * @param array<string>                       $keywords   Raw keywords (lower-case).
	 * @param array<string, string|array<string>> $picks      Slot name => piece ID (or list for multi slots).
	 * @param string|null                         $title      Accessible title override.
	 * @param bool                                $decorative Render aria-hidden with no title/desc.
	 */
	private function __construct(
		public readonly ?string $template,
		public readonly int $seed,
		public readonly string|array $palette,
		public readonly array $keywords,
		public readonly array $picks,
		public readonly ?string $title,
		public readonly bool $decorative,
	) {}

	/**
	 * Build a normalized spec from untrusted input (REST, shortcode, block attributes, AI output).
	 *
	 * @param array<string, mixed> $data Input.
	 * @return self
	 */
	public static function from_array( array $data ): self {
		$template = isset( $data['template'] ) && is_string( $data['template'] ) && preg_match( '/^[a-z0-9][a-z0-9-]*$/D', $data['template'] )
			? $data['template']
			: null;

		$seed = isset( $data['seed'] ) && is_numeric( $data['seed'] ) ? (int) $data['seed'] : 1;
		$seed = max( 0, min( self::MAX_SEED, abs( $seed ) ) );

		$title = isset( $data['title'] ) && is_scalar( $data['title'] )
			? trim( mb_substr( strip_tags( (string) $data['title'] ), 0, 200 ) )
			: '';

		return new self(
			$template,
			$seed,
			self::normalize_palette( $data['palette'] ?? 'default' ),
			self::normalize_keywords( $data['keywords'] ?? [] ),
			self::normalize_picks( $data['picks'] ?? [] ),
			'' === $title ? null : $title,
			filter_var( $data['decorative'] ?? false, FILTER_VALIDATE_BOOLEAN )
		);
	}

	/**
	 * Copy with a different template.
	 *
	 * @param string $template Template ID.
	 * @return self
	 */
	public function with_template( string $template ): self {
		return self::from_array( [ 'template' => $template ] + $this->to_array() );
	}

	/**
	 * Copy with different picks.
	 *
	 * @param array<string, string|array<string>> $picks Picks.
	 * @return self
	 */
	public function with_picks( array $picks ): self {
		return self::from_array( [ 'picks' => $picks ] + $this->to_array() );
	}

	/**
	 * Copy with a different seed.
	 *
	 * @param int $seed Seed.
	 * @return self
	 */
	public function with_seed( int $seed ): self {
		return self::from_array( [ 'seed' => $seed ] + $this->to_array() );
	}

	/**
	 * Export (keys sorted, round-trips through from_array()).
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		$picks = $this->picks;
		ksort( $picks );

		return [
			'decorative' => $this->decorative,
			'keywords'   => $this->keywords,
			'palette'    => $this->palette,
			'picks'      => $picks,
			'seed'       => $this->seed,
			'template'   => $this->template,
			'title'      => $this->title,
		];
	}

	/**
	 * Normalize the palette reference.
	 *
	 * @param mixed $palette Candidate.
	 * @return string|array<string, mixed>
	 */
	private static function normalize_palette( mixed $palette ): string|array {
		if ( is_array( $palette ) ) {
			return array_filter( $palette, static fn( $value, $key ) => is_string( $key ) && ( is_string( $value ) || is_array( $value ) ), ARRAY_FILTER_USE_BOTH );
		}

		if ( is_string( $palette ) && preg_match( '/^(site|default|preset:[a-z0-9-]+)$/D', $palette ) ) {
			return $palette;
		}

		return 'default';
	}

	/**
	 * Normalize keywords from a list or comma-separated string.
	 *
	 * @param mixed $keywords Candidate.
	 * @return array<string>
	 */
	private static function normalize_keywords( mixed $keywords ): array {
		if ( is_string( $keywords ) ) {
			$keywords = explode( ',', $keywords );
		}
		if ( ! is_array( $keywords ) ) {
			return [];
		}

		$clean = [];
		foreach ( $keywords as $keyword ) {
			if ( ! is_scalar( $keyword ) ) {
				continue;
			}
			$keyword = trim( preg_replace( '/[^a-z0-9 -]+/', '', strtolower( strip_tags( (string) $keyword ) ) ) );
			if ( '' !== $keyword ) {
				$clean[] = mb_substr( $keyword, 0, 60 );
			}
		}

		return array_slice( array_values( array_unique( $clean ) ), 0, self::MAX_KEYWORDS );
	}

	/**
	 * Normalize picks.
	 *
	 * @param mixed $picks Candidate.
	 * @return array<string, string|array<string>>
	 */
	private static function normalize_picks( mixed $picks ): array {
		if ( ! is_array( $picks ) ) {
			return [];
		}

		$clean = [];
		$is_id = static fn( $id ) => is_string( $id ) && preg_match( '/^[a-z0-9][a-z0-9-]*$/D', $id );
		foreach ( $picks as $slot => $value ) {
			if ( ! is_string( $slot ) || ! preg_match( '/^[a-z][a-z0-9_-]*$/D', $slot ) ) {
				continue;
			}
			if ( $is_id( $value ) ) {
				$clean[ $slot ] = $value;
			} elseif ( is_array( $value ) ) {
				$ids = array_values( array_filter( $value, $is_id ) );
				if ( $ids ) {
					$clean[ $slot ] = array_slice( $ids, 0, 12 );
				}
			}
		}

		return $clean;
	}
}
