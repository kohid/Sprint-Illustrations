# Sprint Illustrations — Phase 3a (Saved Illustrations, REST, Builder) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Users who can edit posts design illustrations in a React Builder, save them as named `si_illustration` posts through a REST API, and place them anywhere with `id=`.

**Architecture:**
- **Pure additions:** `Piece::$person` plus a person-aware `SceneResolver`, `ComposedSvg::$boxes`, `to_array()` on `Template`/`TemplateSlot`/`Piece`, `Compose\PiecePreview`, `Storage\Illustration`, and an `id` closure on `Render\Renderer`.
- **WordPress classes:** `Storage\IllustrationPostType` and `IllustrationRepository`, `Rest\*` controllers, and `Admin\BuilderPage`.
- **The Builder:** React (`src-js/builder/`), built by `@wordpress/scripts` into a committed `build/`.

**Tech Stack:** PHP 8.1+, WordPress 7.1.2 (6.4+), PHPUnit 10.5, WPCS 3, Node 20 with `@wordpress/scripts` 30 (React through `@wordpress/element`, `@wordpress/components`, `@wordpress/api-fetch`).

**Spec:** `docs/superpowers/specs/2026-09-29-phase-3a-builder-design.md`. The data layer follows the code-architect blueprint summarised there.

## Global Constraints

- Every PHP file starts with `declare( strict_types=1 );`. Use WPCS style. The text domain is `sprint-illustrations`. `Storage` and `Rest` are WordPress-facing and are **not** added to the pure-path exclusions.
- The REST namespace is `sprint-illustrations/v1`. The post type is `si_illustration` and its meta key `_si_spec`. Error codes are `sprint_illustrations_{rest_forbidden,cannot_edit,cannot_delete,not_found,invalid_title,composition_failed}`.
- `/compose` uses the **uncached** `Plugin::services()->composer`. Saving and front-end rendering use `Plugin::composer()`.
- Every repository method or route that takes an ID first checks `post_type === si_illustration`, returning 404 before any capability check.
- Plugin version is `0.4.0`.
- `build/` is committed and `node_modules/` is ignored.
- Commit messages end with the `Co-Authored-By:` trailer.

## File map

| Path | Responsibility |
|---|---|
| `src/Library/Piece.php`, `src/Cli/ManifestBuilder.php`, `src/Compose/SceneResolver.php`, `assets/manifest.json`, `tests/fixtures/library/assets/manifest.json` | Person field and the no-repeat rule |
| `src/Compose/ComposedSvg.php`, `src/Compose/Composer.php`, `src/Cache/CachingComposer.php` | Placement boxes |
| `src/Library/Template.php`, `src/Library/TemplateSlot.php`, `src/Compose/PiecePreview.php` | Serialisation and piece thumbnails |
| `src/Storage/{Illustration,IllustrationPostType,IllustrationRepository}.php` | Saved illustrations |
| `src/Rest/{Permissions,ComposeController,LibraryController,IllustrationsController,PiecePreviews}.php` | REST API |
| `src/Render/Renderer.php`, `src/Integrations/Shortcode.php`, `blocks/illustration/*`, `src/Integrations/Elementor/Widget.php`, `src/Plugin.php` | `id=` on every surface, and wiring |
| `src/Admin/{Menu,BuilderPage,SettingsPage}.php`, `assets/admin/builder.css`, `src-js/builder/*`, `package.json`, `build/*`, `.gitignore` | The Builder |

---

### Task 1: Person field and the no-repeat rule

**Files:**
- Modify: `src/Library/Piece.php`, `src/Cli/ManifestBuilder.php`, `src/Compose/SceneResolver.php`, `tests/fixtures/library/assets/manifest.json`
- Regenerate: `assets/manifest.json`
- Test: `tests/Unit/Compose/SceneResolverTest.php`, `tests/Unit/Cli/ManifestBuilderTest.php`, `tests/Unit/StarterPackTest.php`

**Interfaces:**
- Produces: `Piece::$person` (`?string`, the last constructor parameter, default null) and `Piece::to_array(): array{id, label, category, tags, accepts, mounts, person}`. Manifest entries for characters carry `"person"`.

- [ ] **Step 1: Write the failing tests**

Add to `tests/Unit/Compose/SceneResolverTest.php` (imports already cover `Manifest`, `SceneResolver`, `SceneSpec`):

```php
	public function test_two_character_slots_never_share_a_person(): void {
		for ( $seed = 1; $seed <= 50; $seed++ ) {
			$scene = $this->resolve( 'fixture-duo', [ 'seed' => $seed ] );
			$left  = $this->by_slot( $scene, 'left' )[0]->piece->person;
			$right = $this->by_slot( $scene, 'right' )[0]->piece->person;

			$this->assertNotSame( $left, $right, "seed $seed" );
		}
	}

	public function test_explicit_pick_person_is_avoided_by_auto_slots(): void {
		for ( $seed = 1; $seed <= 20; $seed++ ) {
			$scene = $this->resolve(
				'fixture-duo',
				[
					'seed'  => $seed,
					'picks' => [ 'left' => 'char-stick' ],
				]
			);
			$this->assertSame( 'char-stick', $this->by_slot( $scene, 'left' )[0]->piece->id );
			$this->assertSame( 'char-runner', $this->by_slot( $scene, 'right' )[0]->piece->id, "seed $seed" );
		}
	}

	public function test_single_person_library_repeats_instead_of_failing(): void {
		$file = tempnam( sys_get_temp_dir(), 'si-manifest' );
		$data = json_decode( (string) file_get_contents( self::ASSETS . '/manifest.json' ), true );
		$data['pieces'] = array_values( array_filter( $data['pieces'], static fn( $p ) => 'char-runner' !== $p['id'] ) );
		file_put_contents( $file, (string) json_encode( $data ) );

		$scene = ( new SceneResolver( Manifest::from_files( [ $file ] ) ) )->resolve( $this->templates->get( 'fixture-duo' ), SceneSpec::from_array( [ 'template' => 'fixture-duo' ] ), [] );
		unlink( $file );

		$this->assertSame( 'char-stick', $this->by_slot( $scene, 'left' )[0]->piece->id );
		$this->assertSame( 'char-stick', $this->by_slot( $scene, 'right' )[0]->piece->id );
		$this->assertSame( [], $scene->warnings );
	}
```

In `tests/Unit/Cli/ManifestBuilderTest.php`, add these to `test_builds_entries_from_metadata_and_markers()` after the `$hero['viewBox']` assertion:

```php
		$this->assertSame( 'hero', $hero['person'], 'Characters default their person to the first file-name part.' );
```

and after the `$cup['mounts']` assertion:

```php
		$this->assertArrayNotHasKey( 'person', $cup, 'Only characters have a person.' );
```

In `tests/Unit/StarterPackTest.php`, add inside `test_pieces_follow_conventions()`'s `if ( 'characters' === $piece->category )` block:

```php
				$this->assertMatchesRegularExpression( '/^[a-z0-9-]+$/D', (string) $piece->person, $piece->id . ' needs a person.' );
```

and a new test:

```php
	public function test_two_people_collaborating_never_repeats_a_person(): void {
		for ( $seed = 1; $seed <= 100; $seed++ ) {
			$picks = self::$services->composer->compose(
				SceneSpec::from_array(
					[
						'template' => 'two-people-collaborating',
						'seed'     => $seed,
					]
				),
				\SprintIllustrations\Palette\Palette::default()
			)->spec->picks;

			$this->assertNotSame(
				self::$services->manifest->get( (string) $picks['left'] )->person,
				self::$services->manifest->get( (string) $picks['right'] )->person,
				"seed $seed"
			);
		}
	}
```

- [ ] **Step 2: Run them to verify they fail**

Run: `composer test -- tests/Unit/Compose/SceneResolverTest.php`
Expected: FAIL with `Undefined property: SprintIllustrations\Library\Piece::$person`.

- [ ] **Step 3: Implement the person field in `src/Library/Piece.php`**

Add the docblock line `@param string|null $person Person this character depicts (characters only).` and a final constructor parameter `public readonly ?string $person = null,`. In `from_array()`, pass as the last argument:

```php
			isset( $data['person'] ) && is_string( $data['person'] ) && '' !== trim( $data['person'] ) ? strtolower( trim( $data['person'] ) ) : null
```

Add after `has_tag()`:

```php
	/**
	 * Public shape for REST.
	 *
	 * @return array{id: string, label: string, category: string, tags: array<string>, accepts: array<string, string>, mounts: array<string, string>, person: ?string}
	 */
	public function to_array(): array {
		return [
			'id'       => $this->id,
			'label'    => $this->label,
			'category' => $this->category,
			'tags'     => $this->tags,
			'accepts'  => $this->accepts,
			'mounts'   => $this->mounts,
			'person'   => $this->person,
		];
	}
```

- [ ] **Step 4: Emit `person` from `src/Cli/ManifestBuilder.php`**

1. In `take_meta()`, change the key list to `[ 'label', 'tags', 'z', 'accepts', 'mounts', 'person' ]`.
2. Add `data-si-person` to the class docblock's metadata list.
3. In `process()`, after the `$z` line, add:

```php
		$person = 'characters' === $category
			? (string) preg_replace( '/[^a-z0-9-]+/', '', strtolower( $meta['person'] ?? (string) ( $previous['person'] ?? explode( '-', $name )[0] ) ) )
			: '';
```

4. Change the returned array's end from `'hash' => sha1( $clean ),` to:

```php
			'hash'     => sha1( $clean ),
		] + ( '' === $person ? [] : [ 'person' => $person ] );
```

Remove the original closing `];` so the expression reads `return [ … ] + ( … );`.

- [ ] **Step 5: Avoid repeated persons in `src/Compose/SceneResolver.php`**

1. In `resolve()`, add `$used = [];` after `$order = 0;`, and pass `$used` as the last argument to both `resolve_box_slot()` and `resolve_attached_slot()`.
2. Add a last parameter `array &$used` (docblock: `@param array<string, true> $used Persons already placed in this scene (by reference).`) to both methods.
3. In `resolve_box_slot()`, change `$auto = $this->pick( $candidates, $slot, $tokens, $seed );` to `$auto = $this->pick( $this->unused( $candidates, $used ), $slot, $tokens, $seed );`. After the `null === $piece` guard, add:

```php
			if ( null !== $piece->person ) {
				$used[ $piece->person ] = true;
			}
```

4. In `resolve_attached_slot()`, change the `$auto` line to `$auto = $this->pick( $this->unused( $this->candidates( $slot, $type ), $used ), $slot, $tokens, $seed );`, and add the same three-line `$used` update after its `null === $piece` guard.
5. Add this method after `candidates()`:

```php
	/**
	 * Drop pieces whose person is already in the scene, unless that would leave nothing.
	 *
	 * @param array<Piece>        $candidates Candidates.
	 * @param array<string, true> $used       Persons already placed.
	 * @return array<Piece>
	 */
	private function unused( array $candidates, array $used ): array {
		if ( ! $used ) {
			return $candidates;
		}

		$fresh = array_values( array_filter( $candidates, static fn( Piece $piece ): bool => null === $piece->person || ! isset( $used[ $piece->person ] ) ) );

		return $fresh ? $fresh : $candidates;
	}
```

- [ ] **Step 6: Data**

In `tests/fixtures/library/assets/manifest.json`, add `"person": "stick",` to `char-stick` and `"person": "runner",` to `char-runner` (after `"category"`). Then run `php bin/build-manifest.php --non-interactive` to regenerate `assets/manifest.json`, which adds `person` to all 8 characters.

- [ ] **Step 7: Run the tests**

Run: `composer test`, then `composer lint`
Expected: all pass and lint is clean. If a pre-existing fixture test depended on a repeated person, update its expectation and record that in the execution notes.

- [ ] **Step 8: Commit**

```bash
git add src/Library/Piece.php src/Cli/ManifestBuilder.php src/Compose/SceneResolver.php tests assets/manifest.json assets/pieces
git commit -m "fix(compose): a scene never shows the same person twice"
```

---

### Task 2: Placement boxes, serialisation and piece previews

**Files:**
- Modify: `src/Compose/ComposedSvg.php`, `src/Compose/Composer.php`, `src/Cache/CachingComposer.php`, `src/Library/Template.php`, `src/Library/TemplateSlot.php`
- Create: `src/Compose/PiecePreview.php`
- Test: `tests/Unit/Compose/ComposerTest.php`, `tests/Unit/Compose/ComposedSvgTest.php`, `tests/Unit/Library/TemplateRepositoryTest.php`, `tests/Unit/Compose/PiecePreviewTest.php`

**Interfaces:**
- `ComposedSvg::__construct( string $markup, SceneSpec $spec, array $warnings, array $boxes = [] )` has `public readonly array $boxes` (`array<string, array<array{0: float, 1: float, 2: float, 3: float}>>`), and it's included in `to_array()`/`from_array()`.
- `TemplateSlot::to_array(): array{name, category, required, attach: ?array{to, anchor}, multiple}` and `Template::to_array(): array{id, label, canvas, tags, slots}`.
- `PiecePreview::render( Piece, Palette, PieceLoader, Sanitizer ): string`.

- [ ] **Step 1: Write the failing tests**

`tests/Unit/Compose/ComposerTest.php`, a new method:

```php
	public function test_boxes_describe_each_slot_inside_the_canvas(): void {
		$result = $this->compose(
			[
				'template' => 'fixture-duo',
				'seed'     => 3,
			]
		);

		$this->assertSame( [ 'left', 'left-prop', 'right', 'right-prop' ], array_keys( $result->boxes ) );
		foreach ( $result->boxes as $slot => $boxes ) {
			foreach ( $boxes as [ $x, $y, $w, $h ] ) {
				$this->assertGreaterThan( 0, $w, $slot );
				$this->assertGreaterThan( 0, $h, $slot );
				$this->assertGreaterThanOrEqual( -1, $x, $slot );
				$this->assertLessThanOrEqual( 401, $x + $w, $slot );
			}
		}
		$this->assertEqualsWithDelta( 300, $result->boxes['right'][0][0] + $result->boxes['right'][0][2] / 2, 0.01, 'Right character is centred in its box even when flipped.' );
	}
```

`tests/Unit/Compose/ComposedSvgTest.php`: in `test_round_trip()`, construct with a 4th argument `[ 'left' => [ [ 1.5, 2, 30, 40 ] ] ]` and add `$this->assertSame( [ 'left' => [ [ 1.5, 2, 30, 40 ] ] ], $copy->boxes );`. Add `$this->assertSame( [], ComposedSvg::from_array( [ 'markup' => '<svg/>', 'spec' => [], 'warnings' => [] ] )->boxes );` as a new test `test_boxes_default_to_empty()`.

`tests/Unit/Library/TemplateRepositoryTest.php`, a new method:

```php
	public function test_to_array_shapes(): void {
		$template = \SprintIllustrations\Library\TemplateRepository::from_directories( [ __DIR__ . '/../../fixtures/library/assets/templates' ] )->get( 'fixture-duo' );
		$data     = $template->to_array();

		$this->assertSame( 'fixture-duo', $data['id'] );
		$this->assertSame( [ 400.0, 300.0 ], array_map( 'floatval', $data['canvas'] ) );
		$this->assertSame(
			[
				'name'     => 'left-prop',
				'category' => 'objects',
				'required' => false,
				'attach'   => [
					'to'     => 'left',
					'anchor' => 'hold',
				],
				'multiple' => false,
			],
			$data['slots'][1]
		);
		$this->assertNull( $data['slots'][0]['attach'] );
	}
```

`tests/Unit/Compose/PiecePreviewTest.php`:

```php
<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Compose;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Compose\PieceLoader;
use SprintIllustrations\Compose\PiecePreview;
use SprintIllustrations\Palette\Palette;
use SprintIllustrations\Security\Sanitizer;
use SprintIllustrations\Services;

final class PiecePreviewTest extends TestCase {

	private function render( string $id, ?Palette $palette = null ): string {
		$services  = Services::create( __DIR__ . '/../../fixtures/library' );
		$sanitizer = new Sanitizer();

		return PiecePreview::render( $services->manifest->get( $id ), $palette ?? Palette::default(), new PieceLoader( $sanitizer ), $sanitizer );
	}

	public function test_renders_piece_alone_recoloured(): void {
		$svg = $this->render( 'char-stick' );

		$this->assertStringStartsWith( '<svg', $svg );
		$this->assertStringContainsString( 'viewBox="0 0 100 200"', $svg );
		$this->assertStringNotContainsString( 'slot-', $svg );
		$this->assertStringContainsString( '#5b5bd6', $svg );
	}

	public function test_palette_changes_colours(): void {
		$this->assertNotSame( $this->render( 'char-stick' ), $this->render( 'char-stick', Palette::from_array( [ 'primary' => '#000000' ] ) ) );
	}
}
```

- [ ] **Step 2: Run to verify they fail**

Run `composer test -- tests/Unit/Compose` and `composer test -- tests/Unit/Library`. Expect failures on the unknown `boxes` property, the missing `to_array()`, and the missing `PiecePreview`.

- [ ] **Step 3: Implement**

In `src/Compose/ComposedSvg.php`:
- Add the constructor parameter `public readonly array $boxes = [],` with docblock `@param array<string, array<array{0: float, 1: float, 2: float, 3: float}>> $boxes Slot => rendered boxes [x, y, w, h] in canvas units.`
- Add `'boxes' => $this->boxes,` to `to_array()`.
- In `from_array()`, pass `is_array( $data['boxes'] ?? null ) ? $data['boxes'] : []` as the 4th argument.

In `src/Compose/Composer.php`:
- Change the `return new ComposedSvg(` block in `compose()` so it passes `self::boxes( $scene )` as the 4th argument.
- Add:

```php
	/**
	 * Rendered box of every placement, grouped by slot.
	 *
	 * @param ResolvedScene $scene Scene.
	 * @return array<string, array<array{0: float, 1: float, 2: float, 3: float}>>
	 */
	private static function boxes( ResolvedScene $scene ): array {
		$boxes = [];
		foreach ( $scene->placements as $placement ) {
			$boxes[ $placement->slot ][] = [ round( $placement->x, 2 ), round( $placement->y, 2 ), round( $placement->width(), 2 ), round( $placement->height(), 2 ) ];
		}

		$ordered = [];
		foreach ( $scene->template->slots as $slot ) {
			if ( isset( $boxes[ $slot->name ] ) ) {
				$ordered[ $slot->name ] = $boxes[ $slot->name ];
			}
		}

		return $ordered;
	}
```

In `src/Cache/CachingComposer.php`, change the hit return to `return new ComposedSvg( $markup, $hit->spec, $hit->warnings, $hit->boxes );`.

In `src/Library/TemplateSlot.php`, add:

```php
	/**
	 * Public shape for REST.
	 *
	 * @return array{name: string, category: string, required: bool, attach: array{to: string, anchor: string}|null, multiple: bool}
	 */
	public function to_array(): array {
		return [
			'name'     => $this->name,
			'category' => $this->category,
			'required' => $this->required,
			'attach'   => null === $this->attach_to ? null : [
				'to'     => $this->attach_to,
				'anchor' => (string) $this->attach_anchor,
			],
			'multiple' => $this->is_multiple(),
		];
	}
```

In `src/Library/Template.php`, add:

```php
	/**
	 * Public shape for REST.
	 *
	 * @return array{id: string, label: string, canvas: array, tags: array<string>, slots: array<array<string, mixed>>}
	 */
	public function to_array(): array {
		return [
			'id'     => $this->id,
			'label'  => $this->label,
			'canvas' => $this->canvas,
			'tags'   => $this->tags,
			'slots'  => array_map( static fn( TemplateSlot $slot ): array => $slot->to_array(), $this->slots ),
		];
	}
```

Create `src/Compose/PiecePreview.php`:

```php
<?php
/**
 * A single piece rendered on its own (Builder thumbnails).
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Compose;

use SprintIllustrations\Library\Piece;
use SprintIllustrations\Palette\Palette;
use SprintIllustrations\Security\Sanitizer;
use SprintIllustrations\Svg\SvgDom;

/**
 * The same per-piece steps the Composer uses, without a template.
 */
final class PiecePreview {

	/**
	 * Render a piece in its own viewBox, recoloured, ID-scoped and sanitized.
	 *
	 * @param Piece       $piece     Piece.
	 * @param Palette     $palette   Palette.
	 * @param PieceLoader $loader    Loader.
	 * @param Sanitizer   $sanitizer Sanitizer.
	 * @return string
	 */
	public static function render( Piece $piece, Palette $palette, PieceLoader $loader, Sanitizer $sanitizer ): string {
		$doc = new \DOMDocument( '1.0', 'UTF-8' );
		$svg = $doc->createElementNS( SvgDom::NS, 'svg' );
		$doc->appendChild( $svg );

		[ $vx, $vy, $vw, $vh ] = $piece->view_box;
		$svg->setAttribute( 'viewBox', implode( ' ', array_map( static fn( float $n ): string => SvgDom::num( $n ), [ $vx, $vy, $vw, $vh ] ) ) );
		$svg->setAttribute( 'aria-hidden', 'true' );
		$svg->setAttribute( 'focusable', 'false' );

		foreach ( $loader->load( $piece )->documentElement->childNodes as $child ) {
			if ( $child instanceof \DOMElement && ! in_array( $child->localName, [ 'title', 'desc', 'metadata' ], true ) ) {
				$svg->appendChild( $doc->importNode( $child, true ) );
			}
		}

		( new Recolorer() )->apply( $svg, $palette, 0, 0 );
		( new IdScoper() )->scope( $svg, 'si-piece-' . $piece->id . '-' );

		return $sanitizer->sanitize( (string) $doc->saveXML( $svg ) );
	}
}
```

- [ ] **Step 4: Run the tests** with `composer test` and `composer lint`. Expected: pass and clean.

- [ ] **Step 5: Commit**

```bash
git add src/Compose src/Cache/CachingComposer.php src/Library tests
git commit -m "feat(compose): placement boxes, REST shapes and standalone piece previews"
```

---

### Task 3: Saved illustrations (pure value object and the renderer's `id`)

**Files:**
- Create: `src/Storage/Illustration.php`, `tests/Unit/Storage/IllustrationTest.php`
- Modify: `src/Render/Renderer.php`, `tests/Unit/Render/RendererTest.php`

**Interfaces:**
- `Illustration::from_array( array ): self` and `->to_array()`, with public readonly `?int $id`, `string $title`, `SceneSpec $spec`, `string $status`, `int $author_id` and `?string $modified_gmt`. There are also `with_title()` and `with_spec()`.
- `Renderer::__construct( ComposesSvg, \Closure $palettes, ?\Closure $log = null, ?\Closure $illustrations = null )`, where `$illustrations` is `fn( int $id ): ?SceneSpec`.

- [ ] **Step 1: Write the failing tests**

`tests/Unit/Storage/IllustrationTest.php`:

```php
<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Storage;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Compose\SceneSpec;
use SprintIllustrations\Storage\Illustration;

final class IllustrationTest extends TestCase {

	public function test_round_trip(): void {
		$data = [
			'id'           => 12,
			'title'        => 'Homepage hero',
			'spec'         => SceneSpec::from_array(
				[
					'template' => 'hero-left-character',
					'seed'     => 4,
				]
			)->to_array(),
			'status'       => 'publish',
			'author_id'    => 3,
			'modified_gmt' => '2026-09-29T12:00:00',
		];

		$this->assertSame( $data, Illustration::from_array( $data )->to_array() );
	}

	public function test_normalizes_untrusted_input(): void {
		$item = Illustration::from_array(
			[
				'id'     => '-5',
				'title'  => '  <b>' . str_repeat( 'x', 300 ) . '</b> ',
				'spec'   => 'nope',
				'status' => 'weird',
			]
		);

		$this->assertNull( $item->id );
		$this->assertSame( 200, mb_strlen( $item->title ) );
		$this->assertStringNotContainsString( '<b>', $item->title );
		$this->assertNull( $item->spec->template );
		$this->assertSame( 'publish', $item->status );
		$this->assertSame( 0, $item->author_id );
	}

	public function test_copies(): void {
		$item = Illustration::from_array( [ 'title' => 'A' ] );

		$this->assertSame( 'B', $item->with_title( 'B' )->title );
		$this->assertSame( 9, $item->with_spec( SceneSpec::from_array( [ 'seed' => 9 ] ) )->spec->seed );
		$this->assertSame( 'A', $item->title );
	}
}
```

Add to `tests/Unit/Render/RendererTest.php`. First, a helper that builds a renderer with an illustrations closure, placed after `renderer()`:

```php
	private function renderer_with_saved(): Renderer {
		return new Renderer(
			Services::create( __DIR__ . '/../../fixtures/library' )->composer,
			static fn( string|array $ref ): Palette => Palette::default(),
			function ( string $message ): void {
				$this->logged[] = $message;
			},
			static fn( int $id ): ?\SprintIllustrations\Compose\SceneSpec => 7 === $id
				? \SprintIllustrations\Compose\SceneSpec::from_array(
					[
						'template' => 'fixture-object',
						'seed'     => 11,
						'title'    => 'Saved title',
					]
				)
				: null
		);
	}

	public function test_saved_illustration_wins_over_attributes(): void {
		$html = $this->renderer_with_saved()->render(
			[
				'id'       => '7',
				'template' => 'fixture-hero',
				'seed'     => 1,
			]
		);

		$this->assertStringContainsString( 'si-template-fixture-object', $html );
		$this->assertStringContainsString( '>Saved title</title>', $html );
	}

	public function test_saved_illustration_title_and_decorative_can_be_overridden(): void {
		$renderer = $this->renderer_with_saved();

		$this->assertStringContainsString( '>Placement title</title>', $renderer->render( [ 'id' => 7, 'title' => 'Placement title' ] ) );
		$this->assertStringContainsString( 'aria-hidden="true"', $renderer->render( [ 'id' => 7, 'decorative' => true ] ) );
	}

	public function test_unknown_saved_illustration_takes_the_error_path(): void {
		$this->assertSame( '<!-- Sprint Illustrations: illustration could not be rendered. -->', $this->renderer_with_saved()->render( [ 'id' => 99 ] ) );
		$this->assertStringContainsString( 'Saved illustration 99 was not found.', $this->logged[0] );
	}

	public function test_id_is_ignored_without_a_lookup(): void {
		$this->assertStringContainsString( 'si-template-fixture-hero', $this->renderer()->render( [ 'id' => 7, 'template' => 'fixture-hero' ] ) );
	}
```

The Placement-title case uses a separate `render()` call, so `decorative` isn't passed there. `array_key_exists( 'decorative' )` must be false when it isn't passed.

- [ ] **Step 2: Run them to verify they fail**

Run: `composer test -- tests/Unit/Storage`, then `composer test -- tests/Unit/Render`. Expect a class-not-found error and a constructor argument error.

- [ ] **Step 3: Implement `src/Storage/Illustration.php`**

```php
<?php
/**
 * A saved illustration (value object).
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Storage;

use SprintIllustrations\Compose\SceneSpec;

/**
 * Named, frozen composition. Note: $title is the illustration's name (post title); $spec->title is the
 * accessible <title> of the SVG. They are different fields.
 */
final class Illustration {

	public const STATUSES = [ 'publish', 'draft', 'trash' ];

	public const MAX_TITLE = 200;

	/**
	 * Constructor. Use from_array().
	 *
	 * @param int|null    $id           Post ID (null when unsaved).
	 * @param string      $title        Name.
	 * @param SceneSpec   $spec         Resolved spec.
	 * @param string      $status       Post status.
	 * @param int         $author_id    Author.
	 * @param string|null $modified_gmt RFC 3339 modified time.
	 */
	private function __construct(
		public readonly ?int $id,
		public readonly string $title,
		public readonly SceneSpec $spec,
		public readonly string $status,
		public readonly int $author_id,
		public readonly ?string $modified_gmt,
	) {}

	/**
	 * Build from untrusted data. Never throws.
	 *
	 * @param array<string, mixed> $data Data.
	 * @return self
	 */
	public static function from_array( array $data ): self {
		$id    = isset( $data['id'] ) && is_numeric( $data['id'] ) && (int) $data['id'] > 0 ? (int) $data['id'] : null;
		$title = isset( $data['title'] ) && is_scalar( $data['title'] ) ? trim( strip_tags( (string) $data['title'] ) ) : '';

		return new self(
			$id,
			mb_substr( $title, 0, self::MAX_TITLE ),
			SceneSpec::from_array( is_array( $data['spec'] ?? null ) ? $data['spec'] : [] ),
			in_array( $data['status'] ?? null, self::STATUSES, true ) ? $data['status'] : 'publish',
			isset( $data['author_id'] ) && is_numeric( $data['author_id'] ) ? max( 0, (int) $data['author_id'] ) : 0,
			isset( $data['modified_gmt'] ) && is_string( $data['modified_gmt'] ) ? $data['modified_gmt'] : null
		);
	}

	/**
	 * Export.
	 *
	 * @return array{id: ?int, title: string, spec: array<string, mixed>, status: string, author_id: int, modified_gmt: ?string}
	 */
	public function to_array(): array {
		return [
			'id'           => $this->id,
			'title'        => $this->title,
			'spec'         => $this->spec->to_array(),
			'status'       => $this->status,
			'author_id'    => $this->author_id,
			'modified_gmt' => $this->modified_gmt,
		];
	}

	/**
	 * Copy with a new name.
	 *
	 * @param string $title Name.
	 * @return self
	 */
	public function with_title( string $title ): self {
		return self::from_array( [ 'title' => $title ] + $this->to_array() );
	}

	/**
	 * Copy with a new spec.
	 *
	 * @param SceneSpec $spec Spec.
	 * @return self
	 */
	public function with_spec( SceneSpec $spec ): self {
		return self::from_array( [ 'spec' => $spec->to_array() ] + $this->to_array() );
	}
}
```

- [ ] **Step 4: Renderer `id`**

In `src/Render/Renderer.php`:
- Add the constructor parameter `private ?\Closure $illustrations = null,` after `$log`, with docblock `@param \Closure|null $illustrations fn( int $id ): ?SceneSpec, for saved illustrations.`
- In `render()`, move `$spec = self::spec( $args );` inside the `try` as `$spec = $this->resolve_spec( $args );`, so it's the first line of the `try`.
- Add:

```php
	/**
	 * Saved illustration (when "id" is set and a lookup exists) or the attributes.
	 *
	 * @param array<string, mixed> $args Attributes.
	 * @return SceneSpec
	 * @throws CompositionException When the saved illustration is missing.
	 */
	private function resolve_spec( array $args ): SceneSpec {
		$id = isset( $args['id'] ) && is_numeric( $args['id'] ) ? (int) $args['id'] : 0;

		if ( $id <= 0 || null === $this->illustrations ) {
			return self::spec( $args );
		}

		$saved = ( $this->illustrations )( $id );
		if ( ! $saved instanceof SceneSpec ) {
			throw new CompositionException( sprintf( 'Saved illustration %d was not found.', $id ) );
		}

		$overrides = [];
		if ( isset( $args['title'] ) && is_string( $args['title'] ) && '' !== trim( $args['title'] ) ) {
			$overrides['title'] = $args['title'];
		}
		if ( array_key_exists( 'decorative', $args ) ) {
			$overrides['decorative'] = $args['decorative'];
		}

		return $overrides ? SceneSpec::from_array( $overrides + $saved->to_array() ) : $saved;
	}
```

Surfaces always pass `decorative`, so a saved illustration placed through the shortcode or block uses the surface's decorative value. That's intended: each placement decides whether it's decorative.

- [ ] **Step 5: Run the tests** with `composer test` and `composer lint`. Expected: pass and clean.

- [ ] **Step 6: Commit**

```bash
git add src/Storage/Illustration.php src/Render/Renderer.php tests/Unit/Storage tests/Unit/Render
git commit -m "feat(storage): Illustration value object; renderer resolves saved ids"
```

---

### Task 4: Post type, repository, REST API and `id=` wiring

**Files:**
- Create: `src/Storage/IllustrationPostType.php`, `src/Storage/IllustrationRepository.php`, `src/Rest/Permissions.php`, `src/Rest/PiecePreviews.php`, `src/Rest/ComposeController.php`, `src/Rest/LibraryController.php`, `src/Rest/IllustrationsController.php`
- Modify: `src/Plugin.php`, `src/Integrations/Shortcode.php`, `blocks/illustration/block.json`, `blocks/illustration/render.php`, `blocks/illustration/editor.js`, `src/Integrations/Elementor/Widget.php`, `sprint-illustrations.php` (version `0.4.0`), `blocks/illustration/block.json` (version)

**Interfaces:**
- `Plugin::illustrations(): IllustrationRepository`.
- `Plugin::editor_choices()` gains `illustrations: array<array{label: string, value: int}>`.

- [ ] **Step 1: `src/Storage/IllustrationPostType.php`**

```php
<?php
/**
 * The si_illustration post type.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Storage;

/**
 * Private storage for saved illustrations (the Builder is the UI; REST is our own).
 */
final class IllustrationPostType {

	public const POST_TYPE = 'si_illustration';

	public const META_SPEC = '_si_spec';

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'init', [ $this, 'register_post_type' ] );
	}

	/**
	 * Register the post type.
	 */
	public function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			[
				'label'           => __( 'Illustrations', 'sprint-illustrations' ),
				'public'          => false,
				'show_ui'         => false,
				'show_in_rest'    => false,
				'capability_type' => 'post',
				'map_meta_cap'    => true,
				'supports'        => [ 'title', 'author' ],
				'rewrite'         => false,
				'query_var'       => false,
				'has_archive'     => false,
			]
		);
	}
}
```

- [ ] **Step 2: `src/Storage/IllustrationRepository.php`**

```php
<?php
/**
 * CRUD for saved illustrations.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Storage;

use SprintIllustrations\Compose\SceneSpec;

/**
 * WP_Post <-> Illustration. Every ID is checked to be an si_illustration before use.
 */
final class IllustrationRepository {

	/**
	 * Any status.
	 *
	 * @param int $id Post ID.
	 * @return Illustration|null
	 */
	public function find( int $id ): ?Illustration {
		$post = $this->post( $id );

		return null === $post ? null : $this->hydrate( $post );
	}

	/**
	 * Published only (the public render path).
	 *
	 * @param int $id Post ID.
	 * @return Illustration|null
	 */
	public function find_published( int $id ): ?Illustration {
		$post = $this->post( $id );

		return null !== $post && 'publish' === $post->post_status ? $this->hydrate( $post ) : null;
	}

	/**
	 * Whether the ID is an illustration.
	 *
	 * @param int $id Post ID.
	 * @return bool
	 */
	public function exists( int $id ): bool {
		return null !== $this->post( $id );
	}

	/**
	 * Published illustrations, newest first.
	 *
	 * @param int    $page     Page (1-based).
	 * @param int    $per_page Page size.
	 * @param string $search   Search text.
	 * @return array{items: array<Illustration>, total: int}
	 */
	public function list( int $page = 1, int $per_page = 20, string $search = '' ): array {
		$query = new \WP_Query(
			[
				'post_type'      => IllustrationPostType::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => $per_page,
				'paged'          => max( 1, $page ),
				's'              => $search,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			]
		);

		return [
			'items' => array_map( fn( \WP_Post $post ): Illustration => $this->hydrate( $post ), $query->posts ),
			'total' => (int) $query->found_posts,
		];
	}

	/**
	 * Create.
	 *
	 * @param string    $title     Name.
	 * @param SceneSpec $spec      Resolved spec.
	 * @param int       $author_id Author.
	 * @return Illustration|null Null when WordPress refused.
	 */
	public function create( string $title, SceneSpec $spec, int $author_id ): ?Illustration {
		$id = wp_insert_post(
			[
				'post_type'   => IllustrationPostType::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => $title,
				'post_author' => $author_id,
			],
			true
		);

		if ( is_wp_error( $id ) || ! $id ) {
			return null;
		}

		update_post_meta( $id, IllustrationPostType::META_SPEC, wp_slash( (string) wp_json_encode( $spec->to_array() ) ) );

		return $this->find( (int) $id );
	}

	/**
	 * Update name and/or spec.
	 *
	 * @param int            $id    Post ID.
	 * @param string|null    $title New name.
	 * @param SceneSpec|null $spec  New spec.
	 * @return Illustration|null Null when not an illustration.
	 */
	public function update( int $id, ?string $title, ?SceneSpec $spec ): ?Illustration {
		if ( null === $this->post( $id ) ) {
			return null;
		}

		if ( null !== $spec ) {
			update_post_meta( $id, IllustrationPostType::META_SPEC, wp_slash( (string) wp_json_encode( $spec->to_array() ) ) );
		}

		wp_update_post( [ 'ID' => $id ] + ( null === $title ? [] : [ 'post_title' => $title ] ) );

		return $this->find( $id );
	}

	/**
	 * Move to trash.
	 *
	 * @param int $id Post ID.
	 * @return bool
	 */
	public function trash( int $id ): bool {
		return null !== $this->post( $id ) && (bool) wp_trash_post( $id );
	}

	/**
	 * Delete permanently.
	 *
	 * @param int $id Post ID.
	 * @return bool
	 */
	public function delete( int $id ): bool {
		return null !== $this->post( $id ) && (bool) wp_delete_post( $id, true );
	}

	/**
	 * The post, only when it is an illustration.
	 *
	 * @param int $id Post ID.
	 * @return \WP_Post|null
	 */
	private function post( int $id ): ?\WP_Post {
		$post = $id > 0 ? get_post( $id ) : null;

		return $post instanceof \WP_Post && IllustrationPostType::POST_TYPE === $post->post_type ? $post : null;
	}

	/**
	 * Post to value object.
	 *
	 * @param \WP_Post $post Post.
	 * @return Illustration
	 */
	private function hydrate( \WP_Post $post ): Illustration {
		$raw  = get_post_meta( $post->ID, IllustrationPostType::META_SPEC, true );
		$spec = is_string( $raw ) ? json_decode( $raw, true ) : null;

		return Illustration::from_array(
			[
				'id'           => $post->ID,
				'title'        => $post->post_title,
				'spec'         => is_array( $spec ) ? $spec : [],
				'status'       => $post->post_status,
				'author_id'    => (int) $post->post_author,
				'modified_gmt' => mysql_to_rfc3339( $post->post_modified_gmt ),
			]
		);
	}
}
```

- [ ] **Step 3: `src/Rest/Permissions.php`**

```php
<?php
/**
 * Shared REST permission checks.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Rest;

use SprintIllustrations\Plugin;

/**
 * Capability checks returning WP_Error with the right status.
 */
final class Permissions {

	public const NAMESPACE = 'sprint-illustrations/v1';

	/**
	 * Base check for every route.
	 *
	 * @return true|\WP_Error
	 */
	public static function edit_posts(): bool|\WP_Error {
		return current_user_can( 'edit_posts' )
			? true
			: new \WP_Error( 'sprint_illustrations_rest_forbidden', __( 'You are not allowed to use Sprint Illustrations.', 'sprint-illustrations' ), [ 'status' => rest_authorization_required_code() ] );
	}

	/**
	 * Per-post check: 404 for non-illustrations first, then the capability.
	 *
	 * @param \WP_REST_Request $request Request with an "id" URL param.
	 * @param string           $cap     "edit_post" or "delete_post".
	 * @return true|\WP_Error
	 */
	public static function item( \WP_REST_Request $request, string $cap ): bool|\WP_Error {
		$base = self::edit_posts();
		if ( true !== $base ) {
			return $base;
		}

		$id = (int) $request['id'];
		if ( ! Plugin::instance()->illustrations()->exists( $id ) ) {
			return new \WP_Error( 'sprint_illustrations_not_found', __( 'Illustration not found.', 'sprint-illustrations' ), [ 'status' => 404 ] );
		}

		if ( ! current_user_can( $cap, $id ) ) {
			$code = 'delete_post' === $cap ? 'sprint_illustrations_cannot_delete' : 'sprint_illustrations_cannot_edit';
			return new \WP_Error( $code, __( 'You are not allowed to change this illustration.', 'sprint-illustrations' ), [ 'status' => 403 ] );
		}

		return true;
	}
}
```

- [ ] **Step 4: `src/Rest/PiecePreviews.php`**

```php
<?php
/**
 * Cached piece thumbnails for the Builder.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Rest;

use SprintIllustrations\Compose\PieceLoader;
use SprintIllustrations\Compose\PiecePreview;
use SprintIllustrations\Library\Piece;
use SprintIllustrations\Palette\Palette;
use SprintIllustrations\Security\Sanitizer;

/**
 * Transient-backed PiecePreview; keyed by piece hash + palette hash, so it invalidates itself.
 */
final class PiecePreviews {

	/**
	 * Constructor.
	 *
	 * @param PieceLoader $loader    Loader.
	 * @param Sanitizer   $sanitizer Sanitizer.
	 */
	public function __construct( private PieceLoader $loader, private Sanitizer $sanitizer ) {}

	/**
	 * Preview markup.
	 *
	 * @param Piece   $piece   Piece.
	 * @param Palette $palette Palette.
	 * @return string
	 */
	public function svg( Piece $piece, Palette $palette ): string {
		$key    = 'si_piece_preview_' . substr( sha1( $piece->id . '|' . $piece->hash . '|' . $palette->hash() ), 0, 32 );
		$cached = get_transient( $key );

		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}

		$markup = PiecePreview::render( $piece, $palette, $this->loader, $this->sanitizer );
		set_transient( $key, $markup, 30 * DAY_IN_SECONDS );

		return $markup;
	}
}
```

- [ ] **Step 5: `src/Rest/ComposeController.php`**

```php
<?php
/**
 * POST /compose — live Builder preview.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Rest;

use SprintIllustrations\Compose\CompositionException;
use SprintIllustrations\Compose\SceneSpec;
use SprintIllustrations\Plugin;

/**
 * Composes with the uncached composer (unsaved edits must not fill the cache).
 */
final class ComposeController {

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
		register_rest_route(
			Permissions::NAMESPACE,
			'/compose',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'compose' ],
				'permission_callback' => [ Permissions::class, 'edit_posts' ],
			]
		);
	}

	/**
	 * Compose a spec.
	 *
	 * @param \WP_REST_Request $request Request (SceneSpec JSON body).
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function compose( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$params = $request->get_json_params();
		$spec   = SceneSpec::from_array( is_array( $params ) ? $params : $request->get_body_params() );

		try {
			$result = $this->plugin->services()->composer->compose( $spec, $this->plugin->site_palette()->resolve( $spec->palette ) );
		} catch ( CompositionException $e ) {
			return new \WP_Error( 'sprint_illustrations_composition_failed', $e->getMessage(), [ 'status' => 422 ] );
		}

		$template = $this->plugin->services()->templates->get( (string) $result->spec->template );
		$slots    = [];
		foreach ( null === $template ? [] : $template->slots as $slot ) {
			$slots[] = $slot->to_array() + [
				'picked' => $result->spec->picks[ $slot->name ] ?? null,
				'locked' => array_key_exists( $slot->name, $spec->picks ),
				'boxes'  => $result->boxes[ $slot->name ] ?? [],
			];
		}

		return new \WP_REST_Response(
			[
				'svg'      => $result->with_instance_id( 'si-b' . substr( md5( uniqid( '', true ) ), 0, 10 ) ),
				'spec'     => $result->spec->to_array(),
				'warnings' => $result->warnings,
				'template' => null === $template ? null : [
					'id'     => $template->id,
					'label'  => $template->label,
					'canvas' => $template->canvas,
				],
				'slots'    => $slots,
			]
		);
	}
}
```

- [ ] **Step 6: `src/Rest/LibraryController.php`**

```php
<?php
/**
 * GET /library — templates and pieces for the Builder.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Rest;

use SprintIllustrations\Compose\PieceLoader;
use SprintIllustrations\Plugin;

/**
 * Library listing with cached piece thumbnails in the site palette.
 */
final class LibraryController {

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
		register_rest_route(
			Permissions::NAMESPACE,
			'/library',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'library' ],
				'permission_callback' => [ Permissions::class, 'edit_posts' ],
			]
		);
	}

	/**
	 * Templates and pieces.
	 *
	 * @return \WP_REST_Response
	 */
	public function library(): \WP_REST_Response {
		$services = $this->plugin->services();
		$previews = new PiecePreviews( new PieceLoader( $services->sanitizer ), $services->sanitizer );
		$palette  = $this->plugin->site_palette()->palette();
		$pieces   = [];

		foreach ( $services->manifest->all() as $piece ) {
			$pieces[] = $piece->to_array() + [ 'preview' => $previews->svg( $piece, $palette ) ];
		}

		return new \WP_REST_Response(
			[
				'templates' => array_map( static fn( $template ) => $template->to_array(), array_values( $services->templates->all() ) ),
				'pieces'    => $pieces,
				'presets'   => $this->plugin->editor_choices()['presets'],
			]
		);
	}
}
```

- [ ] **Step 7: `src/Rest/IllustrationsController.php`**

```php
<?php
/**
 * /illustrations — saved illustration CRUD.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Rest;

use SprintIllustrations\Compose\CompositionException;
use SprintIllustrations\Compose\SceneSpec;
use SprintIllustrations\Plugin;
use SprintIllustrations\Storage\Illustration;

/**
 * Own controller (the post type is not exposed through core REST).
 */
final class IllustrationsController {

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
		register_rest_route(
			Permissions::NAMESPACE,
			'/illustrations',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'index' ],
					'permission_callback' => [ Permissions::class, 'edit_posts' ],
					'args'                => [
						'page'     => [
							'type'    => 'integer',
							'default' => 1,
							'minimum' => 1,
						],
						'per_page' => [
							'type'    => 'integer',
							'default' => 20,
							'minimum' => 1,
							'maximum' => 100,
						],
						'search'   => [
							'type'    => 'string',
							'default' => '',
						],
					],
				],
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'create' ],
					'permission_callback' => [ Permissions::class, 'edit_posts' ],
				],
			]
		);

		register_rest_route(
			Permissions::NAMESPACE,
			'/illustrations/(?P<id>\d+)',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'show' ],
					'permission_callback' => static fn( \WP_REST_Request $request ) => Permissions::item( $request, 'read_post' ),
				],
				[
					'methods'             => 'PUT',
					'callback'            => [ $this, 'update' ],
					'permission_callback' => static fn( \WP_REST_Request $request ) => Permissions::item( $request, 'edit_post' ),
				],
				[
					'methods'             => 'DELETE',
					'callback'            => [ $this, 'destroy' ],
					'permission_callback' => static fn( \WP_REST_Request $request ) => Permissions::item( $request, 'delete_post' ),
				],
			]
		);
	}

	/**
	 * List.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function index( \WP_REST_Request $request ): \WP_REST_Response {
		$per_page = (int) $request['per_page'];
		$result   = $this->plugin->illustrations()->list( (int) $request['page'], $per_page, sanitize_text_field( (string) $request['search'] ) );

		return new \WP_REST_Response(
			[
				'items' => array_map(
					fn( Illustration $item ): array => [
						'id'           => $item->id,
						'title'        => $item->title,
						'template'     => $item->spec->template,
						'modified_gmt' => $item->modified_gmt,
						'author_id'    => $item->author_id,
					] + $this->caps( (int) $item->id ),
					$result['items']
				),
				'total' => $result['total'],
				'pages' => (int) ceil( $result['total'] / max( 1, $per_page ) ),
			]
		);
	}

	/**
	 * Single.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function show( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$item = $this->plugin->illustrations()->find( (int) $request['id'] );

		return null === $item ? $this->not_found() : new \WP_REST_Response( $this->shape( $item ) );
	}

	/**
	 * Create.
	 *
	 * @param \WP_REST_Request $request Request {title, spec}.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$title = $this->title( $request );
		if ( is_wp_error( $title ) ) {
			return $title;
		}

		$spec = $this->resolved_spec( $request['spec'] ?? null );
		if ( is_wp_error( $spec ) ) {
			return $spec;
		}

		$item = $this->plugin->illustrations()->create( $title, $spec, get_current_user_id() );

		return null === $item
			? new \WP_Error( 'sprint_illustrations_save_failed', __( 'The illustration could not be saved.', 'sprint-illustrations' ), [ 'status' => 500 ] )
			: new \WP_REST_Response( $this->shape( $item ), 201 );
	}

	/**
	 * Update (partial).
	 *
	 * @param \WP_REST_Request $request Request {title?, spec?}.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$has_title = null !== $request['title'];
		$has_spec  = null !== $request['spec'];

		if ( ! $has_title && ! $has_spec ) {
			return new \WP_Error( 'sprint_illustrations_invalid_title', __( 'Send a name, a design, or both.', 'sprint-illustrations' ), [ 'status' => 400 ] );
		}

		$title = $has_title ? $this->title( $request ) : null;
		if ( is_wp_error( $title ) ) {
			return $title;
		}

		$spec = $has_spec ? $this->resolved_spec( $request['spec'] ) : null;
		if ( is_wp_error( $spec ) ) {
			return $spec;
		}

		$item = $this->plugin->illustrations()->update( (int) $request['id'], $title, $spec );

		return null === $item ? $this->not_found() : new \WP_REST_Response( $this->shape( $item ) );
	}

	/**
	 * Trash or delete.
	 *
	 * @param \WP_REST_Request $request Request (?force=true to delete permanently).
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function destroy( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id    = (int) $request['id'];
		$force = rest_sanitize_boolean( $request['force'] ?? false );
		$done  = $force ? $this->plugin->illustrations()->delete( $id ) : $this->plugin->illustrations()->trash( $id );

		return $done
			? new \WP_REST_Response(
				[
					'deleted' => true,
					'id'      => $id,
					'status'  => $force ? 'deleted' : 'trash',
				]
			)
			: $this->not_found();
	}

	/**
	 * Validated name.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return string|\WP_Error
	 */
	private function title( \WP_REST_Request $request ): string|\WP_Error {
		$title = trim( sanitize_text_field( (string) $request['title'] ) );

		return '' === $title
			? new \WP_Error( 'sprint_illustrations_invalid_title', __( 'Give the illustration a name.', 'sprint-illustrations' ), [ 'status' => 400 ] )
			: mb_substr( $title, 0, Illustration::MAX_TITLE );
	}

	/**
	 * Compose the submitted spec (cached composer) and return the resolved spec to store.
	 *
	 * @param mixed $raw Spec data.
	 * @return SceneSpec|\WP_Error
	 */
	private function resolved_spec( mixed $raw ): SceneSpec|\WP_Error {
		$spec = SceneSpec::from_array( is_array( $raw ) ? $raw : [] );

		try {
			return $this->plugin->composer()->compose( $spec, $this->plugin->site_palette()->resolve( $spec->palette ) )->spec;
		} catch ( CompositionException $e ) {
			return new \WP_Error( 'sprint_illustrations_composition_failed', $e->getMessage(), [ 'status' => 422 ] );
		}
	}

	/**
	 * Full item shape.
	 *
	 * @param Illustration $item Item.
	 * @return array<string, mixed>
	 */
	private function shape( Illustration $item ): array {
		return $item->to_array() + $this->caps( (int) $item->id );
	}

	/**
	 * Current user's rights on an item.
	 *
	 * @param int $id Post ID.
	 * @return array{can_edit: bool, can_delete: bool}
	 */
	private function caps( int $id ): array {
		return [
			'can_edit'   => current_user_can( 'edit_post', $id ),
			'can_delete' => current_user_can( 'delete_post', $id ),
		];
	}

	/**
	 * 404.
	 *
	 * @return \WP_Error
	 */
	private function not_found(): \WP_Error {
		return new \WP_Error( 'sprint_illustrations_not_found', __( 'Illustration not found.', 'sprint-illustrations' ), [ 'status' => 404 ] );
	}
}
```

- [ ] **Step 8: Wire `src/Plugin.php`**

1. Add `use` lines for `Rest\ComposeController`, `Rest\IllustrationsController`, `Rest\LibraryController`, `Storage\IllustrationPostType`, `Storage\IllustrationRepository` and `Compose\SceneSpec`.
2. Add a property `private ?IllustrationRepository $illustrations = null;` and this method:

```php
	/**
	 * Saved illustrations.
	 *
	 * @return IllustrationRepository
	 */
	public function illustrations(): IllustrationRepository {
		return $this->illustrations ??= new IllustrationRepository();
	}
```

3. In `renderer()`, add a 4th constructor argument:

```php
			fn( int $id ): ?SceneSpec => $this->illustrations()->find_published( $id )?->spec
```

4. In `editor_choices()`, before `return`, build `$illustrations` from `$this->illustrations()->list( 1, 100 )['items']` as `[ 'label' => $item->title, 'value' => (int) $item->id ]`, and add `'illustrations' => $illustrations` to the returned array. Update the docblock's return shape.
5. In `register_hooks()`, after the `Sync` line, add:

```php
		( new IllustrationPostType() )->register();
		( new ComposeController( $this ) )->register();
		( new LibraryController( $this ) )->register();
		( new IllustrationsController( $this ) )->register();
```

- [ ] **Step 9: `id=` on the surfaces**

- `src/Integrations/Shortcode.php`: add `'id' => '0',` as the first `shortcode_atts` default.
- `blocks/illustration/block.json`: add `"illustrationId": { "type": "integer", "default": 0 },` as the first attribute, and set `"version": "0.4.0"`.
- `blocks/illustration/render.php`: replace the renderer argument `$attributes` with `array( 'id' => (int) ( $attributes['illustrationId'] ?? 0 ) ) + $attributes`.
- `blocks/illustration/editor.js`: at the top of the Illustration panel, before the Template `SelectControl`, add:

```js
						el( SelectControl, {
							label: __( 'Saved illustration', 'sprint-illustrations' ),
							help: attributes.illustrationId ? __( 'Uses the saved design. Only the accessibility settings below apply.', 'sprint-illustrations' ) : __( 'Or design one here with the settings below.', 'sprint-illustrations' ),
							value: String( attributes.illustrationId || 0 ),
							options: [ { label: __( 'None (design here)', 'sprint-illustrations' ), value: '0' } ].concat( ( choices.illustrations || [] ).map( function ( item ) {
								return { label: item.label, value: String( item.value ) };
							} ) ),
							onChange: function ( value ) {
								setAttributes( { illustrationId: parseInt( value, 10 ) || 0 } );
							},
							__nextHasNoMarginBottom: true,
						} ),
```

- `src/Integrations/Elementor/Widget.php`: as the first control in the `illustration` section, add:

```php
		$this->add_control(
			'illustration_id',
			[
				'label'       => esc_html__( 'Saved illustration', 'sprint-illustrations' ),
				'type'        => \Elementor\Controls_Manager::SELECT,
				'default'     => '0',
				'options'     => [ '0' => esc_html__( 'None (design here)', 'sprint-illustrations' ) ] + array_column( $choices['illustrations'], 'label', 'value' ),
				'description' => esc_html__( 'A saved design ignores the settings below except accessibility.', 'sprint-illustrations' ),
			]
		);
```

  In `render()`, add `'id' => (int) ( $settings['illustration_id'] ?? 0 ),` to the args.
- `sprint-illustrations.php`: set the version to `0.4.0` (header and constant).

- [ ] **Step 10: Verify with WP-CLI**

Run `composer lint` and `composer test`, then run a verification script (scratchpad `verify-3a.php`, run with `wp eval-file`) that, per role, sets `wp_set_current_user()` and dispatches `rest_do_request()`:
- Anonymous `POST /compose` returns 401. A Subscriber (create a temporary one, deleted at the end) gets 403. An Administrator gets 200 with `slots[*].boxes`.
- `POST /illustrations` as Administrator returns 201. `GET` returns it. `PUT` a title returns 200.
- A temporary Author gets 403 on `PUT`/`DELETE` for the Administrator's item. The Author's own item returns 200.
- `PUT /illustrations/<page 411>` returns 404, the IDOR guard.
- The `/compose` twice leaves the cache file count unchanged, and `POST /illustrations` raises it.
- `[sprint_illustration id=N]`, `render_block` with `illustrationId` N, and the widget with `illustration_id` N produce the same `<figure class=…>` opening and SVG body, apart from the per-copy instance IDs.
- `DELETE` returns `trash`, and `?force=true` removes the post.
- The script deletes the temporary users and illustrations.

- [ ] **Step 11: Commit**

```bash
git add src sprint-illustrations.php blocks
git commit -m "feat(rest): saved illustrations post type, REST API and id= on every surface"
```

---

### Task 5: Builder screen

**REQUIRED SUB-SKILL:** Load `frontend-design:frontend-design` before writing the CSS and components. The direction is fixed by spec §3.5.

**Files:**
- Create: `src/Admin/BuilderPage.php`, `assets/admin/builder.css`, `src-js/builder/index.js`, `src-js/builder/api.js`, `src-js/builder/App.js`, `src-js/builder/TemplatePicker.js`, `src-js/builder/SlotList.js`, `src-js/builder/Stage.js`, `src-js/builder/SidePanel.js`, `src-js/builder/TopBar.js`, `package.json` (already created), `package-lock.json`, `build/builder.js`, `build/builder.asset.php`
- Modify: `src/Admin/Menu.php`, `src/Admin/SettingsPage.php` (slug), `.gitignore` (stop ignoring `/build/`), `phpcs.xml.dist` (exclude `/build/*` stays)

**Known deviation from the writing-plans rules:** this task gives contracts, not complete component code. The React components are UI that must be shaped against real `/compose` responses and screenshot critique, so writing them blind here would only be rewritten. The data they talk to is fully specified in Tasks 1–4. The component code is written in Task 5 execution against these contracts:
- `api.js` exports `getLibrary()`, `compose( spec )`, `listIllustrations( search )`, `getIllustration( id )`, `saveIllustration( { id, title, spec } )` (POST, or PUT when `id` is set) and `thumbnails( templates )` (composes seed 3, site palette, decorative, per template). All go through `@wordpress/api-fetch` with `path: '/sprint-illustrations/v1/…'`, and the nonce comes from core's middleware.
- `App.js` state (`useReducer`): `{ library, spec: { template, seed, palette, keywords, title, decorative }, picks, result, status, hover, name, id, dirty, error }`.
  - The reducer handles `LIBRARY`, `SET_SPEC`, `SET_TEMPLATE` (clears picks), `PICK` (sets and locks), `UNPICK`, `TOGGLE_LOCK` (adds or removes the slot's current `picked` in `picks`), `SHUFFLE` (new seed, keeps picks), `RESULT`, `STATUS`, `HOVER`, `LOADED` (from a saved illustration), `SAVED` and `NAME`.
  - A debounced (150 ms) effect posts `compose( { ...spec, picks } )` and applies only the latest response.
  - A `beforeunload` listener runs while `dirty`.
- The components follow spec §3.3–3.4 exactly. `Stage.js` renders `dangerouslySetInnerHTML` (server-sanitized SVG) plus an absolutely positioned overlay `<svg viewBox="0 0 W H">` drawing `rect`s for the hovered slot's `boxes`.

- [ ] **Step 1:** Update `.gitignore`: remove `/build/` and keep `/node_modules/`.
- [ ] **Step 2:** Create `src/Admin/BuilderPage.php` (capability `edit_posts`):
  - `render()` outputs `<div class="wrap si-builder-wrap"><h1 class="screen-reader-text">…</h1><div id="si-builder"></div><noscript>…</noscript></div>`.
  - `enqueue()` loads `build/builder.asset.php`, enqueues `build/builder.js` (in the footer) with its dependencies and version, the `wp-components` style, and `assets/admin/builder.css`.
  - It localizes `sprintIllustrationsBuilder = { initialId, settingsUrl (only for manage_options), shortcodeTag }`.
  - If `build/builder.asset.php` is missing, it shows the notice "The Builder hasn't been built. Run npm install and npm run build in the plugin folder."
- [ ] **Step 3:** Update `src/Admin/Menu.php`:
  - Top level: slug `sprint-illustrations`, capability `edit_posts`, callback `BuilderPage::render`.
  - Submenus: Builder (same slug), Settings (`sprint-illustrations-settings`, `manage_options`) and Test page.
  - Enqueue by hook suffix for all three.
  - Add `Menu::SETTINGS_SLUG`.
  - `SettingsPage::purge()` redirects to `admin.php?page=` . `Menu::SETTINGS_SLUG`.
- [ ] **Step 4:** Write `src-js/builder/*` and `assets/admin/builder.css` to the contracts above and spec §3. Then run `npm run build` (expect `build/builder.js` and `build/builder.asset.php`) and `npm run lint:js` (clean, or only formatting that `--fix` resolves).
- [ ] **Step 5:** Verify:
  - `composer lint` and `composer test` pass.
  - `wp --user=<admin> eval` rendering `BuilderPage::render()` outputs the mount point.
  - `wp eval 'var_dump( file_exists( …/build/builder.asset.php ) );'` shows the build output exists.
  - A static review page loads `build/builder.js` with mocked `wp.apiFetch` responses (captured from real `rest_do_request` output) at 1440 px and 1024 px, and gets a screenshot critique.
- [ ] **Step 6:** Commit:

```bash
git add .gitignore package.json package-lock.json src-js build assets/admin/builder.css src/Admin
git commit -m "feat(builder): React Builder screen"
```

---

### Task 6: Docs and hand-off

- [ ] Update `CLAUDE.md`:
  - Commands: `npm install`, `npm run build`, `npm run lint:js`, and "build/ is committed".
  - Architecture: saved illustrations, REST routes, the Builder, and the person rule.
  - Menu slugs.
- [ ] Add execution notes to this plan.
- [ ] Run `composer test`, `composer lint` and `npm run build` one final time and confirm there's no diff in `build/`.
- [ ] Commit, push the branch, and open a PR with a manual checklist for the Builder click-through, which needs a login.

## Execution notes

- Tasks 1–4 went as written. No existing fixture test depended on a repeated person, so no expectations changed.
- **WordPress verification:** a `verify-3a.php` scratch script, run with `wp eval-file`, passed all 38 checks. It covered:
  - The permission matrix and own-versus-others checks.
  - The post-type guard: an existing page's ID returns 404.
  - The cache split.
  - A saved ID rendering identically on all three surfaces.
  - Trash versus permanent delete.
  - Cleanup of the temporary users and posts.
- **Task 5 findings:**
  - `@wordpress/eslint-plugin` crashed without `typescript` installed. It's now a dev dependency.
  - ESLint `no-nested-ternary` flagged the save-state label, which now uses a plain `if`.
  - The design review changed the default template to `hero-left-character`, fixed the lock column alignment, made the locked state solid blue, moved the keywords help below its row, and set the template grid to 3 columns under 960 px.
- **Builder review method:** a login couldn't be automated, so the real `build/builder.js` was loaded on a static page with WordPress's own dependency scripts, which were computed with `wp_scripts()->all_deps()`. Only `wp.apiFetch` was mocked, returning REST responses captured with `rest_do_request`. There were no console errors. Lock plus Shuffle sends `picks` and a new seed. Saving without a name shows the inline error. The `beforeunload` prompt fires. Nothing overflows at 1440, 1024 or 782 px.
- **Not automated (needs a login):** the real click-through against live REST: Save, Open, Save as new, Copy shortcode, and the piece picker changing the live composition.
