<?php
/**
 * Turns a template + spec into concrete placements.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Compose;

use SprintIllustrations\Library\Manifest;
use SprintIllustrations\Library\Piece;
use SprintIllustrations\Library\Template;
use SprintIllustrations\Library\TemplateSlot;

/**
 * Deterministic slot filling.
 *
 * Every slot draws from its own Seed derived from (seed, template, slot name), so an explicit pick in
 * one slot never changes the random choices made for other slots. Within a slot the draw order is
 * fixed: count, then per item: piece, scale, [skin, hair], [scatter position].
 */
final class SceneResolver {

	/**
	 * Constructor.
	 *
	 * @param Manifest $manifest Piece library.
	 */
	public function __construct( private Manifest $manifest ) {}

	/**
	 * Resolve all slots.
	 *
	 * @param Template      $template Template.
	 * @param SceneSpec     $spec     Spec (seed and picks are used).
	 * @param array<string> $tokens   Expanded keyword tokens for scoring.
	 * @return ResolvedScene
	 * @throws CompositionException When a required slot cannot be filled.
	 */
	public function resolve( Template $template, SceneSpec $spec, array $tokens ): ResolvedScene {
		$placed   = [];
		$warnings = [];
		$order    = 0;
		$used     = [];

		foreach ( $template->slots as $slot ) {
			$seed  = Seed::from_string( $spec->seed . '|' . $template->id . '|' . $slot->name );
			$items = null === $slot->attach_to
				? $this->resolve_box_slot( $template, $slot, $spec, $tokens, $seed, $warnings, $order, $used )
				: $this->resolve_attached_slot( $slot, $placed, $spec, $tokens, $seed, $warnings, $order, $used );

			if ( ! $items && $slot->required ) {
				throw new CompositionException( sprintf( 'Required slot "%s" in template "%s" could not be filled.', $slot->name, $template->id ) );
			}

			$placed[ $slot->name ] = $items;
		}

		$placements = array_merge( ...array_values( $placed ) );
		usort( $placements, static fn( Placement $a, Placement $b ) => [ $a->z, $a->order ] <=> [ $b->z, $b->order ] );

		$canvas     = $spec->canvas ?? $template->canvas;
		$placements = SceneLayout::fit( $placements, $template->canvas, $canvas );
		$placements = array_merge( $placements, $this->resolve_items( $spec, $warnings, $order ) );
		$layout     = SceneLayout::order( $placements, $this->roots( $template ), $spec->layers );

		return new ResolvedScene( $template, $layout['placements'], $this->picks_from( $template, $placed ), $warnings, $canvas, $layout['layers'] );
	}

	/**
	 * Freely placed pieces, on top in spec order. Skin and hair come from the item's own key and piece,
	 * so they stay the same when other items change or the scene is shuffled.
	 *
	 * @param SceneSpec     $spec     Spec.
	 * @param array<string> $warnings Warnings (by reference).
	 * @param int           $order    Order counter.
	 * @return array<Placement>
	 */
	private function resolve_items( SceneSpec $spec, array &$warnings, int $order ): array {
		$items = [];

		foreach ( $spec->items as $item ) {
			$piece = $this->manifest->get( $item['piece'] );
			if ( null === $piece ) {
				$warnings[] = sprintf( 'Piece "%s" is not in the library; it was left out.', $item['piece'] );
				continue;
			}

			$seed    = Seed::from_string( 'item|' . $item['key'] . '|' . $piece->id );
			$skin    = $seed->int( 0, 63 );
			$hair    = $seed->int( 0, 63 );
			$items[] = new Placement( 'item:' . $item['key'], $piece, $item['x'], $item['y'], $item['w'] / $piece->width(), 0, $order++, $item['flip'], $skin, $hair );
		}

		return $items;
	}

	/**
	 * Layer key of every slot: its own name, or the top-level slot it is attached to.
	 *
	 * @param Template $template Template.
	 * @return array<string, string>
	 */
	private function roots( Template $template ): array {
		$parent = [];
		foreach ( $template->slots as $slot ) {
			$parent[ $slot->name ] = $slot->attach_to;
		}

		$roots = [];
		foreach ( array_keys( $parent ) as $name ) {
			$root = $name;
			for ( $guard = 0; null !== ( $parent[ $root ] ?? null ) && $guard < 16; $guard++ ) {
				$root = (string) $parent[ $root ];
			}
			$roots[ $name ] = $root;
		}

		return $roots;
	}

	/**
	 * Resolve a box slot (single, multiple, or scattered).
	 *
	 * @param Template            $template Template.
	 * @param TemplateSlot        $slot     Slot.
	 * @param SceneSpec           $spec     Spec.
	 * @param array<string>       $tokens   Tokens.
	 * @param Seed                $seed     Slot seed.
	 * @param array<string>       $warnings Warnings (by reference).
	 * @param int                 $order    Order counter (by reference).
	 * @param array<string, true> $used Persons already placed in this scene (by reference).
	 * @return array<Placement>
	 */
	private function resolve_box_slot( Template $template, TemplateSlot $slot, SceneSpec $spec, array $tokens, Seed $seed, array &$warnings, int &$order, array &$used ): array {
		$requested  = $this->requested_pieces( $slot, $spec, null, $warnings );
		$count      = $seed->int( $slot->count[0], $slot->count[1] );
		$count      = $requested ? count( $requested ) : $count;
		$candidates = $this->candidates( $slot, null );
		$occupied   = [];
		$items      = [];

		for ( $i = 0; $i < $count; $i++ ) {
			$auto   = $this->pick( $this->unused( $candidates, $used ), $slot, $tokens, $seed );
			$factor = $seed->float( $slot->scale[0], $slot->scale[1] );
			$skin   = $seed->int( 0, 63 );
			$hair   = $seed->int( 0, 63 );
			$piece  = $requested[ $i ] ?? $auto;

			if ( null === $piece ) {
				continue;
			}

			if ( null !== $piece->person ) {
				$used[ $piece->person ] = true;
			}

			$scale = $this->box_scale( $template, $slot, $piece ) * $factor;
			$w     = $piece->width() * $scale;
			$h     = $piece->height() * $scale;

			[ $x, $y ] = $slot->scatter
				? $this->scatter_position( $slot, $w, $h, $seed, $occupied )
				: $this->aligned_position( $slot, $w, $h );

			$items[] = new Placement( $slot->name, $piece, $x, $y, $scale, $slot->z ?? $piece->z, $order++, $slot->flip, $skin, $hair );
		}//end for

		return $items;
	}

	/**
	 * Resolve a slot attached to a parent slot's anchor.
	 *
	 * @param TemplateSlot                    $slot     Slot.
	 * @param array<string, array<Placement>> $placed  Already-resolved slots.
	 * @param SceneSpec                       $spec     Spec.
	 * @param array<string>                   $tokens   Tokens.
	 * @param Seed                            $seed     Slot seed.
	 * @param array<string>                   $warnings Warnings (by reference).
	 * @param int                             $order    Order counter (by reference).
	 * @param array<string, true>             $used     Persons already placed in this scene (by reference).
	 * @return array<Placement>
	 */
	private function resolve_attached_slot( TemplateSlot $slot, array $placed, SceneSpec $spec, array $tokens, Seed $seed, array &$warnings, int &$order, array &$used ): array {
		$parent = $placed[ (string) $slot->attach_to ][0] ?? null;
		if ( null === $parent ) {
			return [];
		}

		$type  = $parent->piece->accepts[ (string) $slot->attach_anchor ] ?? null;
		$point = $parent->anchor_point( (string) $slot->attach_anchor );
		if ( null === $type || null === $point ) {
			return [];
		}

		$requested = $this->requested_pieces( $slot, $spec, $type, $warnings );
		$auto      = $this->pick( $this->unused( $this->candidates( $slot, $type ), $used ), $slot, $tokens, $seed );
		$factor    = $seed->float( $slot->scale[0], $slot->scale[1] );
		$piece     = $requested[0] ?? $auto;

		if ( null === $piece ) {
			return [];
		}

		if ( null !== $piece->person ) {
			$used[ $piece->person ] = true;
		}

		[ $vx, $vy, $vw, $vh ] = $piece->view_box;

		$scale = $parent->scale * $factor;
		$mount = $piece->anchor( $piece->mounts[ $type ] ) ?? [ $vx + $vw / 2, $vy + $vh / 2 ];
		$x     = $parent->flip
			? $point[0] - ( $vx + $vw - $mount[0] ) * $scale
			: $point[0] - ( $mount[0] - $vx ) * $scale;
		$y     = $point[1] - ( $mount[1] - $vy ) * $scale;
		$z     = $parent->z + ( $slot->behind ? -1 : 1 );

		return [ new Placement( $slot->name, $piece, $x, $y, $scale, $z, $order++, $parent->flip ) ];
	}

	/**
	 * Candidate pieces for a slot.
	 *
	 * @param TemplateSlot $slot       Slot.
	 * @param string|null  $mount_type Required mount type for attached slots.
	 * @return array<Piece>
	 */
	private function candidates( TemplateSlot $slot, ?string $mount_type ): array {
		return array_values(
			array_filter(
				$this->manifest->by_category( $slot->category ),
				static function ( Piece $piece ) use ( $slot, $mount_type ): bool {
					foreach ( $slot->require_tags as $tag ) {
						if ( ! $piece->has_tag( $tag ) ) {
							return false;
						}
					}
					return null === $mount_type || isset( $piece->mounts[ $mount_type ] );
				}
			)
		);
	}

	/**
	 * Drop pieces whose person is already in the scene, unless that would leave nothing.
	 *
	 * @param array<Piece>        $candidates Candidates.
	 * @param array<string, true> $used       Persons already placed.
	 * @return array<Piece>
	 */
	private function unused( array $candidates, array $used ): array {
		if ( ! $used ) {
			return $candidates;
		}

		$fresh = array_values( array_filter( $candidates, static fn( Piece $piece ): bool => null === $piece->person || ! isset( $used[ $piece->person ] ) ) );

		return $fresh ? $fresh : $candidates;
	}

	/**
	 * Score candidates (2 per keyword hit, 1 per preferred tag) and pick among the near-best.
	 *
	 * @param array<Piece>  $candidates Candidates.
	 * @param TemplateSlot  $slot       Slot.
	 * @param array<string> $tokens     Tokens.
	 * @param Seed          $seed       Seed.
	 * @return Piece|null
	 */
	private function pick( array $candidates, TemplateSlot $slot, array $tokens, Seed $seed ): ?Piece {
		if ( ! $candidates ) {
			return null;
		}

		$scores = array_map(
			static fn( Piece $piece ): int => 2 * count( array_intersect( $piece->tags, $tokens ) ) + count( array_intersect( $piece->tags, $slot->prefer ) ),
			$candidates
		);
		$best   = max( $scores );
		$pool   = array_values( array_filter( $candidates, static fn( $piece, $i ) => $scores[ $i ] >= $best - 1, ARRAY_FILTER_USE_BOTH ) );

		return $seed->pick( $pool );
	}

	/**
	 * Valid explicit picks for a slot; invalid ones produce warnings.
	 *
	 * @param TemplateSlot  $slot       Slot.
	 * @param SceneSpec     $spec       Spec.
	 * @param string|null   $mount_type Required mount type.
	 * @param array<string> $warnings   Warnings (by reference).
	 * @return array<Piece>
	 */
	private function requested_pieces( TemplateSlot $slot, SceneSpec $spec, ?string $mount_type, array &$warnings ): array {
		$pieces = [];

		foreach ( (array) ( $spec->picks[ $slot->name ] ?? [] ) as $id ) {
			$piece = $this->manifest->get( (string) $id );

			if ( null === $piece || $piece->category !== $slot->category || ( null !== $mount_type && ! isset( $piece->mounts[ $mount_type ] ) ) ) {
				$warnings[] = sprintf( 'Pick "%s" is not valid for slot "%s"; auto-selected instead.', $id, $slot->name );
				continue;
			}

			$pieces[] = $piece;
		}

		return $slot->is_multiple() ? $pieces : array_slice( $pieces, 0, 1 );
	}

	/**
	 * Scale for a box slot before the random factor.
	 *
	 * @param Template     $template Template.
	 * @param TemplateSlot $slot     Slot.
	 * @param Piece        $piece    Piece.
	 * @return float
	 */
	private function box_scale( Template $template, TemplateSlot $slot, Piece $piece ): float {
		$box = (array) $slot->box;
		$fit = min( $box[2] / $piece->width(), $box[3] / $piece->height() );

		return 'contain' === $slot->fit ? $fit : min( $template->unit, $fit );
	}

	/**
	 * Horizontally centred, vertically aligned position in the box.
	 *
	 * @param TemplateSlot $slot Slot.
	 * @param float        $w    Rendered width.
	 * @param float        $h    Rendered height.
	 * @return array{0: float, 1: float}
	 */
	private function aligned_position( TemplateSlot $slot, float $w, float $h ): array {
		[ $bx, $by, $bw, $bh ] = (array) $slot->box;

		$y = match ( $slot->align ) {
			'top'    => $by,
			'center' => $by + ( $bh - $h ) / 2,
			default  => $by + $bh - $h,
		};

		return [ $bx + ( $bw - $w ) / 2, $y ];
	}

	/**
	 * Random position in the box, avoiding other scattered items and "avoid" rectangles.
	 * Up to 20 attempts; the last attempt is used if none is clear.
	 *
	 * @param TemplateSlot                               $slot     Slot.
	 * @param float                                      $w        Rendered width.
	 * @param float                                      $h        Rendered height.
	 * @param Seed                                       $seed     Seed.
	 * @param array<array{0: float, 1: float, 2: float}> $occupied Centres + radii (by reference).
	 * @return array{0: float, 1: float}
	 */
	private function scatter_position( TemplateSlot $slot, float $w, float $h, Seed $seed, array &$occupied ): array {
		[ $bx, $by, $bw, $bh ] = (array) $slot->box;

		$radius   = max( $w, $h ) / 2;
		$position = [ $bx, $by ];

		for ( $attempt = 0; $attempt < 20; $attempt++ ) {
			$position = [
				$bx + $seed->next() * max( 0.0, $bw - $w ),
				$by + $seed->next() * max( 0.0, $bh - $h ),
			];
			$cx       = $position[0] + $w / 2;
			$cy       = $position[1] + $h / 2;

			if ( $this->is_clear( $cx, $cy, $radius, $slot->avoid, $occupied ) ) {
				break;
			}
		}

		$occupied[] = [ $position[0] + $w / 2, $position[1] + $h / 2, $radius ];

		return $position;
	}

	/**
	 * Whether a centre point is clear of avoid boxes and other items.
	 *
	 * @param float                                                $cx       Centre X.
	 * @param float                                                $cy       Centre Y.
	 * @param float                                                $radius   Item radius.
	 * @param array<array{0: float, 1: float, 2: float, 3: float}> $avoid Avoid rectangles.
	 * @param array<array{0: float, 1: float, 2: float}>           $occupied Other items.
	 * @return bool
	 */
	private function is_clear( float $cx, float $cy, float $radius, array $avoid, array $occupied ): bool {
		foreach ( $avoid as [ $ax, $ay, $aw, $ah ] ) {
			if ( $cx >= $ax && $cx <= $ax + $aw && $cy >= $ay && $cy <= $ay + $ah ) {
				return false;
			}
		}

		foreach ( $occupied as [ $ox, $oy, $or ] ) {
			if ( hypot( $cx - $ox, $cy - $oy ) < ( $radius + $or ) * 1.2 ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Picks actually used, for echoing back to editors.
	 *
	 * @param Template                        $template Template.
	 * @param array<string, array<Placement>> $placed   Placements by slot.
	 * @return array<string, string|array<string>>
	 */
	private function picks_from( Template $template, array $placed ): array {
		$picks = [];

		foreach ( $template->slots as $slot ) {
			$ids = array_map( static fn( Placement $p ) => $p->piece->id, $placed[ $slot->name ] ?? [] );
			if ( $ids ) {
				$picks[ $slot->name ] = $slot->is_multiple() ? $ids : $ids[0];
			}
		}

		return $picks;
	}
}
