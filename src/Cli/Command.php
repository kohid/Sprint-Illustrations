<?php
/**
 * WP-CLI commands.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Cli;

use SprintIllustrations\Compose\CompositionException;
use SprintIllustrations\Compose\SceneSpec;
use SprintIllustrations\Palette\Palette;
use SprintIllustrations\Plugin;

/**
 * Compose illustrations and build piece manifests.
 */
final class Command {

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( private Plugin $plugin ) {}

	/**
	 * Compose an illustration and print (or save) the SVG.
	 *
	 * ## OPTIONS
	 *
	 * [--template=<id>]
	 * : Template ID. Omit to choose one from --keywords.
	 *
	 * [--seed=<n>]
	 * : Seed.
	 * ---
	 * default: 1
	 * ---
	 *
	 * [--keywords=<csv>]
	 * : Comma-separated keywords.
	 *
	 * [--out=<file>]
	 * : Write to a file instead of STDOUT.
	 *
	 * ## EXAMPLES
	 *
	 *     wp sprint-illustrations compose --template=hero-left-character --seed=7
	 *     wp sprint-illustrations compose --keywords="remote team" --out=hero.svg
	 *
	 * @param array<int, string>    $args       Positional args.
	 * @param array<string, string> $assoc_args Options.
	 */
	public function compose( array $args, array $assoc_args ): void {
		$spec = SceneSpec::from_array(
			[
				'template' => $assoc_args['template'] ?? null,
				'seed'     => $assoc_args['seed'] ?? 1,
				'keywords' => $assoc_args['keywords'] ?? '',
			]
		);

		try {
			$result = $this->plugin->services()->composer->compose( $spec, Palette::default() );
		} catch ( CompositionException $e ) {
			\WP_CLI::error( $e->getMessage() );
		}

		foreach ( $result->warnings as $warning ) {
			\WP_CLI::warning( $warning );
		}

		$svg = $result->with_instance_id( 'si-cli' );

		if ( isset( $assoc_args['out'] ) ) {
			file_put_contents( $assoc_args['out'], $svg . "\n" );
			\WP_CLI::success( sprintf( 'Wrote %s (template %s, seed %d).', $assoc_args['out'], (string) $result->spec->template, $result->spec->seed ) );
			return;
		}

		\WP_CLI::line( $svg );
	}

	/**
	 * Build a piece manifest from SVG sources.
	 *
	 * ## OPTIONS
	 *
	 * [--source=<dir>]
	 * : Source folder containing characters/, objects/, backgrounds/, decor/.
	 * Default: wp-content/uploads/sprint-illustrations/inbox
	 *
	 * [--target=<dir>]
	 * : Library folder receiving manifest.json and pieces/.
	 * Default: wp-content/uploads/sprint-illustrations
	 *
	 * [--non-interactive]
	 * : Accept defaults instead of prompting.
	 *
	 * @subcommand build-manifest
	 *
	 * @param array<int, string>         $args       Positional args.
	 * @param array<string, string|bool> $assoc_args Options.
	 */
	public function build_manifest( array $args, array $assoc_args ): void {
		$library = $this->plugin->user_library_dir();
		$source  = (string) ( $assoc_args['source'] ?? $library . '/inbox' );
		$target  = (string) ( $assoc_args['target'] ?? $library );

		if ( ! is_dir( $source ) ) {
			\WP_CLI::error( sprintf( 'Source folder not found: %s', $source ) );
		}

		$prompter = isset( $assoc_args['non-interactive'] ) ? new NullPrompter() : new StdinPrompter();
		$report   = ( new ManifestBuilder( $this->plugin->services()->sanitizer, $prompter ) )->build( $source, $target );

		foreach ( $report->warnings as $warning ) {
			\WP_CLI::warning( $warning );
		}
		foreach ( $report->errors as $error ) {
			\WP_CLI::warning( 'Skipped ' . $error );
		}

		\WP_CLI::success( sprintf( 'Built %d pieces into %s/manifest.json (version %d).', count( $report->built ), $target, $report->version ) );
	}
}
