<?php
/**
 * Plugin updates from GitHub releases, shown on the normal Plugins page.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Update;

/**
 * Uses the "Update URI" header (host github.com) so WordPress asks this class, not wordpress.org.
 * A release is a tag `vX.Y.Z` whose `sprint-illustrations.zip` asset is built by .github/workflows/release.yml.
 * Off for a git checkout (an update would replace it) and when SPRINT_ILLUSTRATIONS_DISABLE_UPDATES is true.
 */
final class GitHubUpdater {

	public const REPO       = 'kohid/Sprint-Illustrations';
	public const ASSET      = 'sprint-illustrations.zip';
	private const CACHE     = 'sprint_illustrations_release';
	private const CACHE_TTL = 600;

	/**
	 * Plugin basename (folder/file.php).
	 *
	 * @var string
	 */
	private string $basename;

	/**
	 * Constructor.
	 *
	 * @param string $plugin_file Main plugin file.
	 * @param string $plugin_dir  Plugin folder.
	 */
	public function __construct( private string $plugin_file, private string $plugin_dir ) {
		$this->basename = plugin_basename( $plugin_file );
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		if ( ! $this->enabled() ) {
			return;
		}
		add_filter( 'update_plugins_github.com', [ $this, 'offer_update' ], 10, 3 );
		add_filter( 'plugins_api', [ $this, 'plugin_info' ], 10, 3 );
		add_filter( 'upgrader_source_selection', [ $this, 'keep_folder_name' ], 10, 4 );
		add_action( 'upgrader_process_complete', [ $this, 'forget_release' ], 10, 2 );
	}

	/**
	 * Whether updates come from GitHub for this copy of the plugin.
	 *
	 * @return bool
	 */
	public function enabled(): bool {
		if ( defined( 'SPRINT_ILLUSTRATIONS_DISABLE_UPDATES' ) && SPRINT_ILLUSTRATIONS_DISABLE_UPDATES ) {
			return false;
		}

		return ! is_dir( rtrim( wp_normalize_path( $this->plugin_dir ), '/' ) . '/.git' );
	}

	/**
	 * The update WordPress lists for this plugin (filter `update_plugins_github.com`).
	 *
	 * @param array<string,mixed>|false $update      Update data so far.
	 * @param array<string,mixed>       $plugin_data Plugin header data.
	 * @param string                    $plugin_file Plugin basename.
	 * @return array<string,mixed>|false
	 */
	public function offer_update( $update, $plugin_data, $plugin_file ) {
		unset( $plugin_data );
		if ( $plugin_file !== $this->basename ) {
			return $update;
		}
		$release = $this->latest();
		if ( null === $release ) {
			return $update;
		}

		return [
			'id'           => 'github.com/' . self::REPO,
			'slug'         => dirname( $this->basename ),
			'plugin'       => $this->basename,
			'version'      => $release->version,
			'url'          => $release->url,
			'package'      => $release->package,
			'requires'     => '6.4',
			'requires_php' => '8.1',
		];
	}

	/**
	 * The "View details" popup (filter `plugins_api`).
	 *
	 * @param false|object|array<string,mixed> $result Result so far.
	 * @param string                           $action API action.
	 * @param object                           $args   Request arguments.
	 * @return false|object|array<string,mixed>
	 */
	public function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || ! is_object( $args ) || dirname( $this->basename ) !== ( $args->slug ?? '' ) ) {
			return $result;
		}
		$release = $this->latest();
		if ( null === $release ) {
			return $result;
		}

		return (object) [
			'name'          => 'Sprint Illustrations',
			'slug'          => dirname( $this->basename ),
			'version'       => $release->version,
			'author'        => 'Sprint',
			'homepage'      => 'https://github.com/' . self::REPO,
			'requires'      => '6.4',
			'requires_php'  => '8.1',
			'download_link' => $release->package,
			'sections'      => [
				'description' => esc_html__( 'Composes flat, brand-coloured illustrations from a library of SVG pieces.', 'sprint-illustrations' ),
				'changelog'   => wp_kses_post( wpautop( esc_html( '' !== $release->notes ? $release->notes : 'See ' . $release->url ) ) ),
			],
		];
	}

	/**
	 * Make sure the update lands in the folder the plugin is installed in, whatever the zip's folder is called.
	 *
	 * @param string|\WP_Error    $source        Unpacked folder.
	 * @param string              $remote_source Temporary folder holding it.
	 * @param \WP_Upgrader        $upgrader      Upgrader.
	 * @param array<string,mixed> $hook_extra Upgrade details.
	 * @return string|\WP_Error
	 */
	public function keep_folder_name( $source, $remote_source, $upgrader, $hook_extra = [] ) {
		unset( $upgrader );
		if ( ! is_string( $source ) || ( $hook_extra['plugin'] ?? '' ) !== $this->basename ) {
			return $source;
		}
		$wanted = trailingslashit( $remote_source ) . dirname( $this->basename );
		if ( untrailingslashit( $source ) === $wanted ) {
			return $source;
		}
		global $wp_filesystem;
		if ( ! is_object( $wp_filesystem ) || ! $wp_filesystem->move( untrailingslashit( $source ), $wanted, true ) ) {
			return new \WP_Error( 'sprint_illustrations_folder', __( 'Could not prepare the Sprint Illustrations update folder.', 'sprint-illustrations' ) );
		}

		return trailingslashit( $wanted );
	}

	/**
	 * Drop the cached release after this plugin was updated.
	 *
	 * @param \WP_Upgrader        $upgrader Upgrader.
	 * @param array<string,mixed> $extra    Upgrade details.
	 */
	public function forget_release( $upgrader, $extra ): void {
		unset( $upgrader );
		if ( 'plugin' === ( $extra['type'] ?? '' ) && in_array( $this->basename, (array) ( $extra['plugins'] ?? [] ), true ) ) {
			delete_site_transient( self::CACHE );
		}
	}

	/**
	 * The latest published release (cached for a few minutes, failures included).
	 *
	 * @return Release|null
	 */
	private function latest(): ?Release {
		$cached = get_site_transient( self::CACHE );
		if ( is_array( $cached ) ) {
			return is_array( $cached['data'] ?? null ) ? Release::from_github( $cached['data'], self::REPO, self::ASSET ) : null;
		}

		$data     = null;
		$response = wp_remote_get(
			'https://api.github.com/repos/' . self::REPO . '/releases/latest',
			[
				'timeout' => 10,
				'headers' => [
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'Sprint-Illustrations/' . ( defined( 'SPRINT_ILLUSTRATIONS_VERSION' ) ? SPRINT_ILLUSTRATIONS_VERSION : '' ),
				],
			]
		);
		if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
			$decoded = json_decode( wp_remote_retrieve_body( $response ), true );
			$data    = is_array( $decoded ) ? $decoded : null;
		}
		set_site_transient( self::CACHE, [ 'data' => $data ], self::CACHE_TTL );

		return is_array( $data ) ? Release::from_github( $data, self::REPO, self::ASSET ) : null;
	}
}
