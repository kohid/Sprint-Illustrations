# Sprint Illustrations — Phase 3b Design: Media export and Library browser

Date: 2026-09-29
Status: Approved design
Parent: `2026-09-28-sprint-illustrations-design.md` §12 (MediaExporter), §13 (Library browser), §10 (PNG upload rules); builds on phase 3a (`2026-09-29-phase-3a-builder-design.md`).

## 1. Site facts that shaped this design

- SVG uploads are **not** allowed site-wide, and they stay that way. The export allows SVG only for the single `wp_upload_bits()` call that writes our own sanitized file.
- **Imagick is not loaded** on this site, so the server can't rasterize SVG. PNG is drawn in the browser (SVG → `<img>` → canvas → PNG blob), as parent §12 planned. GD is present, so WordPress still reads PNG sizes and generates thumbnails.

## 2. Media export

### 2.1 Pure helpers

- `Media\SvgFile::standalone( string $markup ): string` takes composer output that already carries a real instance ID. It prefixes the XML declaration and adds `width` and `height` to the root `<svg>` from its `viewBox`, so the file has an intrinsic size in the Media Library and in image editors. `SvgFile::size( string $markup ): ?array{0: int, 1: int}` returns those dimensions.
- `Media\PngCheck::problem( string $head, int $bytes ): ?string` checks a file's first 32 bytes and its size. It returns an error message, or null when the file is valid. The rules:
  - The PNG signature must be present.
  - Width and height come from IHDR and must each be between 1 and 8000.
  - The size must be at most 5 MB.

### 2.2 REST (`sprint-illustrations/v1`, `upload_files`)

| Route | Input | Behaviour |
|---|---|---|
| `POST /media/svg` | `{ spec, illustration_id?, title? }` | Composes through the caching composer, gives it a unique instance ID, and runs `SvgFile::standalone()`. Writes the file with `wp_upload_bits()` while an `upload_mimes` filter adds `svg` for that call only, removed afterwards. Inserts an attachment (`image/svg+xml`) with metadata `{ width, height, file }` and alt text from `spec.title`, falling back to the template label. Stores `_si_source_illustration` when `illustration_id` is a saved illustration the user can read. Returns 201 `{ id, url, edit_url }`. A composition failure returns 422. |
| `POST /media/png` | multipart `file`, plus `illustration_id?`, `title?`, `alt?` | Runs `PngCheck::problem()` on the upload, then `wp_check_filetype_and_ext()` and `getimagesize()` must both say `image/png`, otherwise 400 with a plain-language message. `media_handle_sideload()` generates GD thumbnails. Then it sets the alt text and the source meta. Returns 201 `{ id, url, edit_url }`. |

- The error codes are `sprint_illustrations_{rest_forbidden, composition_failed, invalid_png, upload_failed}`.
- `POST /compose` also returns `background` (the resolved palette's background hex), for the social PNG.

### 2.3 Builder Export

An **Export** dropdown in the top bar offers three options:

| Option | Output |
|---|---|
| **SVG to Media Library** | `POST /media/svg` with the current spec. |
| **PNG, 2× canvas** | The current stage SVG drawn at twice the template canvas (for example 1600 × 1200), on a transparent background. |
| **PNG for social sharing, 1200 × 630** | The scene fitted and centred on the palette background colour. |

- PNG is drawn in the browser: the SVG gets explicit `width`/`height`, becomes a Blob URL and then an `Image`, is drawn onto a canvas, and `toBlob('image/png')` produces the file, which goes to `POST /media/png` as FormData.
- Export works on unsaved designs. The illustration is linked as the source only when saved (`state.id`).
- **On success**, a success notice reads "Saved to Media Library." with an **Edit** action (`edit_url`).
- **On failure**, an error notice shows the server's message, or "The PNG couldn't be created in this browser." when canvas rendering fails.
- While an export runs, the toggle is busy and disabled.

## 3. Library browser

- **Menu:** Sprint Illustrations → **Library** (`sprint-illustrations-library`, `edit_posts`). It's server-rendered PHP with no JavaScript.
- **Tabs** (`nav-tab-wrapper`): Characters, Objects, Backgrounds, Decor and Templates (`&tab=`), each with a count.
- **Search** (`&s=`) matches labels and tags, case-insensitively, with every word required. It's pure: `Library\LibraryFilter::pieces( array $pieces, string $category, string $search ): array` and `::templates( array $templates, string $search ): array`.
- **Piece cards:**
  - A preview in the site palette, from the cached `Rest\PiecePreviews`.
  - The label, and tags as small chips.
  - The person, for characters.
  - How the piece attaches. For objects: "Held in a hand" (handheld), "Rests on a lap" (lap), "Stands on a surface" (surface). For characters: "Holds things in a hand" (hold: handheld) or "Holds a laptop on the lap" (hold: lap).
- **Template cards:** the template's scene (seed 3, site palette, decorative, through the caching composer), the label, the slot names, and an **Open in Builder** link to `admin.php?page=sprint-illustrations&template=<id>`.
  - `BuilderPage` localizes `initialTemplate` (sanitized), and the Builder starts on it when it exists in the library and no `illustration` is being opened.
- **Empty search:** "No pieces match “<search>”. Try a tag like laptop or team." Empty categories say where to add pieces.
- **Visual direction:** the same white admin panels and `#f6f7f7` dotted-canvas previews as Settings and the Builder, a 4-column card grid at 1280 px or wider and 2 columns below 782 px. Nothing new.

## 4. Testing

- **PHPUnit:** `SvgFileTest` (width/height from the viewBox, the XML declaration, idempotent), `PngCheckTest` (valid PNG, wrong signature, too large, zero or huge dimensions) and `LibraryFilterTest` (category, multi-word search across label and tags, templates).
- **WP-CLI script:**
  - `/media/svg`: anonymous gets 401, Subscriber 403, Author 201. The attachment has `image/svg+xml`, width and height meta, alt text and the source meta, and the file starts with `<?xml` and contains `width="800"`.
  - **`svg` is not in `get_allowed_mime_types()` after the call.**
  - `/media/png`: a real GD-generated 64 × 32 PNG gives 201 with thumbnails. A text file renamed `.png` gives 400. A PNG over 5 MB gives 400.
  - `/compose` includes `background`.
  - The Library page renders through `wp --user eval` for each tab and for a search.
  - The script deletes every attachment it creates.
- **Builder:** the static review page, with `/media/*` mocked, checks that the three Export options send the right request, and that the PNG blobs have the expected dimensions (1600 × 1200 and 1200 × 630).
- **Library:** its rendered markup is checked at 1440 px and 782 px.
- **Needs a login:** real exports from the Builder, which then appear in Media.

## 5. Out of scope

Bulk export, cropping or format options beyond the three presets, and editing pieces from the Library page.
