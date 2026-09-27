<?php
/**
 * Result of a manifest build.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Cli;

/**
 * Mutable report filled by ManifestBuilder.
 */
final class BuildReport {

	/**
	 * IDs written.
	 *
	 * @var array<string>
	 */
	public array $built = [];

	/**
	 * Non-fatal issues.
	 *
	 * @var array<string>
	 */
	public array $warnings = [];

	/**
	 * Files that could not be processed.
	 *
	 * @var array<string>
	 */
	public array $errors = [];

	/**
	 * New manifest version.
	 *
	 * @var int
	 */
	public int $version = 0;
}
