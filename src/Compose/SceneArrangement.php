<?php
/**
 * Where new pieces go when they are added to a scene automatically.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Compose;

/**
 * Lays pieces out in one row along the bottom of the canvas, evenly spaced and never overlapping,
 * as free items the Builder can then move.
 */
final class SceneArrangement {

	/**
	 * Row of free items.
	 *
	 * @param array<string, array{0: float, 1: float}> $sizes  Piece ID => natural [width, height].
	 * @param array{0: int, 1: int}                     $canvas Canvas size.
	 * @param array<int>                                $taken  Item keys already used.
	 * @return array<int, array{key: int, piece: string, x: float, y: float, w: float, flip: bool}>
	 */
	public static function row( array $sizes, array $canvas, array $taken = [] ): array {
		$sizes = array_filter( $sizes, static fn( array $size ): bool => $size[0] > 0 && $size[1] > 0 );
		$count = min( count( $sizes ), SceneSpec::MAX_ITEMS - count( $taken ) );
		if ( $count < 1 ) {
			return [];
		}

		[ $width, $height ] = $canvas;
		$slot_width         = $width / $count;
		$max_height         = $height * 0.4;
		$items              = [];
		$key                = 0;
		$index              = 0;

		foreach ( array_slice( $sizes, 0, $count, true ) as $piece => $size ) {
			while ( in_array( $key, $taken, true ) ) {
				++$key;
			}

			$scale   = min( ( $slot_width * 0.8 ) / $size[0], $max_height / $size[1] );
			$w       = round( $size[0] * $scale, 2 );
			$h       = $size[1] * $scale;
			$items[] = [
				'key'   => $key,
				'piece' => (string) $piece,
				'x'     => round( $slot_width * $index + ( $slot_width - $w ) / 2, 2 ),
				'y'     => round( $height - $h - $height * 0.05, 2 ),
				'w'     => $w,
				'flip'  => false,
			];
			++$key;
			++$index;
		}

		return $items;
	}
}
