<?php
/**
 * Output of SceneResolver: concrete placements ready to render.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Compose;

use SprintIllustrations\Library\Template;

/**
 * Placements in paint order (back to front).
 */
final class ResolvedScene {

	/**
	 * Constructor.
	 *
	 * @param Template                            $template   Template.
	 * @param array<Placement>                    $placements Paint order.
	 * @param array<string, string|array<string>> $picks      Slot => piece ID(s) actually used.
	 * @param array<string>                       $warnings   Non-fatal issues.
	 * @param array{0: int|float, 1: int|float}   $canvas     Rendered canvas size.
	 * @param array<int, string>                  $layers     Layer groups back to front (top-level slots and "item:<key>").
	 * @param array<string, string>               $roots      Slot name => layer key (attached slots map to their root).
	 */
	public function __construct(
		public readonly Template $template,
		public readonly array $placements,
		public readonly array $picks,
		public readonly array $warnings,
		public readonly array $canvas = [ 0, 0 ],
		public readonly array $layers = [],
		public readonly array $roots = [],
	) {}
}
