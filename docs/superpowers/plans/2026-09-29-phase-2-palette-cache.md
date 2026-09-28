# Sprint Illustrations — Phase 2 (Palette, Settings, Elementor Import, Cache) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Admins choose, edit or import a brand palette on a Settings page with a live preview, `"site"`/`"preset:<id>"` palettes resolve everywhere, and composed illustrations are cached on disk.

**Architecture:** Pure units, which are unit-tested with plain PHPUnit, do all the logic:
- `Palette\Contrast`, `PresetRepository`, `ColorValue`, `ElementorMapping` and `PaletteSettings`.
- `Cache\CacheKey`, `SvgCache` and `CachingComposer`, plus the `Compose\ComposesSvg` interface.

Thin WordPress classes do the I/O and are verified in the Local site:
- `Settings\SitePalette` (the option).
- `Integrations\Elementor\ColorSource` and `Sync`.
- `Admin\SettingsPage` and `Admin\Notices`.
- `Cli\CacheCommand`.

`Plugin` wires them together.

**Tech Stack:** PHP 8.1+, WordPress 6.4+, PHPUnit 10.5, WPCS 3 + PHPCompatibilityWP, plain JS and CSS for the Settings page (no build step), Elementor 4.2.4 (optional, read-only).

**Spec:** `docs/superpowers/specs/2026-09-29-phase-2-palette-cache-design.md`. The code-explorer report on Elementor 4.2.4, which was verified against the source, gave these facts:
- The active kit comes from `get_option( 'elementor_active_kit' )` and `kits_manager->get_active_kit_for_frontend()` or `get_active_kit()`.
- `system_colors` and `custom_colors` items have the shape `{ _id, title, color }`, and the system IDs are `primary`, `secondary`, `text` and `accent`.
- Variables are read with `new Variables_Service( new Variables_Repository( $kit ), new Batch_Processor() )` and `->get_variables_list()`, which returns an array keyed by ID with `{ type, label, value, deleted? }`. Colour variables have `type` `global-color-variable`.
- The classes are `Elementor\Modules\Variables\Services\Variables_Service`, `Elementor\Modules\Variables\Storage\Variables_Repository` and `Elementor\Modules\Variables\Services\Batch_Operations\Batch_Processor`.
- Variables require the `e_variables` and `e_atomic_elements` experiments.
- Hooks: `elementor/document/after_save ($document, $data)`, and Variables fire no action of their own, so sync watches `updated_post_meta` and `added_post_meta` for `_elementor_global_variables`.

## Global Constraints

- PHP `>=8.1`, WordPress `>=6.4`, text domain `sprint-illustrations`, root namespace `SprintIllustrations\`.
- Every PHP file starts with `declare( strict_types=1 );`. Use WPCS formatting (tabs, spaces inside parentheses, Yoda conditions). Methods and variables are `snake_case`. Short array syntax is allowed.
- Pure namespaces (`Library`, `Compose`, `Palette`, `Security`, `Selection`, `Svg`, `Dev`, `Cache`, and `Cli` except `Cli\Command` and `Cli\CacheCommand`) must not call WordPress functions.
- Strauss stays pinned at `0.26.4`.
- The option name is `sprint_illustrations_palette`. The cache directory is `uploads/sprint-illustrations/cache/`. The cron hook is `sprint_illustrations_cache_gc`. The AJAX action is `sprint_illustrations_preview`. The purge action is `sprint_illustrations_purge`.
- The contrast threshold is `1.3:1` against `background`. Only **base** slots (`primary`, `secondary`, `accent`, `neutral`, `outline`) are flagged.
- Commands use `composer test -- <path>` from the plugin root. WP-CLI runs in Local's site shell, or as described in `CLAUDE.md` when used from an agent's shell.
- Commit messages end with the `Co-Authored-By:` trailer your environment specifies. It's omitted from the examples below.

## Deviations from the spec (decided while planning)

- `Palette::warnings()` flags base slots only, returned as `slot => message`. `-light` variant warnings fired on the default palette's intentionally soft highlights (secondary-light is 1.2:1), which would be noise.
- The WordPress option class is `Settings\SitePalette`, and the pure normalization and resolve logic lives in `Palette\PaletteSettings`. The spec's single `Palette\SiteRepository` is split this way so the `Palette` namespace stays pure and testable.
- `CacheKey::make()` replaces `CacheKey::for()`, whose name is a keyword.
- `Services` keeps its concrete `Composer`. Only `ContactSheet` switches to `ComposesSvg`. `Plugin::composer()` returns the caching composer.
- The preview AJAX has no `size` parameter. Preset cards render server-side on page load, through the caching composer. AJAX only redraws the stage, uncached.
- The skin and hair lists are six fixed hex fields each ("leave blank to remove"), so no add or remove script is needed.
- WP-CLI turns `--no-cache` into `cache=false`, so `compose` documents `[--[no-]cache]` and reads it with `\WP_CLI\Utils\get_flag_value()`.

## File map

| Path | Responsibility |
|---|---|
| `src/Palette/Contrast.php`, `src/Palette/Palette.php` (modify) | WCAG ratio; `Palette::warnings()` |
| `assets/palettes/presets.json`, `src/Palette/PresetRepository.php`, `src/Dev/ContactSheet.php` (modify) | Six presets; review palettes come from presets |
| `src/Palette/ColorValue.php`, `src/Palette/ElementorMapping.php` | CSS colour → hex; Elementor item → slot mapping |
| `src/Palette/PaletteSettings.php` | Option normalization and palette reference resolution (pure) |
| `src/Compose/ComposesSvg.php`, `src/Compose/Composer.php` (modify), `src/Compose/ComposedSvg.php` (modify) | Composer interface; array round-trip |
| `src/Cache/CacheKey.php`, `src/Cache/SvgCache.php`, `src/Cache/CachingComposer.php` | Disk cache |
| `src/Settings/SitePalette.php`, `src/Admin/Notices.php`, `src/Plugin.php` (modify), `sprint-illustrations.php` (modify), `src/Cli/Command.php` (modify), `src/Cli/CacheCommand.php`, `src/Admin/TestPage.php` (modify) | WordPress wiring, cron, CLI |
| `src/Integrations/Elementor/ColorSource.php`, `src/Integrations/Elementor/Sync.php` | Elementor read + keep synced |
| `src/Admin/Menu.php` (modify), `src/Admin/SettingsPage.php`, `assets/admin/settings.css`, `assets/admin/settings.js` | Settings page |
| `phpcs.xml.dist` (modify) | Add `Cache` to the pure-namespace exclusions |
| `tests/Unit/Palette/*`, `tests/Unit/Cache/*`, `tests/Unit/Compose/ComposedSvgTest.php` | Tests |

---

### Task 1: Contrast and palette warnings

**Files:**
- Create: `src/Palette/Contrast.php`
- Modify: `src/Palette/Palette.php`
- Test: `tests/Unit/Palette/ContrastTest.php`

**Interfaces:**
- Produces: `Contrast::MIN_RATIO` (float, 1.3), `Contrast::ratio( string $hex_a, string $hex_b ): float`, `Contrast::luminance( string $hex ): float`, `Palette::CONTRAST_SLOTS`, `Palette::warnings(): array<string, string>` (slot => message).

- [ ] **Step 1: Write the failing test**

`tests/Unit/Palette/ContrastTest.php`:

```php
<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Palette;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Palette\Contrast;
use SprintIllustrations\Palette\Palette;

final class ContrastTest extends TestCase {

	public function test_known_ratios(): void {
		$this->assertEqualsWithDelta( 21.0, Contrast::ratio( '#000000', '#ffffff' ), 0.01 );
		$this->assertEqualsWithDelta( 1.0, Contrast::ratio( '#5b5bd6', '#5b5bd6' ), 0.001 );
		$this->assertEqualsWithDelta( Contrast::ratio( '#ffffff', '#767676' ), Contrast::ratio( '#767676', '#ffffff' ), 0.0001 );
		$this->assertEqualsWithDelta( 4.54, Contrast::ratio( '#767676', '#ffffff' ), 0.01 );
	}

	public function test_invalid_hex_counts_as_black(): void {
		$this->assertEqualsWithDelta( 21.0, Contrast::ratio( 'nope', '#fff' ), 0.01 );
	}

	public function test_default_palette_has_no_warnings(): void {
		$this->assertSame( [], Palette::default()->warnings() );
	}

	public function test_low_contrast_base_slot_is_flagged(): void {
		$warnings = Palette::from_array(
			[
				'primary'    => '#f4f4f4',
				'background' => '#ffffff',
			]
		)->warnings();

		$this->assertSame( [ 'primary' ], array_keys( $warnings ) );
		$this->assertStringContainsString( 'Low contrast against background (1.1:1)', $warnings['primary'] );
	}
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `composer test -- tests/Unit/Palette/ContrastTest.php`
Expected: FAIL with `Class "SprintIllustrations\Palette\Contrast" not found`.

- [ ] **Step 3: Implement `src/Palette/Contrast.php`**

```php
<?php
/**
 * WCAG contrast maths.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Palette;

/**
 * Relative luminance and contrast ratio (WCAG 2.x).
 */
final class Contrast {

	/**
	 * Below this ratio against the background, a slot is flagged as hard to see.
	 */
	public const MIN_RATIO = 1.3;

	/**
	 * Contrast ratio between two colours, 1.0 to 21.0. Invalid colours count as black.
	 *
	 * @param string $hex_a Colour.
	 * @param string $hex_b Colour.
	 * @return float
	 */
	public static function ratio( string $hex_a, string $hex_b ): float {
		$a = self::luminance( $hex_a );
		$b = self::luminance( $hex_b );

		return ( max( $a, $b ) + 0.05 ) / ( min( $a, $b ) + 0.05 );
	}

	/**
	 * Relative luminance, 0.0 to 1.0.
	 *
	 * @param string $hex Colour.
	 * @return float
	 */
	public static function luminance( string $hex ): float {
		$hex     = Color::normalize_hex( $hex ) ?? '#000000';
		$channel = static function ( string $pair ): float {
			$c = hexdec( $pair ) / 255;

			return $c <= 0.03928 ? $c / 12.92 : ( ( $c + 0.055 ) / 1.055 ) ** 2.4;
		};

		return 0.2126 * $channel( substr( $hex, 1, 2 ) ) + 0.7152 * $channel( substr( $hex, 3, 2 ) ) + 0.0722 * $channel( substr( $hex, 5, 2 ) );
	}
}
```

- [ ] **Step 4: Add `CONTRAST_SLOTS` and `warnings()` to `src/Palette/Palette.php`**

Add after the `VARIANT_DELTA` constant:

```php
	/**
	 * Slots checked for contrast against the background.
	 */
	public const CONTRAST_SLOTS = [ 'primary', 'secondary', 'accent', 'neutral', 'outline' ];
```

Add after `resolve()`:

```php
	/**
	 * Base slots that are hard to see against the background.
	 *
	 * @return array<string, string> Slot => message.
	 */
	public function warnings(): array {
		$background = (string) $this->resolve( 'background' );
		$warnings   = [];

		foreach ( self::CONTRAST_SLOTS as $slot ) {
			$ratio = Contrast::ratio( (string) $this->resolve( $slot ), $background );

			if ( $ratio < Contrast::MIN_RATIO ) {
				$warnings[ $slot ] = sprintf( 'Low contrast against background (%s:1).', number_format( $ratio, 1 ) );
			}
		}

		return $warnings;
	}
```

- [ ] **Step 5: Run it to verify it passes**

Run: `composer test -- tests/Unit/Palette`
Expected: PASS (all Palette tests).

- [ ] **Step 6: Commit**

```bash
git add src/Palette/Contrast.php src/Palette/Palette.php tests/Unit/Palette/ContrastTest.php
git commit -m "feat(palette): WCAG contrast and base-slot warnings"
```

---

### Task 2: Presets

**Files:**
- Create: `assets/palettes/presets.json`, `src/Palette/PresetRepository.php`
- Modify: `src/Dev/ContactSheet.php` (`review_palettes()`)
- Test: `tests/Unit/Palette/PresetRepositoryTest.php`

**Interfaces:**
- Consumes: `Palette::from_array()`, `Palette::warnings()` (Task 1).
- Produces: `PresetRepository::from_file( string $file ): self` (throws `\RuntimeException` when the file is missing or not a JSON object), `PresetRepository::bundled(): self`, `->all(): array<string, array{label: string, palette: Palette}>`, `->get( string $id ): ?Palette`, `->ids(): array<string>`.

- [ ] **Step 1: Write the failing test**

`tests/Unit/Palette/PresetRepositoryTest.php`:

```php
<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Palette;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Dev\ContactSheet;
use SprintIllustrations\Palette\Palette;
use SprintIllustrations\Palette\PresetRepository;

final class PresetRepositoryTest extends TestCase {

	public function test_bundled_presets_in_order(): void {
		$this->assertSame( [ 'sprint', 'forest', 'night', 'ocean', 'sunset', 'mono' ], PresetRepository::bundled()->ids() );
	}

	public function test_sprint_equals_default_palette(): void {
		$this->assertSame( Palette::default()->to_array(), PresetRepository::bundled()->get( 'sprint' )->to_array() );
	}

	public function test_every_preset_is_readable(): void {
		foreach ( PresetRepository::bundled()->all() as $id => $preset ) {
			$this->assertNotSame( '', $preset['label'], $id );
			$this->assertSame( [], $preset['palette']->warnings(), "Preset $id has low-contrast slots." );
		}
	}

	public function test_unknown_preset_is_null(): void {
		$this->assertNull( PresetRepository::bundled()->get( 'nope' ) );
	}

	public function test_invalid_entries_are_skipped(): void {
		$file = tempnam( sys_get_temp_dir(), 'si-presets' );
		file_put_contents( $file, '{"ok":{"label":"OK","primary":"#123456"},"Bad Id":{"label":"x"},"list":[1,2]}' );

		$presets = PresetRepository::from_file( $file );
		unlink( $file );

		$this->assertSame( [ 'ok', 'list' ], $presets->ids() );
		$this->assertSame( '#123456', $presets->get( 'ok' )->resolve( 'primary' ) );
		$this->assertSame( 'list', $presets->all()['list']['label'] );
	}

	public function test_missing_file_throws(): void {
		$this->expectException( \RuntimeException::class );
		PresetRepository::from_file( sys_get_temp_dir() . '/si-missing-presets.json' );
	}

	public function test_review_palettes_come_from_presets(): void {
		$review = ContactSheet::review_palettes();

		$this->assertSame( [ 'Sprint', 'Forest', 'Night' ], array_keys( $review ) );
		$this->assertSame( '#2f7d5b', $review['Forest']->resolve( 'primary' ) );
	}
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `composer test -- tests/Unit/Palette/PresetRepositoryTest.php`
Expected: FAIL with `Class "SprintIllustrations\Palette\PresetRepository" not found`.

- [ ] **Step 3: Write `assets/palettes/presets.json`**

```json
{
    "sprint": { "label": "Sprint", "primary": "#5b5bd6", "secondary": "#ffb224", "accent": "#ff6b6b", "neutral": "#2b2d42", "background": "#eef0ff", "outline": "#2b2d42" },
    "forest": { "label": "Forest", "primary": "#2f7d5b", "secondary": "#f2c14e", "accent": "#f25c54", "neutral": "#233038", "background": "#e8f3ee", "outline": "#233038" },
    "night": { "label": "Night", "primary": "#8b5cf6", "secondary": "#22d3ee", "accent": "#f472b6", "neutral": "#1e1b4b", "background": "#ede9fe", "outline": "#1e1b4b" },
    "ocean": { "label": "Ocean", "primary": "#0e7490", "secondary": "#fbbf24", "accent": "#f43f5e", "neutral": "#1e293b", "background": "#e0f2fe", "outline": "#1e293b" },
    "sunset": { "label": "Sunset", "primary": "#e8590c", "secondary": "#7048e8", "accent": "#fcc419", "neutral": "#2d1e2f", "background": "#fff4e6", "outline": "#2d1e2f" },
    "mono": { "label": "Mono", "primary": "#495057", "secondary": "#868e96", "accent": "#fa5252", "neutral": "#212529", "background": "#f1f3f5", "outline": "#212529" }
}
```

(Base-slot contrast was checked while planning: the lowest is 1.45:1, ocean's secondary.)

- [ ] **Step 4: Implement `src/Palette/PresetRepository.php`**

```php
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
```

- [ ] **Step 5: Replace `review_palettes()` in `src/Dev/ContactSheet.php`**

Add `use SprintIllustrations\Palette\PresetRepository;` next to the other `use` statements, and replace the whole `review_palettes()` method with:

```php
	/**
	 * Presets used for review: the default plus two contrasting ones.
	 *
	 * @return array<string, Palette>
	 */
	public static function review_palettes(): array {
		$presets  = PresetRepository::bundled()->all();
		$palettes = [];

		foreach ( [ 'sprint', 'forest', 'night' ] as $id ) {
			$palettes[ $presets[ $id ]['label'] ] = $presets[ $id ]['palette'];
		}

		return $palettes;
	}
```

- [ ] **Step 6: Run the tests to verify they pass**

Run: `composer test`
Expected: PASS (all tests, including `StarterPackTest`, which uses `review_palettes()`).

- [ ] **Step 7: Commit**

```bash
git add assets/palettes/presets.json src/Palette/PresetRepository.php src/Dev/ContactSheet.php tests/Unit/Palette/PresetRepositoryTest.php
git commit -m "feat(palette): six bundled presets; review palettes read from them"
```

---

### Task 3: CSS colour values and Elementor mapping

**Files:**
- Create: `src/Palette/ColorValue.php`, `src/Palette/ElementorMapping.php`
- Test: `tests/Unit/Palette/ColorValueTest.php`, `tests/Unit/Palette/ElementorMappingTest.php`

**Interfaces:**
- Consumes: `Color::normalize_hex()`, `Palette::SLOTS`.
- Produces:
  - `ColorValue::to_hex( string $css ): ?string`.
  - `new ElementorMapping( array $items )`, where `$items` is `array<array{id: string, label: string, value: string}>` and IDs are `kit:<_id>` or `var:<id>`.
  - `->choices(): array<array{id: string, label: string, hex: ?string}>`.
  - `->suggest(): array<string, string>` (slot => item ID).
  - `->apply( array $map, array $current ): array{colors: array<string, string>, missing: array<string>}`.

- [ ] **Step 1: Write the failing tests**

`tests/Unit/Palette/ColorValueTest.php`:

```php
<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Palette;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SprintIllustrations\Palette\ColorValue;

final class ColorValueTest extends TestCase {

	public static function values(): array {
		return [
			'short hex'           => [ '#ABC', '#aabbcc' ],
			'long hex'            => [ '#6EC1E4', '#6ec1e4' ],
			'hex with alpha'      => [ '#6ec1e480', '#6ec1e4' ],
			'short hex alpha'     => [ '#abcd', '#aabbcc' ],
			'padded'              => [ '  #6ec1e4 ', '#6ec1e4' ],
			'rgb commas'          => [ 'rgb(110, 193, 228)', '#6ec1e4' ],
			'rgba'                => [ 'rgba(110,193,228,0.5)', '#6ec1e4' ],
			'rgb space syntax'    => [ 'rgb(110 193 228 / 50%)', '#6ec1e4' ],
			'rgb out of range'    => [ 'rgb(300, 0, 0)', null ],
			'css variable'        => [ 'var(--e-global-color-primary)', null ],
			'hsl'                 => [ 'hsl(0 0% 0%)', null ],
			'named colour'        => [ 'red', null ],
			'empty'               => [ '', null ],
			'hex without hash'    => [ '6ec1e4', null ],
		];
	}

	#[DataProvider( 'values' )]
	public function test_to_hex( string $css, ?string $expected ): void {
		$this->assertSame( $expected, ColorValue::to_hex( $css ) );
	}
}
```

`tests/Unit/Palette/ElementorMappingTest.php`:

```php
<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Palette;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Palette\ElementorMapping;

final class ElementorMappingTest extends TestCase {

	private function kit(): array {
		return [
			[ 'id' => 'kit:primary', 'label' => 'Primary', 'value' => '#6EC1E4' ],
			[ 'id' => 'kit:secondary', 'label' => 'Secondary', 'value' => '#54595F' ],
			[ 'id' => 'kit:text', 'label' => 'Text', 'value' => '#7A7A7A' ],
			[ 'id' => 'kit:accent', 'label' => 'Accent', 'value' => '#61CE70' ],
			[ 'id' => 'kit:a1b2c3', 'label' => 'Page background', 'value' => '#F4F6FF' ],
		];
	}

	public function test_suggests_kit_system_colours(): void {
		$this->assertSame(
			[
				'accent'     => 'kit:accent',
				'background' => 'kit:a1b2c3',
				'neutral'    => 'kit:text',
				'outline'    => 'kit:text',
				'primary'    => 'kit:primary',
				'secondary'  => 'kit:secondary',
			],
			( new ElementorMapping( $this->kit() ) )->suggest()
		);
	}

	public function test_variables_named_after_slots_override_and_win_background(): void {
		$items = array_merge(
			[
				[ 'id' => 'var:e-gv-1', 'label' => 'Primary', 'value' => '#4F46E5' ],
				[ 'id' => 'var:e-gv-2', 'label' => 'brand-bg', 'value' => '#FAFAFF' ],
				[ 'id' => 'var:e-gv-3', 'label' => 'accent', 'value' => 'var(--e-gv-1)' ],
			],
			$this->kit()
		);

		$map = ( new ElementorMapping( $items ) )->suggest();

		$this->assertSame( 'var:e-gv-1', $map['primary'] );
		$this->assertSame( 'var:e-gv-2', $map['background'] );
		$this->assertSame( 'kit:accent', $map['accent'], 'A variable that is not importable never overrides.' );
	}

	public function test_nothing_suggested_without_items(): void {
		$this->assertSame( [], ( new ElementorMapping( [] ) )->suggest() );
	}

	public function test_apply_uses_mapped_colours_and_reports_missing(): void {
		$current = [
			'primary'    => '#111111',
			'background' => '#eeeeee',
		];
		$result  = ( new ElementorMapping( $this->kit() ) )->apply(
			[
				'primary'    => 'kit:primary',
				'background' => 'var:e-gv-9',
				'bogus'      => 'kit:text',
			],
			$current
		);

		$this->assertSame(
			[
				'primary'    => '#6ec1e4',
				'background' => '#eeeeee',
			],
			$result['colors']
		);
		$this->assertSame( [ 'var:e-gv-9' ], $result['missing'] );
	}

	public function test_choices_mark_unimportable_items(): void {
		$choices = ( new ElementorMapping( [ [ 'id' => 'var:e-gv-3', 'label' => 'Link', 'value' => 'var(--x)' ] ] ) )->choices();

		$this->assertSame(
			[
				[
					'id'    => 'var:e-gv-3',
					'label' => 'Link',
					'hex'   => null,
				],
			],
			$choices
		);
	}
}
```

- [ ] **Step 2: Run them to verify they fail**

Run: `composer test -- tests/Unit/Palette`
Expected: FAIL with `Class "SprintIllustrations\Palette\ColorValue" not found` and `Class "SprintIllustrations\Palette\ElementorMapping" not found`.

- [ ] **Step 3: Implement `src/Palette/ColorValue.php`**

```php
<?php
/**
 * CSS colour strings to hex.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Palette;

/**
 * Converts the colour formats Elementor stores into "#rrggbb". Alpha is dropped.
 */
final class ColorValue {

	/**
	 * Convert a CSS colour to hex.
	 *
	 * @param string $css "#rgb", "#rgba", "#rrggbb", "#rrggbbaa", "rgb()" or "rgba()".
	 * @return string|null Null for anything else (var(), hsl(), names, invalid).
	 */
	public static function to_hex( string $css ): ?string {
		$css = strtolower( trim( $css ) );

		if ( preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6})$/D', $css ) ) {
			return Color::normalize_hex( $css );
		}

		if ( preg_match( '/^#([0-9a-f]{3})[0-9a-f]$/D', $css, $m ) || preg_match( '/^#([0-9a-f]{6})[0-9a-f]{2}$/D', $css, $m ) ) {
			return Color::normalize_hex( $m[1] );
		}

		$sep = '(?:\s*,\s*|\s+)';
		if ( preg_match( '/^rgba?\(\s*(\d{1,3})' . $sep . '(\d{1,3})' . $sep . '(\d{1,3})\s*(?:[,\/]\s*[\d.]+%?\s*)?\)$/D', $css, $m ) ) {
			$channels = [ (int) $m[1], (int) $m[2], (int) $m[3] ];

			return max( $channels ) > 255 ? null : sprintf( '#%02x%02x%02x', ...$channels );
		}

		return null;
	}
}
```

- [ ] **Step 4: Implement `src/Palette/ElementorMapping.php`**

```php
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
```

- [ ] **Step 5: Run them to verify they pass**

Run: `composer test -- tests/Unit/Palette`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add src/Palette/ColorValue.php src/Palette/ElementorMapping.php tests/Unit/Palette/ColorValueTest.php tests/Unit/Palette/ElementorMappingTest.php
git commit -m "feat(palette): CSS colour parsing and Elementor slot mapping"
```

---

### Task 4: Palette settings (pure normalization and resolution)

**Files:**
- Create: `src/Palette/PaletteSettings.php`
- Test: `tests/Unit/Palette/PaletteSettingsTest.php`

**Interfaces:**
- Consumes: `PresetRepository` (Task 2), `Palette`.
- Produces:
  - `PaletteSettings::SOURCES`, `PaletteSettings::MAX_TONES` (6).
  - `new PaletteSettings( PresetRepository $presets )`.
  - `->normalize( mixed $raw ): array{source: string, preset: string, colors: array<string, string>, skin: array<string>, hair: array<string>, elementor: array{map: array<string, string>, sync: bool}}`.
  - `->palette( array $settings ): Palette`.
  - `->resolve( string|array $ref, array $settings ): Palette`.

- [ ] **Step 1: Write the failing test**

`tests/Unit/Palette/PaletteSettingsTest.php`:

```php
<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Palette;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Palette\Palette;
use SprintIllustrations\Palette\PaletteSettings;
use SprintIllustrations\Palette\PresetRepository;

final class PaletteSettingsTest extends TestCase {

	private PaletteSettings $settings;

	protected function setUp(): void {
		$this->settings = new PaletteSettings( PresetRepository::bundled() );
	}

	public function test_missing_option_means_sprint_preset(): void {
		$normalized = $this->settings->normalize( false );

		$this->assertSame( 'preset', $normalized['source'] );
		$this->assertSame( 'sprint', $normalized['preset'] );
		$this->assertSame( '#5b5bd6', $normalized['colors']['primary'] );
		$this->assertSame( Palette::default()->to_array()['skin'], $normalized['skin'] );
		$this->assertSame(
			[
				'map'  => [],
				'sync' => false,
			],
			$normalized['elementor']
		);
	}

	public function test_preset_source_copies_preset_colours(): void {
		$normalized = $this->settings->normalize(
			[
				'source' => 'preset',
				'preset' => 'forest',
				'colors' => [ 'primary' => '#000000' ],
			]
		);

		$this->assertSame( '#2f7d5b', $normalized['colors']['primary'] );
	}

	public function test_unknown_source_and_preset_fall_back(): void {
		$normalized = $this->settings->normalize(
			[
				'source' => 'magic',
				'preset' => 'nope',
			]
		);

		$this->assertSame( 'preset', $normalized['source'] );
		$this->assertSame( 'sprint', $normalized['preset'] );
	}

	public function test_custom_colours_are_validated(): void {
		$normalized = $this->settings->normalize(
			[
				'source' => 'custom',
				'colors' => [
					'primary'      => '#ABC',
					'secondary'    => 'javascript:alert(1)',
					'primary-dark' => '#000000',
					'bogus'        => '#123456',
				],
				'skin'   => [ '#111111', '', 'nope', '#222', '#333333', '#444444', '#555555', '#666666', '#777777' ],
				'hair'   => 'not a list',
			]
		);

		$this->assertSame( '#aabbcc', $normalized['colors']['primary'] );
		$this->assertSame( '#ffb224', $normalized['colors']['secondary'] );
		$this->assertSame( Palette::SLOTS, array_keys( $normalized['colors'] ) );
		$this->assertSame( [ '#111111', '#222222', '#333333', '#444444', '#555555', '#666666' ], $normalized['skin'] );
		$this->assertSame( Palette::default()->to_array()['hair'], $normalized['hair'] );
	}

	public function test_elementor_map_is_filtered(): void {
		$normalized = $this->settings->normalize(
			[
				'source'    => 'elementor',
				'elementor' => [
					'map'  => [
						'primary'   => 'kit:primary',
						'accent'    => 'var:e-gv-3',
						'neutral'   => '',
						'secondary' => 'evil:<script>',
						'bogus'     => 'kit:text',
					],
					'sync' => '1',
				],
			]
		);

		$this->assertSame( 'elementor', $normalized['source'] );
		$this->assertSame(
			[
				'primary' => 'kit:primary',
				'accent'  => 'var:e-gv-3',
			],
			$normalized['elementor']['map']
		);
		$this->assertTrue( $normalized['elementor']['sync'] );
	}

	public function test_normalize_is_idempotent(): void {
		$once = $this->settings->normalize(
			[
				'source' => 'custom',
				'colors' => [ 'accent' => '#00ff00' ],
			]
		);

		$this->assertSame( $once, $this->settings->normalize( $once ) );
	}

	public function test_resolve_references(): void {
		$site = $this->settings->normalize(
			[
				'source' => 'custom',
				'colors' => [ 'primary' => '#010203' ],
			]
		);

		$this->assertSame( '#010203', $this->settings->resolve( 'site', $site )->resolve( 'primary' ) );
		$this->assertSame( '#0e7490', $this->settings->resolve( 'preset:ocean', $site )->resolve( 'primary' ) );
		$this->assertSame( '#5b5bd6', $this->settings->resolve( 'preset:nope', $site )->resolve( 'primary' ) );
		$this->assertSame( '#5b5bd6', $this->settings->resolve( 'default', $site )->resolve( 'primary' ) );
		$this->assertSame( '#abcdef', $this->settings->resolve( [ 'primary' => '#abcdef' ], $site )->resolve( 'primary' ) );
	}
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `composer test -- tests/Unit/Palette/PaletteSettingsTest.php`
Expected: FAIL with `Class "SprintIllustrations\Palette\PaletteSettings" not found`.

- [ ] **Step 3: Implement `src/Palette/PaletteSettings.php`**

```php
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
	 * @param string|array<string, mixed>                                                      $ref      Reference.
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
```

- [ ] **Step 4: Run it to verify it passes**

Run: `composer test -- tests/Unit/Palette`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Palette/PaletteSettings.php tests/Unit/Palette/PaletteSettingsTest.php
git commit -m "feat(palette): site palette settings normalization and references"
```

---

### Task 5: Composer interface, round-trip and the disk cache

**Files:**
- Create: `src/Compose/ComposesSvg.php`, `src/Cache/CacheKey.php`, `src/Cache/SvgCache.php`
- Modify: `src/Compose/Composer.php` (implements the interface), `src/Compose/ComposedSvg.php` (`to_array`/`from_array`), `phpcs.xml.dist`
- Test: `tests/Unit/Compose/ComposedSvgTest.php`, `tests/Unit/Cache/CacheKeyTest.php`, `tests/Unit/Cache/SvgCacheTest.php`

**Interfaces:**
- Consumes: `SceneSpec::to_array()` and `from_array()`, `Palette::hash()`.
- Produces:
  - `interface ComposesSvg { compose( SceneSpec, Palette ): ComposedSvg }`.
  - `ComposedSvg::to_array(): array{markup: string, spec: array, warnings: array<string>}` and `ComposedSvg::from_array( array ): ?ComposedSvg`.
  - `CacheKey::make( SceneSpec, Palette, string $manifest_version, string $plugin_version ): string` (40 hex characters).
  - `new SvgCache( string $dir )`, `->get( string $key ): ?ComposedSvg`, `->put( string $key, ComposedSvg ): void`, `->writable(): bool`, `->purge(): int`, `->stats(): array{files: int, bytes: int}`, `->collect_garbage( int $max_age_seconds, ?int $now = null ): int`, `->directory(): string`.

- [ ] **Step 1: Write the failing tests**

`tests/Unit/Compose/ComposedSvgTest.php`:

```php
<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Compose;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Compose\ComposedSvg;
use SprintIllustrations\Compose\SceneSpec;

final class ComposedSvgTest extends TestCase {

	public function test_round_trip(): void {
		$svg = new ComposedSvg(
			'<svg xmlns="http://www.w3.org/2000/svg"/>',
			SceneSpec::from_array(
				[
					'template' => 'fixture-hero',
					'seed'     => 4,
					'picks'    => [ 'subject' => 'char-stick' ],
				]
			),
			[ 'note' ]
		);

		$copy = ComposedSvg::from_array( json_decode( (string) json_encode( $svg->to_array() ), true ) );

		$this->assertSame( $svg->markup, $copy->markup );
		$this->assertSame( $svg->spec->to_array(), $copy->spec->to_array() );
		$this->assertSame( [ 'note' ], $copy->warnings );
	}

	public function test_from_array_rejects_bad_shapes(): void {
		$this->assertNull( ComposedSvg::from_array( [] ) );
		$this->assertNull(
			ComposedSvg::from_array(
				[
					'markup'   => 1,
					'spec'     => [],
					'warnings' => [],
				]
			)
		);
	}
}
```

`tests/Unit/Cache/CacheKeyTest.php`:

```php
<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Cache;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Cache\CacheKey;
use SprintIllustrations\Compose\SceneSpec;
use SprintIllustrations\Palette\Palette;

final class CacheKeyTest extends TestCase {

	private function key( array $spec = [], ?Palette $palette = null, string $manifest = '3', string $plugin = '0.2.0' ): string {
		return CacheKey::make( SceneSpec::from_array( $spec + [ 'template' => 'hero-left-character' ] ), $palette ?? Palette::default(), $manifest, $plugin );
	}

	public function test_is_sha1_and_stable(): void {
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{40}$/D', $this->key() );
		$this->assertSame( $this->key(), $this->key() );
	}

	public function test_pick_order_does_not_matter(): void {
		$a = $this->key(
			[
				'picks' => [
					'subject' => 'char-ava-wave',
					'prop'    => 'obj-phone',
				],
			]
		);
		$b = $this->key(
			[
				'picks' => [
					'prop'    => 'obj-phone',
					'subject' => 'char-ava-wave',
				],
			]
		);

		$this->assertSame( $a, $b );
	}

	public function test_every_input_changes_the_key(): void {
		$base = $this->key();

		$this->assertNotSame( $base, $this->key( [ 'seed' => 2 ] ) );
		$this->assertNotSame( $base, $this->key( [], Palette::from_array( [ 'primary' => '#000000' ] ) ) );
		$this->assertNotSame( $base, $this->key( [], null, '4' ) );
		$this->assertNotSame( $base, $this->key( [], null, '3', '0.2.1' ) );
	}
}
```

`tests/Unit/Cache/SvgCacheTest.php`:

```php
<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Cache;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Cache\SvgCache;
use SprintIllustrations\Compose\ComposedSvg;
use SprintIllustrations\Compose\SceneSpec;

final class SvgCacheTest extends TestCase {

	private string $dir;

	protected function setUp(): void {
		$this->dir = sys_get_temp_dir() . '/si-cache-' . uniqid();
	}

	protected function tearDown(): void {
		if ( ! is_dir( $this->dir ) ) {
			return;
		}
		foreach ( array_diff( scandir( $this->dir ), [ '.', '..' ] ) as $name ) {
			unlink( $this->dir . '/' . $name );
		}
		rmdir( $this->dir );
	}

	private function svg(): ComposedSvg {
		return new ComposedSvg( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"/>', SceneSpec::from_array( [ 'template' => 'fixture-hero' ] ), [] );
	}

	public function test_round_trip_and_protection_files(): void {
		$cache = new SvgCache( $this->dir );
		$key   = sha1( 'a' );

		$this->assertNull( $cache->get( $key ) );
		$cache->put( $key, $this->svg() );

		$this->assertSame( $this->svg()->markup, $cache->get( $key )->markup );
		$this->assertFileExists( $this->dir . '/index.php' );
		$this->assertFileExists( $this->dir . '/.htaccess' );
		$this->assertSame( 1, $cache->stats()['files'] );
		$this->assertGreaterThan( 0, $cache->stats()['bytes'] );
	}

	public function test_purge(): void {
		$cache = new SvgCache( $this->dir );
		$cache->put( sha1( 'a' ), $this->svg() );
		$cache->put( sha1( 'b' ), $this->svg() );

		$this->assertSame( 2, $cache->purge() );
		$this->assertNull( $cache->get( sha1( 'a' ) ) );
		$this->assertSame( 0, $cache->stats()['files'] );
	}

	public function test_garbage_collection_uses_last_use(): void {
		$cache = new SvgCache( $this->dir );
		$old   = sha1( 'old' );
		$used  = sha1( 'used' );
		$cache->put( $old, $this->svg() );
		$cache->put( $used, $this->svg() );
		touch( $this->dir . "/$old.json", time() - 40 * 86400 );
		touch( $this->dir . "/$used.json", time() - 40 * 86400 );

		$cache->get( $used );

		$this->assertSame( 1, $cache->collect_garbage( 30 * 86400 ) );
		$this->assertNull( $cache->get( $old ) );
		$this->assertNotNull( $cache->get( $used ) );
	}

	public function test_corrupt_entry_is_dropped(): void {
		$cache = new SvgCache( $this->dir );
		$key   = sha1( 'bad' );
		$cache->put( $key, $this->svg() );
		file_put_contents( $this->dir . "/$key.json", '{nope' );

		$this->assertNull( $cache->get( $key ) );
		$this->assertFileDoesNotExist( $this->dir . "/$key.json" );
	}

	public function test_falls_back_to_memory_when_not_writable(): void {
		$file = tempnam( sys_get_temp_dir(), 'si-not-a-dir' );
		$cache = new SvgCache( $file );

		$this->assertFalse( $cache->writable() );
		$cache->put( sha1( 'a' ), $this->svg() );
		$this->assertNotNull( $cache->get( sha1( 'a' ) ) );
		$this->assertSame( 0, $cache->stats()['files'] );

		unlink( $file );
	}

	public function test_rejects_invalid_keys(): void {
		$this->expectException( \InvalidArgumentException::class );
		( new SvgCache( $this->dir ) )->get( '../../wp-config' );
	}
}
```

- [ ] **Step 2: Run them to verify they fail**

Run: `composer test -- tests/Unit/Cache`, then `composer test -- tests/Unit/Compose/ComposedSvgTest.php` (PHPUnit takes one path per run).
Expected: FAIL with `Class "SprintIllustrations\Cache\CacheKey" not found`, then `Call to undefined method SprintIllustrations\Compose\ComposedSvg::to_array()`.

- [ ] **Step 3: Create `src/Compose/ComposesSvg.php` and make `Composer` implement it**

```php
<?php
/**
 * Anything that turns a scene spec into SVG.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Compose;

use SprintIllustrations\Palette\Palette;

/**
 * Implemented by Composer and Cache\CachingComposer.
 */
interface ComposesSvg {

	/**
	 * Compose a scene.
	 *
	 * @param SceneSpec $spec    Scene request.
	 * @param Palette   $palette Resolved palette.
	 * @return ComposedSvg
	 * @throws CompositionException When the scene cannot be composed.
	 */
	public function compose( SceneSpec $spec, Palette $palette ): ComposedSvg;
}
```

In `src/Compose/Composer.php`, change `final class Composer {` to `final class Composer implements ComposesSvg {`.

- [ ] **Step 4: Add the round-trip to `src/Compose/ComposedSvg.php`** (after `with_instance_id()`)

```php
	/**
	 * Export for storage.
	 *
	 * @return array{markup: string, spec: array<string, mixed>, warnings: array<string>}
	 */
	public function to_array(): array {
		return [
			'markup'   => $this->markup,
			'spec'     => $this->spec->to_array(),
			'warnings' => $this->warnings,
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

		return new self( $data['markup'], SceneSpec::from_array( $data['spec'] ), array_values( array_filter( $data['warnings'], 'is_string' ) ) );
	}
```

- [ ] **Step 5: Create `src/Cache/CacheKey.php`**

```php
<?php
/**
 * Cache keys for composed SVG.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Cache;

use SprintIllustrations\Compose\SceneSpec;
use SprintIllustrations\Palette\Palette;

/**
 * Any change to the spec, palette, library or plugin produces a new key.
 */
final class CacheKey {

	/**
	 * Build a key.
	 *
	 * @param SceneSpec $spec             Spec.
	 * @param Palette   $palette          Resolved palette.
	 * @param string    $manifest_version Manifest::version().
	 * @param string    $plugin_version   Plugin version.
	 * @return string 40 hex characters.
	 */
	public static function make( SceneSpec $spec, Palette $palette, string $manifest_version, string $plugin_version ): string {
		return sha1(
			(string) json_encode(
				[
					'spec'     => self::sorted( $spec->to_array() ),
					'palette'  => $palette->hash(),
					'manifest' => $manifest_version,
					'plugin'   => $plugin_version,
				]
			)
		);
	}

	/**
	 * Sort map keys recursively (lists keep their order).
	 *
	 * @param array<mixed> $data Data.
	 * @return array<mixed>
	 */
	private static function sorted( array $data ): array {
		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) ) {
				$data[ $key ] = self::sorted( $value );
			}
		}

		if ( ! array_is_list( $data ) ) {
			ksort( $data );
		}

		return $data;
	}
}
```

- [ ] **Step 6: Create `src/Cache/SvgCache.php`**

```php
<?php
/**
 * File cache for composed SVG.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Cache;

use SprintIllustrations\Compose\ComposedSvg;

/**
 * One JSON file per key. Falls back to memory for the request when the directory is not writable.
 */
final class SvgCache {

	/**
	 * Entries kept in memory when the directory is not writable.
	 *
	 * @var array<string, ComposedSvg>
	 */
	private array $memory = [];

	/**
	 * Whether the directory is usable (null until checked).
	 *
	 * @var bool|null
	 */
	private ?bool $writable = null;

	/**
	 * Constructor.
	 *
	 * @param string $dir Cache directory (created on first write).
	 */
	public function __construct( private string $dir ) {
		$this->dir = rtrim( $dir, '/\\' );
	}

	/**
	 * Cached result, or null. A hit refreshes the file's modified time (used as "last used").
	 *
	 * @param string $key 40-character hex key.
	 * @return ComposedSvg|null
	 */
	public function get( string $key ): ?ComposedSvg {
		$file = $this->path( $key );

		if ( isset( $this->memory[ $key ] ) ) {
			return $this->memory[ $key ];
		}

		if ( ! is_file( $file ) ) {
			return null;
		}

		$data   = json_decode( (string) file_get_contents( $file ), true );
		$result = is_array( $data ) ? ComposedSvg::from_array( $data ) : null;

		if ( null === $result ) {
			unlink( $file );
			return null;
		}

		touch( $file );

		return $result;
	}

	/**
	 * Store a result.
	 *
	 * @param string      $key Key.
	 * @param ComposedSvg $svg Result.
	 */
	public function put( string $key, ComposedSvg $svg ): void {
		$file = $this->path( $key );

		if ( $this->writable() ) {
			file_put_contents( $file, (string) json_encode( $svg->to_array() ), LOCK_EX );
			return;
		}

		$this->memory[ $key ] = $svg;
	}

	/**
	 * Whether files can be written (creates the directory and its guard files on first call).
	 *
	 * @return bool
	 */
	public function writable(): bool {
		if ( null === $this->writable ) {
			$this->writable = $this->prepare();
		}

		return $this->writable;
	}

	/**
	 * Delete every entry.
	 *
	 * @return int Entries removed.
	 */
	public function purge(): int {
		$count = 0;
		foreach ( $this->entries() as $file ) {
			if ( unlink( $file ) ) {
				++$count;
			}
		}
		$this->memory = [];

		return $count;
	}

	/**
	 * Entry count and total size.
	 *
	 * @return array{files: int, bytes: int}
	 */
	public function stats(): array {
		$files = $this->entries();

		return [
			'files' => count( $files ),
			'bytes' => (int) array_sum( array_map( 'filesize', $files ) ),
		];
	}

	/**
	 * Delete entries not used for $max_age_seconds.
	 *
	 * @param int      $max_age_seconds Maximum age.
	 * @param int|null $now             Current time (tests).
	 * @return int Entries removed.
	 */
	public function collect_garbage( int $max_age_seconds, ?int $now = null ): int {
		$cutoff = ( $now ?? time() ) - $max_age_seconds;
		$count  = 0;

		foreach ( $this->entries() as $file ) {
			if ( filemtime( $file ) < $cutoff && unlink( $file ) ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Cache directory.
	 *
	 * @return string
	 */
	public function directory(): string {
		return $this->dir;
	}

	/**
	 * Create the directory with index.php and .htaccess guards.
	 *
	 * @return bool
	 */
	private function prepare(): bool {
		if ( file_exists( $this->dir ) && ! is_dir( $this->dir ) ) {
			return false;
		}

		if ( ! is_dir( $this->dir ) && ! mkdir( $this->dir, 0755, true ) && ! is_dir( $this->dir ) ) {
			return false;
		}

		if ( ! is_writable( $this->dir ) ) {
			return false;
		}

		if ( ! is_file( $this->dir . '/index.php' ) ) {
			file_put_contents( $this->dir . '/index.php', "<?php // Silence is golden.\n" );
		}
		if ( ! is_file( $this->dir . '/.htaccess' ) ) {
			file_put_contents( $this->dir . '/.htaccess', "Require all denied\n" );
		}

		return true;
	}

	/**
	 * Entry files.
	 *
	 * @return array<string>
	 */
	private function entries(): array {
		if ( ! is_dir( $this->dir ) ) {
			return [];
		}

		$files = glob( $this->dir . '/*.json' );

		return $files ? $files : [];
	}

	/**
	 * Entry path.
	 *
	 * @param string $key Key.
	 * @return string
	 * @throws \InvalidArgumentException When the key is not 40 hex characters.
	 */
	private function path( string $key ): string {
		if ( ! preg_match( '/^[a-f0-9]{40}$/D', $key ) ) {
			throw new \InvalidArgumentException( 'Invalid cache key.' );
		}

		return $this->dir . '/' . $key . '.json';
	}
}
```

- [ ] **Step 7: Add `Cache` to the pure-namespace exclusions in `phpcs.xml.dist`**

In both `exclude-pattern` lines that read `/src/(Library|Compose|Palette|Security|Selection|Svg|Dev|Cli)/*`, change the group to `(Library|Compose|Palette|Security|Selection|Svg|Dev|Cli|Cache)`. Also update the comment above them to list `Cache`.

- [ ] **Step 8: Run the tests to verify they pass**

Run: `composer test -- tests/Unit/Cache`, then `composer test -- tests/Unit/Compose`
Expected: PASS.

- [ ] **Step 9: Commit**

```bash
git add src/Compose/ComposesSvg.php src/Compose/Composer.php src/Compose/ComposedSvg.php src/Cache/CacheKey.php src/Cache/SvgCache.php phpcs.xml.dist tests/Unit/Compose/ComposedSvgTest.php tests/Unit/Cache/CacheKeyTest.php tests/Unit/Cache/SvgCacheTest.php
git commit -m "feat(cache): composer interface, cache keys and file cache"
```

---

### Task 6: Caching composer

**Files:**
- Create: `src/Cache/CachingComposer.php`
- Modify: `src/Dev/ContactSheet.php` (constructor type)
- Test: `tests/Unit/Cache/CachingComposerTest.php`

**Interfaces:**
- Consumes: `ComposesSvg`, `CacheKey::make()`, `SvgCache` (Task 5), `Security\Sanitizer::sanitize( string ): string` (throws `SanitizationException`).
- Produces: `new CachingComposer( ComposesSvg $inner, SvgCache $cache, Sanitizer $sanitizer, string $manifest_version, string $plugin_version )`. It implements `ComposesSvg`. `ContactSheet::__construct( ComposesSvg $composer )`.

- [ ] **Step 1: Write the failing test**

`tests/Unit/Cache/CachingComposerTest.php`:

```php
<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Cache;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Cache\CacheKey;
use SprintIllustrations\Cache\CachingComposer;
use SprintIllustrations\Cache\SvgCache;
use SprintIllustrations\Compose\ComposedSvg;
use SprintIllustrations\Compose\ComposesSvg;
use SprintIllustrations\Compose\SceneSpec;
use SprintIllustrations\Palette\Palette;
use SprintIllustrations\Security\Sanitizer;
use SprintIllustrations\Services;

final class CachingComposerTest extends TestCase {

	private string $dir;

	protected function setUp(): void {
		$this->dir = sys_get_temp_dir() . '/si-cc-' . uniqid();
	}

	protected function tearDown(): void {
		( new SvgCache( $this->dir ) )->purge();
		foreach ( [ 'index.php', '.htaccess' ] as $guard ) {
			if ( is_file( $this->dir . '/' . $guard ) ) {
				unlink( $this->dir . '/' . $guard );
			}
		}
		if ( is_dir( $this->dir ) ) {
			rmdir( $this->dir );
		}
	}

	/**
	 * A composer that counts calls and delegates to the fixture library (or returns a canned result).
	 */
	private function counting( ?ComposedSvg $canned = null ): ComposesSvg {
		$inner = Services::create( __DIR__ . '/../../fixtures/library' )->composer;

		return new class( $inner, $canned ) implements ComposesSvg {
			public int $calls = 0;

			public function __construct( private ComposesSvg $inner, private ?ComposedSvg $canned ) {}

			public function compose( SceneSpec $spec, Palette $palette ): ComposedSvg {
				++$this->calls;

				return $this->canned ?? $this->inner->compose( $spec, $palette );
			}
		};
	}

	private function caching( ComposesSvg $inner, ?SvgCache $cache = null ): CachingComposer {
		return new CachingComposer( $inner, $cache ?? new SvgCache( $this->dir ), new Sanitizer(), '1', '0.2.0' );
	}

	private function spec( int $seed = 1 ): SceneSpec {
		return SceneSpec::from_array(
			[
				'template' => 'fixture-hero',
				'seed'     => $seed,
			]
		);
	}

	public function test_second_call_is_a_hit(): void {
		$inner   = $this->counting();
		$caching = $this->caching( $inner );

		$first  = $caching->compose( $this->spec(), Palette::default() );
		$second = $caching->compose( $this->spec(), Palette::default() );

		$this->assertSame( 1, $inner->calls );
		$this->assertSame( $first->markup, $second->markup );
		$this->assertSame( $first->spec->to_array(), $second->spec->to_array() );
	}

	public function test_different_inputs_miss(): void {
		$inner   = $this->counting();
		$caching = $this->caching( $inner );

		$caching->compose( $this->spec(), Palette::default() );
		$caching->compose( $this->spec( 2 ), Palette::default() );
		$caching->compose( $this->spec(), Palette::from_array( [ 'primary' => '#000000' ] ) );

		$this->assertSame( 3, $inner->calls );
	}

	public function test_results_with_warnings_are_not_stored(): void {
		$inner   = $this->counting( new ComposedSvg( '<svg xmlns="http://www.w3.org/2000/svg"/>', $this->spec(), [ 'Pick "x" is not valid.' ] ) );
		$caching = $this->caching( $inner );

		$caching->compose( $this->spec(), Palette::default() );
		$caching->compose( $this->spec(), Palette::default() );

		$this->assertSame( 2, $inner->calls );
	}

	public function test_tampered_entries_are_sanitized_on_read(): void {
		$cache = new SvgCache( $this->dir );
		$key   = CacheKey::make( $this->spec(), Palette::default(), '1', '0.2.0' );
		$cache->put( $key, new ComposedSvg( '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><rect width="1" height="1"/></svg>', $this->spec(), [] ) );

		$result = $this->caching( $this->counting(), $cache )->compose( $this->spec(), Palette::default() );

		$this->assertStringNotContainsString( 'script', $result->markup );
		$this->assertStringContainsString( '<rect', $result->markup );
	}
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `composer test -- tests/Unit/Cache/CachingComposerTest.php`
Expected: FAIL with `Class "SprintIllustrations\Cache\CachingComposer" not found`.

- [ ] **Step 3: Implement `src/Cache/CachingComposer.php`**

```php
<?php
/**
 * Composer decorator backed by SvgCache.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Cache;

use SprintIllustrations\Compose\ComposedSvg;
use SprintIllustrations\Compose\ComposesSvg;
use SprintIllustrations\Compose\SceneSpec;
use SprintIllustrations\Palette\Palette;
use SprintIllustrations\Security\SanitizationException;
use SprintIllustrations\Security\Sanitizer;

/**
 * Serves repeats from disk. Only warning-free results are stored; every hit is re-sanitized.
 */
final class CachingComposer implements ComposesSvg {

	/**
	 * Constructor.
	 *
	 * @param ComposesSvg $inner            Real composer.
	 * @param SvgCache    $cache            Cache.
	 * @param Sanitizer   $sanitizer        Sanitizer (guards against tampered files).
	 * @param string      $manifest_version Manifest::version().
	 * @param string      $plugin_version   Plugin version.
	 */
	public function __construct(
		private ComposesSvg $inner,
		private SvgCache $cache,
		private Sanitizer $sanitizer,
		private string $manifest_version,
		private string $plugin_version,
	) {}

	/**
	 * Compose, using the cache when possible.
	 *
	 * @param SceneSpec $spec    Scene request.
	 * @param Palette   $palette Resolved palette.
	 * @return ComposedSvg
	 */
	public function compose( SceneSpec $spec, Palette $palette ): ComposedSvg {
		$key = CacheKey::make( $spec, $palette, $this->manifest_version, $this->plugin_version );
		$hit = $this->cache->get( $key );

		if ( null !== $hit ) {
			try {
				return new ComposedSvg( $this->sanitizer->sanitize( $hit->markup ), $hit->spec, $hit->warnings );
			} catch ( SanitizationException ) {
				// Unreadable entry: fall through, recompose and overwrite it.
			}
		}

		$result = $this->inner->compose( $spec, $palette );

		if ( [] === $result->warnings ) {
			$this->cache->put( $key, $result );
		}

		return $result;
	}
}
```

- [ ] **Step 4: Type `ContactSheet` against the interface**

In `src/Dev/ContactSheet.php`, replace `use SprintIllustrations\Compose\Composer;` with `use SprintIllustrations\Compose\ComposesSvg;`, and change the constructor to:

```php
	/**
	 * Constructor.
	 *
	 * @param ComposesSvg $composer Composer (plain or caching).
	 */
	public function __construct( private ComposesSvg $composer ) {}
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `composer test`
Expected: PASS (all tests).

If `test_second_call_is_a_hit` fails because the markup differs, the sanitizer isn't idempotent on composed output. Stop and report it rather than weakening the test.

- [ ] **Step 6: Commit**

```bash
git add src/Cache/CachingComposer.php src/Dev/ContactSheet.php tests/Unit/Cache/CachingComposerTest.php
git commit -m "feat(cache): caching composer with sanitize-on-read"
```

---

### Task 7: WordPress wiring — site palette, notices, cron, CLI

**Files:**
- Create: `src/Settings/SitePalette.php`, `src/Admin/Notices.php`, `src/Cli/CacheCommand.php`
- Modify: `src/Plugin.php`, `sprint-illustrations.php`, `src/Cli/Command.php`, `src/Admin/TestPage.php`

**Interfaces:**
- Consumes: `PaletteSettings`, `PresetRepository`, `SvgCache`, `CachingComposer`.
- Produces:
  - `SitePalette::OPTION`, `new SitePalette( PaletteSettings )`, `->settings(): array` (normalized), `->palette(): Palette`, `->resolve( string|array $ref ): Palette`, `->save( array $raw ): void`, `->normalize( mixed $raw ): array`.
  - `Notices::add( string $message, string $type = 'warning' ): void`, `->register(): void`.
  - On `Plugin`: `presets(): PresetRepository`, `site_palette(): SitePalette`, `cache(): SvgCache`, `composer(): ComposesSvg`, `Plugin::CRON_HOOK`, `Plugin::activate()`, `Plugin::deactivate()`, `->collect_garbage(): void`.

WordPress-facing, so there's no PHPUnit test. Verification uses WP-CLI in Step 8.

- [ ] **Step 1: Create `src/Settings/SitePalette.php`**

```php
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
```

- [ ] **Step 2: Create `src/Admin/Notices.php`**

```php
<?php
/**
 * One-off admin notices.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Admin;

/**
 * Queues notices in a transient and shows them once to administrators.
 */
final class Notices {

	private const TRANSIENT = 'sprint_illustrations_notices';

	private const TYPES = [ 'success', 'warning', 'error', 'info' ];

	/**
	 * Queue a notice (keeps the last five).
	 *
	 * @param string $message Plain text.
	 * @param string $type    success, warning, error or info.
	 */
	public static function add( string $message, string $type = 'warning' ): void {
		$list   = get_transient( self::TRANSIENT );
		$list   = is_array( $list ) ? $list : [];
		$list[] = [
			'type'    => in_array( $type, self::TYPES, true ) ? $type : 'info',
			'message' => $message,
		];

		set_transient( self::TRANSIENT, array_slice( $list, -5 ), DAY_IN_SECONDS );
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'admin_notices', [ $this, 'render' ] );
	}

	/**
	 * Print and clear queued notices.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$list = get_transient( self::TRANSIENT );
		if ( ! is_array( $list ) || ! $list ) {
			return;
		}

		delete_transient( self::TRANSIENT );

		foreach ( $list as $notice ) {
			printf(
				'<div class="notice notice-%1$s is-dismissible"><p><strong>%2$s</strong> %3$s</p></div>',
				esc_attr( (string) ( $notice['type'] ?? 'info' ) ),
				esc_html__( 'Sprint Illustrations:', 'sprint-illustrations' ),
				esc_html( (string) ( $notice['message'] ?? '' ) )
			);
		}
	}
}
```

- [ ] **Step 3: Create `src/Cli/CacheCommand.php`**

```php
<?php
/**
 * WP-CLI cache commands.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Cli;

use SprintIllustrations\Plugin;

/**
 * Inspect and clear the illustration cache.
 */
final class CacheCommand {

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( private Plugin $plugin ) {}

	/**
	 * Show the number and total size of cached illustrations.
	 *
	 * ## EXAMPLES
	 *
	 *     wp sprint-illustrations cache stats
	 *
	 * @param array<int, string>    $args       Positional args.
	 * @param array<string, string> $assoc_args Options.
	 */
	public function stats( array $args = [], array $assoc_args = [] ): void {
		$cache = $this->plugin->cache();
		$stats = $cache->stats();

		\WP_CLI::line( sprintf( '%d files, %s, in %s', $stats['files'], size_format( $stats['bytes'] ) ? size_format( $stats['bytes'] ) : '0 B', $cache->directory() ) );

		if ( ! $cache->writable() ) {
			\WP_CLI::warning( 'The cache folder is not writable, so illustrations are recomposed on every request.' );
		}
	}

	/**
	 * Delete every cached illustration.
	 *
	 * ## EXAMPLES
	 *
	 *     wp sprint-illustrations cache purge
	 *
	 * @param array<int, string>    $args       Positional args.
	 * @param array<string, string> $assoc_args Options.
	 */
	public function purge( array $args = [], array $assoc_args = [] ): void {
		\WP_CLI::success( sprintf( 'Removed %d cached illustrations.', $this->plugin->cache()->purge() ) );
	}
}
```

- [ ] **Step 4: Extend `src/Plugin.php`**

Replace the `use` block with:

```php
use SprintIllustrations\Admin\Menu;
use SprintIllustrations\Admin\Notices;
use SprintIllustrations\Cache\CachingComposer;
use SprintIllustrations\Cache\SvgCache;
use SprintIllustrations\Cli\CacheCommand;
use SprintIllustrations\Cli\Command;
use SprintIllustrations\Compose\ComposesSvg;
use SprintIllustrations\Integrations\Elementor\ColorSource;
use SprintIllustrations\Integrations\Elementor\Sync;
use SprintIllustrations\Palette\PaletteSettings;
use SprintIllustrations\Palette\PresetRepository;
use SprintIllustrations\Settings\SitePalette;
```

(`ColorSource` and `Sync` are created in Task 8. Until then, leave those two `use` lines and the `Sync` registration below out, and add them in Task 8 Step 3.)

Add after the `$services` property:

```php
	/**
	 * Daily cache clean-up event.
	 */
	public const CRON_HOOK = 'sprint_illustrations_cache_gc';

	/**
	 * Entries unused for this long are deleted.
	 */
	public const CACHE_MAX_AGE = 30 * DAY_IN_SECONDS;

	/**
	 * Presets.
	 *
	 * @var PresetRepository|null
	 */
	private ?PresetRepository $presets = null;

	/**
	 * Site palette option.
	 *
	 * @var SitePalette|null
	 */
	private ?SitePalette $site_palette = null;

	/**
	 * Disk cache.
	 *
	 * @var SvgCache|null
	 */
	private ?SvgCache $cache = null;

	/**
	 * Caching composer.
	 *
	 * @var ComposesSvg|null
	 */
	private ?ComposesSvg $composer = null;
```

Add after `services()`:

```php
	/**
	 * Bundled presets.
	 *
	 * @return PresetRepository
	 */
	public function presets(): PresetRepository {
		return $this->presets ??= PresetRepository::bundled();
	}

	/**
	 * Site palette option.
	 *
	 * @return SitePalette
	 */
	public function site_palette(): SitePalette {
		return $this->site_palette ??= new SitePalette( new PaletteSettings( $this->presets() ) );
	}

	/**
	 * Disk cache in uploads/sprint-illustrations/cache.
	 *
	 * @return SvgCache
	 */
	public function cache(): SvgCache {
		return $this->cache ??= new SvgCache( $this->user_library_dir() . '/cache' );
	}

	/**
	 * Composer that serves repeats from the cache.
	 *
	 * @return ComposesSvg
	 */
	public function composer(): ComposesSvg {
		return $this->composer ??= new CachingComposer(
			$this->services()->composer,
			$this->cache(),
			$this->services()->sanitizer,
			$this->services()->manifest->version(),
			SPRINT_ILLUSTRATIONS_VERSION
		);
	}

	/**
	 * Schedule the daily cache clean-up (idempotent; also runs on init for sites activated before phase 2).
	 */
	public static function activate(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Remove the scheduled clean-up.
	 */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * Cron callback.
	 */
	public function collect_garbage(): void {
		$this->cache()->collect_garbage( self::CACHE_MAX_AGE );
	}
```

Replace `register_hooks()` with:

```php
	/**
	 * Register hooks.
	 */
	private function register_hooks(): void {
		add_action( 'init', [ self::class, 'activate' ] );
		add_action( self::CRON_HOOK, [ $this, 'collect_garbage' ] );

		if ( is_admin() ) {
			( new Menu( $this ) )->register();
			( new Notices() )->register();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'sprint-illustrations', new Command( $this ) );
			\WP_CLI::add_command( 'sprint-illustrations cache', new CacheCommand( $this ) );
		}
	}
```

- [ ] **Step 5: Register activation hooks in `sprint-illustrations.php`**

After the two `require_once` lines, add:

```php
register_activation_hook( __FILE__, [ \SprintIllustrations\Plugin::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ \SprintIllustrations\Plugin::class, 'deactivate' ] );
```

Also bump the version to `0.2.0` in both the `Version:` header and the `SPRINT_ILLUSTRATIONS_VERSION` constant.

- [ ] **Step 6: Update `compose` in `src/Cli/Command.php`**

Replace the `compose()` docblock options and body with:

```php
	/**
	 * Compose an illustration and print (or save) the SVG.
	 *
	 * ## OPTIONS
	 *
	 * [--template=<id>]
	 * : Template ID. Omit to choose one from --keywords.
	 *
	 * [--seed=<n>]
	 * : Seed.
	 * ---
	 * default: 1
	 * ---
	 *
	 * [--keywords=<csv>]
	 * : Comma-separated keywords.
	 *
	 * [--palette=<ref>]
	 * : "site", "default" or "preset:<id>".
	 * ---
	 * default: site
	 * ---
	 *
	 * [--[no-]cache]
	 * : Use the illustration cache (default). --no-cache always recomposes.
	 *
	 * [--out=<file>]
	 * : Write to a file instead of STDOUT.
	 *
	 * ## EXAMPLES
	 *
	 *     wp sprint-illustrations compose --template=hero-left-character --seed=7
	 *     wp sprint-illustrations compose --keywords="remote team" --palette=preset:ocean --out=hero.svg
	 *
	 * @param array<int, string>         $args       Positional args.
	 * @param array<string, string|bool> $assoc_args Options.
	 */
	public function compose( array $args, array $assoc_args ): void {
		$spec = SceneSpec::from_array(
			[
				'template' => $assoc_args['template'] ?? null,
				'seed'     => $assoc_args['seed'] ?? 1,
				'keywords' => $assoc_args['keywords'] ?? '',
				'palette'  => $assoc_args['palette'] ?? 'site',
			]
		);

		$composer = \WP_CLI\Utils\get_flag_value( $assoc_args, 'cache', true )
			? $this->plugin->composer()
			: $this->plugin->services()->composer;

		try {
			$result = $composer->compose( $spec, $this->plugin->site_palette()->resolve( $spec->palette ) );
		} catch ( CompositionException $e ) {
			\WP_CLI::error( $e->getMessage() );
		}

		foreach ( $result->warnings as $warning ) {
			\WP_CLI::warning( $warning );
		}

		$svg = $result->with_instance_id( 'si-cli' );

		if ( isset( $assoc_args['out'] ) ) {
			file_put_contents( (string) $assoc_args['out'], $svg . "\n" );
			\WP_CLI::success( sprintf( 'Wrote %s (template %s, seed %d).', $assoc_args['out'], (string) $result->spec->template, $result->spec->seed ) );
			return;
		}

		\WP_CLI::line( $svg );
	}
```

Remove the now-unused `use SprintIllustrations\Palette\Palette;` from `Command.php`.

- [ ] **Step 7: Show the site palette on the Test page**

In `src/Admin/TestPage.php`:
1. Change `$sheet     = new ContactSheet( $services->composer );` to `$sheet     = new ContactSheet( $this->plugin->composer() );`.
2. Change the render call's palettes argument from `ContactSheet::review_palettes()` to `[ __( 'Site palette', 'sprint-illustrations' ) => $this->plugin->site_palette()->palette() ] + ContactSheet::review_palettes()`.
3. Change the hidden field `value="<?php echo esc_attr( Menu::SLUG ); ?>"` to `value="<?php echo esc_attr( Menu::TEST_SLUG ); ?>"`. The constant is added in Task 9. Until then, keep `Menu::SLUG`.

- [ ] **Step 8: Verify in WordPress**

Run `composer test` and `composer lint`. Both are expected to pass and print nothing. Then, in the Local site shell:

```bash
wp eval 'var_dump( get_option( "sprint_illustrations_palette", "missing" ) );'
# Expected: string(7) "missing" (nothing saved yet; the site palette is the Sprint preset)
wp sprint-illustrations compose --template=hero-left-character --seed=7 --palette=preset:ocean --out=wp-content/uploads/si-ocean.svg
# Expected: Success: Wrote ... (template hero-left-character, seed 7); the file contains #0e7490
wp sprint-illustrations cache stats
# Expected: "1 files, ... in .../uploads/sprint-illustrations/cache"
wp sprint-illustrations compose --template=hero-left-character --seed=7 --palette=preset:ocean --no-cache > /dev/null && wp sprint-illustrations cache stats
# Expected: still 1 file
wp cron event list --hook=sprint_illustrations_cache_gc --fields=hook,recurrence
# Expected: one row, recurrence "1 day"
wp sprint-illustrations cache purge
# Expected: Success: Removed 1 cached illustrations.
```

Delete `wp-content/uploads/si-ocean.svg` afterwards.

- [ ] **Step 9: Commit**

```bash
git add src/Settings/SitePalette.php src/Admin/Notices.php src/Cli/CacheCommand.php src/Plugin.php sprint-illustrations.php src/Cli/Command.php src/Admin/TestPage.php
git commit -m "feat(wp): site palette option, cache wiring, cron and CLI"
```

---

### Task 8: Elementor colour source and keep-synced

**Files:**
- Create: `src/Integrations/Elementor/ColorSource.php`, `src/Integrations/Elementor/Sync.php`
- Modify: `src/Plugin.php` (register `Sync`)

**Interfaces:**
- Consumes: `ElementorMapping` (Task 3), `SitePalette`, `Notices` (Task 7).
- Produces:
  - `ColorSource::available(): bool` (static), `ColorSource::variables_available(): bool` (static).
  - `->items(): array<array{id: string, label: string, value: string}>`, `->failed(): bool`.
  - `new Sync( SitePalette, ColorSource )`, `->register(): void`, `->resync(): void`.

- [ ] **Step 1: Create `src/Integrations/Elementor/ColorSource.php`**

```php
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
```

- [ ] **Step 2: Create `src/Integrations/Elementor/Sync.php`**

```php
<?php
/**
 * Keeps the site palette in step with Elementor.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Integrations\Elementor;

use SprintIllustrations\Admin\Notices;
use SprintIllustrations\Palette\ElementorMapping;
use SprintIllustrations\Settings\SitePalette;

/**
 * Re-applies the stored mapping when the Kit or its Variables change.
 */
final class Sync {

	/**
	 * Constructor.
	 *
	 * @param SitePalette $site   Site palette.
	 * @param ColorSource $source Elementor colours.
	 */
	public function __construct( private SitePalette $site, private ColorSource $source ) {}

	/**
	 * Hook in only when the palette follows Elementor with "keep synced" on.
	 */
	public function register(): void {
		$settings = $this->site->settings();

		if ( 'elementor' !== $settings['source'] || ! $settings['elementor']['sync'] || ! ColorSource::available() ) {
			return;
		}

		add_action( 'elementor/document/after_save', [ $this, 'on_document_saved' ] );
		add_action( 'updated_post_meta', [ $this, 'on_meta_changed' ], 10, 3 );
		add_action( 'added_post_meta', [ $this, 'on_meta_changed' ], 10, 3 );
	}

	/**
	 * Kit saved in Elementor's Site Settings.
	 *
	 * @param object $document Elementor document.
	 */
	public function on_document_saved( $document ): void {
		if ( is_object( $document ) && method_exists( $document, 'get_id' ) && (int) $document->get_id() === (int) get_option( 'elementor_active_kit' ) ) {
			$this->resync();
		}
	}

	/**
	 * V4 Variables changed (they fire no Elementor action, so watch the meta).
	 *
	 * @param int    $meta_id   Meta ID.
	 * @param int    $object_id Post ID.
	 * @param string $meta_key  Meta key.
	 */
	public function on_meta_changed( $meta_id, $object_id, $meta_key ): void {
		if ( '_elementor_global_variables' === $meta_key ) {
			$this->resync();
		}
	}

	/**
	 * Re-read Elementor and update the option when colours changed.
	 */
	public function resync(): void {
		$settings = $this->site->settings();
		$items    = $this->source->items();

		if ( ! $items ) {
			if ( $this->source->failed() ) {
				Notices::add( __( 'Elementor colours couldn\'t be read, so the palette was not updated.', 'sprint-illustrations' ) );
			}
			return;
		}

		$result = ( new ElementorMapping( $items ) )->apply( $settings['elementor']['map'], $settings['colors'] );

		if ( $result['missing'] ) {
			/* translators: %s: comma-separated Elementor colour IDs. */
			Notices::add( sprintf( __( 'Some mapped Elementor colours no longer exist: %s', 'sprint-illustrations' ), implode( ', ', $result['missing'] ) ) );
		}

		if ( $result['colors'] !== $settings['colors'] ) {
			$settings['colors'] = $result['colors'];
			$this->site->save( $settings );
		}
	}
}
```

- [ ] **Step 3: Register `Sync` in `src/Plugin.php`**

Add the `use` lines for `ColorSource` and `Sync`, if Task 7 left them out, and add this as the first line of `register_hooks()`:

```php
		( new Sync( $this->site_palette(), new ColorSource() ) )->register();
```

- [ ] **Step 4: Verify against the real Elementor 4.2.4 site**

Run `composer lint`, which is expected to print nothing. Then, in the Local site shell:

```bash
wp eval '$s = new \SprintIllustrations\Integrations\Elementor\ColorSource(); var_dump( \SprintIllustrations\Integrations\Elementor\ColorSource::variables_available() ); print_r( $s->items() ); print_r( ( new \SprintIllustrations\Palette\ElementorMapping( $s->items() ) )->suggest() );'
```

Expected:
- The Kit's four system colours appear as `kit:primary`, `kit:secondary`, `kit:text` and `kit:accent`, followed by any custom colours and variables.
- The suggestion maps `primary`, `secondary` and `accent`, and maps `neutral` and `outline` to `kit:text`.
- `failed()` is false.

Record the real output in the task report.

Then check sync end to end:

```bash
wp eval '$p = \SprintIllustrations\Plugin::instance(); $p->site_palette()->save( [ "source" => "elementor", "elementor" => [ "map" => [ "primary" => "kit:primary" ], "sync" => true ] ] ); ( new \SprintIllustrations\Integrations\Elementor\Sync( $p->site_palette(), new \SprintIllustrations\Integrations\Elementor\ColorSource() ) )->resync(); echo $p->site_palette()->settings()["colors"]["primary"], "\n";'
# Expected: the Kit's primary colour as lower-case hex
wp option delete sprint_illustrations_palette
```

The in-editor check (change the Kit's primary colour in Elementor's Site Settings, save, and see the Settings page follow) is part of Task 10.

- [ ] **Step 5: Commit**

```bash
git add src/Integrations/Elementor/ColorSource.php src/Integrations/Elementor/Sync.php src/Plugin.php
git commit -m "feat(elementor): read Kit colours and V4 variables; keep palette synced"
```

---

### Task 9: Settings page

**REQUIRED SUB-SKILL:** Load `frontend-design:frontend-design` before writing the CSS. The visual direction is fixed by the spec: native WP admin, and palettes are shown as illustrations. Use the skill for the quality floor (responsive, focus, reduced motion) and for the self-critique screenshots, not to invent a new look.

**Files:**
- Create: `src/Admin/SettingsPage.php`, `assets/admin/settings.css`, `assets/admin/settings.js`
- Modify: `src/Admin/Menu.php`, `src/Admin/TestPage.php` (hidden `page` field, see Task 7 Step 7.3)

**Interfaces:**
- Consumes:
  - From `Plugin`: `site_palette()`, `presets()`, `composer()`, `services()`, `cache()`.
  - From Elementor: `ColorSource`, `ElementorMapping`.
  - `Notices::add()`, `Palette::warnings()`, `Palette::resolve()`.
- Produces:
  - `SettingsPage::CAPABILITY`, `SettingsPage::PREVIEW_TEMPLATE`, `SettingsPage::PREVIEW_SEED`.
  - `->register(): void`, `->render(): void`, `->sanitize( mixed $input ): array`, `->preview(): void`, `->purge(): void`, `->enqueue(): void`.
  - `Menu::TEST_SLUG`.

- [ ] **Step 1: Create `src/Admin/SettingsPage.php`**

```php
<?php
/**
 * Settings page: palette source, presets, colours, Elementor mapping, cache.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Admin;

use SprintIllustrations\Compose\CompositionException;
use SprintIllustrations\Compose\SceneSpec;
use SprintIllustrations\Integrations\Elementor\ColorSource;
use SprintIllustrations\Palette\ElementorMapping;
use SprintIllustrations\Palette\Palette;
use SprintIllustrations\Palette\PaletteSettings;
use SprintIllustrations\Plugin;
use SprintIllustrations\Settings\SitePalette;

/**
 * Server-rendered form (Settings API) with a live preview over admin-ajax.
 */
final class SettingsPage {

	public const CAPABILITY = 'manage_options';

	public const PREVIEW_TEMPLATE = 'hero-left-character';

	public const PREVIEW_SEED = 3;

	private const GROUP = 'sprint_illustrations';

	private const AJAX_ACTION = 'sprint_illustrations_preview';

	private const PURGE_ACTION = 'sprint_illustrations_purge';

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( private Plugin $plugin ) {}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'admin_init', [ $this, 'register_setting' ] );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, [ $this, 'preview' ] );
		add_action( 'admin_post_' . self::PURGE_ACTION, [ $this, 'purge' ] );
	}

	/**
	 * Settings API registration.
	 */
	public function register_setting(): void {
		register_setting(
			self::GROUP,
			SitePalette::OPTION,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize' ],
				'default'           => [],
				'show_in_rest'      => false,
			]
		);
	}

	/**
	 * Sanitize submitted settings. For the Elementor source, colours are read from Elementor now.
	 *
	 * @param mixed $input Submitted value (already unslashed by options.php).
	 * @return array<string, mixed>
	 */
	public function sanitize( mixed $input ): array {
		$input     = is_array( $input ) ? $input : [];
		$elementor = is_array( $input['elementor'] ?? null ) ? $input['elementor'] : [];
		$text      = static fn( mixed $list ): array => array_map( static fn( $value ) => sanitize_text_field( (string) $value ), is_array( $list ) ? array_filter( $list, 'is_scalar' ) : [] );

		$raw = [
			'source'    => sanitize_key( (string) ( $input['source'] ?? '' ) ),
			'preset'    => sanitize_key( (string) ( $input['preset'] ?? '' ) ),
			'colors'    => $text( $input['colors'] ?? [] ),
			'skin'      => array_values( array_filter( $text( $input['skin'] ?? [] ) ) ),
			'hair'      => array_values( array_filter( $text( $input['hair'] ?? [] ) ) ),
			'elementor' => [
				'map'  => array_filter( $text( $elementor['map'] ?? [] ) ),
				'sync' => ! empty( $elementor['sync'] ),
			],
		];

		if ( 'elementor' === $raw['source'] && ColorSource::available() ) {
			$source = new ColorSource();
			$result = ( new ElementorMapping( $source->items() ) )->apply( $raw['elementor']['map'], $raw['colors'] );

			$raw['colors'] = $result['colors'];

			if ( $source->failed() ) {
				add_settings_error( SitePalette::OPTION, 'elementor', __( 'Elementor colours couldn\'t be read. The colours shown were saved instead.', 'sprint-illustrations' ) );
			}
		}

		return $this->plugin->site_palette()->normalize( $raw );
	}

	/**
	 * AJAX: render the stage for unsaved form values (uncached).
	 */
	public function preview(): void {
		check_ajax_referer( self::AJAX_ACTION, 'nonce' );

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( null, 403 );
		}

		// Values are validated by Palette::from_array() (hex only); nothing is stored.
		$input   = isset( $_POST['palette'] ) && is_array( $_POST['palette'] ) ? wp_unslash( $_POST['palette'] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$seed    = isset( $_POST['seed'] ) ? absint( $_POST['seed'] ) : self::PREVIEW_SEED;
		$palette = Palette::from_array(
			( is_array( $input['colors'] ?? null ) ? $input['colors'] : [] ) + [
				'skin' => is_array( $input['skin'] ?? null ) ? array_values( $input['skin'] ) : null,
				'hair' => is_array( $input['hair'] ?? null ) ? array_values( $input['hair'] ) : null,
			]
		);

		try {
			$svg = $this->art( $palette, $seed, 'si-stage', false );
		} catch ( CompositionException $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ], 500 );
		}

		wp_send_json_success(
			[
				'svg'      => $svg,
				'warnings' => $palette->warnings(),
				'variants' => $this->variants( $palette ),
			]
		);
	}

	/**
	 * admin-post: purge the cache.
	 */
	public function purge(): void {
		check_admin_referer( self::PURGE_ACTION );

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'sprint-illustrations' ), 403 );
		}

		$count = $this->plugin->cache()->purge();

		/* translators: %d: number of cached files removed. */
		Notices::add( sprintf( _n( 'Removed %d cached illustration.', 'Removed %d cached illustrations.', $count, 'sprint-illustrations' ), $count ), 'success' );

		wp_safe_redirect( admin_url( 'admin.php?page=' . Menu::SLUG ) );
		exit;
	}

	/**
	 * Enqueue the page's CSS and JS.
	 */
	public function enqueue(): void {
		wp_enqueue_style( 'sprint-illustrations-settings', plugins_url( 'assets/admin/settings.css', SPRINT_ILLUSTRATIONS_FILE ), [], SPRINT_ILLUSTRATIONS_VERSION );
		wp_enqueue_script( 'sprint-illustrations-settings', plugins_url( 'assets/admin/settings.js', SPRINT_ILLUSTRATIONS_FILE ), [], SPRINT_ILLUSTRATIONS_VERSION, true );
		wp_localize_script(
			'sprint-illustrations-settings',
			'sprintIllustrationsSettings',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::AJAX_ACTION ),
				'seed'    => self::PREVIEW_SEED,
				'i18n'    => [
					'unavailable' => __( 'Preview unavailable. Your changes can still be saved.', 'sprint-illustrations' ),
					'updating'    => __( 'Updating preview…', 'sprint-illustrations' ),
				],
			]
		);
	}

	/**
	 * Render the page.
	 */
	public function render(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'sprint-illustrations' ) );
		}

		$settings  = $this->plugin->site_palette()->settings();
		$palette   = $this->plugin->site_palette()->palette();
		$elementor = ColorSource::available();
		$source    = new ColorSource();
		$mapping   = new ElementorMapping( $elementor ? $source->items() : [] );
		$map       = $settings['elementor']['map'] ? $settings['elementor']['map'] : $mapping->suggest();

		echo '<div class="wrap si-settings" data-si-settings>';
		echo '<h1>' . esc_html__( 'Sprint Illustrations', 'sprint-illustrations' ) . '</h1>';
		settings_errors( SitePalette::OPTION );

		echo '<div class="si-settings__layout"><div class="si-settings__main">';
		echo '<form method="post" action="options.php" data-si-form>';
		settings_fields( self::GROUP );

		$this->render_source( $settings, $elementor );
		$this->render_presets( $settings );
		$this->render_colors( $palette );
		$this->render_tones( $settings );

		if ( $elementor ) {
			$this->render_elementor( $settings, $mapping, $map, $source->failed() );
		}

		submit_button( __( 'Save changes', 'sprint-illustrations' ) );
		echo '</form>';

		$this->render_cache();
		echo '</div>';

		$this->render_stage( $palette );
		echo '</div></div>';
	}

	/**
	 * Source radios.
	 *
	 * @param array<string, mixed> $settings  Settings.
	 * @param bool                 $elementor Elementor available.
	 */
	private function render_source( array $settings, bool $elementor ): void {
		$labels = [
			'preset'    => __( 'Preset', 'sprint-illustrations' ),
			'custom'    => __( 'Custom', 'sprint-illustrations' ),
			'elementor' => __( 'Elementor', 'sprint-illustrations' ),
		];

		echo '<fieldset class="si-panel si-source"><legend class="si-panel__title">' . esc_html__( 'Palette source', 'sprint-illustrations' ) . '</legend>';

		foreach ( PaletteSettings::SOURCES as $value ) {
			if ( 'elementor' === $value && ! $elementor ) {
				continue;
			}

			printf(
				'<label class="si-source__option"><input type="radio" name="%1$s[source]" value="%2$s" %3$s data-si-source> %4$s</label>',
				esc_attr( SitePalette::OPTION ),
				esc_attr( $value ),
				checked( $settings['source'], $value, false ),
				esc_html( $labels[ $value ] )
			);
		}

		echo '<p class="description">' . esc_html__( 'Editing any colour switches the source to Custom.', 'sprint-illustrations' ) . '</p></fieldset>';
	}

	/**
	 * Preset cards, each showing the preview scene in that preset.
	 *
	 * @param array<string, mixed> $settings Settings.
	 */
	private function render_presets( array $settings ): void {
		echo '<fieldset class="si-panel"><legend class="si-panel__title">' . esc_html__( 'Presets', 'sprint-illustrations' ) . '</legend><div class="si-presets">';

		foreach ( $this->plugin->presets()->all() as $id => $preset ) {
			$colors = array_intersect_key( $preset['palette']->to_array(), array_flip( Palette::SLOTS ) );

			printf(
				'<label class="si-preset"><input type="radio" class="si-preset__input" name="%1$s[preset]" value="%2$s" %3$s data-si-preset data-si-colors="%4$s"><span class="si-preset__art">%5$s</span><span class="si-preset__name">%6$s</span></label>',
				esc_attr( SitePalette::OPTION ),
				esc_attr( $id ),
				checked( $settings['preset'], $id, false ),
				esc_attr( (string) wp_json_encode( $colors ) ),
				$this->art( $preset['palette'], self::PREVIEW_SEED, 'si-card-' . $id, true ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitized SVG from the composer.
				esc_html( $preset['label'] )
			);
		}

		echo '</div></fieldset>';
	}

	/**
	 * Colour slot rows.
	 *
	 * @param Palette $palette Current palette.
	 */
	private function render_colors( Palette $palette ): void {
		$labels   = [
			'primary'    => __( 'Primary', 'sprint-illustrations' ),
			'secondary'  => __( 'Secondary', 'sprint-illustrations' ),
			'accent'     => __( 'Accent', 'sprint-illustrations' ),
			'neutral'    => __( 'Neutral', 'sprint-illustrations' ),
			'background' => __( 'Background', 'sprint-illustrations' ),
			'outline'    => __( 'Outline', 'sprint-illustrations' ),
		];
		$warnings = $palette->warnings();
		$variants = $this->variants( $palette );

		echo '<fieldset class="si-panel"><legend class="si-panel__title">' . esc_html__( 'Colours', 'sprint-illustrations' ) . '</legend><div class="si-slots">';

		foreach ( Palette::SLOTS as $slot ) {
			$hex = (string) $palette->resolve( $slot );
			$id  = 'si-color-' . $slot;

			printf(
				'<div class="si-slot" data-si-slot="%1$s">'
				. '<input type="color" class="si-slot__picker" value="%2$s" data-si-picker="%3$s" aria-label="%4$s">'
				. '<label class="si-slot__label" for="%3$s">%5$s</label>'
				. '<input type="text" class="si-slot__hex code" id="%3$s" name="%6$s[colors][%1$s]" value="%2$s" pattern="#[0-9a-fA-F]{6}" maxlength="7" spellcheck="false" data-si-hex="%1$s">'
				. '<span class="si-slot__chips"><span class="si-chip" data-si-variant="light" title="%7$s" style="background-color:%7$s"></span><span class="si-chip" data-si-variant="dark" title="%8$s" style="background-color:%8$s"></span></span>'
				. '<p class="si-slot__warning" data-si-warning>%9$s</p>'
				. '</div>',
				esc_attr( $slot ),
				esc_attr( $hex ),
				esc_attr( $id ),
				/* translators: %s: colour slot name, e.g. "Primary". */
				esc_attr( sprintf( __( '%s colour picker', 'sprint-illustrations' ), $labels[ $slot ] ) ),
				esc_html( $labels[ $slot ] ),
				esc_attr( SitePalette::OPTION ),
				esc_attr( $variants[ $slot ]['light'] ?? $hex ),
				esc_attr( $variants[ $slot ]['dark'] ?? $hex ),
				esc_html( $warnings[ $slot ] ?? '' )
			);
		}

		echo '</div><p class="description">' . esc_html__( 'Light and dark shades are derived automatically. Low contrast is a warning only; it never blocks saving.', 'sprint-illustrations' ) . '</p></fieldset>';
	}

	/**
	 * Skin and hair tone fields (six each; blank removes).
	 *
	 * @param array<string, mixed> $settings Settings.
	 */
	private function render_tones( array $settings ): void {
		$groups = [
			'skin' => __( 'Skin tones', 'sprint-illustrations' ),
			'hair' => __( 'Hair colours', 'sprint-illustrations' ),
		];

		echo '<fieldset class="si-panel"><legend class="si-panel__title">' . esc_html__( 'People', 'sprint-illustrations' ) . '</legend>';
		echo '<p class="description">' . esc_html__( 'Each person in a scene gets one of these, chosen by the seed. Leave a field blank to remove it.', 'sprint-illustrations' ) . '</p>';

		foreach ( $groups as $group => $label ) {
			echo '<div class="si-tones"><span class="si-tones__label">' . esc_html( $label ) . '</span><div class="si-tones__list">';

			for ( $i = 0; $i < PaletteSettings::MAX_TONES; $i++ ) {
				$hex = $settings[ $group ][ $i ] ?? '';
				$id  = 'si-' . $group . '-' . $i;

				printf(
					'<span class="si-tone"><input type="color" class="si-slot__picker" value="%1$s" data-si-picker="%2$s" aria-label="%3$s"><input type="text" class="si-tone__hex code" id="%2$s" name="%4$s[%5$s][]" value="%6$s" pattern="#[0-9a-fA-F]{6}" maxlength="7" spellcheck="false" placeholder="#rrggbb" aria-label="%3$s" data-si-list="%5$s"></span>',
					esc_attr( '' !== $hex ? $hex : '#ffffff' ),
					esc_attr( $id ),
					/* translators: 1: "Skin tones" or "Hair colours", 2: position 1-6. */
					esc_attr( sprintf( __( '%1$s %2$d', 'sprint-illustrations' ), $label, $i + 1 ) ),
					esc_attr( SitePalette::OPTION ),
					esc_attr( $group ),
					esc_attr( $hex )
				);
			}

			echo '</div></div>';
		}

		echo '</fieldset>';
	}

	/**
	 * Elementor mapping.
	 *
	 * @param array<string, mixed>  $settings Settings.
	 * @param ElementorMapping      $mapping  Mapping.
	 * @param array<string, string> $map      Selected slot => item ID.
	 * @param bool                  $failed   Reading Elementor failed.
	 */
	private function render_elementor( array $settings, ElementorMapping $mapping, array $map, bool $failed ): void {
		$choices = $mapping->choices();

		echo '<fieldset class="si-panel"><legend class="si-panel__title">' . esc_html__( 'Elementor colours', 'sprint-illustrations' ) . '</legend>';

		if ( $failed ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Elementor colours couldn\'t be read.', 'sprint-illustrations' ) . '</p></div>';
		} elseif ( ! $choices ) {
			echo '<p class="description">' . esc_html__( 'This site has no Elementor global colours yet.', 'sprint-illustrations' ) . '</p>';
		}

		echo '<p class="description">' . esc_html__( 'Choose which Elementor colour fills each slot. Colours are copied when you save with Elementor as the source.', 'sprint-illustrations' ) . '</p>';
		echo '<table class="si-map"><tbody>';

		foreach ( Palette::SLOTS as $slot ) {
			printf(
				'<tr><th scope="row"><label for="si-map-%1$s">%2$s</label></th><td><select id="si-map-%1$s" name="%3$s[elementor][map][%1$s]" data-si-map="%1$s"><option value="">%4$s</option>',
				esc_attr( $slot ),
				esc_html( ucfirst( $slot ) ),
				esc_attr( SitePalette::OPTION ),
				esc_html__( '— Keep current colour —', 'sprint-illustrations' )
			);

			foreach ( $choices as $choice ) {
				printf(
					'<option value="%1$s" %2$s %3$s data-si-hex="%4$s">%5$s</option>',
					esc_attr( $choice['id'] ),
					selected( $map[ $slot ] ?? '', $choice['id'], false ),
					disabled( null === $choice['hex'], true, false ),
					esc_attr( (string) $choice['hex'] ),
					esc_html(
						null === $choice['hex']
							/* translators: %s: Elementor colour name. */
							? sprintf( __( '%s (not importable)', 'sprint-illustrations' ), $choice['label'] )
							: $choice['label'] . ' · ' . $choice['hex']
					)
				);
			}

			echo '</select></td></tr>';
		}

		echo '</tbody></table>';
		printf(
			'<label class="si-sync"><input type="checkbox" name="%1$s[elementor][sync]" value="1" %2$s> %3$s</label>',
			esc_attr( SitePalette::OPTION ),
			checked( $settings['elementor']['sync'], true, false ),
			esc_html__( 'Keep synced: update the palette when Elementor colours change', 'sprint-illustrations' )
		);
		echo '</fieldset>';
	}

	/**
	 * Cache status and purge (a separate form).
	 */
	private function render_cache(): void {
		$cache = $this->plugin->cache();
		$stats = $cache->stats();

		echo '<section class="si-panel si-cache"><h2 class="si-panel__title">' . esc_html__( 'Illustration cache', 'sprint-illustrations' ) . '</h2>';

		if ( ! $cache->writable() ) {
			printf(
				'<div class="notice notice-warning inline"><p>%s <code>%s</code></p></div>',
				esc_html__( 'The illustration cache folder isn\'t writable, so illustrations are recomposed on every request.', 'sprint-illustrations' ),
				esc_html( $cache->directory() )
			);
		}

		printf(
			'<form method="post" action="%1$s" class="si-cache__form"><input type="hidden" name="action" value="%2$s">%3$s<p>%4$s</p>%5$s</form>',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr( self::PURGE_ACTION ),
			wp_nonce_field( self::PURGE_ACTION, '_wpnonce', true, false ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core markup.
			esc_html(
				sprintf(
					/* translators: 1: file count, 2: human-readable size. */
					_n( '%1$d cached illustration, %2$s.', '%1$d cached illustrations, %2$s.', $stats['files'], 'sprint-illustrations' ),
					$stats['files'],
					size_format( $stats['bytes'] ) ? size_format( $stats['bytes'] ) : '0 B'
				)
			),
			get_submit_button( __( 'Purge cache', 'sprint-illustrations' ), 'secondary', 'submit', false ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core markup.
		);

		echo '</section>';
	}

	/**
	 * Sticky live preview.
	 *
	 * @param Palette $palette Saved palette.
	 */
	private function render_stage( Palette $palette ): void {
		printf(
			'<aside class="si-stage" aria-labelledby="si-stage-title"><h2 class="si-stage__title" id="si-stage-title">%1$s</h2><div class="si-stage__art" data-si-stage>%2$s</div><div class="si-stage__bar"><button type="button" class="button" data-si-shuffle>%3$s</button><p class="si-stage__status" data-si-status aria-live="polite"></p></div></aside>',
			esc_html__( 'Preview', 'sprint-illustrations' ),
			$this->art( $palette, self::PREVIEW_SEED, 'si-stage', true ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitized SVG from the composer.
			esc_html__( 'Shuffle', 'sprint-illustrations' )
		);
	}

	/**
	 * Compose the preview scene.
	 *
	 * @param Palette $palette  Palette.
	 * @param int     $seed     Seed.
	 * @param string  $instance Instance ID.
	 * @param bool    $cached   Use the caching composer (fixed palettes only).
	 * @return string Sanitized SVG, or "" when composition fails on page render.
	 * @throws CompositionException When uncached composition fails (AJAX reports it).
	 */
	private function art( Palette $palette, int $seed, string $instance, bool $cached ): string {
		$spec = SceneSpec::from_array(
			[
				'template'   => self::PREVIEW_TEMPLATE,
				'seed'       => $seed,
				'decorative' => true,
			]
		);

		if ( ! $cached ) {
			return $this->plugin->services()->composer->compose( $spec, $palette )->with_instance_id( $instance );
		}

		try {
			return $this->plugin->composer()->compose( $spec, $palette )->with_instance_id( $instance );
		} catch ( CompositionException $e ) {
			return '';
		}
	}

	/**
	 * Derived shades per slot.
	 *
	 * @param Palette $palette Palette.
	 * @return array<string, array{light: string, dark: string}>
	 */
	private function variants( Palette $palette ): array {
		$variants = [];
		foreach ( Palette::SLOTS as $slot ) {
			$variants[ $slot ] = [
				'light' => (string) $palette->resolve( $slot, 'light' ),
				'dark'  => (string) $palette->resolve( $slot, 'dark' ),
			];
		}

		return $variants;
	}
}
```

- [ ] **Step 2: Rework `src/Admin/Menu.php`**

Replace the class body with:

```php
final class Menu {

	public const SLUG = 'sprint-illustrations';

	public const TEST_SLUG = 'sprint-illustrations-test';

	/**
	 * Settings page.
	 *
	 * @var SettingsPage
	 */
	private SettingsPage $settings;

	/**
	 * Settings page hook suffix.
	 *
	 * @var string
	 */
	private string $settings_hook = '';

	/**
	 * Test page hook suffix.
	 *
	 * @var string
	 */
	private string $test_hook = '';

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( private Plugin $plugin ) {
		$this->settings = new SettingsPage( $plugin );
	}

	/**
	 * Register WordPress hooks.
	 */
	public function register(): void {
		$this->settings->register();
		add_action( 'admin_menu', [ $this, 'add_pages' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
	}

	/**
	 * Add menu pages: Settings (landing) and Test page.
	 */
	public function add_pages(): void {
		$this->settings_hook = (string) add_menu_page(
			__( 'Sprint Illustrations', 'sprint-illustrations' ),
			__( 'Sprint Illustrations', 'sprint-illustrations' ),
			SettingsPage::CAPABILITY,
			self::SLUG,
			[ $this->settings, 'render' ],
			'dashicons-art',
			58
		);

		add_submenu_page( self::SLUG, __( 'Sprint Illustrations Settings', 'sprint-illustrations' ), __( 'Settings', 'sprint-illustrations' ), SettingsPage::CAPABILITY, self::SLUG, [ $this->settings, 'render' ] );

		$this->test_hook = (string) add_submenu_page(
			self::SLUG,
			__( 'Sprint Illustrations Test page', 'sprint-illustrations' ),
			__( 'Test page', 'sprint-illustrations' ),
			TestPage::CAPABILITY,
			self::TEST_SLUG,
			[ new TestPage( $this->plugin ), 'render' ]
		);
	}

	/**
	 * Enqueue assets for our pages only.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue( string $hook_suffix ): void {
		if ( $hook_suffix === $this->settings_hook ) {
			$this->settings->enqueue();
			return;
		}

		if ( $hook_suffix === $this->test_hook ) {
			wp_register_style( 'sprint-illustrations-test', false, [], SPRINT_ILLUSTRATIONS_VERSION );
			wp_enqueue_style( 'sprint-illustrations-test' );
			wp_add_inline_style( 'sprint-illustrations-test', ContactSheet::styles() );
		}
	}
}
```

Update the class docblock to say "Top-level menu: Settings (landing page) and the Test page." In `src/Admin/TestPage.php`, set the hidden `page` field to `Menu::TEST_SLUG` (Task 7 Step 7.3).

- [ ] **Step 3: Load `frontend-design:frontend-design`, then write `assets/admin/settings.css`**

The token system is inherited from WP admin: `#2271b1` action blue, `#c3c4c7` borders, `#f6f7f7` canvas, `#646970` muted text, and system fonts. The signature is the dotted stage and the illustrated preset cards. Everything else stays quiet.

```css
/* Sprint Illustrations: Settings page. Native WP admin; colour comes only from the user's palette. */
.si-settings__layout{display:grid;grid-template-columns:minmax(0,1fr);gap:24px;align-items:start;margin-top:16px}
.si-settings__main{min-width:0}
.si-stage{order:-1}
@media (min-width:960px){
	.si-settings__layout{grid-template-columns:minmax(0,1fr) minmax(320px,400px)}
	.si-stage{order:0;position:sticky;top:48px}
}

.si-panel{background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px 20px 20px;margin:0 0 20px;min-width:0}
.si-panel__title{font-size:14px;font-weight:600;line-height:1.4;margin:0;padding:0 4px}
fieldset.si-panel>.si-panel__title{float:left;width:100%;padding:0;margin-bottom:12px}
fieldset.si-panel>.si-panel__title+*{clear:both}
h2.si-panel__title{margin-bottom:12px;padding:0}

.si-source__option{display:inline-flex;align-items:center;gap:6px;margin:0 20px 8px 0}

.si-presets{display:grid;grid-template-columns:repeat(auto-fill,minmax(148px,1fr));gap:12px}
.si-preset{position:relative;display:flex;flex-direction:column;border:1px solid #c3c4c7;border-radius:4px;background:#fff;cursor:pointer;overflow:hidden}
.si-preset__input{position:absolute;opacity:0;width:1px;height:1px;margin:0}
.si-preset__art{display:block;aspect-ratio:4/3;background:#f6f7f7}
.si-preset__art svg{display:block;width:100%;height:100%}
.si-preset__name{padding:6px 10px;font-weight:500;border-top:1px solid #f0f0f1}
.si-preset:hover{border-color:#8c8f94}
.si-preset:has(.si-preset__input:checked){border-color:#2271b1;box-shadow:0 0 0 1px #2271b1}
.si-preset:has(.si-preset__input:checked) .si-preset__name{color:#2271b1}
.si-preset:has(.si-preset__input:focus-visible){outline:2px solid #2271b1;outline-offset:2px}

.si-slots{display:grid;gap:10px}
.si-slot{display:grid;grid-template-columns:36px minmax(88px,1fr) 104px auto;grid-template-areas:"picker label hex chips" ". warning warning warning";align-items:center;column-gap:10px}
.si-slot__picker{grid-area:picker;width:36px;height:30px;padding:0;border:1px solid #8c8f94;border-radius:4px;background:none;cursor:pointer}
.si-slot__label{grid-area:label;font-weight:500}
.si-slot__hex{grid-area:hex;width:104px}
.si-slot__chips{grid-area:chips;display:inline-flex;gap:4px}
.si-chip{width:18px;height:18px;border-radius:50%;box-shadow:inset 0 0 0 1px rgba(0,0,0,.15)}
.si-slot__warning{grid-area:warning;margin:0;color:#996800;font-size:12px}
.si-slot__warning:empty{display:none}

.si-tones{display:flex;flex-wrap:wrap;align-items:center;gap:8px 16px;margin-top:12px}
.si-tones__label{min-width:96px;font-weight:500}
.si-tones__list{display:flex;flex-wrap:wrap;gap:8px}
.si-tone{display:inline-flex;align-items:center;gap:4px}
.si-tone .si-slot__picker{width:30px;height:30px}
.si-tone__hex{width:88px}

.si-map th{text-align:left;font-weight:500;padding:6px 16px 6px 0;width:110px}
.si-map td{padding:6px 0}
.si-map select{max-width:100%}
.si-sync{display:inline-flex;align-items:center;gap:6px;margin-top:12px}

.si-stage{background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px}
.si-stage__title{font-size:14px;font-weight:600;margin:0 0 12px}
.si-stage__art{aspect-ratio:4/3;border-radius:2px;background:#f6f7f7 radial-gradient(#dcdcde 1px,transparent 1.5px) 0 0/16px 16px;display:grid;place-items:center;overflow:hidden}
.si-stage__art svg{display:block;width:100%;height:100%}
.si-stage__bar{display:flex;align-items:center;gap:12px;margin-top:12px}
.si-stage__status{margin:0;color:#646970;font-size:12px}
.si-stage.is-updating .si-stage__art{opacity:.6}
@media (prefers-reduced-motion:no-preference){.si-stage__art{transition:opacity .15s}}

.si-cache__form p{margin:0 0 12px}
```

- [ ] **Step 4: Write `assets/admin/settings.js`**

```js
/* Sprint Illustrations: Settings page live preview. No build step; progressive enhancement only. */
( function () {
	'use strict';

	const root = document.querySelector( '[data-si-settings]' );
	const config = window.sprintIllustrationsSettings;

	if ( ! root || ! config ) {
		return;
	}

	const form = root.querySelector( '[data-si-form]' );
	const stage = root.querySelector( '[data-si-stage]' );
	const status = root.querySelector( '[data-si-status]' );
	const aside = stage.closest( '.si-stage' );
	const HEX = /^#[0-9a-f]{6}$/i;
	let seed = Number( config.seed ) || 1;
	let timer = 0;
	let latest = 0;

	function setSource( value ) {
		const radio = form.querySelector( '[data-si-source][value="' + value + '"]' );
		if ( radio ) {
			radio.checked = true;
		}
	}

	function setColor( slot, hex ) {
		const text = form.querySelector( '[data-si-hex="' + slot + '"]' );
		if ( ! text || ! HEX.test( hex ) ) {
			return;
		}
		text.value = hex.toLowerCase();
		const picker = form.querySelector( '[data-si-picker="' + text.id + '"]' );
		if ( picker ) {
			picker.value = text.value;
		}
	}

	function payload() {
		const data = new FormData();
		data.append( 'action', 'sprint_illustrations_preview' );
		data.append( 'nonce', config.nonce );
		data.append( 'seed', String( seed ) );
		form.querySelectorAll( '[data-si-hex]' ).forEach( function ( input ) {
			data.append( 'palette[colors][' + input.dataset.siHex + ']', input.value.trim() );
		} );
		form.querySelectorAll( '[data-si-list]' ).forEach( function ( input ) {
			if ( HEX.test( input.value.trim() ) ) {
				data.append( 'palette[' + input.dataset.siList + '][]', input.value.trim() );
			}
		} );
		return data;
	}

	function updateSlots( warnings, variants ) {
		form.querySelectorAll( '[data-si-slot]' ).forEach( function ( row ) {
			const slot = row.dataset.siSlot;
			row.querySelector( '[data-si-warning]' ).textContent = warnings[ slot ] || '';
			row.querySelectorAll( '[data-si-variant]' ).forEach( function ( chip ) {
				const hex = variants[ slot ] && variants[ slot ][ chip.dataset.siVariant ];
				if ( hex ) {
					chip.style.backgroundColor = hex;
					chip.title = hex;
				}
			} );
		} );
	}

	function refresh() {
		const id = ++latest;
		aside.classList.add( 'is-updating' );
		status.textContent = config.i18n.updating;

		fetch( config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: payload() } )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( json ) {
				if ( id !== latest ) {
					return;
				}
				if ( ! json || ! json.success ) {
					throw new Error( 'preview failed' );
				}
				stage.innerHTML = json.data.svg; // Sanitized server-side by the composer.
				updateSlots( json.data.warnings || {}, json.data.variants || {} );
				status.textContent = '';
			} )
			.catch( function () {
				if ( id === latest ) {
					status.textContent = config.i18n.unavailable;
				}
			} )
			.finally( function () {
				if ( id === latest ) {
					aside.classList.remove( 'is-updating' );
				}
			} );
	}

	function schedule() {
		window.clearTimeout( timer );
		timer = window.setTimeout( refresh, 150 );
	}

	function applyElementor() {
		form.querySelectorAll( '[data-si-map]' ).forEach( function ( select ) {
			const option = select.options[ select.selectedIndex ];
			if ( option && option.dataset.siHex ) {
				setColor( select.dataset.siMap, option.dataset.siHex );
			}
		} );
	}

	// Colour picker <-> hex field. Editing a slot colour means "Custom".
	form.querySelectorAll( '[data-si-picker]' ).forEach( function ( picker ) {
		const text = document.getElementById( picker.dataset.siPicker );
		if ( ! text ) {
			return;
		}
		picker.addEventListener( 'input', function () {
			text.value = picker.value;
			if ( text.dataset.siHex ) {
				setSource( 'custom' );
			}
			schedule();
		} );
		text.addEventListener( 'input', function () {
			if ( HEX.test( text.value.trim() ) ) {
				picker.value = text.value.trim().toLowerCase();
			}
			if ( text.dataset.siHex ) {
				setSource( 'custom' );
			}
			schedule();
		} );
	} );

	form.querySelectorAll( '[data-si-preset]' ).forEach( function ( radio ) {
		radio.addEventListener( 'change', function () {
			const colors = JSON.parse( radio.dataset.siColors || '{}' );
			Object.keys( colors ).forEach( function ( slot ) {
				setColor( slot, colors[ slot ] );
			} );
			setSource( 'preset' );
			schedule();
		} );
	} );

	form.querySelectorAll( '[data-si-source]' ).forEach( function ( radio ) {
		radio.addEventListener( 'change', function () {
			if ( 'elementor' === radio.value ) {
				applyElementor();
				schedule();
			}
		} );
	} );

	form.querySelectorAll( '[data-si-map]' ).forEach( function ( select ) {
		select.addEventListener( 'change', function () {
			setSource( 'elementor' );
			applyElementor();
			schedule();
		} );
	} );

	root.querySelector( '[data-si-shuffle]' ).addEventListener( 'click', function () {
		seed = Math.floor( Math.random() * 100000 ) + 1;
		refresh();
	} );
} )();
```

- [ ] **Step 5: Lint and test**

Run: `composer lint`, then `composer test`
Expected: both clean.

- [ ] **Step 6: Verify in the browser (self-critique per frontend-design)**

Open **Sprint Illustrations** in wp-admin (it lands on Settings) at 1440 px and at 782 px, and take screenshots. Check:
- Six preset cards each show the scene in their palette. The saved preset is outlined in blue. Keyboard Tab reaches the cards and shows a focus ring.
- Clicking **Ocean** fills the colour fields, selects Preset, and the stage re-renders within about 150 ms plus the request. Typing `#f4f4f4` into Primary switches the source to Custom and shows "Low contrast against background (1.1:1)" under Primary.
- **Shuffle** changes the scene but keeps the colours.
- With Elementor as the source, the mapping selects fill the colour fields. **Save changes** persists the choice. After a reload, the stage matches the saved palette.
- **Purge cache** shows "Removed N cached illustrations." and the count drops to 0.
- At 782 px, the stage sits above the form and nothing scrolls horizontally.
- With JavaScript disabled, the form still saves.

Fix anything that fails. Then remove one decoration you don't need (the frontend-design "mirror" check) and note what you removed in the task report.

- [ ] **Step 7: Commit**

```bash
git add src/Admin/SettingsPage.php src/Admin/Menu.php src/Admin/TestPage.php assets/admin/settings.css assets/admin/settings.js
git commit -m "feat(admin): Settings page with illustrated presets, live preview, Elementor mapping and cache purge"
```

---

### Task 10: Final verification and hand-off

**Files:**
- Modify: `CLAUDE.md` (commands and architecture additions)
- Modify: this plan (add test instructions and open questions at the end if anything changed)

- [ ] **Step 1: Full suite and standards**

Run: `composer test`, then `composer lint`
Expected: all tests pass. Phase 1's 134 tests plus roughly 40 new ones. PHPCS prints nothing.

- [ ] **Step 2: End-to-end in WordPress**

1. Deactivate and reactivate the plugin. `wp cron event list --hook=sprint_illustrations_cache_gc` shows exactly one event. After deactivating, it shows none. Reactivate.
2. On Settings, choose **Forest** and save. `wp sprint-illustrations compose --template=empty-state --out=wp-content/uploads/si-forest.svg` produces SVG containing `#2f7d5b`. Delete the file.
3. Open the **Test page**. The first row is labelled "Site palette" and renders in Forest.
4. Elementor sync: set the source to Elementor, map Primary to the Kit's Primary, tick **Keep synced**, and save. In Elementor → Site Settings → Global Colors, change Primary to `#123456` and update. Reload Settings: Primary shows `#123456`. Restore the Kit colour afterwards.
5. The preview endpoint rejects a request with a bad nonce: `curl -s -X POST <site>/wp-admin/admin-ajax.php -d action=sprint_illustrations_preview` returns `-1` or a 403.
6. Reset: `wp option delete sprint_illustrations_palette` and `wp sprint-illustrations cache purge`.

- [ ] **Step 3: Update `CLAUDE.md`**

Under Commands, add `wp sprint-illustrations cache stats|purge` and the `compose --palette=<site|default|preset:id> --[no-]cache` flags.

Under Architecture, add a paragraph:
- **Palettes:** `Palette\PaletteSettings` (pure) normalizes the `sprint_illustrations_palette` option and resolves `site`, `default`, `preset:<id>` or an inline array. `Settings\SitePalette` is its WordPress wrapper. Presets live in `assets/palettes/presets.json`.
- **Cache:** `Plugin::composer()` is a `Cache\CachingComposer` over `SvgCache` (`uploads/sprint-illustrations/cache/*.json`). Keys come from spec, palette hash, manifest version and plugin version. Only warning-free results are stored, and every hit is re-sanitized.
- **Elementor:** `Integrations\Elementor\ColorSource` reads Kit colours and V4 Variables through Elementor's own classes. `Sync` re-applies the mapping on Kit save and on `_elementor_global_variables` meta changes.

- [ ] **Step 4: Commit**

```bash
git add CLAUDE.md docs/superpowers/plans/2026-09-29-phase-2-palette-cache.md
git commit -m "docs: phase 2 commands and architecture in CLAUDE.md"
```

---

## Phase 2 test instructions (for the user)

1. Run `composer test` and `composer lint`. Both should be clean.
2. Open **Sprint Illustrations** in wp-admin. Pick a preset, tweak a colour, watch the preview, and save.
3. With Elementor active: choose Elementor as the source, check the mapping, tick **Keep synced**, and save. Change a global colour in Elementor and reload.
4. Run `wp sprint-illustrations compose --palette=site --out=test.svg` in Local's site shell, then run `wp sprint-illustrations cache stats`.

## Open questions (decide during or after phase 2)

1. **Hair colour per character** (from phase 1) is still drawn from the seed.
2. **Same person twice** in `two-people-collaborating` is planned for phase 3, with a `person` field on character pieces.
3. **Keep synced** re-reads Elementor on every Kit save, even when only typography changed. That's cheap, but if it becomes noisy, compare `system_colors` and `custom_colors` in `$data['settings']` before re-reading.
4. **Preview cards cost six compositions** on a cold cache when the Settings page first loads. After that they're cache hits. Measure the cold load in Task 9 Step 6 and record it here.
