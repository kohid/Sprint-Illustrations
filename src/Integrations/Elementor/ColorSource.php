<?php
/**
 * Reads colours from Elementor (Kit globals and V4 colour Variables).
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Integrations\Elementor;

/**
 * Read-only adapter. Uses Elementor's own classes, never raw post meta.
 */
final class ColorSource {

	/**
	 * Set when Elementor threw while reading.
	 *
	 * @var bool
	 */
	private bool $failed = false;

	/**
	 * Whether Elementor is loaded.
	 *
	 * @return bool
	 */
	public static function available(): bool {
		return defined( 'ELEMENTOR_VERSION' ) && class_exists( '\Elementor\Plugin' );
	}

	/**
	 * Whether V4 colour Variables can be read.
	 *
	 * @return bool
	 */
	public static function variables_available(): bool {
		if ( ! self::available() || ! class_exists( '\Elementor\Modules\Variables\Services\Variables_Service' ) ) {
			return false;
		}

		$experiments = \Elementor\Plugin::$instance->experiments ?? null;

		return null !== $experiments
			&& $experiments->is_feature_active( 'e_variables' )
			&& $experiments->is_feature_active( 'e_atomic_elements' );
	}

	/**
	 * All colours, V4 Variables first, then Kit system and custom colours.
	 *
	 * @return array<array{id: string, label: string, value: string}>
	 */
	public function items(): array {
		$this->failed = false;

		if ( ! self::available() ) {
			return [];
		}

		try {
			return array_merge( $this->variables(), $this->kit_colors() );
		} catch ( \Throwable $e ) {
			$this->failed = true;
			return [];
		}
	}

	/**
	 * Whether the last items() call failed inside Elementor.
	 *
	 * @return bool
	 */
	public function failed(): bool {
		return $this->failed;
	}

	/**
	 * Kit system_colors and custom_colors.
	 *
	 * @return array<array{id: string, label: string, value: string}>
	 */
	private function kit_colors(): array {
		$kit = \Elementor\Plugin::$instance->kits_manager->get_active_kit_for_frontend();
		if ( ! $kit ) {
			return [];
		}

		$items = [];
		foreach ( [ 'system_colors', 'custom_colors' ] as $control ) {
			$list = $kit->get_settings_for_display( $control );

			foreach ( is_array( $list ) ? $list : [] as $item ) {
				if ( ! is_array( $item ) || ! isset( $item['_id'] ) || ! is_string( $item['_id'] ) ) {
					continue;
				}

				$items[] = [
					'id'    => 'kit:' . $item['_id'],
					'label' => isset( $item['title'] ) && is_string( $item['title'] ) && '' !== $item['title'] ? $item['title'] : $item['_id'],
					'value' => isset( $item['color'] ) && is_string( $item['color'] ) ? $item['color'] : '',
				];
			}
		}

		return $items;
	}

	/**
	 * V4 colour Variables that are not deleted.
	 *
	 * @return array<array{id: string, label: string, value: string}>
	 */
	private function variables(): array {
		if ( ! self::variables_available() ) {
			return [];
		}

		$kit = \Elementor\Plugin::$instance->kits_manager->get_active_kit();
		if ( ! $kit ) {
			return [];
		}

		$service = new \Elementor\Modules\Variables\Services\Variables_Service(
			new \Elementor\Modules\Variables\Storage\Variables_Repository( $kit ),
			new \Elementor\Modules\Variables\Services\Batch_Operations\Batch_Processor()
		);

		$items = [];
		foreach ( $service->get_variables_list() as $id => $variable ) {
			if ( ! is_array( $variable ) || ! empty( $variable['deleted'] ) || ! empty( $variable['deleted_at'] ) || 'global-color-variable' !== ( $variable['type'] ?? '' ) ) {
				continue;
			}

			$value = $variable['value'] ?? '';
			if ( is_array( $value ) ) {
				$value = $value['value'] ?? '';
			}

			$items[] = [
				'id'    => 'var:' . $id,
				'label' => isset( $variable['label'] ) && is_string( $variable['label'] ) ? $variable['label'] : (string) $id,
				'value' => is_string( $value ) ? $value : '',
			];
		}

		return $items;
	}
}
