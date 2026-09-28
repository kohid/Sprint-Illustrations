<?php
/**
 * Settings page: palette source, presets, colours, Elementor mapping, AI suggestions, cache.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Admin;

use SprintIllustrations\Ai\AnthropicClient;
use SprintIllustrations\Ai\Settings as AiSettings;
use SprintIllustrations\Compose\CompositionException;
use SprintIllustrations\Compose\SceneSpec;
use SprintIllustrations\Integrations\Elementor\ColorSource;
use SprintIllustrations\Palette\ElementorMapping;
use SprintIllustrations\Palette\Palette;
use SprintIllustrations\Palette\PaletteSettings;
use SprintIllustrations\Plugin;
use SprintIllustrations\Rest\SuggestController;
use SprintIllustrations\Selection\AiFailure;
use SprintIllustrations\Selection\AiRequest;
use SprintIllustrations\Settings\SitePalette;

/**
 * Server-rendered form (Settings API) with a live preview over admin-ajax.
 */
final class SettingsPage {

	public const CAPABILITY = 'manage_options';

	public const PREVIEW_TEMPLATE = 'hero-left-character';

	public const PREVIEW_SEED = 3;

	private const GROUP = 'sprint_illustrations';

	private const AJAX_ACTION = 'sprint_illustrations_preview';

	private const PURGE_ACTION = 'sprint_illustrations_purge';

	private const AI_TEST_ACTION = 'sprint_illustrations_ai_test';

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
		add_action( 'admin_init', [ $this, 'register_setting' ] );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, [ $this, 'preview' ] );
		add_action( 'admin_post_' . self::PURGE_ACTION, [ $this, 'purge' ] );
		add_action( 'admin_post_' . self::AI_TEST_ACTION, [ $this, 'test_ai' ] );
	}

	/**
	 * Settings API registration.
	 */
	public function register_setting(): void {
		register_setting(
			self::GROUP,
			SitePalette::OPTION,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize' ],
				'default'           => [],
				'show_in_rest'      => false,
			]
		);
		register_setting(
			self::GROUP,
			AiSettings::OPTION,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this->plugin->ai(), 'sanitize' ],
				'default'           => [],
				'show_in_rest'      => false,
			]
		);
	}

	/**
	 * Sanitize submitted settings. For the Elementor source, colours are read from Elementor now.
	 *
	 * @param mixed $input Submitted value (already unslashed by options.php).
	 * @return array<string, mixed>
	 */
	public function sanitize( mixed $input ): array {
		$input     = is_array( $input ) ? $input : [];
		$elementor = is_array( $input['elementor'] ?? null ) ? $input['elementor'] : [];
		$text      = static fn( mixed $values ): array => array_map( static fn( $value ) => sanitize_text_field( (string) $value ), is_array( $values ) ? array_filter( $values, 'is_scalar' ) : [] );

		$raw = [
			'source'    => sanitize_key( (string) ( $input['source'] ?? '' ) ),
			'preset'    => sanitize_key( (string) ( $input['preset'] ?? '' ) ),
			'colors'    => $text( $input['colors'] ?? [] ),
			'skin'      => array_values( array_filter( $text( $input['skin'] ?? [] ) ) ),
			'hair'      => array_values( array_filter( $text( $input['hair'] ?? [] ) ) ),
			'elementor' => [
				'map'  => array_filter( $text( $elementor['map'] ?? [] ) ),
				'sync' => ! empty( $elementor['sync'] ),
			],
		];

		if ( 'elementor' === $raw['source'] && ColorSource::available() ) {
			$source = new ColorSource();
			$result = ( new ElementorMapping( $source->items() ) )->apply( $raw['elementor']['map'], $raw['colors'] );

			$raw['colors'] = $result['colors'];

			if ( $source->failed() ) {
				add_settings_error( SitePalette::OPTION, 'elementor', __( 'Elementor colours couldn\'t be read. The colours shown were saved instead.', 'sprint-illustrations' ) );
			}
		}

		return $this->plugin->site_palette()->normalize( $raw );
	}

	/**
	 * AJAX: render the stage for unsaved form values (uncached).
	 */
	public function preview(): void {
		check_ajax_referer( self::AJAX_ACTION, 'nonce' );

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( null, 403 );
		}

		// Values are validated by Palette::from_array() (hex only); nothing is stored.
		$input   = isset( $_POST['palette'] ) && is_array( $_POST['palette'] ) ? wp_unslash( $_POST['palette'] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$seed    = isset( $_POST['seed'] ) ? absint( $_POST['seed'] ) : self::PREVIEW_SEED;
		$palette = Palette::from_array(
			( is_array( $input['colors'] ?? null ) ? $input['colors'] : [] ) + [
				'skin' => is_array( $input['skin'] ?? null ) ? array_values( $input['skin'] ) : null,
				'hair' => is_array( $input['hair'] ?? null ) ? array_values( $input['hair'] ) : null,
			]
		);

		try {
			$svg = $this->art( $palette, $seed, 'si-stage', false );
		} catch ( CompositionException $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ], 500 );
		}

		wp_send_json_success(
			[
				'svg'      => $svg,
				'warnings' => $palette->warnings(),
				'variants' => $this->variants( $palette ),
			]
		);
	}

	/**
	 * Purge the cache (admin-post handler).
	 */
	public function purge(): void {
		check_admin_referer( self::PURGE_ACTION );

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'sprint-illustrations' ), 403 );
		}

		$count = $this->plugin->cache()->purge();

		/* translators: %d: number of cached files removed. */
		Notices::add( sprintf( _n( 'Removed %d cached illustration.', 'Removed %d cached illustrations.', $count, 'sprint-illustrations' ), $count ), 'success' );

		wp_safe_redirect( admin_url( 'admin.php?page=' . Menu::SETTINGS_SLUG ) );
		exit;
	}

	/**
	 * Test connection (admin-post handler): one tiny request with the saved settings.
	 */
	public function test_ai(): void {
		check_admin_referer( self::AI_TEST_ACTION );

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'sprint-illustrations' ), 403 );
		}

		$result = $this->ai_test_result();
		Notices::add( $result['text'], $result['ok'] ? 'success' : 'error' );

		wp_safe_redirect( admin_url( 'admin.php?page=' . Menu::SETTINGS_SLUG ) );
		exit;
	}

	/**
	 * Ping Claude with the saved key, workspace and model.
	 *
	 * @param AnthropicClient|null $client Client (injectable for verification).
	 * @return array{ok: bool, text: string}
	 */
	public function ai_test_result( ?AnthropicClient $client = null ): array {
		$ai  = $this->plugin->ai();
		$key = $ai->key();

		if ( null === $key ) {
			return [
				'ok'   => false,
				'text' => __( 'Add an API key first.', 'sprint-illustrations' ),
			];
		}

		$response = ( $client ?? new AnthropicClient() )->messages( AiRequest::ping( $ai->model() ), $key, $ai->workspace() );

		if ( is_wp_error( $response ) ) {
			return [
				'ok'   => false,
				'text' => SuggestController::failure_text( AiFailure::reason( (string) $response->get_error_code(), $response->get_error_message() ), $response->get_error_message() ),
			];
		}

		return [
			'ok'   => true,
			/* translators: %s: model name. */
			'text' => sprintf( __( 'Connected to %s.', 'sprint-illustrations' ), preg_replace( '/\s*\(.*\)$/', '', AiRequest::MODELS[ $ai->model() ] ) ),
		];
	}

	/**
	 * Enqueue the page's CSS and JS.
	 */
	public function enqueue(): void {
		wp_enqueue_style( 'sprint-illustrations-settings', plugins_url( 'assets/admin/settings.css', SPRINT_ILLUSTRATIONS_FILE ), [], SPRINT_ILLUSTRATIONS_VERSION );
		wp_enqueue_script( 'sprint-illustrations-settings', plugins_url( 'assets/admin/settings.js', SPRINT_ILLUSTRATIONS_FILE ), [], SPRINT_ILLUSTRATIONS_VERSION, true );
		wp_localize_script(
			'sprint-illustrations-settings',
			'sprintIllustrationsSettings',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::AJAX_ACTION ),
				'seed'    => self::PREVIEW_SEED,
				'i18n'    => [
					'unavailable' => __( 'Preview unavailable. Your changes can still be saved.', 'sprint-illustrations' ),
					'updating'    => __( 'Updating preview…', 'sprint-illustrations' ),
				],
			]
		);
	}

	/**
	 * Render the page.
	 */
	public function render(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'sprint-illustrations' ) );
		}

		$settings  = $this->plugin->site_palette()->settings();
		$palette   = $this->plugin->site_palette()->palette();
		$elementor = ColorSource::available();
		$source    = new ColorSource();
		$mapping   = new ElementorMapping( $elementor ? $source->items() : [] );
		$map       = $settings['elementor']['map'] ? $settings['elementor']['map'] : $mapping->suggest();

		echo '<div class="wrap si-settings" data-si-settings>';
		echo '<h1>' . esc_html__( 'Sprint Illustrations', 'sprint-illustrations' ) . '</h1>';
		settings_errors( SitePalette::OPTION );

		echo '<div class="si-settings__layout"><div class="si-settings__main">';
		echo '<form method="post" action="options.php" data-si-form>';
		settings_fields( self::GROUP );

		$this->render_source( $settings, $elementor );
		$this->render_presets( $settings );
		$this->render_colors( $palette );
		$this->render_tones( $settings );

		if ( $elementor ) {
			$this->render_elementor( $settings, $mapping, $map, $source->failed() );
		}

		$this->render_ai();

		submit_button( __( 'Save changes', 'sprint-illustrations' ) );
		echo '</form>';

		$this->render_cache();
		echo '</div>';

		$this->render_stage( $palette );
		echo '</div></div>';
	}

	/**
	 * Source radios.
	 *
	 * @param array<string, mixed> $settings  Settings.
	 * @param bool                 $elementor Elementor available.
	 */
	private function render_source( array $settings, bool $elementor ): void {
		$labels = [
			'preset'    => __( 'Preset', 'sprint-illustrations' ),
			'custom'    => __( 'Custom', 'sprint-illustrations' ),
			'elementor' => __( 'Elementor', 'sprint-illustrations' ),
		];

		echo '<fieldset class="si-panel si-source"><legend class="si-panel__title">' . esc_html__( 'Palette source', 'sprint-illustrations' ) . '</legend>';

		foreach ( PaletteSettings::SOURCES as $value ) {
			if ( 'elementor' === $value && ! $elementor ) {
				continue;
			}

			printf(
				'<label class="si-source__option"><input type="radio" name="%1$s[source]" value="%2$s" %3$s data-si-source> %4$s</label>',
				esc_attr( SitePalette::OPTION ),
				esc_attr( $value ),
				checked( $settings['source'], $value, false ),
				esc_html( $labels[ $value ] )
			);
		}

		echo '<p class="description">' . esc_html__( 'Editing any colour switches the source to Custom.', 'sprint-illustrations' ) . '</p></fieldset>';
	}

	/**
	 * Preset cards, each showing the preview scene in that preset.
	 *
	 * @param array<string, mixed> $settings Settings.
	 */
	private function render_presets( array $settings ): void {
		echo '<fieldset class="si-panel"><legend class="si-panel__title">' . esc_html__( 'Presets', 'sprint-illustrations' ) . '</legend><div class="si-presets">';

		foreach ( $this->plugin->presets()->all() as $id => $preset ) {
			$colors = array_intersect_key( $preset['palette']->to_array(), array_flip( Palette::SLOTS ) );

			printf(
				'<label class="si-preset"><input type="radio" class="si-preset__input" name="%1$s[preset]" value="%2$s" %3$s data-si-preset data-si-colors="%4$s"><span class="si-preset__art">%5$s</span><span class="si-preset__name">%6$s</span></label>',
				esc_attr( SitePalette::OPTION ),
				esc_attr( $id ),
				checked( $settings['preset'], $id, false ),
				esc_attr( (string) wp_json_encode( $colors ) ),
				$this->art( $preset['palette'], self::PREVIEW_SEED, 'si-card-' . $id, true ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitized SVG from the composer.
				esc_html( $preset['label'] )
			);
		}

		echo '</div></fieldset>';
	}

	/**
	 * Colour slot rows.
	 *
	 * @param Palette $palette Current palette.
	 */
	private function render_colors( Palette $palette ): void {
		$labels   = [
			'primary'    => __( 'Primary', 'sprint-illustrations' ),
			'secondary'  => __( 'Secondary', 'sprint-illustrations' ),
			'accent'     => __( 'Accent', 'sprint-illustrations' ),
			'neutral'    => __( 'Neutral', 'sprint-illustrations' ),
			'background' => __( 'Background', 'sprint-illustrations' ),
			'outline'    => __( 'Outline', 'sprint-illustrations' ),
		];
		$warnings = $palette->warnings();
		$variants = $this->variants( $palette );

		echo '<fieldset class="si-panel"><legend class="si-panel__title">' . esc_html__( 'Colours', 'sprint-illustrations' ) . '</legend><div class="si-slots">';

		foreach ( Palette::SLOTS as $slot ) {
			$hex = (string) $palette->resolve( $slot );
			$id  = 'si-color-' . $slot;

			printf(
				'<div class="si-slot" data-si-slot="%1$s">'
				. '<input type="color" class="si-slot__picker" value="%2$s" data-si-picker="%3$s" aria-label="%4$s">'
				. '<label class="si-slot__label" for="%3$s">%5$s</label>'
				. '<input type="text" class="si-slot__hex code" id="%3$s" name="%6$s[colors][%1$s]" value="%2$s" pattern="#[0-9a-fA-F]{6}" maxlength="7" spellcheck="false" data-si-hex="%1$s">'
				. '<span class="si-slot__chips"><span class="si-chip" data-si-variant="light" title="%7$s" style="background-color:%7$s"></span><span class="si-chip" data-si-variant="dark" title="%8$s" style="background-color:%8$s"></span></span>'
				. '<p class="si-slot__warning" data-si-warning>%9$s</p>'
				. '</div>',
				esc_attr( $slot ),
				esc_attr( $hex ),
				esc_attr( $id ),
				/* translators: %s: colour slot name, e.g. "Primary". */
				esc_attr( sprintf( __( '%s colour picker', 'sprint-illustrations' ), $labels[ $slot ] ) ),
				esc_html( $labels[ $slot ] ),
				esc_attr( SitePalette::OPTION ),
				esc_attr( $variants[ $slot ]['light'] ?? $hex ),
				esc_attr( $variants[ $slot ]['dark'] ?? $hex ),
				esc_html( $warnings[ $slot ] ?? '' )
			);
		}//end foreach

		echo '</div><p class="description">' . esc_html__( 'Light and dark shades are derived automatically. Low contrast is a warning only; it never blocks saving.', 'sprint-illustrations' ) . '</p></fieldset>';
	}

	/**
	 * Skin and hair tone fields (six each; blank removes).
	 *
	 * @param array<string, mixed> $settings Settings.
	 */
	private function render_tones( array $settings ): void {
		$groups = [
			'skin' => __( 'Skin tones', 'sprint-illustrations' ),
			'hair' => __( 'Hair colours', 'sprint-illustrations' ),
		];

		echo '<fieldset class="si-panel"><legend class="si-panel__title">' . esc_html__( 'People', 'sprint-illustrations' ) . '</legend>';
		echo '<p class="description">' . esc_html__( 'Each person in a scene gets one of these, chosen by the seed. Leave a field blank to remove it. If every field in a row is blank, the built-in colours are used.', 'sprint-illustrations' ) . '</p>';

		foreach ( $groups as $group => $label ) {
			echo '<div class="si-tones"><span class="si-tones__label">' . esc_html( $label ) . '</span><div class="si-tones__list">';

			for ( $i = 0; $i < PaletteSettings::MAX_TONES; $i++ ) {
				$hex = $settings[ $group ][ $i ] ?? '';
				$id  = 'si-' . $group . '-' . $i;

				printf(
					'<span class="si-tone"><input type="color" class="si-slot__picker" value="%1$s" data-si-picker="%2$s" aria-label="%3$s"><input type="text" class="si-tone__hex code" id="%2$s" name="%4$s[%5$s][]" value="%6$s" pattern="#[0-9a-fA-F]{6}" maxlength="7" spellcheck="false" placeholder="#rrggbb" aria-label="%3$s" data-si-list="%5$s"></span>',
					esc_attr( '' !== $hex ? $hex : '#ffffff' ),
					esc_attr( $id ),
					/* translators: 1: "Skin tones" or "Hair colours", 2: position 1-6. */
					esc_attr( sprintf( __( '%1$s %2$d', 'sprint-illustrations' ), $label, $i + 1 ) ),
					esc_attr( SitePalette::OPTION ),
					esc_attr( $group ),
					esc_attr( $hex )
				);
			}

			echo '</div></div>';
		}//end foreach

		echo '</fieldset>';
	}

	/**
	 * Elementor mapping.
	 *
	 * @param array<string, mixed>  $settings Settings.
	 * @param ElementorMapping      $mapping  Mapping.
	 * @param array<string, string> $map      Selected slot => item ID.
	 * @param bool                  $failed   Reading Elementor failed.
	 */
	private function render_elementor( array $settings, ElementorMapping $mapping, array $map, bool $failed ): void {
		$choices = $mapping->choices();

		echo '<fieldset class="si-panel"><legend class="si-panel__title">' . esc_html__( 'Elementor colours', 'sprint-illustrations' ) . '</legend>';

		if ( $failed ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Elementor colours couldn\'t be read.', 'sprint-illustrations' ) . '</p></div>';
		} elseif ( ! $choices ) {
			echo '<p class="description">' . esc_html__( 'This site has no Elementor global colours yet.', 'sprint-illustrations' ) . '</p>';
		}

		echo '<p class="description">' . esc_html__( 'Choose which Elementor colour fills each slot. Colours are copied when you save with Elementor as the source.', 'sprint-illustrations' ) . '</p>';
		echo '<table class="si-map"><tbody>';

		foreach ( Palette::SLOTS as $slot ) {
			printf(
				'<tr><th scope="row"><label for="si-map-%1$s">%2$s</label></th><td><select id="si-map-%1$s" name="%3$s[elementor][map][%1$s]" data-si-map="%1$s"><option value="">%4$s</option>',
				esc_attr( $slot ),
				esc_html( ucfirst( $slot ) ),
				esc_attr( SitePalette::OPTION ),
				esc_html__( '— Keep current colour —', 'sprint-illustrations' )
			);

			foreach ( $choices as $choice ) {
				printf(
					'<option value="%1$s" %2$s %3$s data-si-hex="%4$s">%5$s</option>',
					esc_attr( $choice['id'] ),
					selected( $map[ $slot ] ?? '', $choice['id'], false ),
					disabled( null === $choice['hex'], true, false ),
					esc_attr( (string) $choice['hex'] ),
					esc_html(
						null === $choice['hex']
							/* translators: %s: Elementor colour name. */
							? sprintf( __( '%s (not importable)', 'sprint-illustrations' ), $choice['label'] )
							: $choice['label'] . ' · ' . $choice['hex']
					)
				);
			}

			echo '</select></td></tr>';
		}//end foreach

		echo '</tbody></table>';
		printf(
			'<label class="si-sync"><input type="checkbox" name="%1$s[elementor][sync]" value="1" %2$s> %3$s</label>',
			esc_attr( SitePalette::OPTION ),
			checked( $settings['elementor']['sync'], true, false ),
			esc_html__( 'Keep synced: update the palette when Elementor colours change', 'sprint-illustrations' )
		);
		echo '</fieldset>';
	}

	/**
	 * Cache status and purge (a separate form).
	 */
	private function render_cache(): void {
		$cache = $this->plugin->cache();
		$stats = $cache->stats();

		echo '<section class="si-panel si-cache"><h2 class="si-panel__title">' . esc_html__( 'Illustration cache', 'sprint-illustrations' ) . '</h2>';

		if ( ! $cache->writable() ) {
			printf(
				'<div class="notice notice-warning inline"><p>%s <code>%s</code></p></div>',
				esc_html__( 'The illustration cache folder isn\'t writable, so illustrations are recomposed on every request.', 'sprint-illustrations' ),
				esc_html( $cache->directory() )
			);
		}

		printf(
			'<form method="post" action="%1$s" class="si-cache__form"><input type="hidden" name="action" value="%2$s">%3$s<p>%4$s</p>%5$s</form>',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr( self::PURGE_ACTION ),
			wp_nonce_field( self::PURGE_ACTION, '_wpnonce', true, false ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core markup.
			esc_html(
				sprintf(
					/* translators: 1: file count, 2: human-readable size. */
					_n( '%1$d cached illustration, %2$s.', '%1$d cached illustrations, %2$s.', $stats['files'], 'sprint-illustrations' ),
					$stats['files'],
					size_format( $stats['bytes'] ) ? size_format( $stats['bytes'] ) : '0 B'
				)
			),
			get_submit_button( __( 'Purge cache', 'sprint-illustrations' ), 'secondary', 'submit', false ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core markup.
		);

		echo '</section>';
	}

	/**
	 * Sticky live preview.
	 *
	 * @param Palette $palette Saved palette.
	 */
	private function render_stage( Palette $palette ): void {
		printf(
			'<aside class="si-stage" aria-labelledby="si-stage-title"><h2 class="si-stage__title" id="si-stage-title">%1$s</h2><div class="si-stage__art" data-si-stage>%2$s</div><div class="si-stage__bar"><button type="button" class="button" data-si-shuffle>%3$s</button><p class="si-stage__status" data-si-status aria-live="polite"></p></div></aside>',
			esc_html__( 'Preview', 'sprint-illustrations' ),
			$this->art( $palette, self::PREVIEW_SEED, 'si-stage', true ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitized SVG from the composer.
			esc_html__( 'Shuffle', 'sprint-illustrations' )
		);
	}

	/**
	 * Compose the preview scene.
	 *
	 * @param Palette $palette  Palette.
	 * @param int     $seed     Seed.
	 * @param string  $instance Instance ID.
	 * @param bool    $cached   Use the caching composer (fixed palettes only).
	 * @return string Sanitized SVG, or "" when composition fails on page render.
	 * @throws CompositionException When uncached composition fails (AJAX reports it).
	 */
	private function art( Palette $palette, int $seed, string $instance, bool $cached ): string {
		$spec = SceneSpec::from_array(
			[
				'template'   => self::PREVIEW_TEMPLATE,
				'seed'       => $seed,
				'decorative' => true,
			]
		);

		if ( ! $cached ) {
			return $this->plugin->services()->composer->compose( $spec, $palette )->with_instance_id( $instance );
		}

		try {
			return $this->plugin->composer()->compose( $spec, $palette )->with_instance_id( $instance );
		} catch ( CompositionException $e ) {
			return '';
		}
	}

	/**
	 * Derived shades per slot.
	 *
	 * @param Palette $palette Palette.
	 * @return array<string, array{light: string, dark: string}>
	 */
	private function variants( Palette $palette ): array {
		$variants = [];
		foreach ( Palette::SLOTS as $slot ) {
			$variants[ $slot ] = [
				'light' => (string) $palette->resolve( $slot, 'light' ),
				'dark'  => (string) $palette->resolve( $slot, 'dark' ),
			];
		}

		return $variants;
	}

	/**
	 * AI suggestions panel. The key field is write-only.
	 */
	private function render_ai(): void {
		$ai       = $this->plugin->ai();
		$settings = $ai->settings();
		$name     = esc_attr( AiSettings::OPTION );
		$has_key  = $ai->has_key();

		echo '<fieldset class="si-panel si-ai"><legend class="si-panel__title">' . esc_html__( 'AI suggestions', 'sprint-illustrations' ) . '</legend>';
		echo '<p class="si-ai__intro">' . esc_html__( 'Suggest reads a description or your page text and picks a template, keywords and alt text. Without Claude it uses keyword matching.', 'sprint-illustrations' ) . '</p>';

		printf(
			'<label class="si-ai__toggle"><input type="checkbox" name="%1$s[enabled]" value="1" %2$s %3$s> %4$s</label>',
			$name, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
			checked( $settings['enabled'], true, false ),
			disabled( $has_key, false, false ),
			esc_html__( 'Use Claude for Suggest', 'sprint-illustrations' )
		);
		if ( ! $has_key ) {
			echo '<p class="description">' . esc_html__( 'Add an API key to turn this on.', 'sprint-illustrations' ) . '</p>';
		}

		echo '<div class="si-ai__row"><label class="si-ai__label" for="si-ai-key">' . esc_html__( 'Anthropic API key', 'sprint-illustrations' ) . '</label><div class="si-ai__field">';
		if ( $ai->from_constant() ) {
			echo '<p class="si-ai__status">' . esc_html__( 'Using the key from wp-config.php.', 'sprint-illustrations' ) . '</p>';
		} else {
			printf(
				'<input type="password" id="si-ai-key" name="%1$s[key]" value="" autocomplete="new-password" spellcheck="false" class="regular-text" placeholder="%2$s">',
				$name, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
				esc_attr( $has_key ? __( 'Paste a new key to replace it', 'sprint-illustrations' ) : 'sk-ant-…' )
			);
			if ( $has_key ) {
				echo '<p class="si-ai__status"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> ' . esc_html__( 'A key is saved.', 'sprint-illustrations' ) . '</p>';
				printf(
					'<label class="si-ai__remove"><input type="checkbox" name="%1$s[remove_key]" value="1"> %2$s</label>',
					$name, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
					esc_html__( 'Remove the saved key', 'sprint-illustrations' )
				);
			}
		}
		echo '</div></div>';

		echo '<div class="si-ai__row"><label class="si-ai__label" for="si-ai-workspace">' . esc_html__( 'Workspace ID', 'sprint-illustrations' ) . '</label><div class="si-ai__field">';
		if ( $ai->workspace_from_constant() ) {
			echo '<p class="si-ai__status">' . esc_html__( 'Using the workspace ID from wp-config.php.', 'sprint-illustrations' ) . '</p>';
		} else {
			printf(
				'<input type="text" id="si-ai-workspace" name="%1$s[workspace]" value="%2$s" spellcheck="false" class="regular-text code" placeholder="wrkspc_…" aria-describedby="si-ai-workspace-help">',
				$name, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
				esc_attr( $settings['workspace'] )
			);
			echo '<p class="description" id="si-ai-workspace-help">' . esc_html__( 'Only needed for API keys shared across workspaces. Find it in Claude Console → Settings → Workspaces.', 'sprint-illustrations' ) . '</p>';
		}
		echo '</div></div>';

		echo '<div class="si-ai__row"><label class="si-ai__label" for="si-ai-model">' . esc_html__( 'Model', 'sprint-illustrations' ) . '</label><div class="si-ai__field">';
		printf( '<select id="si-ai-model" name="%s[model]">', $name ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
		foreach ( AiRequest::MODELS as $id => $label ) {
			printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $id ), selected( $settings['model'], $id, false ), esc_html( $label ) );
		}
		echo '</select></div></div>';

		if ( $has_key ) {
			printf(
				'<p class="si-ai__test"><a class="button" href="%1$s">%2$s</a> <span class="description">%3$s</span></p>',
				esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=' . self::AI_TEST_ACTION ), self::AI_TEST_ACTION ) ),
				esc_html__( 'Test connection', 'sprint-illustrations' ),
				esc_html__( 'Uses the saved key, workspace and model. Save changes first.', 'sprint-illustrations' )
			);
		}

		echo '<p class="description si-ai__privacy">' . esc_html__( 'Suggest sends the text you choose, plus your template names and tags, to Anthropic. Nothing is sent when visitors view pages.', 'sprint-illustrations' ) . '</p>';
		echo '</fieldset>';
	}
}
