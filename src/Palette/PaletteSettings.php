<?php
/**
 * Site palette settings: normalization and palette references.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Palette;

/**
 * Pure logic behind the sprint_illustrations_palette option.
 */
final class PaletteSettings {

	/**
	 * Allowed palette sources.
	 */
	public const SOURCES = [ 'preset', 'custom', 'elementor' ];

	/**
	 * Maximum skin or hair tones.
	 */
	public const MAX_TONES = 6;

	/**
	 * Constructor.
	 *
	 * @param PresetRepository $presets Presets.
	 */
	public function __construct( private PresetRepository $presets ) {}

	/**
	 * Normalize untrusted option data. Idempotent.
	 *
	 * @param mixed $raw Stored or submitted value.
	 * @return array{source: string, preset: string, colors: array<string, string>, skin: array<string>, hair: array<string>, elementor: array{map: array<string, string>, sync: bool}}
	 */
	public function normalize( mixed $raw ): array {
		$raw       = is_array( $raw ) ? $raw : [];
		$source    = in_array( $raw['source'] ?? null, self::SOURCES, true ) ? $raw['source'] : 'preset';
		$preset_id = isset( $raw['preset'] ) && is_string( $raw['preset'] ) && null !== $this->presets->get( $raw['preset'] ) ? $raw['preset'] : 'sprint';
		$preset    = $this->presets->get( $preset_id ) ?? Palette::default();
		$colors    = 'preset' === $source
			? $preset->to_array()
			: Palette::from_array( is_array( $raw['colors'] ?? null ) ? $raw['colors'] : [] )->to_array();
		$tones     = Palette::from_array(
			[
				'skin' => $raw['skin'] ?? null,
				'hair' => $raw['hair'] ?? null,
			]
		)->to_array();
		$elementor = is_array( $raw['elementor'] ?? null ) ? $raw['elementor'] : [];

		$map = [];
		foreach ( is_array( $elementor['map'] ?? null ) ? $elementor['map'] : [] as $slot => $id ) {
			if ( in_array( $slot, Palette::SLOTS, true ) && is_string( $id ) && preg_match( '/^(kit|var):[A-Za-z0-9_-]+$/D', $id ) ) {
				$map[ $slot ] = $id;
			}
		}

		$slot_colors = [];
		foreach ( Palette::SLOTS as $slot ) {
			$slot_colors[ $slot ] = $colors[ $slot ];
		}

		return [
			'source'    => $source,
			'preset'    => $preset_id,
			'colors'    => $slot_colors,
			'skin'      => array_slice( $tones['skin'], 0, self::MAX_TONES ),
			'hair'      => array_slice( $tones['hair'], 0, self::MAX_TONES ),
			'elementor' => [
				'map'  => $map,
				'sync' => ! empty( $elementor['sync'] ),
			],
		];
	}

	/**
	 * The palette described by normalized settings.
	 *
	 * @param array{colors: array<string, string>, skin: array<string>, hair: array<string>} $settings Normalized settings.
	 * @return Palette
	 */
	public function palette( array $settings ): Palette {
		return Palette::from_array(
			$settings['colors'] + [
				'skin' => $settings['skin'],
				'hair' => $settings['hair'],
			]
		);
	}

	/**
	 * Resolve a SceneSpec palette reference: "site", "default", "preset:<id>" or an inline array.
	 *
	 * @param string|array<string, mixed>                                                    $ref      Reference.
	 * @param array{colors: array<string, string>, skin: array<string>, hair: array<string>} $settings Normalized settings for "site".
	 * @return Palette
	 */
	public function resolve( string|array $ref, array $settings ): Palette {
		if ( is_array( $ref ) ) {
			return Palette::from_array( $ref );
		}

		if ( 'site' === $ref ) {
			return $this->palette( $settings );
		}

		if ( str_starts_with( $ref, 'preset:' ) ) {
			return $this->presets->get( substr( $ref, 7 ) ) ?? Palette::default();
		}

		return Palette::default();
	}
}
