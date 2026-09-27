<?php
/**
 * Brand palette value object.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Palette;

/**
 * Named colour slots plus skin and hair tone lists. Resolves "-light" / "-dark" variants.
 */
final class Palette {

	/**
	 * Single-colour slot names.
	 */
	public const SLOTS = [ 'primary', 'secondary', 'accent', 'neutral', 'background', 'outline' ];

	/**
	 * Slot names that resolve from a list, chosen per character instance.
	 */
	public const LIST_SLOTS = [ 'skin', 'hair' ];

	/**
	 * Lightness shift for derived variants.
	 */
	public const VARIANT_DELTA = 0.18;

	/**
	 * Constructor. Use from_array() or default() instead.
	 *
	 * @param array<string, string> $colors Slot (or "slot-variant") => hex.
	 * @param array<string>         $skin   Skin tone hex values.
	 * @param array<string>         $hair   Hair colour hex values.
	 */
	private function __construct(
		private array $colors,
		private array $skin,
		private array $hair,
	) {}

	/**
	 * The built-in "Sprint" palette.
	 *
	 * @return self
	 */
	public static function default(): self {
		return new self(
			[
				'primary'    => '#5b5bd6',
				'secondary'  => '#ffb224',
				'accent'     => '#ff6b6b',
				'neutral'    => '#2b2d42',
				'background' => '#eef0ff',
				'outline'    => '#2b2d42',
			],
			[ '#f5c9a8', '#d9a07a', '#a86b4c', '#6b4331' ],
			[ '#2b2d42', '#6b4331', '#c9803e', '#1a1a1a' ]
		);
	}

	/**
	 * Build from an array. Missing or invalid slots fall back to the default palette.
	 *
	 * Accepts slot keys (see SLOTS), explicit variants ("primary-dark"), and "skin" / "hair" lists.
	 *
	 * @param array<string, mixed> $data Palette data.
	 * @return self
	 */
	public static function from_array( array $data ): self {
		$default = self::default();
		$colors  = $default->colors;

		foreach ( $data as $key => $value ) {
			if ( ! is_string( $key ) || ! is_string( $value ) || ! preg_match( '/^(' . implode( '|', self::SLOTS ) . ')(-(light|dark))?$/', $key ) ) {
				continue;
			}
			$hex = Color::normalize_hex( $value );
			if ( null !== $hex ) {
				$colors[ $key ] = $hex;
			}
		}

		return new self(
			$colors,
			self::hex_list( $data['skin'] ?? null ) ?? $default->skin,
			self::hex_list( $data['hair'] ?? null ) ?? $default->hair
		);
	}

	/**
	 * Resolve a slot to a hex colour.
	 *
	 * @param string $name       Slot name, e.g. "primary" or "skin".
	 * @param string $variant    "", "light" or "dark".
	 * @param int    $skin_index Skin tone index (wraps).
	 * @param int    $hair_index Hair colour index (wraps).
	 * @return string|null Null for unknown slots.
	 */
	public function resolve( string $name, string $variant = '', int $skin_index = 0, int $hair_index = 0 ): ?string {
		if ( '' !== $variant && isset( $this->colors[ $name . '-' . $variant ] ) ) {
			return $this->colors[ $name . '-' . $variant ];
		}

		$base = match ( $name ) {
			'skin'  => $this->skin[ abs( $skin_index ) % count( $this->skin ) ],
			'hair'  => $this->hair[ abs( $hair_index ) % count( $this->hair ) ],
			default => $this->colors[ $name ] ?? null,
		};

		if ( null === $base || '' === $variant ) {
			return $base;
		}

		return Color::adjust_lightness( $base, 'light' === $variant ? self::VARIANT_DELTA : -self::VARIANT_DELTA );
	}

	/**
	 * Export as array (round-trips through from_array()).
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return $this->colors + [
			'skin' => $this->skin,
			'hair' => $this->hair,
		];
	}

	/**
	 * Stable hash for cache keys.
	 *
	 * @return string
	 */
	public function hash(): string {
		$data = $this->to_array();
		ksort( $data );

		return md5( (string) json_encode( $data ) );
	}

	/**
	 * Validate a list of hex colours.
	 *
	 * @param mixed $value Candidate list.
	 * @return array<string>|null Null when empty or invalid.
	 */
	private static function hex_list( mixed $value ): ?array {
		if ( ! is_array( $value ) ) {
			return null;
		}

		$list = array_values( array_filter( array_map( static fn( $hex ) => is_string( $hex ) ? Color::normalize_hex( $hex ) : null, $value ) ) );

		return $list ? $list : null;
	}
}
