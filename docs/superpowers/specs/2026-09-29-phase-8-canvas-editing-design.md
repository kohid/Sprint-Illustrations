# Phase 8: Library in the Builder, free placement, layer order and canvas size

## 1. Goal

The Builder gains four abilities:

- **Library panel:** a collapsible **Library** panel above Template, with a search field and a category select.
- **Free placement:** you drag a piece from the Library onto the canvas and it lands where you drop it. You can then move, resize, flip or remove it.
- **Layer order:** in the **Slots** panel, you set what is in front and what is behind.
- **Canvas size:** you set the canvas width and height.

Library, Template and Slots can each be collapsed. Every surface (shortcode, block, Elementor, exports, cache) renders these edits exactly as the Builder shows them, because the server still composes every scene.

## 2. Scene format (pure PHP, unit-tested)

`Compose\SceneSpec` gains three optional fields. A spec without them behaves exactly as before, and `to_array()` leaves them out when they're empty. That keeps old saved designs and old cache keys unchanged.

| Field | Shape | Normalization |
|---|---|---|
| `canvas` | `[w, h]` | Integers, each clamped to 200–4000. An invalid value becomes `null`, which means the template's canvas. |
| `items` | list of `{ key, piece, x, y, w, flip }` | `piece` is a piece ID. `key` is an int 0–99 and unique; a missing or duplicate key gets the next free one. `x` and `y` are clamped to ±8000 and `w` to 1–8000, all rounded to 2 decimals. `flip` is a bool. At most `MAX_ITEMS = 30`. |
| `layers` | list of layer keys, back to front | A key is a slot name, or `item:<key>`. Keys are de-duplicated, with at most 64. |

**Resolving** (`SceneResolver`, plus the new pure `Compose\SceneLayout`):

1. **Template slots** resolve as today, in template coordinates.
2. **Canvas:** when `canvas` is set, every template placement is scaled by `k = min(W / tw, H / th)` and centred at `((W - tw·k) / 2, (H - th·k) / 2)`, using `Placement::framed( k, ox, oy )`. The extra space is empty, like the margins today.
3. **Items:**
   - Each item becomes a `Placement` with slot `item:<key>`, scale `w / piece.width()`, and its own `x`, `y` and `flip`.
   - Skin and hair come from `Seed("<seed>|item|<key>")`, so an item's look never depends on the other items.
   - An unknown piece is left out with a warning.
4. **Order:**
   - The natural order is the template placements sorted by `[z, order]`, then the items in spec order, which puts them on top.
   - With `layers` set, the placements are grouped. Each group is a top-level slot with every slot attached to it (followed through `attach_to`), or a single item.
   - Groups named in `layers` are rearranged into that order among the positions they already hold. Groups not named in `layers` stay where they are. Within a group, the natural order is kept, so a held cup stays with its person.
5. **Result:**
   - `ResolvedScene` carries the final `canvas` and `layers`, which is the full list of group keys back to front.
   - `Composer` renders in that order with that viewBox.
   - `ComposedSvg` gains `layers` (serialized for the cache), and its `boxes` include the `item:<key>` entries.

## 3. REST

- **`POST /compose`** also returns:
  - `canvas`, the size actually rendered;
  - `template.canvas`, the template's own size, as before;
  - `template.unit`;
  - `layers`, back to front;
  - `items`: `[{ key, piece, box }]`.
- **`GET /library`**: pieces gain `size` (view-box `[w, h]`) and `custom` (bool). Templates gain `unit`.
- **Saving** already stores the resolved spec, and `from_array` accepts the new fields, so nothing else changes.

## 4. Builder

The Builder keeps using `@wordpress/components` and the WordPress admin look.

**Left column:** three `PanelBody` sections, **Library, Template and Slots**, in that order. Whether each is open is remembered in `localStorage` under `si-builder-panels`. Every access is wrapped in try/catch.

**Library panel:**
- A search field, where every word must appear in the label or the tags, the same rule as the Library page. Next to it, a category select: All, Characters, Objects, Backgrounds, Decor.
- A thumbnail grid in the site palette, with a **Custom** badge on custom pieces.
- Each thumbnail is `draggable`. It's also a button: activating it (click or Enter) adds the piece to the centre of the canvas.
- When the scene already has 30 dropped pieces, adding is disabled and a note explains why.

**Canvas:**
- **Signature:** while you drag a piece over the canvas, a dashed outline at the piece's real landing size follows the pointer.
- **Drop** position comes from the overlay SVG's `getScreenCTM().inverse()`. The default width is `size[0] × unit × k` (the canvas fit factor), capped at 80% of the canvas in both dimensions.
- **After a drop,** the new item is selected. The selected item shows an outline and four corner handles:
  - drag the body to move it;
  - drag a corner to resize it with its proportions kept;
  - arrow keys nudge it 1 unit, or 10 with Shift;
  - Delete or Backspace removes it.
- **Toolbar:** a small floating toolbar has **Flip** and **Remove**.
- **Instant feedback:** while you drag, the overlay moves immediately. The spec is updated on release, and the debounced `/compose` renders the result.
- **Deselect:** clicking empty canvas or pressing Escape deselects.

**Canvas size bar** above the canvas:
- **W × H** number inputs, 200–4000.
- Preset buttons: **Template**, **Social 1200 × 630**, **Square 1080 × 1080**, **Wide 1200 × 675**.
- **Template** clears `canvas`.

**Slots panel:**
- One list with every layer group, front at the top (the reverse of `layers`).
- **Slot rows** keep Lock and Change. **Dropped pieces** show their label and a Remove button.
- Every row can be dragged to a new position. **Forward** and **Back** buttons do the same from the keyboard.
- Hovering over a row or focusing it outlines that layer on the canvas.
- Any reorder writes the full `layers` list.

**State** (`state.js`):
- `items`, `layers` and `canvas` join the spec body.
- **Shuffle** changes only the seed, so items and layers stay.
- **SET_TEMPLATE** keeps `items` and `canvas` and clears `layers`.
- **NEW** clears everything.
- **LOADED** reads the three fields from the saved spec.

**Exports:** the PNG and SVG exports use `result.canvas`.

## 5. Errors

- The server clamps out-of-range values and drops unknown pieces with a warning. Warnings show under the stage, as they do today.
- A composition failure keeps the last good render on the stage and shows the error, as today.

## 6. Testing

**PHPUnit:**
- `SceneSpec`: normalizing `canvas`, `items` and `layers`; round-tripping them; and an old spec's `to_array()` staying unchanged.
- **Canvas fit:** scale and centring.
- **Items:** placement box, skin and hair that don't depend on other items, and an unknown piece giving a warning.
- **Layers:**
  - reordering;
  - an attached slot following its parent;
  - unlisted groups staying in place;
  - no `layers` meaning the natural order with items on top.
- `ComposedSvg` serialization keeping `layers`.
- The existing suite, including `StarterPackTest`, stays green.

**Tooling:** `npm run build` and `npm run lint:js` must be clean, and so must `composer lint`.

**Browser:** the owner checks in a logged-in browser: drag and drop, move, resize, flip, delete, reorder, resize the canvas, save, reopen, and view the shortcode on a page. Without a login, the check is a server compose via WP-CLI of a saved spec with items, layers and canvas.

## 7. Out of scope

- Moving or resizing the template's own slot pieces. They're still chosen with Change and locked with Lock.
- Rotation.
- Undo and redo.
- Snapping and guides.
