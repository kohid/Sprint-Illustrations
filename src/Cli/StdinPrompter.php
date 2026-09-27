<?php
/**
 * Interactive terminal prompter.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Cli;

/**
 * Reads answers from STDIN. Works under plain PHP CLI and WP-CLI.
 */
final class StdinPrompter implements Prompter {

	/**
	 * Ask on STDOUT, read a line from STDIN.
	 *
	 * @param string $question Question.
	 * @param string $fallback  Answer used on empty input.
	 * @return string
	 */
	public function ask( string $question, string $fallback ): string {
		fwrite( STDOUT, sprintf( '%s [%s]: ', $question, $fallback ) );
		$line = fgets( STDIN );

		$answer = false === $line ? '' : trim( $line );

		return '' === $answer ? $fallback : $answer;
	}
}
