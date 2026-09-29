<?php
/**
 * Turn a resolved scene (what is on the Builder canvas) into template JSON.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Compose;

use SprintIllustrations\Library\TemplateSlot;

/**
 * Every layer becomes a slot at its current box, in the same back-to-front order. Pieces held by
 * another piece stay attached; scattered decor becomes a scatter area. Slots prefer the tags of the
 * piece they held, so Shuffle and keywords give variations of the same layout.
 */
final class TemplateFromScene {

	private const MAX_TAGS = 8;

	/**
	 * Template data (the shape of assets/templates/*.json).
	 *
	 * @param string        $id    Template ID ([a-z0-9-]).
	 * @param string        $label Label.
	 * @param ResolvedScene $scene Scene.
	 * @return array<string, mixed>
	 */
	public static function build( string $id, string $label, ResolvedScene $scene ): array {
		$by_name = [];
		foreach ( $scene->template->slots as $slot ) {
			$by_name[ $slot->name ] = $slot;
		}

		$groups = [];
		foreach ( $scene->placements as $placement ) {
			$groups[ $scene->roots[ $placement->slot ] ?? $placement->slot ][ $placement->slot ][] = $placement;
		}

		$slots = [];
		$tags  = [];
		$z     = 0;
		foreach ( $scene->layers as $key ) {
			$z    += 10;
			$group = $groups[ $key ] ?? [];
			// The layer's own slot first: attached slots need their parent resolved before them.
			uksort( $group, static fn( $a, $b ): int => ( $b === $key ) <=> ( $a === $key ) );
			foreach ( $group as $slot_name => $placements ) {
				$slot_name = (string) $slot_name;
				$source    = $by_name[ $slot_name ] ?? null;
				$name      = str_starts_with( $slot_name, 'item:' ) ? 'piece-' . substr( $slot_name, 5 ) : $slot_name;
				$piece     = $placements[0]->piece;

				if ( null !== $source && null !== $source->attach_to ) {
					$slots[] = array_filter(
						[
							'name'     => $name,
							'category' => $piece->category,
							'attach'   => [
								'to'     => $source->attach_to,
								'anchor' => (string) $source->attach_anchor,
							],
							'behind'   => $source->behind,
							'prefer'   => self::tags( $piece->tags ),
						],
						static fn( $value ) => false !== $value
					);
						continue;
				}

				$slots[] = self::box_slot( $name, $placements, $z, $source );
				if ( ! in_array( $piece->category, [ 'decor', 'backgrounds' ], true ) ) {
					foreach ( $piece->tags as $tag ) {
						$tags[ $tag ] = ( $tags[ $tag ] ?? 0 ) + 1;
					}
				}
			}//end foreach
		}//end foreach

		arsort( $tags );

		return [
			'id'     => $id,
			'label'  => $label,
			'canvas' => [ (int) round( (float) $scene->canvas[0] ), (int) round( (float) $scene->canvas[1] ) ],
			'unit'   => 1,
			'tags'   => array_slice( array_keys( array_diff_key( $tags, [ 'hero' => 1 ] ) ), 0, self::MAX_TAGS ),
			'slots'  => $slots,
		];
	}

	/**
	 * A slot at the placements' current box: one piece fitted exactly, or a scatter area for several.
	 *
	 * @param string            $name       Slot name.
	 * @param array<Placement>  $placements Placements of this slot.
	 * @param int               $z          Paint order.
	 * @param TemplateSlot|null $source     Original template slot, if any.
	 * @return array<string, mixed>
	 */
	private static function box_slot( string $name, array $placements, int $z, ?TemplateSlot $source ): array {
		$x1 = min( array_map( static fn( Placement $p ): float => $p->x, $placements ) );
		$y1 = min( array_map( static fn( Placement $p ): float => $p->y, $placements ) );
		$x2 = max( array_map( static fn( Placement $p ): float => $p->x + $p->width(), $placements ) );
		$y2 = max( array_map( static fn( Placement $p ): float => $p->y + $p->height(), $placements ) );
		$n  = count( $placements );

		$slot = [
			'name'     => $name,
			'category' => $placements[0]->piece->category,
			'box'      => [ round( $x1, 2 ), round( $y1, 2 ), round( $x2 - $x1, 2 ), round( $y2 - $y1, 2 ) ],
			'fit'      => 'contain',
			'align'    => 'bottom',
			'z'        => $z,
			'prefer'   => self::tags( $placements[0]->piece->tags ),
		];

		if ( $n > 1 ) {
			$slot['count']   = [ $n, $n ];
			$slot['scatter'] = true;
			// Scattered pieces keep roughly their size: the box is the whole area, so scale down per piece.
			$area          = max( 1.0, ( $x2 - $x1 ) * ( $y2 - $y1 ) );
			$each          = array_sum( array_map( static fn( Placement $p ): float => $p->width() * $p->height(), $placements ) ) / $n;
			$factor        = round( max( 0.05, min( 1.0, sqrt( $each / $area ) ) ), 3 );
			$slot['scale'] = [ $factor, $factor ];
		}
		if ( $placements[0]->flip ) {
			$slot['flip'] = true;
		}
		if ( null !== $source && [] !== $source->require_tags ) {
			$slot['require_tags'] = $source->require_tags;
		}

		return $slot;
	}

	/**
	 * Tags to prefer (a slot's own piece's tags, capped).
	 *
	 * @param array<string> $tags Tags.
	 * @return array<string>
	 */
	private static function tags( array $tags ): array {
		return array_slice( array_values( $tags ), 0, self::MAX_TAGS );
	}
}
