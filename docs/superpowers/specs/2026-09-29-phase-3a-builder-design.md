# Sprint Illustrations — Phase 3a Design: Saved Illustrations, REST API, Builder

Date: 2026-09-29
Status: Approved design
Parent spec: `2026-09-28-sprint-illustrations-design.md` §6, §10, §12, §13, §14, as amended by §19. Phase 3 is split in two:
- **3a (this document):** saved illustrations, the REST API, the Builder, `id=` on every surface, and the same-person-twice fix.
- **3b (later):** Media export (SVG and PNG) and the Library browser page.

Where this document disagrees with the parent, this one wins.

## 1. Goal

A user who can edit posts opens **Sprint Illustrations → Builder** and does four things:
1. Picks a template.
2. Adjusts each slot's piece, with lock and shuffle.
3. Chooses a palette and accessibility text.
4. Saves the result as a named illustration.

The saved illustration then renders anywhere through `[sprint_illustration id="…"]`, the block's "Saved illustration" setting, or the Elementor widget's "Saved illustration" control.

Success criteria:
- The REST permission matrix holds: anonymous gets 401, Subscriber 403, Author can manage their own illustrations, and Editor or Admin can manage anyone's.
- Saved IDs render identically on all three surfaces.
- `two-people-collaborating` never shows the same person twice.
- The existing starter-pack check keeps zero warnings.
- `composer test` and `composer lint` stay clean. `npm run build` produces `build/builder.js` and `build/builder.asset.php`, and the committed `build/` works without Node on the server.

## 2. Data layer

This section follows the code-architect blueprint. The names and behaviours below are binding.

### 2.1 Person field and the resolver fix (pure)

- `Library\Piece` gains `?string $person` (lower-case) and `to_array()`.
- `Cli\ManifestBuilder` reads `data-si-person` from the root element. For the `characters` category only, the fallback is the first filename segment (`ava-sit` gives `ava`); the metadata is removed from the cleaned file as usual. The manifest entry gets `person`. Rebuild with `php bin/build-manifest.php --non-interactive`; no piece source files change.
- `Compose\SceneResolver` keeps a `$used_persons` set across the slots of one scene:
  - Auto-picks exclude pieces whose `person` is already used. If filtering would leave no candidates, it keeps the full pool silently.
  - Explicit picks are never overridden, but their person is still recorded.
  - The seed's draw sequence is unchanged, so output stays deterministic.

### 2.2 Placement boxes (pure, an addition to the blueprint)

- `Compose\ComposedSvg` gains `array $boxes`: slot name mapped to a list of `[x, y, w, h]` in canvas units, rounded to 2 decimals. It defaults to `[]` and is included in `to_array()`/`from_array()`.
- `Composer::compose()` fills it from the placements. Each box is `[x, y, piece width × scale, piece height × scale]`. For flipped placements, the box is the placement's visual extent.
- Cached entries include boxes. Old entries simply lack them and load as `[]`. The plugin version bump to 0.4.0 changes every cache key anyway.

### 2.3 Serialisation helpers (pure)

- `Library\TemplateSlot::to_array()` returns `{ name, category, required, attach: { to, anchor } | null, multiple }`.
- `Library\Template::to_array()` returns `{ id, label, canvas, tags, slots }`.
- `Compose\PiecePreview::render( Piece, Palette, PieceLoader, Sanitizer ): string` renders a piece on its own:
  - It uses the piece's own `viewBox`.
  - It recolours with skin and hair index 0.
  - It scopes IDs with `si-piece-<id>-` and sanitizes the result.

### 2.4 Saved illustrations

- `Storage\Illustration` is a pure value object. It has `from_array()`/`to_array()`/`with_title()`/`with_spec()` and the fields `id`, `title`, `spec`, `status`, `author_id` and `modified_gmt`.
  - `title` is the illustration's name and is capped at 200 characters.
  - `spec.title` is the SVG's accessible title. It's a different field, and the class comments this.
- `Storage\IllustrationPostType` (WordPress) registers `si_illustration` with these arguments: `public`, `show_ui` and `show_in_rest` all false; `capability_type post`; `map_meta_cap true`; `supports [title]`; no rewrite or query var.
- `Storage\IllustrationRepository` (WordPress) provides `find`, `find_published`, `list( page, per_page, search )`, `create`, `update`, `trash` and `delete`. **Every method taking an ID first checks `post_type === si_illustration`.**

### 2.5 REST (`sprint-illustrations/v1`)

Controllers are registered on `rest_api_init`, outside `is_admin()`. They rely on WordPress's cookie nonce (`X-WP-Nonce`).

| Route | Permission | Behaviour |
|---|---|---|
| `POST /compose` | `edit_posts` | The body is a SceneSpec. It composes with the **uncached** composer and returns `{ svg, spec, warnings, template: {id, label, canvas}, slots: [{…TemplateSlot, picked, locked, boxes}] }`. `locked` is true when the request's `picks` contains the slot. A composition failure returns 422 `sprint_illustrations_composition_failed`. |
| `GET /library` | `edit_posts` | Returns `{ templates: [Template::to_array], pieces: [Piece::to_array + preview] }`. The preview is a `PiecePreview` rendered in the site palette and cached in a transient keyed `si_piece_preview_<sha1(piece hash + palette hash)>` for 30 days. |
| `GET /illustrations` | `edit_posts` | Takes `page`, `per_page` (up to 100) and `search`. Returns `{ items: [{id, title, template, modified_gmt, author_id, can_edit, can_delete}], total, pages }`. |
| `GET /illustrations/<id>` | `edit_posts` | Returns 404 for a missing ID or the wrong post type. Otherwise returns `Illustration::to_array() + can_edit, can_delete`. |
| `POST /illustrations` | `edit_posts` | The body is `{ title, spec }`. An empty title gives 400. A spec that doesn't compose (checked through the caching composer) gives 422. The **resolved** spec is stored. Returns 201. |
| `PUT /illustrations/<id>` | `edit_posts` + `edit_post` | Partial `{ title?, spec? }`. The checks run in order: 404 for the wrong type, 403 for the capability, 400 or 422 for validation. |
| `DELETE /illustrations/<id>` | `edit_posts` + `delete_post` | Trashes by default. `?force=true` deletes permanently. Returns `{ deleted, id, status }`. |

Error codes are `sprint_illustrations_rest_forbidden` (401/403), `_cannot_edit`, `_cannot_delete` (403), `_not_found` (404), `_invalid_title` (400) and `_composition_failed` (422).

### 2.6 `id=` on every surface

- `Render\Renderer` gains an optional fourth constructor argument, `fn( int $id ): ?SceneSpec` (`find_published`).
- With `id` > 0, the saved spec is used. Only `title` (if non-empty) and `decorative` (if given) override it. An unknown ID throws the usual `CompositionException`, so visitors see the silent comment and editors the notice.
- The shortcode takes `id`, the block `illustrationId` (integer), and the widget an `illustration_id` select. `Plugin::editor_choices()` gains `illustrations: [{label, value}]`, up to 100, newest first.

## 3. Builder

### 3.1 Tech

- `package.json` uses `@wordpress/scripts` and sets `"build": "wp-scripts build src-js/builder/index.js --output-path=build"` (or the equivalent `webpack` entry naming) so the output is `build/builder.js` plus `build/builder.asset.php`.
- The code is React on `@wordpress/element`, `@wordpress/components`, `@wordpress/api-fetch` and `@wordpress/i18n`. It uses local state (`useReducer`), not a `@wordpress/data` store (YAGNI).
- `build/` is committed. `node_modules/` is ignored. ESLint runs through `wp-scripts lint-js` as `npm run lint:js`.

### 3.2 Admin page

- The top-level **Sprint Illustrations** menu requires `edit_posts` and lands on **Builder** (`admin.php?page=sprint-illustrations`).
- **Settings** moves to `sprint-illustrations-settings` (`manage_options`), and the **Test page** stays at `sprint-illustrations-test` (`manage_options`). The Settings page's purge redirect and its own URLs follow the new slug.
- The Builder page renders `<div id="si-builder" class="wrap"></div>`. It enqueues `build/builder.js` with its asset dependencies and `wp-components` styles, plus `assets/admin/builder.css`. It localizes `{ restNamespace, siteUrl, canSettings, initialId }`, where `initialId` comes from `?illustration=<id>`.

### 3.3 Layout

The layout is three columns at 1280 px and wider. Below that, the right panel stacks under the stage.

```
Sprint Illustrations ▸ Builder      [Open ▾] [Name ______________] [Save] [Save as new] [Copy shortcode]
┌ Template ──────────┐ ┌──────────────── Stage ────────────────┐ ┌ Palette ───────────────┐
│ [scene][scene]     │ │ dotted canvas, template aspect ratio  │ │ (•) Site  ( ) Ocean …   │
│ [scene][scene]     │ │ hovered slot → outlined box            │ ├ Variation ─────────────┤
├ Slots ─────────────┤ │                                        │ │ Seed [42] [Shuffle]     │
│ Subject  Ava   🔒  │ │                                        │ │ Keywords [……] [Suggest] │
│ Prop     Phone 🔓  │ └────────────────────────────────────────┘ ├ Accessibility ─────────┤
│   [Change]         │  warnings (plain text) · status            │ Alt text · Decorative   │
└────────────────────┘                                            └────────────────────────┘
```

### 3.4 Behaviour

- **On load:** fetch `GET /library`. If `initialId` is set, fetch that illustration; otherwise start from the first template with seed 1 and the site palette.
- **Any change:** `POST /compose`, debounced 150 ms, with only the latest response applied. On failure the last good SVG stays and the status reads "Preview unavailable".
- **Template cards:** each shows that template's scene rendered with the site palette and seed 3, fetched once through `/compose` with `decorative: true`. Choosing a template clears picks and locks.
- **Slot rows:** each shows the label of the picked piece. Its lock toggle (`aria-pressed`) keeps that slot's current pick in `picks`. **Change** opens a Popover grid of the library pieces in the slot's category:
  - For attached slots, only pieces whose `mounts` include the parent anchor's `accepts` type are shown.
  - Choosing a piece sets its pick and locks it.
  - "Automatic" removes the pick and unlocks the slot.
- **Stage hover:** hovering or focusing a slot row outlines its `boxes` over the stage, using an absolutely positioned SVG overlay in canvas units.
- **Shuffle:** sets a new random seed and keeps `picks` only for locked slots.
- **Suggest:** clears the template so the selector chooses one from the keywords. The response's `spec.template` then becomes the selection.
- **Save:** a new illustration uses `POST /illustrations`, an existing one uses `PUT`. The name is required; an empty name shows an inline error. The URL updates to `?illustration=<id>`.
- **Save as new:** always uses `POST`.
- **Copy shortcode:** copies `[sprint_illustration id="<id>"]` to the clipboard and is disabled until the illustration is saved.
- **Open:** a Dropdown with a search field lists `GET /illustrations` and loads the chosen one.
- **Unsaved changes:** a `beforeunload` prompt appears while there are changes that haven't been saved.

### 3.5 Visual direction

This follows the frontend-design approach:
- Native `@wordpress/components`. Colour on the page comes only from the illustrations.
- The stage sits on the same dotted canvas as the Settings page, which keeps the two screens consistent.
- Selection uses admin blue `#2271b1`. Focus is visible, there's no motion beyond the stage's 150 ms opacity dim while updating, and reduced motion is respected.
- Copy is in sentence case with plain verbs.

## 4. Testing

- **PHPUnit:**
  - `Piece` person and `to_array()`.
  - The ManifestBuilder person field: from metadata, from the filename fallback, and absent for non-character pieces.
  - The resolver never repeating a person, and its fallback when only one person is available.
  - `StarterPackTest`: every character has a person, and `two-people-collaborating` doesn't repeat one across seeds 1–100.
  - `Template` and `TemplateSlot` `to_array()`.
  - `PiecePreview`: no `slot-` classes left, the `viewBox` is kept, IDs are scoped, and the sanitizer runs.
  - `Illustration` round-trip and clamping.
  - `ComposedSvg` boxes round-trip, and `Composer` boxes inside the canvas.
  - `Renderer` `id` handling: the saved spec wins, the title and decorative overrides apply, an unknown ID takes the error path, and it does nothing without the closure.
- **WP-CLI (`rest_do_request` per role):**
  - The permission matrix, own-versus-others checks, and the wrong-post-type 404.
  - `/compose` doesn't add cache files, and saving does.
  - `id=` produces identical output from the shortcode, the block and the widget.
  - `/library` returns counts and previews.
  - Trash versus force delete.
- **Builder:**
  - `npm run build` succeeds and `npm run lint:js` is clean.
  - The Builder page markup renders through `wp --user eval`, and `build/builder.js` loads with its dependencies.
  - The layout is reviewed from a static render with mocked REST.
  - **Needs a login:** the full click-through (compose, lock, shuffle, save, open, copy shortcode). This goes on the user's checklist.

## 5. Out of scope (3b and later)

Media export (SVG and PNG), the Library browser page, custom inline palettes in the Builder (it uses the site palette and presets only), per-slot nudging, and AI suggestions (phase 5).
