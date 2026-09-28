<?php
/**
 * Maps Elementor colours onto palette slots.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Palette;

/**
 * Pure mapping logic. Items come from Integrations\Elementor\ColorSource.
 */
final class ElementorMapping {

	/**
	 * Constructor.
	 *
	 * @param array<array{id: string, label: string, value: string}> $items Elementor colours. IDs are "kit:<_id>" or "var:<id>".
	 */
	public function __construct( private array $items ) {}

	/**
	 * Items with their hex value (null when not importable), for pickers.
	 *
	 * @return array<array{id: string, label: string, hex: ?string}>
	 */
	public function choices(): array {
		return array_map(
			static fn( array $item ): array => [
				'id'    => $item['id'],
				'label' => $item['label'],
				'hex'   => ColorValue::to_hex( $item['value'] ),
			],
			$this->items
		);
	}

	/**
	 * Suggested slot mapping for a site that has none yet.
	 *
	 * @return array<string, string> Slot => item ID, keys sorted.
	 */
	public function suggest(): array {
		$hex = $this->importable();
		$map = [];

		foreach ( [ 'primary', 'secondary', 'accent' ] as $slot ) {
			if ( isset( $hex[ 'kit:' . $slot ] ) ) {
				$map[ $slot ] = 'kit:' . $slot;
			}
		}

		if ( isset( $hex['kit:text'] ) ) {
			$map['neutral'] = 'kit:text';
			$map['outline'] = 'kit:text';
		}

		foreach ( $this->variables_first() as $item ) {
			if ( isset( $hex[ $item['id'] ] ) && preg_match( '/background|\bbg\b/i', $item['label'] ) ) {
				$map['background'] = $item['id'];
				break;
			}
		}

		foreach ( $this->items as $item ) {
			$slot = strtolower( $item['label'] );
			if ( str_starts_with( $item['id'], 'var:' ) && isset( $hex[ $item['id'] ] ) && in_array( $slot, Palette::SLOTS, true ) ) {
				$map[ $slot ] = $item['id'];
			}
		}

		ksort( $map );

		return $map;
	}

	/**
	 * Apply a stored mapping on top of the current colours.
	 *
	 * @param array<string, string> $map     Slot => item ID.
	 * @param array<string, string> $current Slot => current hex, kept for unmapped or missing items.
	 * @return array{colors: array<string, string>, missing: array<string>}
	 */
	public function apply( array $map, array $current ): array {
		$hex     = $this->importable();
		$colors  = $current;
		$missing = [];

		foreach ( $map as $slot => $id ) {
			if ( ! in_array( $slot, Palette::SLOTS, true ) ) {
				continue;
			}

			if ( isset( $hex[ $id ] ) ) {
				$colors[ $slot ] = $hex[ $id ];
			} else {
				$missing[] = $id;
			}
		}

		return [
			'colors'  => $colors,
			'missing' => array_values( array_unique( $missing ) ),
		];
	}

	/**
	 * Importable items as ID => hex.
	 *
	 * @return array<string, string>
	 */
	private function importable(): array {
		$hex = [];

		foreach ( $this->items as $item ) {
			$value = ColorValue::to_hex( $item['value'] );
			if ( null !== $value ) {
				$hex[ $item['id'] ] = $value;
			}
		}

		return $hex;
	}

	/**
	 * Items with V4 variables before Kit colours (stable otherwise).
	 *
	 * @return array<array{id: string, label: string, value: string}>
	 */
	private function variables_first(): array {
		$variables = array_filter( $this->items, static fn( array $item ): bool => str_starts_with( $item['id'], 'var:' ) );
		$kit       = array_filter( $this->items, static fn( array $item ): bool => ! str_starts_with( $item['id'], 'var:' ) );

		return array_merge( array_values( $variables ), array_values( $kit ) );
	}
}
