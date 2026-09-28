# Sprint Illustrations — Phase 3b (Media export, Library browser) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Builder users export the current design to the Media Library as SVG or PNG, and anyone who can edit posts can browse the piece and template library on its own page.

**Architecture:**
- **Pure helpers:** `Media\SvgFile`, `Media\PngCheck` and `Library\LibraryFilter`.
- **WordPress classes:** `Rest\MediaController` (SVG written with a single-call `upload_mimes` allowance; PNG received from the browser) and `Admin\LibraryPage` (server-rendered).
- **Builder:** an Export dropdown that draws PNGs on a canvas in the browser.

**Tech Stack:** PHP 8.1+, WordPress 7.1.2, GD (no Imagick), PHPUnit 10.5, WPCS 3, `@wordpress/scripts` 30.

**Spec:** `docs/superpowers/specs/2026-09-29-phase-3b-media-library-design.md`.

## Global Constraints

- `Media` is a pure namespace. Add it to the `phpcs.xml.dist` pure-path groups. `Library` is already pure.
- `svg` must never remain in `get_allowed_mime_types()` after a request. The filter is added and removed around a single `wp_upload_bits()` call.
- The PNG limit is 5 MB, with each dimension between 1 and 8000 px. The media routes need `upload_files`. Error codes are `sprint_illustrations_{composition_failed, invalid_png, upload_failed}`.
- The Library page slug is `sprint-illustrations-library` (`edit_posts`).
- Plugin version is `0.5.0`. Rebuild and commit `build/` after any `src-js` change.
- Commit messages end with the `Co-Authored-By:` trailer.

---

### Task 1: Pure helpers (SvgFile, PngCheck, LibraryFilter)

**Files:**
- Create: `src/Media/SvgFile.php`, `src/Media/PngCheck.php`, `src/Library/LibraryFilter.php`, and the tests `tests/Unit/Media/SvgFileTest.php`, `tests/Unit/Media/PngCheckTest.php` and `tests/Unit/Library/LibraryFilterTest.php`
- Modify: `phpcs.xml.dist` (add `Media` to both pure groups)

**Interfaces:**
- `SvgFile::size( string $markup ): ?array{0: int, 1: int}` and `SvgFile::standalone( string $markup ): string`.
- `PngCheck::MAX_BYTES = 5242880`, `PngCheck::MAX_SIDE = 8000`, `PngCheck::problem( string $head, int $bytes ): ?string`.
- `LibraryFilter::pieces( array<Piece> $pieces, string $category, string $search ): array<Piece>` and `LibraryFilter::templates( array<Template> $templates, string $search ): array<Template>`.

- [ ] **Step 1: Write the tests**

`tests/Unit/Media/SvgFileTest.php`:

```php
<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Media;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Media\SvgFile;

final class SvgFileTest extends TestCase {

	private const SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 600" role="img"><rect width="1" height="1"/></svg>';

	public function test_size_from_viewbox(): void {
		$this->assertSame( [ 800, 600 ], SvgFile::size( self::SVG ) );
		$this->assertNull( SvgFile::size( '<svg xmlns="http://www.w3.org/2000/svg"/>' ) );
	}

	public function test_standalone_adds_declaration_and_size(): void {
		$file = SvgFile::standalone( self::SVG );

		$this->assertStringStartsWith( '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<svg width="800" height="600" ', $file );
		$this->assertStringContainsString( 'viewBox="0 0 800 600"', $file );
	}

	public function test_standalone_is_idempotent_and_keeps_existing_size(): void {
		$once = SvgFile::standalone( self::SVG );

		$this->assertSame( $once, SvgFile::standalone( $once ) );
		$this->assertStringStartsWith( '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<svg width="10"', SvgFile::standalone( '<svg width="10" height="5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 600"/>' ) );
	}
}
```

`tests/Unit/Media/PngCheckTest.php`:

```php
<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Media;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Media\PngCheck;

final class PngCheckTest extends TestCase {

	private static function head( int $w, int $h ): string {
		return "\x89PNG\r\n\x1a\n" . pack( 'N', 13 ) . 'IHDR' . pack( 'N', $w ) . pack( 'N', $h ) . str_repeat( "\0", 8 );
	}

	public function test_valid_png(): void {
		$this->assertNull( PngCheck::problem( self::head( 1600, 1200 ), 250000 ) );
	}

	public function test_rejects_non_png(): void {
		$this->assertStringContainsString( 'not a PNG', (string) PngCheck::problem( 'GIF89a' . str_repeat( "\0", 26 ), 100 ) );
	}

	public function test_rejects_large_files_and_bad_dimensions(): void {
		$this->assertStringContainsString( '5 MB', (string) PngCheck::problem( self::head( 100, 100 ), PngCheck::MAX_BYTES + 1 ) );
		$this->assertStringContainsString( 'dimensions', (string) PngCheck::problem( self::head( 0, 100 ), 100 ) );
		$this->assertStringContainsString( 'dimensions', (string) PngCheck::problem( self::head( 9000, 100 ), 100 ) );
		$this->assertStringContainsString( 'not a PNG', (string) PngCheck::problem( "\x89PNG", 4 ) );
	}
}
```

`tests/Unit/Library/LibraryFilterTest.php`:

```php
<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Library;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Library\LibraryFilter;
use SprintIllustrations\Services;

final class LibraryFilterTest extends TestCase {

	private Services $services;

	protected function setUp(): void {
		$this->services = Services::create( __DIR__ . '/../../fixtures/library' );
	}

	private function ids( array $items ): array {
		return array_map( static fn( $item ) => $item->id, $items );
	}

	public function test_category_filter(): void {
		$this->assertSame( [ 'char-stick', 'char-runner' ], $this->ids( LibraryFilter::pieces( $this->services->manifest->all(), 'characters', '' ) ) );
	}

	public function test_search_matches_label_and_tags_with_every_word(): void {
		$all = $this->services->manifest->all();

		$this->assertSame( [ 'char-runner' ], $this->ids( LibraryFilter::pieces( $all, 'characters', 'SPORT' ) ) );
		$this->assertSame( [ 'obj-mug' ], $this->ids( LibraryFilter::pieces( $all, 'objects', 'mug coffee' ) ) );
		$this->assertSame( [], LibraryFilter::pieces( $all, 'objects', 'mug plant' ) );
	}

	public function test_templates_search(): void {
		$all = $this->services->templates->all();

		$this->assertCount( count( $all ), LibraryFilter::templates( $all, '' ) );
		$this->assertSame( [ 'fixture-duo' ], $this->ids( LibraryFilter::templates( $all, 'team' ) ) );
	}
}
```

- [ ] **Step 2: Run them to verify they fail**

Run: `composer test -- tests/Unit/Media`, then `composer test -- tests/Unit/Library/LibraryFilterTest.php`
Expected: FAIL with class-not-found errors.

- [ ] **Step 3: Implement**

`src/Media/SvgFile.php`:

```php
<?php
/**
 * Composer output as a standalone .svg file.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Media;

/**
 * Adds the XML declaration and an intrinsic width/height from the viewBox.
 */
final class SvgFile {

	private const DECLARATION = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";

	/**
	 * Width and height from the root viewBox.
	 *
	 * @param string $markup SVG markup.
	 * @return array{0: int, 1: int}|null
	 */
	public static function size( string $markup ): ?array {
		if ( ! preg_match( '/<svg\b[^>]*\bviewBox="\s*[-\d.]+[\s,]+[-\d.]+[\s,]+([\d.]+)[\s,]+([\d.]+)\s*"/', $markup, $m ) ) {
			return null;
		}

		return [ (int) round( (float) $m[1] ), (int) round( (float) $m[2] ) ];
	}

	/**
	 * Standalone file content (idempotent).
	 *
	 * @param string $markup SVG markup (instance IDs already applied).
	 * @return string
	 */
	public static function standalone( string $markup ): string {
		$markup = trim( str_replace( self::DECLARATION, '', $markup ) );
		$size   = self::size( $markup );

		if ( null !== $size && ! preg_match( '/^<svg\b[^>]*\swidth="/', $markup ) ) {
			$markup = (string) preg_replace( '/^<svg\b/', sprintf( '<svg width="%d" height="%d"', $size[0], $size[1] ), $markup, 1 );
		}

		return self::DECLARATION . $markup;
	}
}
```

`src/Media/PngCheck.php`:

```php
<?php
/**
 * Cheap PNG sanity checks before WordPress handles an upload.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Media;

/**
 * Signature, IHDR dimensions and size limits.
 */
final class PngCheck {

	public const MAX_BYTES = 5242880;

	public const MAX_SIDE = 8000;

	private const SIGNATURE = "\x89PNG\r\n\x1a\n";

	/**
	 * What is wrong with the file, or null when it is acceptable.
	 *
	 * @param string $head  At least the first 24 bytes.
	 * @param int    $bytes File size.
	 * @return string|null
	 */
	public static function problem( string $head, int $bytes ): ?string {
		if ( strlen( $head ) < 24 || ! str_starts_with( $head, self::SIGNATURE ) || 'IHDR' !== substr( $head, 12, 4 ) ) {
			return 'The file is not a PNG image.';
		}

		if ( $bytes > self::MAX_BYTES ) {
			return 'The PNG is larger than 5 MB.';
		}

		$size = unpack( 'Nwidth/Nheight', substr( $head, 16, 8 ) );
		if ( ! is_array( $size ) || $size['width'] < 1 || $size['height'] < 1 || $size['width'] > self::MAX_SIDE || $size['height'] > self::MAX_SIDE ) {
			return sprintf( 'The PNG dimensions must be between 1 and %d pixels.', self::MAX_SIDE );
		}

		return null;
	}
}
```

`src/Library/LibraryFilter.php`:

```php
<?php
/**
 * Filtering for the Library browser.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Library;

/**
 * Category plus search: every search word must appear in the label or a tag (case-insensitive).
 */
final class LibraryFilter {

	/**
	 * Pieces in a category matching a search.
	 *
	 * @param array<Piece> $pieces   Pieces.
	 * @param string       $category Category.
	 * @param string       $search   Search text.
	 * @return array<Piece>
	 */
	public static function pieces( array $pieces, string $category, string $search ): array {
		return array_values(
			array_filter(
				$pieces,
				static fn( Piece $piece ): bool => $piece->category === $category && self::matches( $piece->label, $piece->tags, $search )
			)
		);
	}

	/**
	 * Templates matching a search.
	 *
	 * @param array<Template> $templates Templates.
	 * @param string          $search    Search text.
	 * @return array<Template>
	 */
	public static function templates( array $templates, string $search ): array {
		return array_values( array_filter( $templates, static fn( Template $template ): bool => self::matches( $template->label, $template->tags, $search ) ) );
	}

	/**
	 * Whether every word of the search appears in the label or tags.
	 *
	 * @param string        $label  Label.
	 * @param array<string> $tags   Tags.
	 * @param string        $search Search.
	 * @return bool
	 */
	private static function matches( string $label, array $tags, string $search ): bool {
		$haystack = strtolower( $label . ' ' . implode( ' ', $tags ) );

		foreach ( preg_split( '/\s+/', strtolower( trim( $search ) ), -1, PREG_SPLIT_NO_EMPTY ) as $word ) {
			if ( ! str_contains( $haystack, $word ) ) {
				return false;
			}
		}

		return true;
	}
}
```

In `phpcs.xml.dist`, change both `(…|Cache|Render)` groups to `(…|Cache|Render|Media)`.

- [ ] **Step 4: Run the tests** with `composer test` and `composer lint`. Both should pass cleanly.
- [ ] **Step 5: Commit:** `feat(media): standalone SVG files, PNG checks and library filtering`.

---

### Task 2: Media REST and `background` on `/compose`

**Files:**
- Create: `src/Rest/MediaController.php`
- Modify: `src/Rest/ComposeController.php`, `src/Plugin.php`, `sprint-illustrations.php` (`0.5.0`)

**Interfaces:**
- `POST /media/svg` and `POST /media/png`, both returning 201 `{ id, url, edit_url }`.
- `/compose` also returns `background` (hex).

- [ ] **Step 1: `src/Rest/MediaController.php`**

```php
<?php
/**
 * POST /media/svg and /media/png — save illustrations to the Media Library.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Rest;

use SprintIllustrations\Compose\CompositionException;
use SprintIllustrations\Compose\SceneSpec;
use SprintIllustrations\Media\PngCheck;
use SprintIllustrations\Media\SvgFile;
use SprintIllustrations\Plugin;

/**
 * SVG is written server-side (SVG allowed for that one call only); PNG is rendered in the browser and validated here.
 */
final class MediaController {

	public const META_SOURCE = '_si_source_illustration';

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( private Plugin $plugin ) {}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Routes.
	 */
	public function register_routes(): void {
		foreach ( [ 'svg', 'png' ] as $format ) {
			register_rest_route(
				Permissions::NAMESPACE,
				'/media/' . $format,
				[
					'methods'             => 'POST',
					'callback'            => [ $this, $format ],
					'permission_callback' => [ self::class, 'can_upload' ],
				]
			);
		}
	}

	/**
	 * Permission: upload_files.
	 *
	 * @return true|\WP_Error
	 */
	public static function can_upload(): bool|\WP_Error {
		return current_user_can( 'upload_files' )
			? true
			: new \WP_Error( 'sprint_illustrations_rest_forbidden', __( 'You are not allowed to add files to the Media Library.', 'sprint-illustrations' ), [ 'status' => rest_authorization_required_code() ] );
	}

	/**
	 * Compose and save an SVG attachment.
	 *
	 * @param \WP_REST_Request $request Request {spec, illustration_id?, title?}.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function svg( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$spec = SceneSpec::from_array( is_array( $request['spec'] ) ? $request['spec'] : [] );

		try {
			$result = $this->plugin->composer()->compose( $spec, $this->plugin->site_palette()->resolve( $spec->palette ) );
		} catch ( CompositionException $e ) {
			return new \WP_Error( 'sprint_illustrations_composition_failed', $e->getMessage(), [ 'status' => 422 ] );
		}

		$markup = SvgFile::standalone( $result->with_instance_id( 'si-m' . strtolower( wp_generate_password( 8, false ) ) ) );
		$size   = SvgFile::size( $markup ) ?? [ 0, 0 ];
		$title  = $this->title( $request, (string) $result->spec->template );

		$allow = static fn( array $mimes ): array => $mimes + [ 'svg' => 'image/svg+xml' ];
		add_filter( 'upload_mimes', $allow );
		$upload = wp_upload_bits( sanitize_file_name( $title . '-' . gmdate( 'Ymd-His' ) . '.svg' ), null, $markup );
		remove_filter( 'upload_mimes', $allow );

		if ( ! empty( $upload['error'] ) ) {
			return new \WP_Error( 'sprint_illustrations_upload_failed', (string) $upload['error'], [ 'status' => 500 ] );
		}

		$id = wp_insert_attachment(
			[
				'post_mime_type' => 'image/svg+xml',
				'post_title'     => $title,
				'post_status'    => 'inherit',
			],
			$upload['file'],
			0,
			true
		);

		if ( is_wp_error( $id ) ) {
			wp_delete_file( $upload['file'] );
			return new \WP_Error( 'sprint_illustrations_upload_failed', $id->get_error_message(), [ 'status' => 500 ] );
		}

		wp_update_attachment_metadata(
			$id,
			[
				'width'  => $size[0],
				'height' => $size[1],
				'file'   => _wp_relative_upload_path( $upload['file'] ),
			]
		);

		return $this->finish( $id, $request, $this->alt( $result->spec ) );
	}

	/**
	 * Validate and save a browser-rendered PNG.
	 *
	 * @param \WP_REST_Request $request Multipart request with "file".
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function png( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$files = $request->get_file_params();
		$file  = $files['file'] ?? null;

		if ( ! is_array( $file ) || UPLOAD_ERR_OK !== ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) || ! is_readable( (string) ( $file['tmp_name'] ?? '' ) ) ) {
			return $this->invalid( __( 'No PNG was received.', 'sprint-illustrations' ) );
		}

		$head    = (string) file_get_contents( $file['tmp_name'], false, null, 0, 32 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local temp file header.
		$problem = PngCheck::problem( $head, (int) filesize( $file['tmp_name'] ) );
		if ( null !== $problem ) {
			return $this->invalid( $problem );
		}

		$title = $this->title( $request, 'illustration' );
		$check = wp_check_filetype_and_ext( $file['tmp_name'], sanitize_file_name( $title . '.png' ) );
		$info  = getimagesize( $file['tmp_name'] );
		if ( 'image/png' !== ( $check['type'] ?? '' ) || ! is_array( $info ) || 'image/png' !== ( $info['mime'] ?? '' ) ) {
			return $this->invalid( __( 'The file is not a PNG image.', 'sprint-illustrations' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$file['name'] = sanitize_file_name( $title . '-' . gmdate( 'Ymd-His' ) . '.png' );
		$id           = media_handle_sideload( $file, 0, $title );

		if ( is_wp_error( $id ) ) {
			return new \WP_Error( 'sprint_illustrations_upload_failed', $id->get_error_message(), [ 'status' => 500 ] );
		}

		return $this->finish( (int) $id, $request, sanitize_text_field( (string) ( $request['alt'] ?? '' ) ) );
	}

	/**
	 * Alt text, source link, response.
	 *
	 * @param int              $id      Attachment ID.
	 * @param \WP_REST_Request $request Request.
	 * @param string           $alt     Alt text.
	 * @return \WP_REST_Response
	 */
	private function finish( int $id, \WP_REST_Request $request, string $alt ): \WP_REST_Response {
		if ( '' !== $alt ) {
			update_post_meta( $id, '_wp_attachment_image_alt', $alt );
		}

		$source = (int) ( $request['illustration_id'] ?? 0 );
		if ( $source > 0 && $this->plugin->illustrations()->exists( $source ) && current_user_can( 'read_post', $source ) ) {
			update_post_meta( $id, self::META_SOURCE, $source );
		}

		return new \WP_REST_Response(
			[
				'id'       => $id,
				'url'      => (string) wp_get_attachment_url( $id ),
				'edit_url' => (string) get_edit_post_link( $id, 'raw' ),
			],
			201
		);
	}

	/**
	 * Attachment title.
	 *
	 * @param \WP_REST_Request $request  Request.
	 * @param string           $fallback Fallback.
	 * @return string
	 */
	private function title( \WP_REST_Request $request, string $fallback ): string {
		$title = trim( sanitize_text_field( (string) ( $request['title'] ?? '' ) ) );

		return '' !== $title ? mb_substr( $title, 0, 120 ) : ( '' !== $fallback ? $fallback : 'illustration' );
	}

	/**
	 * Alt text for a composed spec.
	 *
	 * @param SceneSpec $spec Resolved spec.
	 * @return string
	 */
	private function alt( SceneSpec $spec ): string {
		if ( $spec->decorative ) {
			return '';
		}

		$template = $this->plugin->services()->templates->get( (string) $spec->template );

		return null !== $spec->title ? $spec->title : ( null === $template ? '' : $template->label );
	}

	/**
	 * 400 invalid PNG.
	 *
	 * @param string $message Message.
	 * @return \WP_Error
	 */
	private function invalid( string $message ): \WP_Error {
		return new \WP_Error( 'sprint_illustrations_invalid_png', $message, [ 'status' => 400 ] );
	}
}
```

- [ ] **Step 2:** In `ComposeController::compose()`, add `'background' => (string) $this->plugin->site_palette()->resolve( $spec->palette )->resolve( 'background' ),` to the response. In `Plugin::register_hooks()`, register `( new MediaController( $this ) )->register();` with its `use` line. Bump the version to `0.5.0`.
- [ ] **Step 3: Verify** with the WP-CLI script `verify-3b.php`, following the spec §4 checklist. Run `composer lint` and `composer test`.
- [ ] **Step 4: Commit:** `feat(media): export illustrations to the Media Library as SVG or PNG`.

---

### Task 3: Library page

**Files:**
- Create: `src/Admin/LibraryPage.php`, `assets/admin/library.css`
- Modify: `src/Admin/Menu.php`, `src/Admin/BuilderPage.php` (`initialTemplate`), `src-js/builder/state.js` and `src-js/builder/App.js` (start on `initialTemplate`), `build/*`

- [ ] **Step 1: `src/Admin/LibraryPage.php`.** It's server-rendered with the tabs, search, and piece and template cards from spec §3, using `LibraryFilter`, `Rest\PiecePreviews` and `Plugin::composer()`. Escape all output. The SVG output is composer or sanitizer output, echoed with a `phpcs:ignore` comment giving the reason.
- [ ] **Step 2: Menu.** Add a **Library** submenu after Builder, with slug `Menu::LIBRARY_SLUG = 'sprint-illustrations-library'` and `edit_posts`, and enqueue `assets/admin/library.css` on its hook.
- [ ] **Step 3: Builder `?template=`.**
  - `BuilderPage::enqueue()` localizes `initialTemplate` from `sanitize_key( $_GET['template'] )`.
  - In `state.js`, the `LIBRARY` action accepts `preferred`, and `defaultTemplate( library, preferred )` uses it when that template exists.
  - In `App.js`, dispatch `{ type: 'LIBRARY', library, preferred: config.initialTemplate }`.
- [ ] **Step 4: Verify:**
  - `wp --user=claude eval` renders every tab, and a search, without PHP notices.
  - A static render review at 1440 px and 782 px.
  - `npm run lint:js` and `npm run build`.
- [ ] **Step 5: Commit:** `feat(admin): Library browser page`.

---

### Task 4: Builder Export

**Files:**
- Create: `src-js/builder/exportPng.js`
- Modify: `src-js/builder/api.js`, `src-js/builder/TopBar.js`, `src-js/builder/App.js`, `build/*`

- [ ] **Step 1:** In `api.js`, add `exportSvg( { spec, illustration_id, title } )`, which sends a POST with `data`, and `exportPng( blob, { illustration_id, title, alt } )`, which sends a POST with `body: FormData`.
- [ ] **Step 2:** `exportPng.js` exports `svgToPngBlob( svg, [ cw, ch ], [ tw, th ], background )`. It adds explicit `width` and `height` to the root, makes a Blob URL, loads it into an `Image`, and draws it fitted and centred onto a canvas filled with `background` when one is given. It then calls `toBlob('image/png')`, rejects when the blob is null, and always revokes the URL.
- [ ] **Step 3:** In `TopBar`, add an **Export** `DropdownMenu`, busy while exporting, with three items: **SVG to Media Library**, **PNG, 2× canvas** and **PNG for social sharing, 1200 × 630**.
- [ ] **Step 4:** In `App`, add an `exportAs( format )` handler:
  - It uses `result.svg`, `result.template.canvas` and `result.background` from the latest `/compose`.
  - For SVG it sends the spec body.
  - It sets a notice: "Saved to Media Library." with an **Edit** action linking to `edit_url`, or the error message.
- [ ] **Step 5: Verify:**
  - Lint and build.
  - On the static review page (mocking `/media/*`), each option sends the right request. The PNG blob decodes to 1600 × 1200, or 1200 × 630 for social.
  - Real `/media/svg` and `/media/png` are covered by Task 2.
- [ ] **Step 6: Commit:** `feat(builder): export to the Media Library`.

---

### Task 5: Docs, review, PR

- [ ] Update `CLAUDE.md` (media routes, the Library page, the SVG-allowed-once rule, and browser-drawn PNG because there's no Imagick) and add execution notes to this plan.
- [ ] Send a feature-dev code-reviewer agent over the branch, and fix confirmed findings.
- [ ] Run `composer test`, `composer lint`, `npm run lint:js` and `npm run build` (no diff), then push and open a PR with the logged-in checklist.

## Execution notes

- **Task 1** went as written: 9 new tests, and `Media` was added to the pure-path groups in `phpcs.xml.dist`.
- **Task 2:** `verify-3b.php` (scratch, `wp eval-file`) found that a file with a valid PNG signature and IHDR but a garbage body passed `getimagesize()` and `wp_check_filetype_and_ext()`, which both read only the header. `PngCheck::problem()` now also takes the file's last 12 bytes and requires the IEND trailer, with a new unit test. After that, all 33 checks passed:
  - The permission matrix: 401, 403 and 201.
  - MIME type, width/height metadata, alt text (spec title, template-label fallback, none when decorative), and source meta (not linked for an unknown ID).
  - svg is absent from `get_allowed_mime_types()` after the call.
  - A real GD PNG gives 201 with thumbnails.
  - Fake, incomplete, over-5 MB and missing PNGs give 400.
  - `/compose` returns `background`.
  - Every attachment is cleaned up.
- **Task 3** was checked by rendering every tab, a search, a no-match search and an invalid tab through `wp --user eval`, each in 30 ms or less. The static screenshots at 1440 and 782 px led to three changes:
  - `overflow:hidden` on the art well, so tall characters keep the square ratio.
  - A "Slots:" prefix on template cards.
  - A "No templates match" message for an empty Templates search.
- **Task 4** was checked on a static review page with the real bundle, the real `/library` and `/compose` data, and `wp.apiFetch` mocked:
  - SVG sends the resolved spec.
  - PNG 2× is 1600 × 1200 with a transparent corner.
  - Social is 1200 × 630 with the corner equal to the palette background `#eef0ff`.
  - Each option shows "Saved to Media Library." with Edit, and `?template=` preselects that template.
  - The export menu's two-line items needed `height:auto` (`.si-b-export__menu`).
- **Not automated (needs a login):** real exports from the Builder into Media, and the Library page inside the real admin chrome.
