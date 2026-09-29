# Phase 8: Canvas Editing Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build these Builder features, all composed on the server:
- a Library panel with search and a category select;
- collapsible Library, Template and Slots panels;
- free placement by drag and drop;
- layer ordering;
- canvas size.

**Architecture:**
- `SceneSpec` gains `canvas`, `items` and `layers`.
- `SceneResolver` places the items. The pure `SceneLayout` does the canvas fit and the layer order.
- `/compose` and `/library` expose what the Builder needs.
- The React Builder edits those fields.

**Tech Stack:** PHP 8.1 with PHPUnit 10.5, and React through `@wordpress/scripts` (`src-js/builder`, with `build/` committed).

## Global Constraints

- Spec: `docs/superpowers/specs/2026-09-29-phase-8-canvas-editing-design.md`.
- Limits:
  - `canvas` 200–4000;
  - `MAX_ITEMS = 30`;
  - item `key` 0–99;
  - `x` and `y` within ±8000, `w` 1–8000, all rounded to 2 decimals;
  - `layers` at most 64 keys, each a slot name or `item:<key>`.
- A spec without the new fields must give an identical `to_array()` and identical output.
- `Compose\*` stays pure. `composer test`, `composer lint`, `npm run build` and `npm run lint:js` must all be clean. `build/` is committed with any change to `src-js`.

---

### Task 1: Scene format and resolving (core)

**Files:**
- Modify: `src/Compose/SceneSpec.php`, `src/Compose/Placement.php` (add `framed( float $k, float $ox, float $oy ): self`), `src/Compose/ResolvedScene.php` (add `canvas` and `layers`), `src/Compose/SceneResolver.php` (add items, fit and order), `src/Compose/Composer.php` (viewBox from `$scene->canvas`, item boxes, `layers`), `src/Compose/ComposedSvg.php` (add `layers`, serialized), `src/Cache/CachingComposer.php` (pass `layers` through)
- Create: `src/Compose/SceneLayout.php`, with `fit( array $placements, array $from, array $to ): array` and `order( array $placements, array $roots, array $layers ): array{placements, layers}`
- Test: `tests/Unit/Compose/SceneSpecTest.php`, `tests/Unit/Compose/SceneLayoutTest.php`, `tests/Unit/Compose/ComposerTest.php`, `tests/Unit/Compose/ComposedSvgTest.php`

- [ ] Write tests for:
  - normalizing, clamping and round-tripping the new fields;
  - an old spec's `to_array()` staying unchanged;
  - fit scale and centring;
  - order: reordering, attached slots following the parent, unlisted groups staying, and the natural order with items on top;
  - the composer: an item's box, item seeds that don't depend on each other, an unknown piece warning, the canvas viewBox, and the `layers` output.
- [ ] Run them and see them fail.
- [ ] Implement until they pass.
- [ ] Run `composer test` and `composer lint`, then commit `feat(compose): free items, layer order and canvas size in scenes`.

### Task 2: REST

**Files:** `src/Rest/ComposeController.php` (add `canvas`, `template.unit`, `layers` and `items`), `src/Rest/LibraryController.php` (add piece `size` and `custom`, and template `unit`)

- [ ] Implement.
- [ ] Verify with `bin/claude/wp.sh eval` calling the controllers, using a spec with `items`, `layers` and `canvas`, and check that saving round-trips.
- [ ] Commit `feat(rest): expose items, layers and canvas to the Builder`.

### Task 3: Builder layout (Library panel and collapsible sections)

**Files:**
- Create: `src-js/builder/LibraryPanel.js`, `src-js/builder/panels.js` (a `PanelBody` open-state hook stored in `localStorage`)
- Modify: `App.js`, `TemplatePicker.js`, `SlotList.js` (both wrapped in `PanelBody`), `state.js` (`items`, `layers`, `canvas`, `selected`), `assets/admin/builder.css`

- [ ] Implement the search (every word must appear in the label or the tags) and the category select.
- [ ] Make the thumbnails draggable (`dataTransfer` `application/x-si-piece`). A click or Enter adds the piece at the centre.
- [ ] Run `npm run build` and `lint:js`, then commit.

### Task 4: Canvas drop, select, move, resize and delete

**Files:**
- Create: `src-js/builder/CanvasEditor.js` (the overlay with the drop ghost, selection, handles and toolbar)
- Modify: `Stage.js`, `state.js`, `builder.css`

- [ ] Map pointer positions to canvas coordinates with `getScreenCTM().inverse()`.
- [ ] Give dropped pieces a default width of `size[0] × unit × k`, capped at 80% of the canvas.
- [ ] Move and resize with pointer capture. Arrow keys nudge, and Delete, Escape and Flip work.
- [ ] Build, lint and commit.

### Task 5: Layer order and canvas size

**Files:**
- Create: `src-js/builder/CanvasSize.js`
- Modify: `SlotList.js` (a layer list, front at the top, with drag reorder plus Forward and Back buttons), `App.js` (exports use `result.canvas`), `state.js`, `builder.css`

- [ ] Implement, build, lint and commit.

### Task 6: Verification and review

- [ ] Compose saved specs through WP-CLI and run the full suite.
- [ ] Get a review from feature-dev's code-reviewer and fix what it finds.
- [ ] Update CLAUDE.md and add execution notes here, then commit.
