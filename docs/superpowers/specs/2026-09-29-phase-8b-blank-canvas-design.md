# Phase 8b: Create new on a blank canvas

## Goal

In the Builder, **New** can start on an empty canvas that you fill from the Library. This builds on phase 8, which added free placement, layers and canvas size.

## Design

**Built-in template:** `Template::blank()` has the ID `blank` (`Template::BLANK_ID`), the label "Blank canvas", a canvas of 800 × 600, `unit` 1, and no tags or slots.
- `TemplateRepository::get( 'blank' )` returns it. A template file with the same ID takes precedence.
- It is **not** in `all()` or `ids()`. That keeps it out of `RulesSelector` (automatic choice), the Suggest and AI enums, the Library page and `wp sprint-illustrations library`.
- Asking for it by name works everywhere: the Builder, saved designs, and the shortcode or block with `template="blank"`.

**Composing:** a template without slots resolves to no placements, and the free items (phase 8) are the only content. An empty blank scene is a valid, empty, accessible SVG with no warnings. Its title falls back to "Blank canvas".

**Builder:**
- **New** becomes a dropdown with two items:
  - **Blank canvas**, "Start empty and add pieces from the Library". It dispatches `NEW` with `blank: true`, which sets the template to `blank`.
  - **From a template**, "Start from a ready-made scene", which is today's behaviour.
- The Template panel shows a **Blank** card first, drawn as a dashed empty frame. It isn't rendered through `/compose`.
- On `blank` with no items, the canvas shows the hint "Drag pieces here from the Library" in the centre. The hint is pointer-transparent.

## Tests

- **PHPUnit** (`tests/Unit/Compose/BlankTemplateTest.php`):
  - `get( 'blank' )` returns a template with no slots;
  - `all()` and `ids()` exclude it;
  - composing it empty gives no placements and no warnings;
  - composing it with an item renders only that item;
  - automatic choice never returns `blank`.
- `composer test`, `composer lint`, `npm run build` and `npm run lint:js` must be clean.
