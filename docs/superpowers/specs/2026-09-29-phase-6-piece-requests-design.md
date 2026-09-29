# Sprint Illustrations — Phase 6 Design: Piece requests

Date: 2026-09-29
Status: Approved design
Builds on: phase 3b (Library page), phase 5 §9 (the "Ask Claude Code" workflow). Branch `phase-6-piece-requests`, stacked on `phase-5-ai`.

## 1. Idea

Editors ask for new pieces on the Library page. Claude Code draws them when the owner says "make the requested pieces", following the same authoring rules as the starter pack, and adds them to the **site library** in `uploads/sprint-illustrations/`. That library is already loaded through `sprint_illustrations_library_paths`, so new pieces appear in the Library, the Builder and automatic picks with no plugin change. No API credit is used.

## 2. Requests

**Private post type `si_piece_request`:**
- `post_title` holds the description, and `post_author`/`post_date` record who asked and when.
- Meta: `_si_category`, `_si_state` (`queued`, `done` or `declined`), `_si_piece` (the resulting piece ID) and `_si_note` (Claude Code's note).

**Pure `Library\PieceRequest`:**
- `CATEGORIES` are `Piece::CATEGORIES`; `STATES` are `queued`, `done` and `declined`.
- `validate( string $category, string $description ): string` returns the error message, or `''` when valid. The category must be known, and the description, tag-stripped with whitespace collapsed, must be 3–300 characters long.
- `clean( string $description ): string`

**`Storage\PieceRequestRepository`** (WordPress-facing):
- `create( string $category, string $description, int $author ): int|\WP_Error`
- `get( int $id ): ?array{id, category, description, state, piece, note, author, date}`
- `list( string $state, int $limit ): array`
- `cancel( int $id ): bool`, which deletes the request permanently. Only queued requests can be cancelled.
- `complete( int $id, string $piece ): bool` and `decline( int $id, string $note ): bool`

## 3. Library page

**A "Request a piece" panel** (visible with `edit_posts`) sits above the tabs:
- **Category** select, a **Describe it** textarea (maximum 300 characters, placeholder "e.g. A black Aberdeen taxi, side view"), and an **Add request** button. It posts to `admin-post` action `sprint_illustrations_piece_request`, protected by a nonce.
- Hint: "Then ask Claude Code: “make the requested pieces”."
- **Waiting (n):** each queued request shows its category, description, "by <name>, <time> ago", and a **Cancel** link. Cancel is available to the author and to users with `manage_options`, and uses the nonce action `sprint_illustrations_piece_request_cancel`.
- **Recently added:** the last 5 done requests, each linked to `&tab=<category>&piece=<id>#piece-<id>`.
- **Declined:** the last 5, each with its note.
- Redirects back with a notice: "Request added." or "Request cancelled.", or the validation error. `Admin\Notices` gains a per-user queue so editors see these notices too; the current queue is admin-only.

**Cards:**
- A custom piece (one whose path is under `Plugin::user_library_dir()`) shows a small **Custom** badge.
- Cards get `id="piece-<id>"`. `&piece=<id>` highlights that card with a 2 px admin-blue outline.

The panel is `Admin\PieceRequestPanel`, which renders and handles the form; `LibraryPage` calls it.

## 4. WP-CLI for Claude Code

- `wp sprint-illustrations requests [--state=queued|done|declined|all] [--format=table|json]`
- `wp sprint-illustrations requests done <id> --piece=<piece-id>`: the piece must exist in the loaded library, and the request must be queued.
- `wp sprint-illustrations requests decline <id> --note=<text>`
- `wp sprint-illustrations piece remove <piece-id>`:
  - It applies to custom pieces only; bundled ones are refused.
  - The pure `Library\ManifestEditor::remove( array $manifest, string $id ): array{manifest, file: ?string}` removes the entry and bumps the version.
  - The command then deletes the built file and any source in `inbox/<category>/<name>.svg`.
  - Saved designs that used the piece fall back automatically.

**The recipe goes in `CLAUDE.md`:**
1. List the queued requests.
2. Draw each piece at `uploads/sprint-illustrations/inbox/<category>/<name>.svg` following `docs/importing-pieces.md` and the starter pack's style. Colour comes only from slot classes. Characters need `anchor-ground` and `anchor-hold`, a person, and `data-si-accepts`. Objects need `data-si-mounts` and a grip or base anchor.
3. Run `build-manifest --non-interactive`. It must build with **zero warnings**.
4. Look at the result through `compose` in a fitting template, rendered and viewed.
5. Run `requests done <id> --piece=<id>`, or `decline` with a helpful note.

## 5. Testing

- **PHPUnit:** `PieceRequestTest` covers categories, length, tag stripping and clean. `ManifestEditorTest` covers removing, an unknown ID, the version bump and the file path.
- **WP-CLI script:**
  - Permissions: a subscriber can't create; an author can create and cancel their own but not someone else's; an admin can cancel any.
  - Validation errors.
  - Transitions: `done` rejects an unknown piece and a request that isn't queued; `decline`.
  - A temporary custom piece, built from a scratch SVG, appears in `/library` and gets the Library card badge. `piece remove` deletes it and refuses bundled IDs.
  - Cleanup.
- **Library page review** at 1440 and 782 px, with a frontend-design pass on the panel.
- **Live demo:** a real request (an Aberdeen taxi object) fulfilled end to end and shown in the Builder's piece picker.

## 6. Out of scope

Uploading SVGs through the browser, editing pieces in the browser, a UI delete button for custom pieces (Claude Code runs `piece remove` when asked), and generating pieces through the API.

## 7. Amendment (2026-09-29): review before keeping, and visible progress

The owner wants to see each request's progress and to **keep or discard** a drawn piece before it joins the library. The queue label "by kohid, 30 minutes ago" was also read as a duration.

**States and transitions** (pure `PieceRequest::STATES` and `can_move( $from, $to )`):

| From | To |
|---|---|
| `queued` | `drawing` (Claude Code started), `review` (draft submitted directly), `declined` |
| `drawing` | `review`, `declined`, `queued` (Claude Code gave up) |
| `review` | `done` (Keep), `discarded` (Discard) |
| `discarded` or `declined` | `queued` (Try again, with an optional feedback note) |

Only `queued` requests can be cancelled.

**Drafts** (`Storage\PieceDrafts`, WordPress-facing), kept out of the library until they're kept:
- **Source:** `uploads/sprint-illustrations/drafts/src/<request-id>/<category>/<name>.svg`. It's built with `ManifestBuilder` into `drafts/build/<request-id>/`, which gives a sanitized piece plus its own manifest.
- **Submitting a draft needs zero warnings and zero errors**, and a name that doesn't clash with an existing piece ID.
- **Preview:** the draft piece rendered through `PiecePreviews`, plus a sample scene. `Services::create` is built with the user manifests plus the draft manifest, and the scene uses the pure `PieceRequest::sample( $category, $piece_id ): array{template, picks}`:

  | Category | Template | Slot |
  |---|---|---|
  | characters | `hero-left-character` | `subject` |
  | objects | `centered-object-with-decor` | `hero` |
  | backgrounds | `centered-object-with-decor` | `bg` |
  | decor | `centered-object-with-decor` | `decor` (as a list) |

- **Keep:** copy the source into `inbox/<category>/<name>.svg`, then run `ManifestBuilder( inbox → library )` in PHP with `NullPrompter`. The request moves to `done` with the piece ID, and the draft folders are removed.
- **Discard:** delete the draft folders and move the request to `discarded`.
- **Try again:** store an optional `_si_feedback` note (up to 300 characters) and move the request back to `queued`.

**UI on the Library page:**
- The queue splits into **In progress** (queued, drawing and review), **Recently added**, and **Discarded or declined**.
- In-progress cards show a 4-step track: **Requested → Drawing → Ready for review → Added**. The current step is highlighted; drawing gets a subtle pulse, which respects reduced motion.
- **Wording:** "Requested by <name> <time> ago", "Added <time> ago", and "Waiting for you to ask Claude Code" or "Claude Code is drawing…".
- **Review cards** show the piece preview and the sample scene side by side, with **Keep** and **Discard** buttons: admin-post POST requests with a nonce per request, allowed for the author or `manage_options`.
- **Discarded or declined cards** show the note and a **Try again** form with an optional feedback input.
- **Live updates:** `assets/admin/library.js` polls admin-ajax `sprint_illustrations_request_states` (nonce, `edit_posts`, returns `{id: state}`) every 10 seconds while any request is queued or drawing, and only while the tab is visible. It reloads the page when a state changes.

**WP-CLI (Claude Code):**
- New: `requests start <id>` and `requests draft <id> --file=<svg>`. The latter validates, stores the draft and moves the request to review, printing any warnings or errors.
- `requests done` is removed, since review replaces it.
- `requests list` shows the feedback notes.
- The `CLAUDE.md` recipe is updated to: start → draw to a scratch file → `draft` → the owner keeps or discards.

**Optional watching:** in Claude Code, `/loop make the requested pieces` has Claude Code check the queue while the session stays open.
