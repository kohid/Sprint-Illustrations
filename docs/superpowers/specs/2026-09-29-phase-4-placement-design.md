# Sprint Illustrations — Phase 4 Design: Placement (Renderer, shortcode, block, Elementor widget)

Date: 2026-09-29
Status: Approved design
Parent spec: `2026-09-28-sprint-illustrations-design.md` §7 (errors), §14 (placement surfaces). This phase is built **before** phase 3 (Builder), at the user's request. That order puts illustrations on real pages first. Where this document and the parent disagree, this one wins.

## 1. Goal

Illustrations appear on real pages of the Local site through a shortcode, a block and an Elementor widget. All three use the site palette and the phase 2 cache.

Success criteria:
- `[sprint_illustration …]`, the `sprint-illustrations/illustration` block and the "Sprint Illustration" Elementor widget all render the same markup for the same settings.
- Visitors never see errors. Users who can edit posts see a readable notice.
- The front end loads no JavaScript. The only CSS is a small stylesheet, loaded only on pages that contain an illustration.
- Two published showcase pages on the Local site demonstrate every template and all three surfaces. They're checked on the front end without logging in.

## 2. Site facts that shaped this design

- The theme is `aberdeen-taxi-knowledge`, a classic theme (not a block theme). The block editor is enabled.
- 12 pages are built with Elementor 4.2.4. The `container` and `nested-elements` experiments are active, and pages use the `elementor_header_footer` template.
- WordPress is 7.1.2. `block.json` scripts work without a build step, and the `.asset.php` file is optional (hand-written here to declare dependencies).

## 3. Components

| Unit | Kind | Responsibility |
|---|---|---|
| `Render\Renderer` | pure | `spec( array $args ): SceneSpec` normalizes surface attributes. `render( array $args, bool $show_errors = false ): string` composes and wraps the result. |
| `Plugin::renderer()` | WP | Builds the Renderer from the caching composer, a palette resolver (`SitePalette::resolve`) and a `WP_DEBUG` logger |
| `Plugin::register_assets()` | WP | Registers the `sprint-illustrations` front-end stylesheet on `init` (priority 5) |
| `Plugin::editor_choices()` | WP | `{templates: [{label, value}], presets: [{label, value}]}` for the block and widget controls |
| `Integrations\Shortcode` | WP | `[sprint_illustration]` |
| `Integrations\Block` + `blocks/illustration/{block.json, render.php, editor.js, editor.asset.php}` | WP + plain JS | The block. The server renders it and the editor previews it with ServerSideRender. |
| `Integrations\Elementor\Loader` + `Widget` | WP | Registers the classic `Widget_Base` widget on `elementor/widgets/register` |
| `assets/front/illustration.css` | CSS | Responsive `figure` and the editor-only error notice |

## 4. Attributes (shared by all three surfaces)

| Attribute | Values | Default | Normalization |
|---|---|---|---|
| `template` | template ID, `""` or `"auto"` | `""` | `""` and `"auto"` mean automatic: the selector chooses from keywords, or from the seed when there are no keywords. An unknown ID is an error (notice for editors). |
| `keywords` | comma-separated text | `""` | `SceneSpec` normalization |
| `seed` | integer ≥ 0 | `1` | Numeric strings are accepted. Anything else becomes 1. |
| `palette` | `site`, `default`, `preset:<id>` | `site` | `""` becomes `site`. Unknown values fall back through `SceneSpec`/`PaletteSettings` to the default palette. |
| `title` | text | `""` | Accessible title override. Empty uses the template label. |
| `decorative` | boolean-ish | `false` | `"true"`, `"1"`, `"yes"` and `true` mean decorative. |

## 5. Renderer output

```html
<figure class="si-illustration si-template-hero-left-character"><svg … role="img" aria-labelledby="si-3fa2c1d0-1-t si-3fa2c1d0-1-d">…</svg></figure>
```

- The instance ID is `si-<first 8 hex of sha1(resolved spec JSON)>-<per-request counter>`, so two copies of the same illustration on a page never share IDs.
- The block wraps the figure in `<div {get_block_wrapper_attributes()}>` so alignment and margin supports work.
- **Errors** (`CompositionException`):
  - With `$show_errors` true, the output is `<div class="si-illustration-error" role="alert">Sprint Illustrations: <escaped message></div>`.
  - Otherwise it's `<!-- Sprint Illustrations: illustration could not be rendered. -->`. The comment carries no message, because user text could contain `--`.
  - The message is logged when `WP_DEBUG` is on.
- Every surface passes `current_user_can( 'edit_posts' )` as `$show_errors`.

## 6. Surfaces

- **Shortcode:** `shortcode_atts()` with the defaults above, then enqueue the stylesheet, then render.
- **Block:** `block.json` apiVersion 3, category `media`, icon `art`. The attributes are those in §4 (`seed` is a number, `decorative` a boolean). `supports.align` is wide, full, left, right and center, and margin spacing is supported. The block uses `"style": "sprint-illustrations"`. The editor script is plain JavaScript on the `wp.*` globals and has:
  - **Illustration panel:** Template (with "Automatic (from keywords)"), Keywords, a **Use post title** button, Seed, a **Shuffle** button and Palette ("Site palette" plus six "Preset: …" entries).
  - **Accessibility panel:** Title (alt text) and Decorative.
  - The preview is `ServerSideRender`. The template and preset lists come from an inline `window.sprintIllustrationsBlock` added on `enqueue_block_editor_assets`.
- **Elementor widget:** named `sprint-illustration`, titled "Sprint Illustration", icon `eicon-image`, category `general`. It has the same controls (SELECT, TEXT, NUMBER, SELECT, TEXT, SWITCHER). `get_style_depends()` returns the stylesheet. There's no `content_template()`, so the editor preview is rendered on the server and matches the live page.

## 7. Showcase pages (the deliverable on the Local site)

The pages are created by a scratch script run with `wp eval-file`. The script isn't shipped with the plugin.

1. **"Sprint Illustrations — Showcase"** (block editor page, published):
   - A short intro.
   - For each of the 6 templates, a heading and a two-column row of blocks: seed 3 with the site palette, and seed 7 with a rotating preset.
   - A "Picked from keywords" section: keywords `remote team` and `startup launch`, automatic template.
   - A shortcode example.
2. **"Sprint Illustrations — Elementor"** (Elementor page, published, `elementor_header_footer`): a container with a heading and three Sprint Illustration widgets (hero-right-character, two-people-collaborating, feature-card-object).

Both pages are checked on the front end at 1440 px and 390 px. Checks: no horizontal overflow, the illustrations fit their columns, every non-decorative SVG has a title, and no error notices appear. Delete the pages with `wp post delete <id> --force`.

## 8. Testing

- **Unit tests (`RendererTest`, fixture library, no WordPress):**
  - `spec()` normalization: auto and empty template, empty palette, numeric-string seed, decorative strings.
  - The figure wrapper and its template class.
  - IDs are unique across two renders and no placeholder is left.
  - An unknown template gives a comment for visitors and an escaped notice for editors, and the logger is called.
  - The palette resolver receives `site` by default.
- **WP-CLI:** `do_shortcode`, `render_block` for the block, and the widget rendering from an Elementor document all produce a figure.
- **Front end:** the showcase pages as described in §7.
- **User (needs login):** insert the block in the editor and the widget in Elementor, and check that the controls update the preview.

## 9. Out of scope

- The `id=` attribute (saved illustrations) and picks. Both come with phase 3.
- Atomic (V4) Elementor elements. This site has them switched off, and the classic widget runs on both 3.x and 4.x.
