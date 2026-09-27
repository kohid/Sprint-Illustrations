<?php
/**
 * Content → scene spec suggestion contract.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Selection;

use SprintIllustrations\Compose\SceneSpec;

/**
 * Implemented by RulesSelector (phase 1) and AiSelector (phase 5).
 */
interface Selector {

	/**
	 * Suggest a scene for a piece of content.
	 *
	 * @param string $content Title, excerpt or keywords.
	 * @param int    $seed    Seed for tie-breaking and variety.
	 * @return SceneSpec
	 */
	public function suggest( string $content, int $seed ): SceneSpec;
}
