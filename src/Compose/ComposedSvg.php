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
	 * @param string                                                              $markup   Sanitized SVG containing ID_TOKEN placeholders.
	 * @param SceneSpec                                                           $spec     Spec with template and picks resolved (re-renders identically).
	 * @param array<string>                                                       $warnings Non-fatal issues.
	 * @param array<string, array<array{0: float, 1: float, 2: float, 3: float}>> $boxes Slot => rendered boxes [x, y, w, h] in canvas units.
	 * @param array<int, string>                                                  $layers   Layer groups back to front (top-level slots and "item:<key>").
	 */
	public function __construct(
		public readonly string $markup,
		public readonly SceneSpec $spec,
		public readonly array $warnings,
		public readonly array $boxes = [],
		public readonly array $layers = [],
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

	/**
	 * Export for storage.
	 *
	 * @return array{markup: string, spec: array<string, mixed>, warnings: array<string>, boxes: array<string, mixed>, layers: array<int, string>}
	 */
	public function to_array(): array {
		return [
			'markup'   => $this->markup,
			'spec'     => $this->spec->to_array(),
			'warnings' => $this->warnings,
			'boxes'    => $this->boxes,
			'layers'   => $this->layers,
		];
	}

	/**
	 * Import from storage.
	 *
	 * @param array<string, mixed> $data Output of to_array().
	 * @return self|null Null when the shape is wrong.
	 */
	public static function from_array( array $data ): ?self {
		if ( ! isset( $data['markup'], $data['spec'], $data['warnings'] ) || ! is_string( $data['markup'] ) || ! is_array( $data['spec'] ) || ! is_array( $data['warnings'] ) ) {
			return null;
		}

		return new self(
			$data['markup'],
			SceneSpec::from_array( $data['spec'] ),
			array_values( array_filter( $data['warnings'], 'is_string' ) ),
			is_array( $data['boxes'] ?? null ) ? $data['boxes'] : [],
			is_array( $data['layers'] ?? null ) ? array_values( array_filter( $data['layers'], 'is_string' ) ) : []
		);
	}
}
