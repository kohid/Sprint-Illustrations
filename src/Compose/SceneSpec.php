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
	public const MAX_ITEMS    = 30;
	public const MAX_LAYERS   = 64;
	public const CANVAS_MIN   = 200;
	public const CANVAS_MAX   = 4000;

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
	 * @param array{0: int, 1: int}|null          $canvas     Canvas size; null = the template's.
	 * @param array<int, array<string, mixed>>    $items      Pieces placed freely: key, piece, x, y, w, flip (canvas units).
	 * @param array<int, string>                  $layers     Layer order back to front: slot names and "item:<key>".
	 * @param array<string, array<string, mixed>> $animations Layer key => entrance/loop/delay/speed (see Animation).
	 * @param array<string, array<string, mixed>> $paints     Layer key => slot => colour or gradient (see Paint).
	 */
	private function __construct(
		public readonly ?string $template,
		public readonly int $seed,
		public readonly string|array $palette,
		public readonly array $keywords,
		public readonly array $picks,
		public readonly ?string $title,
		public readonly bool $decorative,
		public readonly ?array $canvas = null,
		public readonly array $items = [],
		public readonly array $layers = [],
		public readonly array $animations = [],
		public readonly array $paints = [],
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
			filter_var( $data['decorative'] ?? false, FILTER_VALIDATE_BOOLEAN ),
			self::normalize_canvas( $data['canvas'] ?? null ),
			self::normalize_items( $data['items'] ?? [] ),
			self::normalize_layers( $data['layers'] ?? [] ),
			Animation::normalize( $data['animations'] ?? [] ),
			Paint::normalize( $data['paints'] ?? [] )
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

		// The phase 8 edits appear only when used, so older specs (and their cache keys) are unchanged.
		$array = [
			'animations' => $this->animations,
			'canvas'     => $this->canvas,
			'decorative' => $this->decorative,
			'items'      => $this->items,
			'keywords'   => $this->keywords,
			'layers'     => $this->layers,
			'paints'     => $this->paints,
			'palette'    => $this->palette,
			'picks'      => $picks,
			'seed'       => $this->seed,
			'template'   => $this->template,
			'title'      => $this->title,
		];
		foreach ( [ 'animations', 'canvas', 'items', 'layers', 'paints' ] as $key ) {
			if ( null === $array[ $key ] || [] === $array[ $key ] ) {
				unset( $array[ $key ] );
			}
		}

		return $array;
	}

	/**
	 * Normalize the canvas size: two numbers, each clamped to CANVAS_MIN..CANVAS_MAX.
	 *
	 * @param mixed $canvas Candidate.
	 * @return array{0: int, 1: int}|null
	 */
	private static function normalize_canvas( mixed $canvas ): ?array {
		if ( ! is_array( $canvas ) || 2 !== count( $canvas ) ) {
			return null;
		}

		$size = [];
		foreach ( array_values( $canvas ) as $value ) {
			if ( ! is_numeric( $value ) ) {
				return null;
			}
			$size[] = (int) max( self::CANVAS_MIN, min( self::CANVAS_MAX, round( (float) $value ) ) );
		}

		return [ $size[0], $size[1] ];
	}

	/**
	 * Normalize freely placed pieces. Keys (0–99) stay stable so an item's look never depends on the others.
	 *
	 * @param mixed $items Candidate.
	 * @return array<int, array{key: int, piece: string, x: float, y: float, w: float, flip: bool}>
	 */
	private static function normalize_items( mixed $items ): array {
		if ( ! is_array( $items ) ) {
			return [];
		}

		$number = static fn( mixed $value, float $min, float $max ): float => round( max( $min, min( $max, is_numeric( $value ) ? (float) $value : 0.0 ) ), 2 );
		$clean  = [];
		$taken  = [];
		foreach ( $items as $item ) {
			if ( self::MAX_ITEMS <= count( $clean ) ) {
				break;
			}
			if ( ! is_array( $item ) || ! isset( $item['piece'] ) || ! is_string( $item['piece'] ) || ! preg_match( '/^[a-z0-9][a-z0-9-]*$/D', $item['piece'] ) ) {
				continue;
			}

			$key = isset( $item['key'] ) && is_numeric( $item['key'] ) ? (int) $item['key'] : -1;
			if ( $key < 0 || $key > 99 || isset( $taken[ $key ] ) ) {
				$key = 0;
				while ( isset( $taken[ $key ] ) ) {
					++$key;
				}
			}
			$taken[ $key ] = true;

			$clean[] = [
				'key'   => $key,
				'piece' => $item['piece'],
				'x'     => $number( $item['x'] ?? 0, -8000, 8000 ),
				'y'     => $number( $item['y'] ?? 0, -8000, 8000 ),
				'w'     => $number( $item['w'] ?? 0, 1, 8000 ),
				'flip'  => filter_var( $item['flip'] ?? false, FILTER_VALIDATE_BOOLEAN ),
			];
		}//end foreach

		return $clean;
	}

	/**
	 * Normalize the layer order: slot names and "item:<key>", no duplicates.
	 *
	 * @param mixed $layers Candidate.
	 * @return array<int, string>
	 */
	private static function normalize_layers( mixed $layers ): array {
		if ( ! is_array( $layers ) ) {
			return [];
		}

		$clean = array_filter( $layers, static fn( mixed $key ): bool => is_string( $key ) && (bool) preg_match( '/^(?:[a-z][a-z0-9_-]*|item:(?:0|[1-9][0-9]?))$/D', $key ) );

		return array_slice( array_values( array_unique( $clean ) ), 0, self::MAX_LAYERS );
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
