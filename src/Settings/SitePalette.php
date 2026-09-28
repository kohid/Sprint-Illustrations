<?php
/**
 * The site palette option.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Settings;

use SprintIllustrations\Palette\Palette;
use SprintIllustrations\Palette\PaletteSettings;

/**
 * Reads and writes sprint_illustrations_palette. All logic lives in PaletteSettings.
 */
final class SitePalette {

	public const OPTION = 'sprint_illustrations_palette';

	/**
	 * Constructor.
	 *
	 * @param PaletteSettings $logic Normalization and resolution.
	 */
	public function __construct( private PaletteSettings $logic ) {}

	/**
	 * Normalized settings.
	 *
	 * @return array{source: string, preset: string, colors: array<string, string>, skin: array<string>, hair: array<string>, elementor: array{map: array<string, string>, sync: bool}}
	 */
	public function settings(): array {
		return $this->logic->normalize( get_option( self::OPTION, [] ) );
	}

	/**
	 * The site palette.
	 *
	 * @return Palette
	 */
	public function palette(): Palette {
		return $this->logic->palette( $this->settings() );
	}

	/**
	 * Resolve a SceneSpec palette reference.
	 *
	 * @param string|array<string, mixed> $ref Reference.
	 * @return Palette
	 */
	public function resolve( string|array $ref ): Palette {
		return $this->logic->resolve( $ref, $this->settings() );
	}

	/**
	 * Normalize without saving (sanitize callback).
	 *
	 * @param mixed $raw Raw settings.
	 * @return array<string, mixed>
	 */
	public function normalize( mixed $raw ): array {
		return $this->logic->normalize( $raw );
	}

	/**
	 * Normalize and save.
	 *
	 * @param array<string, mixed> $raw Raw settings.
	 */
	public function save( array $raw ): void {
		update_option( self::OPTION, $this->logic->normalize( $raw ) );
	}
}
