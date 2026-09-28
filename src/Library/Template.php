<?php
/**
 * Scene template.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Library;

/**
 * Canvas plus ordered slots.
 */
final class Template {

	/**
	 * Constructor.
	 *
	 * @param string                    $id     Template ID.
	 * @param string                    $label  Human label (used as default <title>).
	 * @param array{0: float, 1: float} $canvas [width, height].
	 * @param float                     $unit   Scale applied to "natural" slots (piece units → canvas units).
	 * @param array<string>             $tags   Tags used for content matching.
	 * @param array<TemplateSlot>       $slots  Slots in resolution order.
	 */
	public function __construct(
		public readonly string $id,
		public readonly string $label,
		public readonly array $canvas,
		public readonly float $unit,
		public readonly array $tags,
		public readonly array $slots,
	) {}

	/**
	 * Build from template JSON.
	 *
	 * @param array<string, mixed> $data Template data.
	 * @return self
	 * @throws LibraryException When invalid.
	 */
	public static function from_array( array $data ): self {
		$id = (string) ( $data['id'] ?? '' );
		if ( ! preg_match( '/^[a-z0-9][a-z0-9-]*$/D', $id ) ) {
			throw new LibraryException( 'Template has a missing or invalid "id".' );
		}

		$canvas = array_map( 'floatval', array_values( (array) ( $data['canvas'] ?? [] ) ) );
		if ( 2 !== count( $canvas ) || $canvas[0] <= 0 || $canvas[1] <= 0 ) {
			throw new LibraryException( sprintf( 'Template "%s" has an invalid canvas.', $id ) );
		}

		$slots = [];
		foreach ( (array) ( $data['slots'] ?? [] ) as $slot_data ) {
			$slot = TemplateSlot::from_array( (array) $slot_data, $id );

			if ( isset( $slots[ $slot->name ] ) ) {
				throw new LibraryException( sprintf( 'Template "%s" has duplicate slot "%s".', $id, $slot->name ) );
			}
			if ( null !== $slot->attach_to && ! isset( $slots[ $slot->attach_to ] ) ) {
				throw new LibraryException( sprintf( 'Slot "%s.%s" attaches to "%s", which must be an earlier slot.', $id, $slot->name, $slot->attach_to ) );
			}

			$slots[ $slot->name ] = $slot;
		}

		if ( ! $slots ) {
			throw new LibraryException( sprintf( 'Template "%s" has no slots.', $id ) );
		}

		return new self(
			$id,
			(string) ( $data['label'] ?? $id ),
			$canvas,
			(float) ( $data['unit'] ?? 1.0 ),
			array_map( 'strtolower', array_map( 'strval', (array) ( $data['tags'] ?? [] ) ) ),
			array_values( $slots )
		);
	}

	/**
	 * Public shape for REST.
	 *
	 * @return array{id: string, label: string, canvas: array, tags: array<string>, slots: array<array<string, mixed>>}
	 */
	public function to_array(): array {
		return [
			'id'     => $this->id,
			'label'  => $this->label,
			'canvas' => $this->canvas,
			'tags'   => $this->tags,
			'slots'  => array_map( static fn( TemplateSlot $slot ): array => $slot->to_array(), $this->slots ),
		];
	}
}
