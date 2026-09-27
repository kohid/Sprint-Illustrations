<?php
/**
 * Result of a composition.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Compose;

/**
 * Cacheable markup with an ID placeholder, plus the fully resolved spec.
 */
final class ComposedSvg {

	/**
	 * Placeholder replaced by a per-render instance ID, so cached markup stays shareable while
	 * every rendered copy on a page has unique IDs.
	 */
	public const ID_TOKEN = '__SIID__';

	/**
	 * Constructor.
	 *
	 * @param string        $markup   Sanitized SVG containing ID_TOKEN placeholders.
	 * @param SceneSpec     $spec     Spec with template and picks resolved (re-renders identically).
	 * @param array<string> $warnings Non-fatal issues.
	 */
	public function __construct(
		public readonly string $markup,
		public readonly SceneSpec $spec,
		public readonly array $warnings,
	) {}

	/**
	 * Markup with placeholders replaced.
	 *
	 * @param string $instance_id Letters, digits, "-" and "_"; must start with a letter.
	 * @return string
	 * @throws \InvalidArgumentException When the ID is not a valid XML ID prefix.
	 */
	public function with_instance_id( string $instance_id ): string {
		if ( ! preg_match( '/^[A-Za-z][A-Za-z0-9_-]*$/D', $instance_id ) ) {
			throw new \InvalidArgumentException( 'Invalid instance ID.' );
		}

		return str_replace( self::ID_TOKEN, $instance_id, $this->markup );
	}
}
