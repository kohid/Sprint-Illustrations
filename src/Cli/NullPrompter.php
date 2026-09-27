<?php
/**
 * Non-interactive prompter.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Cli;

/**
 * Always accepts the default.
 */
final class NullPrompter implements Prompter {

	/**
	 * Return the default.
	 *
	 * @param string $question Question (ignored).
	 * @param string $fallback  Answer used on empty input.
	 * @return string
	 */
	public function ask( string $question, string $fallback ): string {
		return $fallback;
	}
}
