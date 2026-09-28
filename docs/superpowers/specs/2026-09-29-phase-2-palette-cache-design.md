# Sprint Illustrations — Phase 2 Design: Palette, Settings, Elementor Import, Cache

Date: 2026-09-29
Status: Approved design, pending spec review
Parent spec: `2026-09-28-sprint-illustrations-design.md` (§8 Palette, §9 Caching, §13 Admin UI, §18 phase 2). This document refines those sections for phase 2. Where the two disagree, this one wins. §9 of this document lists every such change.

## 1. Goal

Site owners pick or edit a brand palette, optionally pull it from Elementor, and see the result live. Composed illustrations are cached on disk so later placement surfaces (phase 4) render quickly.

Success criteria:
- A Settings page where an admin chooses a preset, edits custom colours, or maps Elementor colours. It previews live and saves.
- `"site"` and `"preset:<id>"` palettes resolve everywhere a `SceneSpec` is used (WP-CLI, the Test page and the Settings preview).
- Elementor 4.2.4 Kit colours and V4 colour Variables import correctly on this site, and "keep synced" follows later edits.
- A repeated compose is served from the cache. The cache can be purged, and it cleans itself up.
- All pure units are covered by PHPUnit. `composer test` and `composer lint` stay green.

## 2. Components

Pure namespaces (no WordPress calls, unit-tested) are marked **pure**. The existing boundary rule and the PHPCS exclusions for pure paths extend to `Cache`.

| Unit | Kind | Responsibility |
|---|---|---|
| `Palette\PresetRepository` | pure | Loads `assets/palettes/presets.json`. `all(): array<string, array{label: string, palette: Palette}>`, `get( string $id ): ?Palette` |
| `Palette\Contrast` | pure | `ratio( string $hex_a, string $hex_b ): float` using WCAG relative luminance |
| `Palette::warnings(): array<string>` | pure | For `primary`, `secondary`, `accent`, `neutral` and `outline`, plus the `-light` variant of each: a message when the contrast against `background` is below `1.3` |
| `Palette\ColorValue` | pure | `to_hex( string $css ): ?string`. Accepts `#rgb` and `#rrggbb`. Drops alpha from `#rrggbbaa` and `rgba()`. Converts `rgb()`. Returns `null` for anything else (`var(...)`, `hsl()`, names) |
| `Palette\ElementorMapping` | pure | Given a list of Elementor colour items (`id`, `label`, `value`, `source`), suggests a slot mapping and applies a stored mapping to produce palette colours plus a list of missing IDs |
| `Palette\SiteRepository` | WP | Reads and writes the option. `resolve( string\|array $palette ): Palette` handles `"site"`, `"preset:<id>"` or an inline array. Anything unknown or invalid returns `Palette::default()` |
| `Integrations\Elementor\ColorSource` | WP | Reads Kit and Variables colours through Elementor's own classes. Returns plain item arrays for `ElementorMapping` |
| `Integrations\Elementor\Sync` | WP | The "keep synced" hooks. Re-applies the stored mapping and saves |
| `Compose\ComposesSvg` | pure | Interface: `compose( SceneSpec, Palette ): ComposedSvg`. `Composer` implements it |
| `Cache\SvgCache` | pure | A file cache in a given directory: `get`, `put`, `purge`, `stats`, `collect_garbage( int $max_age_seconds )` |
| `Cache\CachingComposer` | pure | Implements `ComposesSvg` by wrapping an inner composer and an `SvgCache` |
| `Cache\CacheKey` | pure | `for( SceneSpec, Palette, string $manifest_version, string $plugin_version ): string` |
| `Admin\SettingsPage` | WP | Page rendering, the Settings API registration, the sanitize callback and the preview AJAX handler |
| `Admin\Notices` | WP | Queues and shows one-off admin notices (transient-backed) |

`ContactSheet` and `Services` change from typing the concrete `Composer` class to the `ComposesSvg` interface. `ComposedSvg` gains `to_array()` and `from_array()`, whose array holds `markup`, `spec` (as `SceneSpec::to_array()`) and `warnings`.

## 3. Presets

The file `assets/palettes/presets.json` is an object keyed by preset ID. Each value has `label` and the palette keys accepted by `Palette::from_array()`.

| ID | Label | primary | secondary | accent | neutral | background | outline |
|---|---|---|---|---|---|---|---|
| `sprint` | Sprint | #5b5bd6 | #ffb224 | #ff6b6b | #2b2d42 | #eef0ff | #2b2d42 |
| `forest` | Forest | #2f7d5b | #f2c14e | #f25c54 | #233038 | #e8f3ee | #233038 |
| `night` | Night | #8b5cf6 | #22d3ee | #f472b6 | #1e1b4b | #ede9fe | #1e1b4b |
| `ocean` | Ocean | #0e7490 | #fbbf24 | #f43f5e | #1e293b | #e0f2fe | #1e293b |
| `sunset` | Sunset | #e8590c | #7048e8 | #fcc419 | #2d1e2f | #fff4e6 | #2d1e2f |
| `mono` | Mono | #495057 | #868e96 | #fa5252 | #212529 | #f1f3f5 | #212529 |

- `sprint` values equal `Palette::default()`.
- `ContactSheet::review_palettes()` returns the `sprint`, `forest` and `night` presets from this file, and no longer hard-codes them.
- A test asserts that every preset has zero warnings for its **base** slots. `-light` variant warnings are allowed for presets. If a listed value fails, the implementer adjusts that value's lightness and records the final value in the file. The table above is the starting point.

## 4. Site palette option

The option `sprint_illustrations_palette` (autoloaded):

```json
{
  "source": "preset",
  "preset": "sprint",
  "colors": { "primary": "#5b5bd6", "secondary": "…", "accent": "…", "neutral": "…", "background": "…", "outline": "…" },
  "skin": ["#f5c9a8", "…"],
  "hair": ["#2b2d42", "…"],
  "elementor": { "map": { "primary": "kit:primary", "neutral": "kit:text", "background": "var:e-gv-3" }, "sync": false }
}
```

- `source` is one of `preset`, `custom` or `elementor`.
- `colors`, `skin` and `hair` always hold the resolved values, whatever the source, so rendering never reads presets or Elementor.
- An Elementor colour ID is `kit:<_id>` for a Kit system or custom colour, or `var:<variable id>` for a V4 variable.
- A missing or invalid option behaves as `source: preset, preset: sprint`.
- The sanitize callback runs every submitted colour through `Palette::from_array()` and rejects unknown `source` values, falling back to `preset`. When `source` is `preset`, it copies the preset's colours into `colors`, using `sprint` when the preset ID is unknown. When `source` is `elementor`, it reads `ColorSource` and applies the map.

## 5. Settings page

**Menu:** the top-level **Sprint Illustrations** menu opens **Settings** (`manage_options`). **Test page** becomes a submenu. Phase 3 will insert the Builder first.

**Visual direction:** the native WP admin look (parent spec §13), with no custom fonts and no colour theme of its own. Colour on the page comes only from the user's palette. The one distinctive element is that palettes are shown as illustrations, not chips:
- Each preset card is a small render of the same scene (`hero-left-character`, seed 3) in that preset's colours.
- A sticky **stage** on the right re-renders the current form state live, with a **Shuffle** button that picks a new seed. The stage uses `hero-left-character` and is decorative (`aria-hidden`) inside a labelled region.

**Layout** (two columns at 960 px and above. Below that, the stage stacks above the form):

```
Sprint Illustrations ▸ Settings
┌ Palette ────────────────────────────────────────────┐  ┌──── Stage ────┐
│ Source: (•) Preset ( ) Custom ( ) Elementor          │  │  live render   │
│ [preset card][card][card][card][card][card]          │  │  [Shuffle]     │
│ Colours: swatch · label · hex field · light/dark chips│  └───────────────┘
│          · contrast warning text                     │
│ Skin tones ●●●● [+]   Hair ●●●● [+]                  │
│ Elementor (only when Elementor is active)            │
│   slot → [select Elementor colour ▾]  ☐ Keep synced  │
│ Cache: 214 files · 1.2 MB   [Purge cache]            │
└ [Save changes] ─────────────────────────────────────┘
```

**Behaviour:**
- Choosing a preset card fills the colour fields and sets the source to Preset. Editing any colour switches the source to Custom. The Elementor block shows only when Elementor is active, and choosing the Elementor source fills the fields from the current mapping.
- Each slot has `<input type="color">` paired with a hex text field. The two stay in sync, and the hex field is the value that gets submitted. The light and dark chips show the derived variants.
- Contrast warnings are written out in text next to the slot (for example "Low contrast against background (1.1:1)"). They never block saving.
- Skin and hair lists hold 1 to 6 colours each, with add and remove buttons.
- **Purge cache** is a separate POST form to `admin-post.php` (action `sprint_illustrations_purge`, nonce, `manage_options`). It redirects back with a notice.

**Live preview:**
- The AJAX action is `sprint_illustrations_preview`. It checks a nonce and `manage_options`. Its input is `palette` (colours, skin, hair), `seed` and `size`, where `size` is `stage` or `card`.
- It returns JSON `{ svg, warnings }`. The SVG is sanitized and given the instance ID `si-preview-<n>`.
- The `stage` size composes **uncached**, so unsaved colours typed into the form don't fill the cache. The preset `card` renders use a fixed palette and seed, so they go through the caching composer.
- The client script is a plain JavaScript file (`assets/admin/settings.js`, no build step) that debounces input by 150 ms and swaps the stage markup. If a request fails, the last good render stays and a small inline status reads "Preview unavailable".
- The page works with JavaScript off: saving still works, and the stage shows the saved palette.

**Accessibility:** every control has a visible label. The preset cards are radio inputs styled as cards. Focus rings use the admin default. There's no motion.

## 6. Elementor import

**Detection:** Elementor counts as available when `\Elementor\Plugin` exists and `ELEMENTOR_VERSION` is defined. V4 Variables are read only when `experiments->is_feature_active( 'e_variables' )` and `is_feature_active( 'e_atomic_elements' )` are both true and `\Elementor\Modules\Variables\Services\Variables_Service` exists. These checks run lazily at read time.

**Reading** (`ColorSource::items(): array<array{id: string, label: string, value: string, source: string}>`):
- Kit colours come from `Plugin::$instance->kits_manager->get_active_kit_for_frontend()` and its `get_settings_for_display()` for `system_colors` and `custom_colors`. Item ID is `kit:<_id>`, label is `title`, value is `color`.
- Variables come from `new Variables_Service( new Variables_Repository( $kit ), new Batch_Processor() )` and `get_variables_list()`. Only `type === 'global-color-variable'` is kept, and items with `deleted` or `deleted_at` set are skipped. Item ID is `var:<id>`, label is `label`, value is `value`.
- Every value goes through `ColorValue::to_hex()`. Items that return `null` are still listed, marked "not importable", and can't be selected.
- Any `Throwable` from Elementor internals is caught. The result is an empty list plus a notice, "Elementor colours couldn't be read.", so the page never fatals.

**Suggested mapping** (`ElementorMapping::suggest()`), used when no map is stored:
- `kit:primary`, `kit:secondary` and `kit:accent` map to the slots of the same name.
- `kit:text` maps to `neutral` and `outline`.
- `background` maps to the first importable item whose label contains `background` or `bg` (case-insensitive, Variables first). If there's none, it stays unmapped and keeps the current colour.
- A variable whose label equals a slot name (case-insensitive) overrides the Kit suggestion for that slot.

**Keep synced** (`Sync`, registered only when the option's `source` is `elementor` and `sync` is true):
- `elementor/document/after_save`, when the saved document's ID equals `get_option( 'elementor_active_kit' )`.
- `updated_post_meta` and `added_post_meta`, when `$meta_key === '_elementor_global_variables'`.
- On either, it re-reads `ColorSource`, applies the stored map and updates the option. Mapped IDs that no longer resolve keep their previous colour and queue the notice "Some mapped Elementor colours no longer exist: …".

## 7. Cache

**Location:** `uploads/sprint-illustrations/cache/`. The directory is created on first write, together with `index.php` (`<?php // Silence is golden.`) and `.htaccess` (`Require all denied`).

**Key:** `CacheKey::for()` returns `sha1` of the JSON of:
- `spec`: `SceneSpec::to_array()` with keys sorted recursively.
- `palette`: `Palette::hash()`.
- `manifest`: `Manifest::version()`.
- `plugin`: the plugin version.

**Entry:** `<key>.json` holds `ComposedSvg::to_array()`. Only compositions with no warnings are stored, so the warnings shown on a cache miss are never lost.

**CachingComposer::compose():**
1. On a hit, it decodes the entry, sanitizes the markup again, touches the file (its modified time then records the last use, since access times are unreliable on Windows) and returns it.
2. On a miss or an undecodable entry, it composes through the inner composer and stores the result if the result has no warnings.

**Maintenance:**
- A daily WP-Cron event `sprint_illustrations_cache_gc` calls `collect_garbage( 30 * DAY_IN_SECONDS )`, which deletes entries whose modified time is older than that.
- `register_activation_hook` schedules the event and `register_deactivation_hook` clears it. Both are registered in the main plugin file and call static methods on `Plugin`.
- The event is scheduled only when it's missing, so it's never duplicated.

**Not writable:** if the directory can't be created or written, `SvgCache` works in memory for the rest of the request. The Settings page then shows the notice "The illustration cache folder isn't writable, so illustrations are recomposed on every request." with the path.

**CLI:**
- `wp sprint-illustrations cache stats` prints the file count and total size.
- `wp sprint-illustrations cache purge` deletes all entries.
- `compose` gains `--palette=<site|preset:id>` (default `site`) and `--no-cache`.

## 8. Testing

**Unit (PHPUnit, no WordPress):**
- `PresetRepository`: loads all 6 presets, returns null for an unknown ID, and every preset has zero base-slot warnings.
- `Contrast`: known WCAG pairs (black on white 21:1, identical colours 1:1).
- `Palette::warnings()`: a low-contrast custom palette is flagged, and the default palette is clean.
- `ColorValue`: hex forms, alpha dropping, `rgb()`/`rgba()`, and rejecting `var()`, `hsl()` and garbage.
- `ElementorMapping`: suggestions for a Kit-only list, a Variables override, background detection, applying a stored map, and reporting missing IDs.
- `CacheKey`: stable across key order, and different when the palette, manifest version, plugin version or seed changes.
- `SvgCache`: put/get round-trip, purge, stats, garbage collection by modified time, and in-memory fallback when the directory isn't writable. Tests use a temporary directory.
- `CachingComposer`: a second call is a hit and doesn't call the inner composer (counting fake), results with warnings aren't stored, and a tampered entry is sanitized on read.

**In the Local site (WP-CLI and browser, recorded in the plan):**
- Saving and reloading each source.
- The preview endpoint rejects a request with no nonce.
- Importing from the real Elementor 4.2.4 Kit, then changing a Kit colour in Elementor and seeing the sync.
- `cache stats` and `cache purge`, and the garbage-collection event being scheduled once.
- Deactivating the plugin removes the scheduled event.

## 9. Amendments to the parent spec

- §8 **Tints** is not a separate class. `Palette` already derives the variants (§19), and `Palette::warnings()` provides the contrast flag.
- §8 **ElementorColors** becomes `Integrations\Elementor\ColorSource` plus `Sync`, with the pure mapping logic in `Palette\ElementorMapping`. Its hooks are the ones in §6 above: Variables fire no Elementor action, so sync hooks the post meta.
- §9 **CacheVersion is dropped.** The key already contains the palette hash, the manifest version and the plugin version, so counters would add nothing. Entries are `.json`, not `.svg`, because the resolved spec and warnings have to be stored with the markup. Garbage collection uses modified time, which a cache hit refreshes.
- §13 **Menu:** in phase 2, Settings is the landing page.

## 10. Out of scope for phase 2

- REST controllers, the `si_illustration` post type, the React Builder and Media export (phase 3).
- The Renderer, block, shortcode and widget (phase 4). In phase 2 the cache serves WP-CLI, the Test page and the Settings preview.
- Stopping the same person appearing twice in `two-people-collaborating`. That needs a `person` field on character pieces and is planned with the Builder's picking UI in phase 3.
- Font and size variables from Elementor.
