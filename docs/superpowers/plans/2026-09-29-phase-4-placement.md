# Sprint Illustrations — Phase 4 (Placement) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Illustrations render on real pages through the `[sprint_illustration]` shortcode, the `sprint-illustrations/illustration` block and a "Sprint Illustration" Elementor widget, and two published showcase pages demonstrate them on the Local site.

**Architecture:** A pure `Render\Renderer` normalizes attributes into a `SceneSpec`, composes through the caching composer, gives each copy unique IDs and wraps the result in a `<figure>`. The shortcode, block and widget are thin WordPress adapters that all call `Plugin::renderer()->render( $attrs, current_user_can( 'edit_posts' ) )`. The block's editor script is plain JavaScript on `wp.*` globals, with no build step.

**Tech Stack:** PHP 8.1+, WordPress 7.1.2 (6.4+ supported), Elementor 4.2.4 classic `Widget_Base`, PHPUnit 10.5, WPCS 3.

**Spec:** `docs/superpowers/specs/2026-09-29-phase-4-placement-design.md`.

## Global Constraints

- Every PHP file starts with `declare( strict_types=1 );`. Use WPCS style. The text domain is `sprint-illustrations`. `Render` is a pure namespace (no WordPress calls) and must be added to the pure-path exclusions in `phpcs.xml.dist`.
- The stylesheet handle is `sprint-illustrations`, the shortcode tag `sprint_illustration`, the block name `sprint-illustrations/illustration`, and the widget name `sprint-illustration`.
- Attribute defaults: `template ""`, `keywords ""`, `seed 1`, `palette "site"`, `title ""`, `decorative false`. The template values `""` and `"auto"` mean automatic.
- Visitors never see error text. `$show_errors = current_user_can( 'edit_posts' )`.
- Plugin version is `0.3.0`.
- Commit messages end with the `Co-Authored-By:` trailer.

## File map

| Path | Responsibility |
|---|---|
| `src/Render/Renderer.php`, `tests/Unit/Render/RendererTest.php` | Pure renderer |
| `assets/front/illustration.css` | Front-end styles |
| `src/Plugin.php` (modify), `sprint-illustrations.php` (version) | `renderer()`, `register_assets()`, `editor_choices()`, surface wiring |
| `src/Integrations/Shortcode.php` | Shortcode |
| `src/Integrations/Block.php`, `blocks/illustration/{block.json,render.php,editor.js,editor.asset.php}` | Block |
| `src/Integrations/Elementor/{Loader,Widget}.php` | Elementor widget |
| `phpcs.xml.dist` (modify) | Lint `blocks/`, pure path `Render`, exclude `*.asset.php` |

---

### Task 1: Renderer

**Files:** Create `src/Render/Renderer.php` and `tests/Unit/Render/RendererTest.php`. Modify `phpcs.xml.dist`.

**Interfaces:**
- Produces:
  - `new Renderer( ComposesSvg $composer, \Closure $palettes, ?\Closure $log = null )`, where `$palettes` is `fn( string|array $ref ): Palette` and `$log` is `fn( string $message ): void`.
  - `Renderer::spec( array $args ): SceneSpec` (static).
  - `->render( array $args, bool $show_errors = false ): string`.

- [ ] **Step 1: Write the failing test** `tests/Unit/Render/RendererTest.php`

```php
<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Render;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Palette\Palette;
use SprintIllustrations\Render\Renderer;
use SprintIllustrations\Services;

final class RendererTest extends TestCase {

	/** @var array<string|array> */
	private array $palette_refs = [];

	/** @var array<string> */
	private array $logged = [];

	private function renderer(): Renderer {
		return new Renderer(
			Services::create( __DIR__ . '/../../fixtures/library' )->composer,
			function ( string|array $ref ): Palette {
				$this->palette_refs[] = $ref;
				return Palette::default();
			},
			function ( string $message ): void {
				$this->logged[] = $message;
			}
		);
	}

	public function test_spec_normalizes_surface_attributes(): void {
		$spec = Renderer::spec(
			[
				'template'   => 'auto',
				'keywords'   => 'Team, Remote',
				'seed'       => '42',
				'palette'    => '',
				'title'      => 'Our team',
				'decorative' => 'yes',
			]
		);

		$this->assertNull( $spec->template );
		$this->assertSame( [ 'team', 'remote' ], $spec->keywords );
		$this->assertSame( 42, $spec->seed );
		$this->assertSame( 'site', $spec->palette );
		$this->assertSame( 'Our team', $spec->title );
		$this->assertTrue( $spec->decorative );
	}

	public function test_spec_defaults(): void {
		$spec = Renderer::spec( [] );

		$this->assertNull( $spec->template );
		$this->assertSame( 1, $spec->seed );
		$this->assertSame( 'site', $spec->palette );
		$this->assertFalse( $spec->decorative );
	}

	public function test_renders_figure_with_unique_ids(): void {
		$renderer = $this->renderer();
		$args     = [
			'template' => 'fixture-hero',
			'seed'     => 5,
		];

		$first  = $renderer->render( $args );
		$second = $renderer->render( $args );

		$this->assertStringStartsWith( '<figure class="si-illustration si-template-fixture-hero"><svg', $first );
		$this->assertStringEndsWith( '</svg></figure>', $first );
		$this->assertStringNotContainsString( '__SIID__', $first );
		$this->assertMatchesRegularExpression( '/id="si-[0-9a-f]{8}-1-t"/', $first );
		$this->assertMatchesRegularExpression( '/id="si-[0-9a-f]{8}-2-t"/', $second );
		$this->assertSame( [ 'site', 'site' ], $this->palette_refs );
	}

	public function test_errors_are_hidden_from_visitors(): void {
		$html = $this->renderer()->render( [ 'template' => 'no-such-template' ] );

		$this->assertSame( '<!-- Sprint Illustrations: illustration could not be rendered. -->', $html );
		$this->assertCount( 1, $this->logged );
		$this->assertStringContainsString( 'no-such-template', $this->logged[0] );
	}

	public function test_errors_are_shown_escaped_to_editors(): void {
		$html = $this->renderer()->render( [ 'template' => 'no-such-template' ], true );

		$this->assertStringStartsWith( '<div class="si-illustration-error" role="alert">Sprint Illustrations: ', $html );
		$this->assertStringContainsString( '&quot;no-such-template&quot;', $html );
	}
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `composer test -- tests/Unit/Render`
Expected: FAIL with `Class "SprintIllustrations\Render\Renderer" not found`.

- [ ] **Step 3: Implement `src/Render/Renderer.php`**

```php
<?php
/**
 * Renders an illustration for a placement surface (shortcode, block, widget).
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Render;

use SprintIllustrations\Compose\ComposesSvg;
use SprintIllustrations\Compose\CompositionException;
use SprintIllustrations\Compose\SceneSpec;

/**
 * Normalizes surface attributes, composes, scopes IDs per copy and wraps in a figure.
 */
final class Renderer {

	/**
	 * Copies rendered in this request (keeps IDs unique on a page).
	 *
	 * @var int
	 */
	private int $count = 0;

	/**
	 * Constructor.
	 *
	 * @param ComposesSvg   $composer Composer (normally the caching one).
	 * @param \Closure      $palettes fn( string|array $ref ): Palette.
	 * @param \Closure|null $log      fn( string $message ): void, for failures.
	 */
	public function __construct(
		private ComposesSvg $composer,
		private \Closure $palettes,
		private ?\Closure $log = null,
	) {}

	/**
	 * Surface attributes to a scene spec. "" and "auto" template mean automatic; "" palette means site.
	 *
	 * @param array<string, mixed> $args Attributes.
	 * @return SceneSpec
	 */
	public static function spec( array $args ): SceneSpec {
		$template = isset( $args['template'] ) && is_string( $args['template'] ) ? trim( $args['template'] ) : '';
		$palette  = isset( $args['palette'] ) && is_string( $args['palette'] ) && '' !== trim( $args['palette'] ) ? trim( $args['palette'] ) : 'site';

		return SceneSpec::from_array(
			[
				'template'   => in_array( $template, [ '', 'auto' ], true ) ? null : $template,
				'keywords'   => $args['keywords'] ?? '',
				'seed'       => $args['seed'] ?? 1,
				'palette'    => $palette,
				'title'      => $args['title'] ?? '',
				'decorative' => $args['decorative'] ?? false,
			]
		);
	}

	/**
	 * Render markup.
	 *
	 * @param array<string, mixed> $args        Attributes.
	 * @param bool                 $show_errors Show a readable notice instead of a silent comment.
	 * @return string
	 */
	public function render( array $args, bool $show_errors = false ): string {
		$spec = self::spec( $args );

		try {
			$result = $this->composer->compose( $spec, ( $this->palettes )( $spec->palette ) );
		} catch ( CompositionException $e ) {
			if ( null !== $this->log ) {
				( $this->log )( $e->getMessage() );
			}

			return $show_errors
				? '<div class="si-illustration-error" role="alert">' . self::esc( 'Sprint Illustrations: ' . $e->getMessage() ) . '</div>'
				: '<!-- Sprint Illustrations: illustration could not be rendered. -->';
		}

		$instance = 'si-' . substr( sha1( (string) json_encode( $result->spec->to_array() ) ), 0, 8 ) . '-' . ( ++$this->count );

		return '<figure class="si-illustration si-template-' . self::esc( (string) $result->spec->template ) . '">'
			. $result->with_instance_id( $instance )
			. '</figure>';
	}

	/**
	 * Escape for HTML (framework-free).
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function esc( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}
}
```

- [ ] **Step 4: Update `phpcs.xml.dist`**

1. Add `<file>blocks</file>` after `<file>bin</file>`.
2. Add `<exclude-pattern>*.asset.php</exclude-pattern>` after the other exclude patterns.
3. Add `Render` to both pure-path groups: `(Library|Compose|Palette|Security|Selection|Svg|Dev|Cli|Cache|Render)`.

- [ ] **Step 5: Run the tests**

Run: `composer test`, then `composer lint`
Expected: PASS, and lint prints nothing.

- [ ] **Step 6: Commit**

```bash
git add src/Render/Renderer.php tests/Unit/Render/RendererTest.php phpcs.xml.dist
git commit -m "feat(render): pure renderer with per-copy IDs and editor-only errors"
```

---

### Task 2: Plugin wiring, stylesheet and shortcode

**Files:**
- Create: `assets/front/illustration.css`, `src/Integrations/Shortcode.php`
- Modify: `src/Plugin.php`, `sprint-illustrations.php` (version `0.3.0`)

**Interfaces:**
- Produces:
  - `Plugin::STYLE_HANDLE`, `Plugin::renderer(): Renderer`, `Plugin::register_assets(): void`.
  - `Plugin::editor_choices(): array{templates: array<array{label: string, value: string}>, presets: array<array{label: string, value: string}>}`.
  - `Shortcode::TAG`, `new Shortcode( Plugin )`, `->register()`, `->render( $atts ): string`.

- [ ] **Step 1: Write `assets/front/illustration.css`**

```css
/* Sprint Illustrations: front-end output. */
.si-illustration{margin:0 0 1.5em;max-width:100%}
.si-illustration svg{display:block;width:100%;height:auto}
.si-illustration-error{margin:0 0 1.5em;padding:12px 16px;border-left:4px solid #d63638;background:#fcf0f1;color:#1d2327;font:14px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}
```

- [ ] **Step 2: Create `src/Integrations/Shortcode.php`**

```php
<?php
/**
 * [sprint_illustration] shortcode.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Integrations;

use SprintIllustrations\Plugin;

/**
 * [sprint_illustration template="hero-left-character" keywords="team,remote" seed="42" palette="preset:ocean"].
 */
final class Shortcode {

	public const TAG = 'sprint_illustration';

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( private Plugin $plugin ) {}

	/**
	 * Register the shortcode.
	 */
	public function register(): void {
		add_shortcode( self::TAG, [ $this, 'render' ] );
	}

	/**
	 * Shortcode callback.
	 *
	 * @param array<string, string>|string $atts Attributes ("" when none).
	 * @return string
	 */
	public function render( $atts ): string {
		$atts = shortcode_atts(
			[
				'template'   => '',
				'keywords'   => '',
				'seed'       => '1',
				'palette'    => 'site',
				'title'      => '',
				'decorative' => 'false',
			],
			is_array( $atts ) ? $atts : [],
			self::TAG
		);

		wp_enqueue_style( Plugin::STYLE_HANDLE );

		return $this->plugin->renderer()->render( $atts, current_user_can( 'edit_posts' ) );
	}
}
```

- [ ] **Step 3: Extend `src/Plugin.php`**

Add these `use` lines: `SprintIllustrations\Integrations\Block`, `SprintIllustrations\Integrations\Elementor\Loader as ElementorLoader`, `SprintIllustrations\Integrations\Shortcode`, `SprintIllustrations\Palette\Palette`, `SprintIllustrations\Render\Renderer`. (Leave the `Block` and `ElementorLoader` lines and registrations out until Tasks 3 and 4.)

Add `public const STYLE_HANDLE = 'sprint-illustrations';` next to `CRON_HOOK`. Add a `private ?Renderer $renderer = null;` property, and these methods after `composer()`:

```php
	/**
	 * Renderer shared by the shortcode, block and Elementor widget.
	 *
	 * @return Renderer
	 */
	public function renderer(): Renderer {
		return $this->renderer ??= new Renderer(
			$this->composer(),
			fn( string|array $ref ): Palette => $this->site_palette()->resolve( $ref ),
			static function ( string $message ): void {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'Sprint Illustrations: ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug only.
				}
			}
		);
	}

	/**
	 * Register the front-end stylesheet (enqueued only where an illustration renders).
	 */
	public function register_assets(): void {
		wp_register_style( self::STYLE_HANDLE, plugins_url( 'assets/front/illustration.css', SPRINT_ILLUSTRATIONS_FILE ), [], SPRINT_ILLUSTRATIONS_VERSION );
	}

	/**
	 * Template and palette choices for the block and widget controls.
	 *
	 * @return array{templates: array<array{label: string, value: string}>, presets: array<array{label: string, value: string}>}
	 */
	public function editor_choices(): array {
		$templates = [];
		foreach ( $this->services()->templates->all() as $template ) {
			$templates[] = [
				'label' => $template->label,
				'value' => $template->id,
			];
		}

		$presets = [];
		foreach ( $this->presets()->all() as $id => $preset ) {
			$presets[] = [
				/* translators: %s: preset name, e.g. "Ocean". */
				'label' => sprintf( __( 'Preset: %s', 'sprint-illustrations' ), $preset['label'] ),
				'value' => 'preset:' . $id,
			];
		}

		return [
			'templates' => $templates,
			'presets'   => $presets,
		];
	}
```

In `register_hooks()`, after the `Sync` line, add:

```php
		add_action( 'init', [ $this, 'register_assets' ], 5 );
		( new Shortcode( $this ) )->register();
```

- [ ] **Step 4: Bump the version to `0.3.0`** in the `Version:` header and `SPRINT_ILLUSTRATIONS_VERSION` in `sprint-illustrations.php`.

- [ ] **Step 5: Verify**

Run `composer lint` and `composer test` (both clean). Then in the site shell:

```bash
wp eval 'echo do_shortcode( "[sprint_illustration template=\"empty-state\" palette=\"preset:forest\"]" );' | head -c 160
# Expected: <figure class="si-illustration si-template-empty-state"><svg …
wp eval 'echo do_shortcode( "[sprint_illustration template=\"nope\"]" );'
# Expected: <!-- Sprint Illustrations: illustration could not be rendered. -->
wp --user=<admin> eval 'echo do_shortcode( "[sprint_illustration template=\"nope\"]" );'
# Expected: <div class="si-illustration-error" role="alert">Sprint Illustrations: Unknown template &quot;nope&quot;.</div>
```

- [ ] **Step 6: Commit**

```bash
git add assets/front/illustration.css src/Integrations/Shortcode.php src/Plugin.php sprint-illustrations.php
git commit -m "feat(render): front-end stylesheet and [sprint_illustration] shortcode"
```

---

### Task 3: Block

**Files:**
- Create: `blocks/illustration/block.json`, `blocks/illustration/render.php`, `blocks/illustration/editor.js`, `blocks/illustration/editor.asset.php`, `src/Integrations/Block.php`
- Modify: `src/Plugin.php` (register `Block`)

**Interfaces:**
- Consumes: `Plugin::renderer()`, `Plugin::editor_choices()`, `Plugin::STYLE_HANDLE`, `Plugin::dir()`.
- Produces: `Block::NAME`, `new Block( Plugin )`, `->register()`, `->register_type()`, `->editor_data()`.

- [ ] **Step 1: `blocks/illustration/block.json`**

```json
{
	"$schema": "https://schemas.wp.org/trunk/block.json",
	"apiVersion": 3,
	"name": "sprint-illustrations/illustration",
	"version": "0.3.0",
	"title": "Sprint Illustration",
	"category": "media",
	"icon": "art",
	"description": "A flat illustration composed in your brand palette.",
	"keywords": [ "illustration", "svg", "hero", "image" ],
	"textdomain": "sprint-illustrations",
	"attributes": {
		"template": { "type": "string", "default": "" },
		"keywords": { "type": "string", "default": "" },
		"seed": { "type": "number", "default": 1 },
		"palette": { "type": "string", "default": "site" },
		"title": { "type": "string", "default": "" },
		"decorative": { "type": "boolean", "default": false }
	},
	"supports": {
		"html": false,
		"align": [ "left", "center", "right", "wide", "full" ],
		"spacing": { "margin": true }
	},
	"editorScript": "file:./editor.js",
	"style": "sprint-illustrations",
	"render": "file:./render.php"
}
```

- [ ] **Step 2: `blocks/illustration/render.php`**

```php
<?php
/**
 * Server render for sprint-illustrations/illustration.
 *
 * @package SprintIllustrations
 *
 * @var array<string, mixed> $attributes Block attributes.
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

printf(
	'<div %1$s>%2$s</div>',
	get_block_wrapper_attributes(), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes wrapper attributes.
	\SprintIllustrations\Plugin::instance()->renderer()->render( $attributes, current_user_can( 'edit_posts' ) ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitized SVG from the composer.
);
```

- [ ] **Step 3: `blocks/illustration/editor.asset.php`**

```php
<?php return array( 'dependencies' => array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render', 'wp-data', 'wp-i18n' ), 'version' => '0.3.0' );
```

- [ ] **Step 4: `blocks/illustration/editor.js`**

```js
/* Sprint Illustrations block editor. No build step: plain JS on wp.* globals. */
( function ( wp ) {
	'use strict';

	const el = wp.element.createElement;
	const Fragment = wp.element.Fragment;
	const { InspectorControls, useBlockProps } = wp.blockEditor;
	const { PanelBody, SelectControl, TextControl, ToggleControl, Button, Flex } = wp.components;
	const ServerSideRender = wp.serverSideRender;
	const { useSelect } = wp.data;
	const { __ } = wp.i18n;
	const choices = window.sprintIllustrationsBlock || { templates: [], presets: [] };

	wp.blocks.registerBlockType( 'sprint-illustrations/illustration', {
		edit: function Edit( props ) {
			const { attributes, setAttributes } = props;
			const blockProps = useBlockProps();
			const postTitle = useSelect( function ( select ) {
				const editor = select( 'core/editor' );
				return editor ? editor.getEditedPostAttribute( 'title' ) || '' : '';
			}, [] );

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Illustration', 'sprint-illustrations' ) },
						el( SelectControl, {
							label: __( 'Template', 'sprint-illustrations' ),
							value: attributes.template,
							options: [ { label: __( 'Automatic (from keywords)', 'sprint-illustrations' ), value: '' } ].concat( choices.templates ),
							onChange: function ( value ) {
								setAttributes( { template: value } );
							},
							__nextHasNoMarginBottom: true,
						} ),
						el( TextControl, {
							label: __( 'Keywords', 'sprint-illustrations' ),
							help: __( 'Comma-separated. Picks the template and pieces.', 'sprint-illustrations' ),
							value: attributes.keywords,
							onChange: function ( value ) {
								setAttributes( { keywords: value } );
							},
							__nextHasNoMarginBottom: true,
						} ),
						el( Button, {
							variant: 'secondary',
							disabled: ! postTitle,
							onClick: function () {
								setAttributes( { keywords: postTitle } );
							},
						}, __( 'Use post title', 'sprint-illustrations' ) ),
						el( Flex, { align: 'flex-end', style: { marginTop: '16px' } },
							el( TextControl, {
								label: __( 'Seed', 'sprint-illustrations' ),
								type: 'number',
								min: 0,
								value: String( attributes.seed ),
								onChange: function ( value ) {
									setAttributes( { seed: Math.max( 0, parseInt( value, 10 ) || 0 ) } );
								},
								__nextHasNoMarginBottom: true,
							} ),
							el( Button, {
								variant: 'secondary',
								onClick: function () {
									setAttributes( { seed: Math.floor( Math.random() * 100000 ) + 1 } );
								},
							}, __( 'Shuffle', 'sprint-illustrations' ) )
						),
						el( 'div', { style: { marginTop: '16px' } },
							el( SelectControl, {
								label: __( 'Palette', 'sprint-illustrations' ),
								value: attributes.palette,
								options: [ { label: __( 'Site palette', 'sprint-illustrations' ), value: 'site' } ].concat( choices.presets ),
								onChange: function ( value ) {
									setAttributes( { palette: value } );
								},
								__nextHasNoMarginBottom: true,
							} )
						)
					),
					el(
						PanelBody,
						{ title: __( 'Accessibility', 'sprint-illustrations' ), initialOpen: false },
						el( TextControl, {
							label: __( 'Title (alt text)', 'sprint-illustrations' ),
							help: __( 'Leave blank to use the template name.', 'sprint-illustrations' ),
							value: attributes.title,
							onChange: function ( value ) {
								setAttributes( { title: value } );
							},
							__nextHasNoMarginBottom: true,
						} ),
						el( ToggleControl, {
							label: __( 'Decorative (hide from screen readers)', 'sprint-illustrations' ),
							checked: attributes.decorative,
							onChange: function ( value ) {
								setAttributes( { decorative: value } );
							},
							__nextHasNoMarginBottom: true,
						} )
					)
				),
				el( 'div', blockProps, el( ServerSideRender, { block: 'sprint-illustrations/illustration', attributes: attributes } ) )
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp );
```

- [ ] **Step 5: `src/Integrations/Block.php`**

```php
<?php
/**
 * The sprint-illustrations/illustration block.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Integrations;

use SprintIllustrations\Plugin;

/**
 * Registers the block from blocks/illustration/block.json and feeds its editor the choices.
 */
final class Block {

	public const NAME = 'sprint-illustrations/illustration';

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
		add_action( 'init', [ $this, 'register_type' ] );
		add_action( 'enqueue_block_editor_assets', [ $this, 'editor_data' ] );
	}

	/**
	 * Register the block type (after the stylesheet on init priority 5).
	 */
	public function register_type(): void {
		register_block_type( $this->plugin->dir() . '/blocks/illustration' );
	}

	/**
	 * Template and palette choices for the editor script.
	 */
	public function editor_data(): void {
		wp_add_inline_script(
			generate_block_asset_handle( self::NAME, 'editorScript' ),
			'window.sprintIllustrationsBlock = ' . wp_json_encode( $this->plugin->editor_choices() ) . ';',
			'before'
		);
	}
}
```

Register it in `Plugin::register_hooks()` after the shortcode: `( new Block( $this ) )->register();` (with its `use` line).

- [ ] **Step 6: Verify**

Run `composer lint` and `composer test`. Then:

```bash
wp eval 'echo render_block( [ "blockName" => "sprint-illustrations/illustration", "attrs" => [ "template" => "feature-card-object", "palette" => "preset:ocean", "align" => "wide" ], "innerBlocks" => [], "innerHTML" => "", "innerContent" => [] ] );' | head -c 200
# Expected: <div class="wp-block-sprint-illustrations-illustration alignwide"><figure class="si-illustration si-template-feature-card-object"><svg …
wp eval 'var_dump( WP_Block_Type_Registry::get_instance()->is_registered( "sprint-illustrations/illustration" ), wp_style_is( "sprint-illustrations", "registered" ) );'
# Expected: bool(true) bool(true)
```

- [ ] **Step 7: Commit**

```bash
git add blocks src/Integrations/Block.php src/Plugin.php
git commit -m "feat(block): server-rendered illustration block with a no-build editor sidebar"
```

---

### Task 4: Elementor widget

**Files:**
- Create: `src/Integrations/Elementor/Loader.php`, `src/Integrations/Elementor/Widget.php`
- Modify: `src/Plugin.php` (register the loader)

**Interfaces:**
- Consumes: `Plugin::renderer()`, `Plugin::editor_choices()`, `Plugin::STYLE_HANDLE`.
- Produces: the widget named `sprint-illustration`.

- [ ] **Step 1: `src/Integrations/Elementor/Loader.php`**

```php
<?php
/**
 * Registers the Elementor widget.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Integrations\Elementor;

/**
 * Hooks the classic widget into Elementor (3.x and 4.x).
 */
final class Loader {

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'elementor/widgets/register', [ $this, 'register_widget' ] );
	}

	/**
	 * Elementor callback.
	 *
	 * @param object $widgets_manager \Elementor\Widgets_Manager.
	 */
	public function register_widget( $widgets_manager ): void {
		$widgets_manager->register( new Widget() );
	}
}
```

- [ ] **Step 2: `src/Integrations/Elementor/Widget.php`**

```php
<?php
/**
 * "Sprint Illustration" Elementor widget.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Integrations\Elementor;

use SprintIllustrations\Plugin;

/**
 * Classic widget rendered on the server in both the editor preview and the front end.
 */
final class Widget extends \Elementor\Widget_Base {

	/**
	 * Widget name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'sprint-illustration';
	}

	/**
	 * Widget title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return esc_html__( 'Sprint Illustration', 'sprint-illustrations' );
	}

	/**
	 * Panel icon.
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return 'eicon-image';
	}

	/**
	 * Panel categories.
	 *
	 * @return array<string>
	 */
	public function get_categories(): array {
		return [ 'general' ];
	}

	/**
	 * Search keywords.
	 *
	 * @return array<string>
	 */
	public function get_keywords(): array {
		return [ 'illustration', 'svg', 'sprint', 'hero', 'image' ];
	}

	/**
	 * Styles to load with the widget.
	 *
	 * @return array<string>
	 */
	public function get_style_depends(): array {
		return [ Plugin::STYLE_HANDLE ];
	}

	/**
	 * Controls.
	 */
	protected function register_controls(): void {
		$choices = Plugin::instance()->editor_choices();

		$this->start_controls_section( 'illustration', [ 'label' => esc_html__( 'Illustration', 'sprint-illustrations' ) ] );

		$this->add_control(
			'template',
			[
				'label'   => esc_html__( 'Template', 'sprint-illustrations' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => '',
				'options' => [ '' => esc_html__( 'Automatic (from keywords)', 'sprint-illustrations' ) ] + array_column( $choices['templates'], 'label', 'value' ),
			]
		);
		$this->add_control(
			'keywords',
			[
				'label'       => esc_html__( 'Keywords', 'sprint-illustrations' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'placeholder' => esc_html__( 'team, remote', 'sprint-illustrations' ),
				'label_block' => true,
			]
		);
		$this->add_control(
			'seed',
			[
				'label'   => esc_html__( 'Seed', 'sprint-illustrations' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'min'     => 0,
				'default' => 1,
			]
		);
		$this->add_control(
			'palette',
			[
				'label'   => esc_html__( 'Palette', 'sprint-illustrations' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'site',
				'options' => [ 'site' => esc_html__( 'Site palette', 'sprint-illustrations' ) ] + array_column( $choices['presets'], 'label', 'value' ),
			]
		);

		$this->end_controls_section();

		$this->start_controls_section( 'accessibility', [ 'label' => esc_html__( 'Accessibility', 'sprint-illustrations' ) ] );

		$this->add_control(
			'title',
			[
				'label'       => esc_html__( 'Title (alt text)', 'sprint-illustrations' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'description' => esc_html__( 'Leave blank to use the template name.', 'sprint-illustrations' ),
				'label_block' => true,
			]
		);
		$this->add_control(
			'decorative',
			[
				'label'        => esc_html__( 'Decorative (hide from screen readers)', 'sprint-illustrations' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Render (front end and editor preview).
	 */
	protected function render(): void {
		$settings = $this->get_settings_for_display();

		echo Plugin::instance()->renderer()->render( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitized SVG from the composer.
			[
				'template'   => (string) ( $settings['template'] ?? '' ),
				'keywords'   => (string) ( $settings['keywords'] ?? '' ),
				'seed'       => $settings['seed'] ?? 1,
				'palette'    => (string) ( $settings['palette'] ?? 'site' ),
				'title'      => (string) ( $settings['title'] ?? '' ),
				'decorative' => 'yes' === ( $settings['decorative'] ?? '' ),
			],
			current_user_can( 'edit_posts' )
		);
	}
}
```

Register it in `Plugin::register_hooks()` after the block: `( new ElementorLoader() )->register();`. It's harmless when Elementor isn't installed, because the hook never fires.

- [ ] **Step 3: Verify**

Run `composer lint` and `composer test`. Then:

```bash
wp eval 'do_action( "elementor/init" ); $w = \Elementor\Plugin::$instance->widgets_manager->get_widget_types( "sprint-illustration" ); echo $w ? get_class( $w ) : "not registered", "\n";'
# Expected: SprintIllustrations\Integrations\Elementor\Widget
```

The Elementor page in Task 5 is the full render check.

- [ ] **Step 4: Commit**

```bash
git add src/Integrations/Elementor/Loader.php src/Integrations/Elementor/Widget.php src/Plugin.php
git commit -m "feat(elementor): Sprint Illustration widget"
```

---

### Task 5: Showcase pages, front-end review, docs

**REQUIRED SUB-SKILL:** Load `frontend-design:frontend-design` for the front-end review. Its direction is set by the site's own theme; the review is about fit and polish, not a new look.

**Files:**
- Create (scratchpad, not committed): `showcase.php`, run with `wp eval-file`
- Modify: `CLAUDE.md`, this plan (execution notes)

- [ ] **Step 1: Write the showcase script** (scratchpad `showcase.php`)

```php
<?php
// Creates or updates two published showcase pages. Re-runnable: pages are found by slug.
$templates = [
	'hero-left-character'        => 'Hero, person on the left',
	'hero-right-character'       => 'Hero, person on the right',
	'two-people-collaborating'   => 'Two people collaborating',
	'centered-object-with-decor' => 'Featured object',
	'feature-card-object'        => 'Feature card',
	'empty-state'                => 'Empty state',
];
$presets   = [ 'preset:ocean', 'preset:sunset', 'preset:forest', 'preset:night', 'preset:mono', 'preset:sprint' ];
$block     = static fn( array $attrs ): string => '<!-- wp:sprint-illustrations/illustration ' . wp_json_encode( $attrs ) . ' /-->';
$column    = static fn( string $inner ): string => "<!-- wp:column -->\n<div class=\"wp-block-column\">" . $inner . "</div>\n<!-- /wp:column -->";
$columns   = static fn( array $cols ): string => "<!-- wp:columns -->\n<div class=\"wp-block-columns\">" . implode( "\n", $cols ) . "</div>\n<!-- /wp:columns -->";
$heading   = static fn( string $text, int $level = 2 ): string => '<!-- wp:heading' . ( 2 === $level ? '' : ' {"level":' . $level . '}' ) . " -->\n<h$level class=\"wp-block-heading\">" . esc_html( $text ) . "</h$level>\n<!-- /wp:heading -->";
$para      = static fn( string $text ): string => "<!-- wp:paragraph -->\n<p>" . $text . "</p>\n<!-- /wp:paragraph -->";

$content = [ $para( 'Every illustration below is composed live by the Sprint Illustrations plugin from SVG pieces, recoloured to the site palette or a preset. Left column: site palette, seed 3. Right column: a preset, seed 7.' ) ];
$i       = 0;
foreach ( $templates as $id => $label ) {
	$content[] = $heading( $label );
	$content[] = $columns(
		[
			$column( $block( [ 'template' => $id, 'seed' => 3 ] ) ),
			$column( $block( [ 'template' => $id, 'seed' => 7, 'palette' => $presets[ $i++ % count( $presets ) ] ] ) ),
		]
	);
}
$content[] = $heading( 'Picked from keywords' );
$content[] = $columns(
	[
		$column( $para( '<code>remote team</code>' ) . $block( [ 'keywords' => 'remote team', 'seed' => 2 ] ) ),
		$column( $para( '<code>startup launch</code>' ) . $block( [ 'keywords' => 'startup launch', 'seed' => 4, 'palette' => 'preset:sunset' ] ) ),
	]
);
$content[] = $heading( 'Shortcode' );
$content[] = $para( '<code>[sprint_illustration keywords="coffee break" palette="preset:ocean" seed="5"]</code>' );
$content[] = "<!-- wp:shortcode -->\n[sprint_illustration keywords=\"coffee break\" palette=\"preset:ocean\" seed=\"5\"]\n<!-- /wp:shortcode -->";

$upsert = static function ( string $slug, array $post ): int {
	$existing = get_page_by_path( $slug, OBJECT, 'page' );
	$post    += [ 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => $slug ];
	if ( $existing ) {
		$post['ID'] = $existing->ID;
	}
	return (int) wp_insert_post( wp_slash( $post ), true );
};

$showcase = $upsert(
	'sprint-illustrations-showcase',
	[
		'post_title'   => 'Sprint Illustrations — Showcase',
		'post_content' => implode( "\n\n", $content ),
	]
);

$widget   = static fn( string $id, array $settings ): array => [ 'id' => $id, 'elType' => 'widget', 'widgetType' => 'sprint-illustration', 'settings' => $settings, 'elements' => [] ];
$data     = [
	[
		'id'       => 'si0cont',
		'elType'   => 'container',
		'settings' => [ 'content_width' => 'boxed', 'flex_direction' => 'column' ],
		'elements' => [
			[ 'id' => 'si0head', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => [ 'title' => 'Sprint Illustrations in Elementor', 'header_size' => 'h1' ], 'elements' => [] ],
			$widget( 'si0wid1', [ 'template' => 'hero-right-character', 'seed' => 5, 'palette' => 'site' ] ),
			[
				'id'       => 'si0row',
				'elType'   => 'container',
				'isInner'  => true,
				'settings' => [ 'content_width' => 'full', 'flex_direction' => 'row', 'flex_wrap' => 'wrap' ],
				'elements' => [
					$widget( 'si0wid2', [ 'template' => 'two-people-collaborating', 'seed' => 3, 'palette' => 'preset:ocean', '_flex_size' => 'grow' ] ),
					$widget( 'si0wid3', [ 'template' => 'feature-card-object', 'seed' => 8, 'palette' => 'preset:sunset', '_flex_size' => 'grow' ] ),
				],
			],
		],
	],
];
$elementor = $upsert(
	'sprint-illustrations-elementor',
	[
		'post_title'   => 'Sprint Illustrations — Elementor',
		'post_content' => '',
	]
);
update_post_meta( $elementor, '_elementor_edit_mode', 'builder' );
update_post_meta( $elementor, '_elementor_template_type', 'wp-page' );
update_post_meta( $elementor, '_elementor_version', ELEMENTOR_VERSION );
update_post_meta( $elementor, '_wp_page_template', 'elementor_header_footer' );
update_post_meta( $elementor, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
\Elementor\Plugin::$instance->files_manager->clear_cache();

echo 'showcase ', $showcase, ' ', get_permalink( $showcase ), "\n";
echo 'elementor ', $elementor, ' ', get_permalink( $elementor ), "\n";
```

- [ ] **Step 2: Run it**

Run: `wp eval-file showcase.php`
Expected: two lines with the page IDs and permalinks.

- [ ] **Step 3: Front-end review**

Open both permalinks while logged out, at 1440 px and 390 px, and take full-page screenshots. Check:
- Every block, widget and shortcode renders a `figure.si-illustration`, and the pages contain no `si-illustration-error` and no "could not be rendered" comment.
- There's no horizontal overflow, and the illustrations fit their columns.
- Every non-decorative SVG has a `<title>`, and all element IDs are unique on the page.
- The two-column rows stack on mobile.

Fix anything that fails in `assets/front/illustration.css` or the showcase script.

- [ ] **Step 4: Docs**

In `CLAUDE.md`:
- Add the shortcode, block and widget names to Commands.
- Add a **Placement** paragraph: `Render\Renderer` (pure) is the single entry point, and each surface passes `current_user_can( 'edit_posts' )` as `$show_errors`. The block editor script is plain JS (`blocks/illustration/editor.js`, hand-written `editor.asset.php`). The Elementor widget is a classic `Widget_Base` with no `content_template()`.
- Point the phase plans line at this plan.

- [ ] **Step 5: Full suite and commit**

Run: `composer test` and `composer lint` (both clean).

```bash
git add CLAUDE.md docs/superpowers/plans/2026-09-29-phase-4-placement.md
git commit -m "docs: phase 4 placement in CLAUDE.md; plan execution notes"
```

## Test instructions (for the user)

1. Visit the two showcase pages. The IDs and links are printed by Task 5 Step 2 and reported at the end.
2. In the block editor, add **Sprint Illustration** to a post. Change the template, keywords and palette, press **Use post title** and **Shuffle**, and watch the preview.
3. In Elementor, search for **Sprint Illustration** in the widget panel, drop it in, and change its controls.
4. Try `[sprint_illustration keywords="team"]` in any post.
5. To remove the showcase pages: `wp post delete <id> --force`.

## Execution notes

- **Results on the Local site:** Showcase page 411 at `/sprint-illustrations-showcase/` and Elementor page 412 at `/sprint-illustrations-elementor/`, both published. Checked logged out at 1440 px and 390 px:
  - 15 and 3 figures.
  - No error notices, no silent-failure comments, no duplicate IDs, no horizontal overflow.
  - Every SVG has a title, and the stylesheet loads.
- **Fixed during review (showcase script only):**
  - WordPress runs shortcodes inside `<code>`, so the example text rendered a second illustration. The brackets are now written as `&#91;`/`&#93;`.
  - The Elementor hero is now a real two-column hero section: heading and text on the left (45%), the widget on the right (50%, 100% on mobile), with copy that doesn't depend on layout.
- `phpcs.xml.dist` gains `<file>blocks</file>` in Task 3, not Task 1, because PHPCS errors on a missing directory.
- The widget's multi-line `echo` needed its markup in a variable first. A `phpcs:ignore` only covers its own line.
- **Not automated (needs login):** inserting the block in the editor and the widget in Elementor, and checking that the controls update the preview.
