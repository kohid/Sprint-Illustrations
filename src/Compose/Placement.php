<?php
/**
 * A piece positioned on the canvas.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Compose;

use SprintIllustrations\Library\Piece;
use SprintIllustrations\Svg\SvgDom;

/**
 * Position is the canvas coordinate of the rendered piece's top-left corner.
 */
final class Placement {

	/**
	 * Constructor.
	 *
	 * @param string $slot       Slot name.
	 * @param Piece  $piece      Piece.
	 * @param float  $x          Canvas X of the rendered box's left edge.
	 * @param float  $y          Canvas Y of the rendered box's top edge.
	 * @param float  $scale      Piece units → canvas units.
	 * @param int    $z          Z-layer.
	 * @param int    $order      Tie-breaker (resolution order).
	 * @param bool   $flip       Mirrored horizontally.
	 * @param int    $skin_index Skin tone index.
	 * @param int    $hair_index Hair colour index.
	 */
	public function __construct(
		public readonly string $slot,
		public readonly Piece $piece,
		public readonly float $x,
		public readonly float $y,
		public readonly float $scale,
		public readonly int $z,
		public readonly int $order,
		public readonly bool $flip = false,
		public readonly int $skin_index = 0,
		public readonly int $hair_index = 0,
	) {}

	/**
	 * Copy moved into a scaled, offset frame (a template layout fitted into a different canvas).
	 *
	 * @param float $k  Scale.
	 * @param float $ox Offset X.
	 * @param float $oy Offset Y.
	 * @return self
	 */
	public function framed( float $k, float $ox, float $oy ): self {
		return new self( $this->slot, $this->piece, $ox + $this->x * $k, $oy + $this->y * $k, $this->scale * $k, $this->z, $this->order, $this->flip, $this->skin_index, $this->hair_index );
	}

	/**
	 * Rendered width in canvas units.
	 *
	 * @return float
	 */
	public function width(): float {
		return $this->piece->width() * $this->scale;
	}

	/**
	 * Rendered height in canvas units.
	 *
	 * @return float
	 */
	public function height(): float {
		return $this->piece->height() * $this->scale;
	}

	/**
	 * SVG transform mapping piece viewBox coordinates onto the canvas.
	 *
	 * @return string
	 */
	public function transform(): string {
		[ $vx, $vy, $vw ] = $this->piece->view_box;

		$ty = $this->y - $vy * $this->scale;

		if ( $this->flip ) {
			return sprintf(
				'translate(%s %s) scale(%s %s)',
				SvgDom::num( $this->x + ( $vx + $vw ) * $this->scale ),
				SvgDom::num( $ty ),
				SvgDom::num( -$this->scale, 4 ),
				SvgDom::num( $this->scale, 4 )
			);
		}

		return sprintf(
			'translate(%s %s) scale(%s)',
			SvgDom::num( $this->x - $vx * $this->scale ),
			SvgDom::num( $ty ),
			SvgDom::num( $this->scale, 4 )
		);
	}

	/**
	 * Canvas coordinates of a piece-space point, honouring flip.
	 *
	 * @param float $px Piece X.
	 * @param float $py Piece Y.
	 * @return array{0: float, 1: float}
	 */
	public function to_canvas( float $px, float $py ): array {
		[ $vx, $vy, $vw ] = $this->piece->view_box;

		$x = $this->flip
			? $this->x + ( $vx + $vw - $px ) * $this->scale
			: $this->x + ( $px - $vx ) * $this->scale;

		return [ $x, $this->y + ( $py - $vy ) * $this->scale ];
	}

	/**
	 * Canvas coordinates of a named anchor, or null.
	 *
	 * @param string $name Anchor name.
	 * @return array{0: float, 1: float}|null
	 */
	public function anchor_point( string $name ): ?array {
		$anchor = $this->piece->anchor( $name );

		return null === $anchor ? null : $this->to_canvas( $anchor[0], $anchor[1] );
	}
}
