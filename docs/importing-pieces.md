# Importing your own pieces

Your pieces live in `wp-content/uploads/sprint-illustrations/`, so they survive plugin updates.

## 1. Draw

- One piece per SVG with a `viewBox`. Use the starter pack's world scale: a standing adult is about 310 units tall.
- Colour only with classes:
  - `slot-primary`, `slot-secondary`, `slot-accent`, `slot-neutral`, `slot-background`, `slot-skin`, `slot-hair`. Add `-light` or `-dark` for shades.
  - `slot-outline` for outlines. Put `slot-stroke-<name>` on a stroked shape.
- No `<style>`, `<image>`, `<script>`, `<foreignObject>`, fonts or external references. They're removed on import.
- Stroke-only paths: add `fill="none"`. Outline strokes: `stroke-width="2" vector-effect="non-scaling-stroke"`.

## 2. Mark anchors

Add a small circle (or rect) with an ID where things attach. The marker is removed on import.

| Marker ID | Piece | Meaning |
|---|---|---|
| `anchor-ground` | characters | Point between the feet |
| `anchor-hold` | characters | Lowered hand (standing) or lap (seated) |
| `anchor-grip` | handheld objects | Point the hand holds |
| `anchor-base` | objects that sit on something | Bottom centre |

In Figma or Illustrator, name the layer `anchor-hold` and export with "Include id attribute" or "Layer names as IDs" turned on.

## 3. Optional metadata (skips the prompts)

On the root `<svg>`:

    data-si-label="Sam, sitting"
    data-si-tags="person,sitting,laptop"      (single, singular words)
    data-si-z="30"
    data-si-accepts="hold:lap"                 (characters: hold:handheld or hold:lap)
    data-si-mounts="lap:base,surface:base"     (objects: handheld:grip / lap:base / surface:base)

## 4. Import

1. Put the files in `wp-content/uploads/sprint-illustrations/inbox/<category>/`, where `<category>` is `characters`, `objects`, `backgrounds` or `decor`.
2. In Local's **Open site shell**, run:

       wp sprint-illustrations build-manifest            # asks for tags, z, accepts, mounts
       wp sprint-illustrations build-manifest --non-interactive

   Without WP-CLI:

       php wp-content/plugins/sprint-illustrations/bin/build-manifest.php \
         --source=wp-content/uploads/sprint-illustrations/inbox \
         --target=wp-content/uploads/sprint-illustrations

3. Read the warnings. A "literal fill" or "no slot class" warning means that part won't follow the palette.
4. Check the result on **Sprint Illustrations → Test page**.

Re-running is safe. Pieces are matched by ID (`<category prefix>-<file name>`, for example `char-sam-sit`), and a piece whose source file was removed stays in the manifest until you delete its entry.

## Asking Claude Code instead

Editors can request a piece on **Sprint Illustrations → Library** ("Request a piece"). Ask Claude Code to "make the requested pieces": it draws them to these same rules, builds the manifest and marks each request done. New pieces show a **Custom** badge.
