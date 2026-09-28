<?php
/**
 * Anything that turns a scene spec into SVG.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Compose;

use SprintIllustrations\Palette\Palette;

/**
 * Implemented by Composer and Cache\CachingComposer.
 */
interface ComposesSvg {

	/**
	 * Compose a scene.
	 *
	 * @param SceneSpec $spec    Scene request.
	 * @param Palette   $palette Resolved palette.
	 * @return ComposedSvg
	 * @throws CompositionException When the scene cannot be composed.
	 */
	public function compose( SceneSpec $spec, Palette $palette ): ComposedSvg;
}
