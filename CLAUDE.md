# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A WordPress plugin (PHP 8.1+, WP 6.4+) that composes flat, brand-coloured SVG illustrations from a library of SVG "pieces" placed into JSON scene templates. Output is deterministic per seed, sanitized, accessible, and needs no front-end JS.

- Design spec: `docs/superpowers/specs/2026-09-28-sprint-illustrations-design.md`. **§19 (amendments) overrides earlier sections.** §18 lists the build phases (1 core → 2 palette/cache → 3 REST/React builder → 4 block/shortcode/Elementor → 5 AI selector).
- Phase plans: `docs/superpowers/plans/2026-09-28-phase-1-core.md` (done), `docs/superpowers/plans/2026-09-29-phase-2-palette-cache.md` (done), `docs/superpowers/plans/2026-09-29-phase-4-placement.md` (built before phase 3 at the user's request), `docs/superpowers/plans/2026-09-29-phase-3a-builder.md` (saved illustrations, REST, Builder), `docs/superpowers/plans/2026-09-29-phase-3b-media-library.md` (Media export, Library browser), `docs/superpowers/plans/2026-09-29-phase-5-ai.md` (AI suggestions), `docs/superpowers/plans/2026-09-29-phase-6-piece-requests.md` (piece requests). Specs in `docs/superpowers/specs/`. All phases in §18 are built; phase 6 adds piece requests.
- The plugin lives inside a Local (by Flywheel) site (`aberdeen-taxi-knowledge`). Run all commands from the plugin root.

## Commands

```sh
composer install && composer prefix   # first setup; `prefix` runs Strauss → vendor-prefixed/
composer test                         # all unit tests (PHPUnit 10.5, no WordPress)
composer test -- tests/Unit/Compose   # one folder or file
composer test -- --filter test_name   # one test
composer lint                         # PHPCS (WPCS + PHPCompatibilityWP); must be zero errors and warnings
composer lint:fix                     # PHPCBF

php bin/build-manifest.php --non-interactive      # rebuild assets/pieces + assets/manifest.json from assets/pieces-src
php bin/contact-sheet.php [--seeds=1,2] [--templates=a,b] [--keywords=x,y] > sheet.html   # visual review without WP

npm install                           # Builder toolchain (@wordpress/scripts; typescript is needed by its ESLint plugin)
npm run build                         # src-js/builder → build/builder.js + builder.asset.php (build/ is committed)
npm run lint:js                       # ESLint (wp-scripts); `npx wp-scripts lint-js src-js --fix` to format
```

After changing `src-js/`, rebuild and commit `build/` with it: the server never runs Node.

WP-CLI (in Local's "Open site shell"): `wp sprint-illustrations compose --template=<id> --seed=<n> [--keywords=..] [--palette=site|default|preset:<id>] [--no-cache] [--out=file.svg]`, `wp sprint-illustrations cache stats|purge`, and `wp sprint-illustrations build-manifest [--source] [--target] [--non-interactive]` (defaults to `uploads/sprint-illustrations/inbox` → `uploads/sprint-illustrations`). The admin Test page (Sprint Illustrations menu) renders a contact sheet in WordPress.

Admin menu: **Builder** `admin.php?page=sprint-illustrations` (landing, `edit_posts`; `&illustration=<id>` opens a saved one, `&template=<id>` starts on a template), **Library** `sprint-illustrations-library` (`edit_posts`), **Settings** `sprint-illustrations-settings` and **Test page** `sprint-illustrations-test` (`manage_options`).

Placement: shortcode `[sprint_illustration id="<saved id>"]` or `[sprint_illustration template="" keywords="" seed="1" palette="site|preset:<id>" title="" decorative="false"]`, block `sprint-illustrations/illustration`, Elementor widget `sprint-illustration` ("Sprint Illustration"). Showcase pages on the Local site: `/sprint-illustrations-showcase/` (blocks + shortcode) and `/sprint-illustrations-elementor/` (widget); the generator script is in the phase 4 plan, Task 5.

Outside Local's site shell (e.g. from an agent's Bash), set the site's env and call the phar directly; `wp.bat` routes through cmd and mangles quoted arguments. The site id is `K0O3LRE-P` (from `%APPDATA%/Local/sites.json`): `PHPRC=/c/Users/Admin/AppData/Roaming/Local/run/K0O3LRE-P/conf/php`, PHP from `%APPDATA%/Local/lightning-services/php-8.2.29+0/bin/win64`, then `php "/c/Program Files (x86)/Local/resources/extraResources/bin/wp-cli/wp-cli.phar" <args>` from `app/public`. The site must be running in Local. Elementor 4.2.4 and Elementor Pro 4.2.3 are installed.

Both `vendor/autoload.php` **and** `vendor-prefixed/autoload.php` must exist; the plugin, `bin/` scripts and `tests/bootstrap.php` all require both.

## Architecture

**Boundary rule.** `Library`, `Compose`, `Palette`, `Security`, `Selection`, `Svg`, `Dev`, `Cache` and `Cli` (except the `*Command` classes: `Command`, `CacheCommand`, `RequestsCommand`, `PieceCommand`) are pure PHP with **no WordPress calls**. They run from PHPUnit and `bin/` scripts, and `phpcs.xml.dist` relaxes filesystem/escaping sniffs only for those paths. WordPress-facing code (`Plugin`, `Admin\*`, `Settings\*`, `Integrations\*`, the two CLI command classes) gets services from `Plugin` (`services()`, `composer()`, `site_palette()`, `presets()`, `cache()`).

**Wiring.** `Services::create( $root, $extra_manifests, $extra_template_dirs )` builds the whole core from a library root (`<root>/assets/{manifest.json,templates/,keywords/synonyms.json}`). Tests use it with `tests/fixtures/library`; `Plugin` uses it with the plugin dir plus user manifests from the `sprint_illustrations_library_paths` filter (default `uploads/sprint-illustrations/manifest.json`).

**Compose pipeline** (`Compose\Composer::compose( SceneSpec, Palette ): ComposedSvg`):
1. `SceneSpec::from_array()` normalizes input (a malformed template ID is dropped so the selector picks; an unknown well-formed ID throws `CompositionException`).
2. `Selection\RulesSelector` chooses a template from keywords when none is given (tokenize → synonyms → tag scoring, ties broken by seed).
3. `SceneResolver` turns template + manifest + seed into `Placement`s. **Every slot draws from its own PRNG stream** `Seed("<seed>|<template>|<slot>")` (mulberry32), so picking/locking one slot never changes others. Placement is `box` (fit `natural` uses template `unit`; `contain` fills) or `attach` to a parent anchor via the parent's `accepts` type and child's `mounts`.
4. `PieceLoader` loads + sanitizes piece markup; `Recolorer` replaces `slot-*` classes with explicit `fill`/`stroke` (no `<style>` in output); `IdScoper` rewrites ids/refs to a `__SIID__` placeholder that `ComposedSvg::with_instance_id()` fills at output time so cached markup stays shareable.
5. `Security\Sanitizer` (allowlist via prefixed `enshrined/svg-sanitize` plus a hardening pass) runs on the result; the wrapper adds `role="img"` + title/desc or `aria-hidden` when decorative.

**Palettes.** `Palette\PaletteSettings` (pure) normalizes the `sprint_illustrations_palette` option (`source` preset|custom|elementor, resolved `colors`/`skin`/`hair`, Elementor `map` + `sync`) and resolves SceneSpec palette refs `site` / `default` / `preset:<id>` / inline array; `Settings\SitePalette` is its WordPress wrapper. Presets live in `assets/palettes/presets.json` (`ContactSheet::review_palettes()` reads sprint/forest/night from it). `Palette::warnings()` flags base slots under 1.3:1 contrast against `background`.

**Cache.** `Plugin::composer()` is a `Cache\CachingComposer` over the plain `Composer`, backed by `SvgCache` (`uploads/sprint-illustrations/cache/<sha1>.json`). Keys = spec (sorted) + palette hash + manifest version + plugin version, so nothing needs explicit invalidation. Only warning-free results are stored, every hit is re-sanitized, and a hit touches the file (mtime = last use) for the daily `sprint_illustrations_cache_gc` cron (30 days).

**Elementor.** `Integrations\Elementor\ColorSource` reads Kit `system_colors`/`custom_colors` and V4 colour Variables through Elementor's own classes (Variables only when the `e_variables` + `e_atomic_elements` experiments are active — on this site they are set inactive). `Palette\ElementorMapping` (pure) suggests and applies slot mappings. `Sync` re-applies the stored map on `elementor/document/after_save` for the active Kit and on `_elementor_global_variables` meta changes (Variables fire no Elementor action).

**Settings page.** `Admin\SettingsPage` (Settings API form + `wp_ajax_sprint_illustrations_preview` live stage, uncached) with plain `assets/admin/settings.{css,js}` (no build step). It is the menu's landing page; the Test page is the `sprint-illustrations-test` submenu.

**Placement.** `Render\Renderer` (pure) is the single entry point for every surface: `spec()` normalizes attributes ("" or "auto" template means automatic, "" palette means site), `render( $args, $show_errors )` composes through `Plugin::composer()` (cached), gives each copy IDs `si-<hash8>-<n>`, and wraps in `<figure class="si-illustration si-template-…">`. Every surface passes `current_user_can( 'edit_posts' )` as `$show_errors`: visitors get a silent HTML comment, editors a readable notice. `Integrations\Shortcode`, `Integrations\Block` (`blocks/illustration/`: block.json apiVersion 3, `render.php`, plain-JS `editor.js` with a hand-written `editor.asset.php`, no build step) and `Integrations\Elementor\Widget` (classic `Widget_Base`, no `content_template()`, so the editor preview is rendered on the server) are thin adapters. The front-end stylesheet `assets/front/illustration.css` (handle `sprint-illustrations`) loads only where an illustration renders.

**Saved illustrations & REST.** `Storage\Illustration` (pure value object; note `title` = name, `spec.title` = SVG alt text) is stored as the private `si_illustration` post type with the resolved spec JSON in `_si_spec`, via `Storage\IllustrationRepository` (every ID is checked to be an `si_illustration` before use — the IDOR guard). REST namespace `sprint-illustrations/v1` (`Rest\*`, cookie nonce): `POST /compose` (uncached, returns svg + resolved spec + per-slot `picked`/`locked`/`boxes`), `GET /library` (templates, pieces with cached site-palette previews via transients, presets), `/illustrations` CRUD with `edit_posts` plus per-post `read_post`/`edit_post`/`delete_post` (`Rest\Permissions`: 404 for non-illustrations before 403). Saving composes through the caching composer and stores the resolved spec. `Render\Renderer` takes a 4th closure (`find_published`) so `id=` works on every surface; only `title`/`decorative` override a saved design.

**People.** Character pieces carry a `person` (`data-si-person`, default = first file-name part); `SceneResolver` never auto-picks a person already in the scene (falls back silently if that would leave nothing). `ComposedSvg::$boxes` gives each slot's rendered `[x,y,w,h]` for the Builder's hover outline.

**Builder.** React app in `src-js/builder/` (`App` holds a `useReducer` state from `state.js`: `picks` contains only locked slots, so Shuffle re-resolves everything else; a 150 ms debounced `/compose` applies only the latest response). Mounted by `Admin\BuilderPage` from `build/builder.js` with dependencies from `build/builder.asset.php`; `?illustration=<id>` opens a saved design, `?template=<id>` starts on a template.

**Media export.** `Rest\MediaController` (`upload_files`): `POST /media/svg` composes through the caching composer, makes a standalone file (`Media\SvgFile`: XML declaration + width/height from the viewBox) and writes it with `wp_upload_bits()` while an `upload_mimes` filter allows svg **for that one call only** (SVG uploads stay disallowed site-wide). `POST /media/png` takes a PNG rendered in the browser (`src-js/builder/exportPng.js`: SVG → img → canvas; the server has no Imagick, GD only) and checks it with `Media\PngCheck` (signature, IHDR 1–8000 px, ≤ 5 MB, IEND trailer) plus `wp_check_filetype_and_ext()`/`getimagesize()` before `media_handle_sideload()`. Attachments get alt text (spec title, else template label; none when decorative) and `_si_source_illustration` when a readable saved illustration is given. `/compose` also returns the palette `background` for the 1200 × 630 social PNG.

**AI suggestions.** `POST /ai/suggest` (`Rest\SuggestController`, `edit_posts`) turns text into `{ template, keywords, title, source, message }`. Claude (Anthropic Messages API via `Ai\AnthropicClient`, `wp_remote_post`, 20 s) is used only when `Ai\Settings::ready()` (switch on + key) and the user is under 30 calls/hour; any failure falls back to `RulesSelector` with a plain-language `message`. `Selection\AiRequest` (pure) asks for structured output (`output_config.format` json_schema whose template and keyword enums are the library's IDs and tags, `effort: low`); `Selection\AiResponse` (pure) re-validates the reply because the schema can't express limits (≤ 6 keywords, alt ≤ 120 chars). The key is stored in option `sprint_illustrations_ai`, encrypted with `Security\SecretStore` (sodium secretbox, key derived from `wp_salt( 'auth' )`), or comes from the `SPRINT_ILLUSTRATIONS_API_KEY` constant; it is never localized, returned or logged (browsers only get `aiReady`). AI never runs on page views. Surfaces: the Builder's Suggest panel, the block's Suggest from post, and the Elementor widget's Suggest button (`assets/elementor/editor.js`, a BUTTON control event). The AI option shares the palette form's Settings API group because `options.php` saves every option in a group. `paragonie/sodium_compat` (dev) lets tests run on PHP without ext-sodium.

**Illustrations via Claude Code** (the owner's preferred AI path: no API credit needed). When asked to add or refresh an illustration on a page:
1. `wp sprint-illustrations library --format=json` for the allowed template IDs and tags (includes user pieces).
2. Read the page: `wp post get <id> --fields=post_title,post_content` (for Elementor pages the text is in `_elementor_data`).
3. Choose a template, 1–6 keywords **from the tag list only**, and alt text of at most 120 characters describing the scene.
4. Apply with `wp sprint-illustrations apply <id> --template=… --keywords="a, b" --title="…" --user=<admin>`, which updates illustration block/widget `--index` (default 1). Add `--insert=top|bottom` for block pages with no illustration yet, and `--dry-run` to preview. Omitted `--keywords`/`--title`/`--seed` keep current values, and every save keeps a revision. Or use `wp sprint-illustrations save --name=… --template=… …` for a reusable saved illustration plus its shortcode.
Validation is `Selection\Suggestion` (strict: unknown tags and templates are errors, not guesses); page edits are `Cli\PlacementEditor` (pure). Elementor pages are saved through Elementor's document API, and `--insert` isn't supported there.

**Piece requests (Claude Code draws new pieces).** Editors queue requests on the Library page (`Admin\PieceRequestPanel`; private post type `si_piece_request` via `Storage\PieceRequestRepository`; pure rules in `Library\PieceRequest`). When the owner says "make the requested pieces":
1. `wp sprint-illustrations requests list` (add `--format=json` for details).
2. Draw each piece at `wp-content/uploads/sprint-illustrations/inbox/<category>/<name>.svg`, following `docs/importing-pieces.md` and the starter pack's style:
   - Flat shapes, colour **only** from slot classes (no literal colours), and `data-si-label`/`data-si-tags` (single, singular, lower-case tags).
   - Real-world scale: a standing adult is about 310 units tall.
   - Characters: `anchor-ground`, `anchor-hold`, `data-si-accepts`, and a person name as the first part of the file name.
   - Objects: `data-si-mounts` plus an `anchor-grip` or `anchor-base`. Add `hero` to tag big centrepiece objects and `floor` for standing props.
3. Run `wp sprint-illustrations build-manifest --non-interactive`. It must report **zero warnings**.
4. Render it with `wp sprint-illustrations compose --template=<fitting> --keywords=<its tag> --no-cache --out=…` and look at the result.
5. Run `wp sprint-illustrations requests done <id> --piece=<id>`, or `requests decline <id> --note="why + what to ask instead"`.
6. `wp sprint-illustrations piece remove <id>` deletes a custom piece; bundled pieces are refused. Pieces in `uploads/` survive plugin updates and appear with a **Custom** badge. `Cli\RequestsCommand` and `Cli\PieceCommand` are WordPress-facing, like `Cli\Command`.

**Library page.** `Admin\LibraryPage` (`sprint-illustrations-library`, `edit_posts`, server-rendered, `assets/admin/library.css`): tabs per category + Templates, search via pure `Library\LibraryFilter` (every word must appear in the label or tags).

**Piece library.** Author sources in `assets/pieces-src/<category>/` (characters, objects, backgrounds, decor). `Cli\ManifestBuilder` reads `data-si-*` root metadata and `id="anchor-<name>"` marker shapes (resolved through transforms), strips them, sanitizes, and writes `assets/pieces/` + `assets/manifest.json` (both generated but committed). Colour comes only from `slot-<name>`, `slot-<name>-light/-dark`, `slot-stroke-<name>`, `slot-outline` classes; literal colours other than `none`/`transparent` are warnings.

## Conventions and gotchas

- Every PHP file starts with `declare( strict_types=1 );`. WPCS style (tabs, spaces inside parens, Yoda conditions), `snake_case` methods/variables, short arrays allowed, PSR-4 filenames. Globals prefixed `sprint_illustrations`/`SprintIllustrations`; text domain `sprint-illustrations`.
- Reference the sanitizer library only as `SprintIllustrations\Vendor\enshrined\svgSanitize\…`. It is `require-dev`; releases ship `composer install --no-dev` output plus `vendor-prefixed/`.
- Strauss is pinned to **0.26.4** (0.30 fails on Windows paths). Don't bump it.
- Tags (pieces, templates, synonyms) must be single, singular, lower-case tokens that `Keywords::tokenize()` returns unchanged.
- `tests/Unit/StarterPackTest.php` gates the bundled library: sources must build with zero warnings, the committed `assets/manifest.json` must match a fresh build (rerun `php bin/build-manifest.php --non-interactive` after editing `pieces-src`), characters need `ground` and `hold` anchors, and every template × review palette × seeds 1–8 must compose with no warnings.
- WordPress-facing classes aren't covered by PHPUnit; verify them in the Local site (activate plugin, admin Test page, WP-CLI). Server-rendered admin markup can be checked with `wp --user=<admin> eval '… ->render();'`; browser checks of logged-in pages need the user to log in (don't mint auth cookies).
- `.gitattributes` pins LF. With `core.autocrlf=true`, older checkouts can still carry CRLF, which PHPCS rejects; `git add --renormalize .` fixes the index view.
- Git Bash's `sed -i` may miss lines in CRLF files; prefer the Edit tool for exact edits. `python` on this machine is the Windows Store stub (hangs on stdin); use `php -r` for scripting.
