<?php
/**
 * WordPress entry point and hook wiring.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations;

use SprintIllustrations\Admin\Menu;
use SprintIllustrations\Admin\Notices;
use SprintIllustrations\Cache\CachingComposer;
use SprintIllustrations\Cache\SvgCache;
use SprintIllustrations\Cli\CacheCommand;
use SprintIllustrations\Cli\Command;
use SprintIllustrations\Compose\ComposesSvg;
use SprintIllustrations\Integrations\Block;
use SprintIllustrations\Integrations\Elementor\ColorSource;
use SprintIllustrations\Integrations\Elementor\Sync;
use SprintIllustrations\Integrations\Shortcode;
use SprintIllustrations\Palette\Palette;
use SprintIllustrations\Palette\PaletteSettings;
use SprintIllustrations\Palette\PresetRepository;
use SprintIllustrations\Render\Renderer;
use SprintIllustrations\Settings\SitePalette;

/**
 * Singleton that owns the service container and registers hooks.
 */
final class Plugin {

	/**
	 * Daily cache clean-up event.
	 */
	public const CRON_HOOK = 'sprint_illustrations_cache_gc';

	/**
	 * Entries unused for this long are deleted.
	 */
	public const CACHE_MAX_AGE = 30 * DAY_IN_SECONDS;

	/**
	 * Front-end stylesheet handle.
	 */
	public const STYLE_HANDLE = 'sprint-illustrations';

	/**
	 * Instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Lazily built services.
	 *
	 * @var Services|null
	 */
	private ?Services $services = null;

	/**
	 * Presets.
	 *
	 * @var PresetRepository|null
	 */
	private ?PresetRepository $presets = null;

	/**
	 * Site palette option.
	 *
	 * @var SitePalette|null
	 */
	private ?SitePalette $site_palette = null;

	/**
	 * Disk cache.
	 *
	 * @var SvgCache|null
	 */
	private ?SvgCache $cache = null;

	/**
	 * Caching composer.
	 *
	 * @var ComposesSvg|null
	 */
	private ?ComposesSvg $composer = null;

	/**
	 * Placement renderer.
	 *
	 * @var Renderer|null
	 */
	private ?Renderer $renderer = null;

	/**
	 * Boot on plugins_loaded.
	 *
	 * @return self
	 */
	public static function boot(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->register_hooks();
		}

		return self::$instance;
	}

	/**
	 * Current instance (boots if needed).
	 *
	 * @return self
	 */
	public static function instance(): self {
		return self::boot();
	}

	/**
	 * Service container, built on first use.
	 *
	 * @return Services
	 */
	public function services(): Services {
		if ( null === $this->services ) {
			$this->services = Services::create( $this->dir(), $this->user_manifests() );
		}

		return $this->services;
	}

	/**
	 * Bundled presets.
	 *
	 * @return PresetRepository
	 */
	public function presets(): PresetRepository {
		return $this->presets ??= PresetRepository::bundled();
	}

	/**
	 * Site palette option.
	 *
	 * @return SitePalette
	 */
	public function site_palette(): SitePalette {
		return $this->site_palette ??= new SitePalette( new PaletteSettings( $this->presets() ) );
	}

	/**
	 * Disk cache in uploads/sprint-illustrations/cache.
	 *
	 * @return SvgCache
	 */
	public function cache(): SvgCache {
		return $this->cache ??= new SvgCache( $this->user_library_dir() . '/cache' );
	}

	/**
	 * Composer that serves repeats from the cache.
	 *
	 * @return ComposesSvg
	 */
	public function composer(): ComposesSvg {
		return $this->composer ??= new CachingComposer(
			$this->services()->composer,
			$this->cache(),
			$this->services()->sanitizer,
			$this->services()->manifest->version(),
			SPRINT_ILLUSTRATIONS_VERSION
		);
	}

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

	/**
	 * Schedule the daily cache clean-up (idempotent; also runs on init for sites activated before phase 2).
	 */
	public static function activate(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Remove the scheduled clean-up.
	 */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * Cron callback.
	 */
	public function collect_garbage(): void {
		$this->cache()->collect_garbage( self::CACHE_MAX_AGE );
	}

	/**
	 * Plugin directory without trailing slash.
	 *
	 * @return string
	 */
	public function dir(): string {
		return untrailingslashit( plugin_dir_path( SPRINT_ILLUSTRATIONS_FILE ) );
	}

	/**
	 * User library root: wp-content/uploads/sprint-illustrations.
	 *
	 * @return string
	 */
	public function user_library_dir(): string {
		return trailingslashit( wp_upload_dir( null, false )['basedir'] ) . 'sprint-illustrations';
	}

	/**
	 * Extra manifest files, filterable.
	 *
	 * @return array<string>
	 */
	private function user_manifests(): array {
		/**
		 * Filter the list of additional manifest.json files merged after the bundled library.
		 *
		 * @param array<string> $paths Absolute paths.
		 */
		$paths = apply_filters( 'sprint_illustrations_library_paths', [ $this->user_library_dir() . '/manifest.json' ] );

		return array_values( array_filter( (array) $paths, 'is_string' ) );
	}

	/**
	 * Register hooks.
	 */
	private function register_hooks(): void {
		( new Sync( $this->site_palette(), new ColorSource() ) )->register();
		add_action( 'init', [ $this, 'register_assets' ], 5 );
		( new Shortcode( $this ) )->register();
		( new Block( $this ) )->register();
		add_action( 'init', [ self::class, 'activate' ] );
		add_action( self::CRON_HOOK, [ $this, 'collect_garbage' ] );

		if ( is_admin() ) {
			( new Menu( $this ) )->register();
			( new Notices() )->register();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'sprint-illustrations', new Command( $this ) );
			\WP_CLI::add_command( 'sprint-illustrations cache', new CacheCommand( $this ) );
		}
	}
}
