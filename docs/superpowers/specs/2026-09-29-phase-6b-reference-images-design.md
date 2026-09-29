# Phase 6b: Reference images on piece requests

## Goal

You can attach an optional image (a photo or a sketch) to **Request a piece**. Claude Code looks at it and draws the piece by hand in the library's flat style: slot colours only, real-world scale, anchors. It is **not** traced automatically, because traced paths would carry literal colours, ignore the palette and have no anchors. Keep and Discard work as before.

## Rules (pure `Library\ReferenceImage`, unit-tested)

- **Accepted:** PNG, JPEG or WebP, at most **5 MB**, between 16 and 8000 px on each side. `validate( bytes, mime, w, h ): string` returns an error message, or `''` when the image is fine.
- **Size:** `fit( w, h ): [w, h]` scales an image down so its longest side is at most **1600 px**, and never scales up.
- **Names:** stored files are named `ref-<16 random [a-z0-9]>.png|jpg`. `is_name()` checks a name against that pattern before any file operation.

## Storage (`Storage\ReferenceImages`, WordPress-facing)

**`store( $_FILES entry ): name | WP_Error`:**
- It requires `UPLOAD_ERR_OK` and `is_uploaded_file()`. It checks the MIME type with `wp_check_filetype_and_ext()` and the size with `getimagesize()`, then applies the pure rules.
- **Re-encoding with GD:** the image is decoded with `imagecreatefromstring()`, resized with `fit()`, and saved again. It becomes a PNG when it has transparency and a JPEG at quality 85 otherwise. This strips metadata and anything hidden in the file, and only GD-decodable images get through.
- **Where:** `uploads/sprint-illustrations/references/`.

**Helpers:** `path()`, `url()` and `delete()`.

**Linking to requests:**
- The request stores the file name in the meta key `_si_reference`, and rows gain `reference`.
- The file is stored before the request is created. If creating the request fails, the file is deleted.
- **Cancel** and **Keep** delete the reference. **Discard** and **Decline** keep it, so **Try again** can reuse it.

## Library page

- **Form:**
  - The form is `multipart/form-data`, with an optional "Reference image" file input (`accept` png/jpeg/webp).
  - The help text reads "PNG, JPEG or WebP, up to 5 MB. Claude Code draws from it in the library's flat style."
  - `library.js` shows a small preview of the chosen image with a **Remove** link.
- **Requests** that have a reference show a 40 px thumbnail that links to the full image.
- The success notice becomes "Request added. Claude Code draws it while a session is open."

## Claude Code

- `requests list --format=json` and the `requests watch` event include `reference`, the absolute file path or `""`.
- `session-start.sh` and the CLAUDE.md recipe say: when `reference` is set, read that image first and draw from it in the flat style. The recipe also covers keeping the shape, proportions, livery and pose, and mapping colours to slots.

## Tests

- `ReferenceImageTest`: validation limits, the `fit` maths and the name pattern.
- `composer test` and `composer lint` must be clean, and `library.js` must pass `node --check`.
- **Live check:** upload through the real handler, confirm the file is re-encoded and the watcher shows `reference`, then draw a request from its image.
