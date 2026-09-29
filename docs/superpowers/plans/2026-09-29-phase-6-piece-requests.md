# Sprint Illustrations — Phase 6 (Piece requests) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Editors request pieces on the Library page, and Claude Code draws them into the site library and marks the requests done, so they show up in the Library and the Builder.

**Architecture:**
- **Pure:** `Library\PieceRequest` (validation) and `Library\ManifestEditor` (removing an entry).
- **WordPress-facing:** the `si_piece_request` post type and its repository, an `Admin\PieceRequestPanel` rendered in the Library page, per-user notices, and a `Cli\RequestsCommand` plus a `piece remove` command.

**Tech Stack:** PHP 8.1+, WordPress 7.1.2, PHPUnit 10.5, WPCS 3, WP-CLI.

**Spec:** `docs/superpowers/specs/2026-09-29-phase-6-piece-requests-design.md`.

## Global Constraints

- Descriptions are 3–300 characters after stripping tags and collapsing whitespace.
- Categories are `Piece::CATEGORIES`; states are `queued`, `done` and `declined`.
- **Capabilities:** creating a request needs `edit_posts`. Cancelling needs to be the author or have `manage_options`, and only queued requests can be cancelled.
- **Nonce actions:** `sprint_illustrations_piece_request` and `sprint_illustrations_piece_request_cancel`.
- Custom pieces are the ones under `Plugin::user_library_dir()`, and `piece remove` refuses any other piece.
- The version becomes `0.7.0`. Commit messages end with the `Co-Authored-By:` trailer.

---

### Task 1: Pure `PieceRequest` and `ManifestEditor`

**Files:**
- Create: `src/Library/PieceRequest.php`, `src/Library/ManifestEditor.php`, `tests/Unit/Library/PieceRequestTest.php` and `tests/Unit/Library/ManifestEditorTest.php`

- [ ] **Step 1:** Write the tests:
  - `validate()` gives `''` for a valid request. It errors on an unknown category and on descriptions shorter than 3 or longer than 300 characters. It strips tags before counting.
  - `clean()` collapses whitespace.
  - `ManifestEditor::remove()` drops the entry, re-indexes `pieces`, bumps `version`, and returns the entry's `file`. An unknown ID returns `file: null` and leaves the manifest unchanged.
- [ ] **Step 2:** Run the tests and watch them fail.
- [ ] **Step 3:** Implement both classes. They're pure, using `strip_tags` with the reasoned `phpcs:ignore` used elsewhere in pure code.
- [ ] **Step 4:** Run `composer test` and `composer lint`, which should both pass cleanly.
- [ ] **Step 5:** Commit: `feat(library): piece request validation and manifest removal`.

### Task 2: Storage, the Library panel and notices

**Files:**
- Create: `src/Storage/PieceRequestPostType.php`, `src/Storage/PieceRequestRepository.php`, `src/Admin/PieceRequestPanel.php`
- Modify:
  - `src/Admin/LibraryPage.php`: the panel, a Custom badge, card IDs and highlight.
  - `src/Admin/Notices.php`: add `add_for_user()`, and let the queue render for its own user.
  - `src/Plugin.php`: register the post type and `piece_requests()`.
  - `assets/admin/library.css`
  - `sprint-illustrations.php` and `blocks/illustration/block.json`: bump the version to `0.7.0`.

- [ ] **Step 1:** Implement the post type:
  - It's private, with `show_ui` false, `capability_type` `post`, `map_meta_cap`, and supports `title` and `author`.
  - The repository stores `_si_category`, `_si_state`, `_si_piece` and `_si_note`.
- [ ] **Step 2:** Implement `PieceRequestPanel`:
  - `register()` hooks `admin_post_` for create and cancel.
  - `render( string $current_tab )`, `handle_create()` and `handle_cancel()`. Each handler checks the nonce and capability, validates, calls the repository, adds a user notice, and redirects back to the Library tab with `wp_safe_redirect`.
- [ ] **Step 3:** Update `LibraryPage`:
  - Render the panel above the tabs.
  - Cards get `id="piece-<id>"`, a `is-highlighted` class when `$_GET['piece']` matches, and a **Custom** badge when the piece's path is under `user_library_dir()`.
- [ ] **Step 4:** Style it with frontend-design: the panel in the `si-panel` style and a two-column layout (form | queue) at 1280 px and up, the badge, and the highlight. Review the rendered markup at 1440 and 782 px.
- [ ] **Step 5:** Commit: `feat(admin): request pieces from the Library page`.

### Task 3: WP-CLI for Claude Code, the recipe, verification and the demo

**Files:**
- Create: `src/Cli/RequestsCommand.php`, `src/Cli/PieceCommand.php`
- Modify: `src/Plugin.php` (register `sprint-illustrations requests` and `sprint-illustrations piece`), `CLAUDE.md`, `docs/importing-pieces.md` (a pointer)

- [ ] **Step 1:** Write the `requests` commands:
  - `requests` lists requests, with `--state` and `--format`.
  - `requests done <id> --piece=` checks that the piece exists in `services()->manifest` and that the request is queued.
  - `requests decline <id> --note=`
- [ ] **Step 2:** Write `piece remove <id>`:
  - It refuses non-custom pieces.
  - It reads the user `manifest.json`, calls `ManifestEditor::remove`, writes the file back, and deletes the built file and the `inbox/<category>/<name>.svg` source.
  - It purges the preview transients by calling `delete_transient` for the piece's preview key, if one is used. Otherwise the manifest version bump invalidates it.
- [ ] **Step 3:** Add the `CLAUDE.md` recipe (spec §4).
- [ ] **Step 4:** Verify with a WP-CLI script covering spec §5. It cleans up the temporary users, requests and custom piece. Then run `composer test` and `composer lint`.
- [ ] **Step 5:** Commit, push, and open a PR stacked on `phase-5-ai`.
- [ ] **Step 6:** Live demo: file a real request ("Objects: a black Aberdeen taxi, side view"), fulfil it, and show it in the Library and the Builder.

## Execution notes

- **Task 1:** 7 tests. The first commit carried one PHPCS Yoda finding, which was fixed in a follow-up commit before the push.
- **Task 2:**
  - The `admin-post` handlers were exercised through the real callbacks, with `wp_redirect` and `wp_die` stubbed. They passed 23 checks: permissions (subscriber, author, another author, admin), validation, cancel and decline rules, and panel rendering.
  - The owner had already filed a real request (#442, "car") through the panel during the build. The test script left it untouched, and it became the live demo.
  - Visual review at 1440 and 782 px: the form sits beside the queue from 1280 px and stacks below that.
- **Task 3:** a throwaway `obj-si-test-box` covered the rest:
  - Build, the `/library` REST response, and the Custom badge and highlight on the Library page.
  - `requests done`: an unknown piece is rejected, and a request that's already done is rejected.
  - `decline` without a note is refused.
  - `piece remove` refuses `obj-laptop` and removes the manifest entry, the built file and the source.
- **Live demo:** request #442 was fulfilled with `obj-car`:
  - It's a flat side view on 2× units, so it's realistic next to 310-unit people. Its tags are car, vehicle, taxi, transport, travel and hero, with a `base` anchor and a surface mount.
  - It built with zero warnings, composed into Featured object, appears in the Builder library, and can be locked into Feature card's hero slot.

### Amendment (spec §7): review before keeping, and visible progress

- **Transitions and sample scenes:** pure `PieceRequest::can_move` and `sample`, with 2 new tests.
- **Drafts:** `Storage\PieceDrafts` builds each draft into `drafts/` with `ManifestBuilder` and previews it through `Services::create`, using the user manifests plus the draft's manifest. Keep rebuilds the site manifest in PHP.
- **Verification:**
  - **CLI:** `start` twice is refused. A draft with a literal colour, one that clashes with `obj-car`, and one whose name another draft already uses are all refused. A good draft isn't in the library before Keep.
  - **Handlers, 17 checks:** another author can't Keep. The page renders its previews, track and live-update data. Keep adds the piece to the library and redirects to it. Discard deletes the draft, and Try again stores cleaned feedback.
  - **Visual review** at 1440 and 782 px.
  - All throwaway data was removed.
- **Live:** the owner's "taxi" request (#452) was started, drawn (a black cab with roof sign and check stripe, zero warnings) and submitted as draft `obj-taxi`. It's left at **Ready for review** for the owner to Keep or Discard.
