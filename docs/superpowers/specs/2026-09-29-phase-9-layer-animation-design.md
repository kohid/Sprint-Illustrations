# Phase 9: Animating layers

## Goal

Each layer can have an entrance that plays once, a loop that keeps going, or both, set in the Builder's Slots list. The motion is pure CSS driven by classes. The sanitizer strips `style` attributes and the output has no `<style>` element, so there is no JS, no SMIL and no inline style. Visitors who prefer reduced motion see the still picture. Exported PNGs are still. Exported SVG files keep the classes but not the CSS, so they are still too.

## Scene format (pure, unit-tested)

`SceneSpec::$animations` maps a layer key (a slot name or `item:<key>`, the same regex as `layers`) to `{ enter, loop, delay, speed }`, with at most 64 entries.

| Field | Values |
|---|---|
| `enter` | `none`, `fade`, `rise`, `pop` |
| `loop` | `none`, `float`, `sway`, `pulse`, `spin`, `twinkle` |
| `delay` | 0–3 s in steps of 0.1 (`round( x, 1 )`) |
| `speed` | `slow`, `normal`, `fast` |

- An entry with both `enter` and `loop` set to `none` is dropped.
- `to_array()` omits `animations` when it's empty, so older specs and their cache keys are unchanged.

`Compose\Animation` (pure) has:
- `classes( array $entry ): array{outer: string, inner: string}`:
  - **outer:** `si-a-enter-<x>`, `si-a-d-<tenths>` and `si-a-s-<speed>`;
  - **inner:** `si-a-loop-<y>`;
  - a part with nothing to animate is `''`.
- `normalize()`, which `SceneSpec` uses.

**Resolving and rendering:**
- `ResolvedScene` gains `roots` (slot name → layer key; items map to themselves).
- When any animation is set, `SceneLayout::order` paints group by group, so each animated layer is contiguous. With no animations and no layers, the placements are returned untouched, as today.
- `Composer::render` wraps each animated layer's placements in `<g class="si-a si-a-layer {outer}"><g class="{inner}">…</g></g>` and leaves the other layers as they are. The two nested groups let an entrance and a loop run together.

## CSS (`assets/front/illustration.css`, also enqueued on the Builder page)

**Setup:**
- `.si-a-layer` and its inner group use `transform-box: fill-box` and `transform-origin: 50% 100%`. `spin`, `pulse` and `twinkle` use the centre instead.
- Custom properties set timing: `--si-delay` comes from the `si-a-d-N` classes (N = 0–30, generated), and `--si-speed` from slow (1.6), normal (1) or fast (0.6).

**Entrances** run 0.8 s × speed, `both`, once:
- `fade`: opacity 0 → 1.
- `rise`: move up 24 units and fade in.
- `pop`: grow from 0.6 → 1 and fade in.

**Loops** run infinitely, starting after the delay, plus 0.8 s when there's an entrance:

| Loop | Movement | Duration × speed |
|---|---|---|
| `float` | moves up and down 10 units | 4 s |
| `sway` | rotates ±3° from the bottom | 3.5 s |
| `pulse` | grows 1 → 1.06 | 2.4 s |
| `spin` | turns 360°, linear | 14 s |
| `twinkle` | opacity 1 → 0.35 and scale 0.9 | 1.8 s |

Everything sits inside `@media (prefers-reduced-motion: no-preference)`.

## Builder

- Each Slots row (slot or added piece) gets an **Animate** button (Dashicon `controls-play`). It opens a popover with:
  - Entrance: None, Fade in, Rise, Pop;
  - Loop: None, Float, Sway, Pulse, Spin, Twinkle;
  - Delay: a 0–3 s range;
  - Speed: Slow, Normal, Fast;
  - **Remove animation**.
- An animated row shows a small badge naming the motion, such as "Rise + Float".
- A **Replay** button above the canvas restarts the animations by re-mounting the art.
- **State:** `SET_ANIMATION { key, value | null }`. `REMOVE_ITEM` drops its animation. `SET_TEMPLATE` keeps item animations and drops slot ones. `LOADED` reads `animations`.

## Tests

- **PHPUnit:** normalizing animations; the class mapping; an old spec's output staying byte-identical; an animated layer rendered as a nested group with the expected classes; and animated groups painted contiguously.
- **Tooling:** lint and build must be clean.
- **Live check:** compose an animated scene through REST and render the shortcode.
