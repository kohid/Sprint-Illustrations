<?php
/**
 * Named palette presets.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Palette;

/**
 * Loads presets from a JSON object keyed by preset ID.
 */
final class PresetRepository {

	/**
	 * Constructor. Use from_file() or bundled().
	 *
	 * @param array<string, array{label: string, palette: Palette}> $presets Presets by ID.
	 */
	private function __construct( private array $presets ) {}

	/**
	 * Load presets from a file. Entries with invalid IDs or non-object values are skipped.
	 *
	 * @param string $file presets.json path.
	 * @return self
	 * @throws \RuntimeException When the file is missing or is not a JSON object.
	 */
	public static function from_file( string $file ): self {
		$data = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null;

		if ( ! is_array( $data ) ) {
			throw new \RuntimeException( sprintf( 'Palette presets not found or invalid: %s', $file ) );
		}

		$presets = [];
		foreach ( $data as $id => $entry ) {
			if ( ! is_string( $id ) || ! preg_match( '/^[a-z0-9-]+$/D', $id ) || ! is_array( $entry ) ) {
				continue;
			}

			$presets[ $id ] = [
				'label'   => isset( $entry['label'] ) && is_string( $entry['label'] ) && '' !== $entry['label'] ? $entry['label'] : $id,
				'palette' => Palette::from_array( $entry ),
			];
		}

		return new self( $presets );
	}

	/**
	 * The presets shipped with the plugin.
	 *
	 * @return self
	 */
	public static function bundled(): self {
		return self::from_file( dirname( __DIR__, 2 ) . '/assets/palettes/presets.json' );
	}

	/**
	 * All presets in file order.
	 *
	 * @return array<string, array{label: string, palette: Palette}>
	 */
	public function all(): array {
		return $this->presets;
	}

	/**
	 * One preset's palette.
	 *
	 * @param string $id Preset ID.
	 * @return Palette|null
	 */
	public function get( string $id ): ?Palette {
		return $this->presets[ $id ]['palette'] ?? null;
	}

	/**
	 * Preset IDs in file order.
	 *
	 * @return array<string>
	 */
	public function ids(): array {
		return array_keys( $this->presets );
	}
}
