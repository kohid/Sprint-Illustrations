<?php
/**
 * Deterministic PRNG (mulberry32), identical to the common JavaScript implementation.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Compose;

/**
 * Seeded random stream. Same seed → same sequence on every platform.
 */
final class Seed {

	/**
	 * Unsigned 32-bit state.
	 *
	 * @var int
	 */
	private int $state;

	/**
	 * Constructor.
	 *
	 * @param int $seed Any integer; reduced to 32 bits.
	 */
	public function __construct( int $seed ) {
		$this->state = $seed & 0xFFFFFFFF;
	}

	/**
	 * Seed from a string key (crc32).
	 *
	 * @param string $key Key, e.g. "42|hero-left-character|subject".
	 * @return self
	 */
	public static function from_string( string $key ): self {
		return new self( crc32( $key ) );
	}

	/**
	 * Next float in [0, 1).
	 *
	 * @return float
	 */
	public function next(): float {
		$this->state = ( $this->state + 0x6D2B79F5 ) & 0xFFFFFFFF;

		$t = self::imul( $this->state ^ ( $this->state >> 15 ), 1 | $this->state );
		$t = ( ( $t + self::imul( $t ^ ( $t >> 7 ), 61 | $t ) ) & 0xFFFFFFFF ) ^ $t;

		return ( ( $t ^ ( $t >> 14 ) ) & 0xFFFFFFFF ) / 4294967296;
	}

	/**
	 * Integer in [$min, $max] inclusive. Always consumes exactly one draw.
	 *
	 * @param int $min Minimum.
	 * @param int $max Maximum.
	 * @return int
	 */
	public function int( int $min, int $max ): int {
		return $min + (int) floor( $this->next() * ( $max - $min + 1 ) );
	}

	/**
	 * Float in [$min, $max). Always consumes exactly one draw.
	 *
	 * @param float $min Minimum.
	 * @param float $max Maximum.
	 * @return float
	 */
	public function float( float $min, float $max ): float {
		return $min + $this->next() * ( $max - $min );
	}

	/**
	 * Pick one item from a non-empty list. Consumes one draw.
	 *
	 * @template T
	 * @param array<T> $items Items.
	 * @return T
	 * @throws \InvalidArgumentException When $items is empty.
	 */
	public function pick( array $items ): mixed {
		if ( ! $items ) {
			throw new \InvalidArgumentException( 'Cannot pick from an empty list.' );
		}

		$items = array_values( $items );

		return $items[ $this->int( 0, count( $items ) - 1 ) ];
	}

	/**
	 * 32-bit integer multiply with wrap-around (Math.imul).
	 *
	 * @param int $a Operand.
	 * @param int $b Operand.
	 * @return int
	 */
	private static function imul( int $a, int $b ): int {
		$a &= 0xFFFFFFFF;
		$b &= 0xFFFFFFFF;

		$low  = ( $a & 0xFFFF ) * $b;
		$high = ( ( ( $a >> 16 ) & 0xFFFF ) * $b ) & 0xFFFF;

		return ( $low + ( $high << 16 ) ) & 0xFFFFFFFF;
	}
}
