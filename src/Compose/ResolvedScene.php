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
 * Placements sorted back-to-front.
 */
final class ResolvedScene {

	/**
	 * Constructor.
	 *
	 * @param Template                            $template   Template.
	 * @param array<Placement>                    $placements Sorted by z, then order.
	 * @param array<string, string|array<string>> $picks      Slot => piece ID(s) actually used.
	 * @param array<string>                       $warnings   Non-fatal issues.
	 */
	public function __construct(
		public readonly Template $template,
		public readonly array $placements,
		public readonly array $picks,
		public readonly array $warnings,
	) {}
}
