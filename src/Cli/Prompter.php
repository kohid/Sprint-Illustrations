<?php
/**
 * Question/answer abstraction for the manifest builder.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Cli;

/**
 * Asks a question and returns the answer (or the default on empty input).
 */
interface Prompter {

	/**
	 * Ask a question.
	 *
	 * @param string $question Question text.
	 * @param string $fallback  Answer used on empty input.
	 * @return string
	 */
	public function ask( string $question, string $fallback ): string;
}
