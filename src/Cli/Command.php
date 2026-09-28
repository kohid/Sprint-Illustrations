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
use SprintIllustrations\Library\Template;
use SprintIllustrations\Plugin;
use SprintIllustrations\Selection\AiRequest;
use SprintIllustrations\Selection\Suggestion;

/**
 * Compose illustrations, place them on pages (library/apply/save) and build piece manifests.
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
	 * [--palette=<ref>]
	 * : "site", "default" or "preset:<id>".
	 * ---
	 * default: site
	 * ---
	 *
	 * [--[no-]cache]
	 * : Use the illustration cache (default). --no-cache always recomposes.
	 *
	 * [--out=<file>]
	 * : Write to a file instead of STDOUT.
	 *
	 * ## EXAMPLES
	 *
	 *     wp sprint-illustrations compose --template=hero-left-character --seed=7
	 *     wp sprint-illustrations compose --keywords="remote team" --palette=preset:ocean --out=hero.svg
	 *
	 * @param array<int, string>         $args       Positional args.
	 * @param array<string, string|bool> $assoc_args Options.
	 */
	public function compose( array $args, array $assoc_args ): void {
		$spec = SceneSpec::from_array(
			[
				'template' => $assoc_args['template'] ?? null,
				'seed'     => $assoc_args['seed'] ?? 1,
				'keywords' => $assoc_args['keywords'] ?? '',
				'palette'  => $assoc_args['palette'] ?? 'site',
			]
		);

		$composer = \WP_CLI\Utils\get_flag_value( $assoc_args, 'cache', true )
			? $this->plugin->composer()
			: $this->plugin->services()->composer;

		try {
			$result = $composer->compose( $spec, $this->plugin->site_palette()->resolve( $spec->palette ) );
		} catch ( CompositionException $e ) {
			\WP_CLI::error( $e->getMessage() );
		}

		foreach ( $result->warnings as $warning ) {
			\WP_CLI::warning( $warning );
		}

		$svg = $result->with_instance_id( 'si-cli' );

		if ( isset( $assoc_args['out'] ) ) {
			file_put_contents( (string) $assoc_args['out'], $svg . "\n" );
			\WP_CLI::success( sprintf( 'Wrote %s (template %s, seed %d).', $assoc_args['out'], (string) $result->spec->template, $result->spec->seed ) );
			return;
		}

		\WP_CLI::line( $svg );
	}

	/**
	 * List templates and tags (the vocabulary for apply and save).
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : table or json.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 * ---
	 *
	 * @param array<int, string>         $args       Positional args.
	 * @param array<string, string|bool> $assoc_args Options.
	 */
	public function library( array $args, array $assoc_args ): void {
		$services  = $this->plugin->services();
		$templates = array_values( $services->templates->all() );
		$tags      = AiRequest::tags( $services->manifest->all(), $templates );
		$rows      = array_map(
			static fn( Template $template ): array => [
				'id'    => $template->id,
				'label' => $template->label,
				'tags'  => implode( ', ', $template->tags ),
				'shows' => implode( ', ', array_unique( array_map( static fn( $slot ): string => $slot->category, $template->slots ) ) ),
			],
			$templates
		);

		if ( 'json' === ( $assoc_args['format'] ?? 'table' ) ) {
			\WP_CLI::line(
				(string) wp_json_encode(
					[
						'templates' => $rows,
						'tags'      => $tags,
					],
					JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
				)
			);
			return;
		}

		\WP_CLI\Utils\format_items( 'table', $rows, [ 'id', 'label', 'tags', 'shows' ] );
		\WP_CLI::line( 'Tags: ' . implode( ', ', $tags ) );
	}

	/**
	 * Set the illustration on a page: update block/widget N, or insert a block.
	 *
	 * ## OPTIONS
	 *
	 * <post-id>
	 * : Page or post ID.
	 *
	 * --template=<id>
	 * : Template ID (see `library`).
	 *
	 * [--keywords=<csv>]
	 * : Comma-separated library tags (see `library`).
	 *
	 * [--title=<text>]
	 * : Alt text (at most 120 characters).
	 *
	 * [--seed=<n>]
	 * : Seed; omit to keep the current one.
	 *
	 * [--index=<n>]
	 * : Which illustration block/widget to update (1 = first). Default: 1.
	 *
	 * [--insert=<where>]
	 * : Insert a new block instead ("top" or "bottom"). Block-editor pages only.
	 *
	 * [--dry-run]
	 * : Show what would change without saving.
	 *
	 * ## EXAMPLES
	 *
	 *     wp sprint-illustrations apply 26 --template=two-people-collaborating --keywords="team, laptop" --title="Two instructors reviewing a route" --user=admin
	 *     wp sprint-illustrations apply 99 --template=hero-left-character --keywords=book --insert=top --user=admin
	 *
	 * @param array<int, string>         $args       Positional args.
	 * @param array<string, string|bool> $assoc_args Options.
	 */
	public function apply( array $args, array $assoc_args ): void {
		$post_id = absint( $args[0] ?? 0 );
		$post    = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			\WP_CLI::error( sprintf( 'Post %d not found.', $post_id ) );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			\WP_CLI::error( 'Run as a user who can edit this page, e.g. --user=admin.' );
		}

		$suggestion = $this->suggestion( $assoc_args );
		$seed       = isset( $assoc_args['seed'] ) ? max( 0, (int) $assoc_args['seed'] ) : null;
		$insert     = (string) ( $assoc_args['insert'] ?? '' );
		$index      = max( 1, (int) ( $assoc_args['index'] ?? 1 ) );
		$dry_run    = isset( $assoc_args['dry-run'] );

		// Only what was passed changes; omitted --keywords/--title/--seed keep the current values.
		$changes = [ 'template' => $suggestion['template'] ];
		if ( isset( $assoc_args['keywords'] ) ) {
			$changes['keywords'] = implode( ', ', $suggestion['keywords'] );
		}
		if ( isset( $assoc_args['title'] ) ) {
			$changes['title'] = $suggestion['title'];
		}
		if ( null !== $seed ) {
			$changes['seed'] = $seed;
		}

		if ( 'builder' === get_post_meta( $post_id, '_elementor_edit_mode', true ) ) {
			if ( '' !== $insert ) {
				\WP_CLI::error( 'Elementor pages: --insert is not supported. Use `save` and pick the saved illustration in the widget, or add the widget in Elementor and run apply again.' );
			}
			$this->apply_elementor( $post_id, $index, [ 'illustration_id' => '0' ] + $changes, $dry_run );
			return;
		}

		$attrs = [ 'illustrationId' => 0 ] + $changes;

		$result = PlacementEditor::blocks( parse_blocks( $post->post_content ), '' === $insert ? $index : null, $insert, $attrs );
		if ( '' !== $result['error'] ) {
			\WP_CLI::error( $result['error'] );
		}

		$where = '' !== $insert ? sprintf( 'inserted a new illustration block at the %s', $insert ) : sprintf( 'updated illustration block %d', $index );
		if ( $dry_run ) {
			\WP_CLI::success( sprintf( 'Dry run: would have %s of "%s" with %s.', $where, $post->post_title, (string) wp_json_encode( $attrs ) ) );
			return;
		}

		$saved = wp_update_post(
			[
				'ID'           => $post_id,
				'post_content' => serialize_blocks( $result['blocks'] ),
			],
			true
		);
		if ( is_wp_error( $saved ) ) {
			\WP_CLI::error( $saved->get_error_message() );
		}

		\WP_CLI::success( sprintf( 'Saved: %s of "%s" (%s). A revision was kept.', $where, $post->post_title, get_permalink( $post_id ) ) );
	}

	/**
	 * Create a saved illustration and print its shortcode.
	 *
	 * ## OPTIONS
	 *
	 * --name=<text>
	 * : Name shown in the Builder and the block/widget picker.
	 *
	 * --template=<id>
	 * : Template ID (see `library`).
	 *
	 * [--keywords=<csv>]
	 * : Comma-separated library tags.
	 *
	 * [--title=<text>]
	 * : Alt text.
	 *
	 * [--seed=<n>]
	 * : Seed.
	 * ---
	 * default: 1
	 * ---
	 *
	 * @param array<int, string>         $args       Positional args.
	 * @param array<string, string|bool> $assoc_args Options.
	 */
	public function save( array $args, array $assoc_args ): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			\WP_CLI::error( 'Run as a user who can edit posts, e.g. --user=admin.' );
		}

		$name       = trim( sanitize_text_field( (string) ( $assoc_args['name'] ?? '' ) ) );
		$suggestion = $this->suggestion( $assoc_args );
		if ( '' === $name ) {
			\WP_CLI::error( '--name is required.' );
		}

		$item = $this->plugin->illustrations()->create(
			$name,
			SceneSpec::from_array(
				[
					'template' => $suggestion['template'],
					'keywords' => $suggestion['keywords'],
					'title'    => $suggestion['title'],
					'seed'     => $assoc_args['seed'] ?? 1,
				]
			),
			get_current_user_id()
		);
		if ( null === $item ) {
			\WP_CLI::error( 'The illustration could not be saved.' );
		}

		\WP_CLI::success( sprintf( 'Saved illustration %d "%s". Shortcode: [sprint_illustration id="%d"]', (int) $item->id, $item->title, (int) $item->id ) );
	}

	/**
	 * Validated suggestion from CLI options (strict: unknown tags are an error).
	 *
	 * @param array<string, string|bool> $assoc_args Options.
	 * @return array{template: string, keywords: array<string>, title: string}
	 */
	private function suggestion( array $assoc_args ): array {
		$services  = $this->plugin->services();
		$templates = array_values( $services->templates->all() );
		$result    = Suggestion::validate(
			[
				'template' => (string) ( $assoc_args['template'] ?? '' ),
				'keywords' => (string) ( $assoc_args['keywords'] ?? '' ),
				'title'    => (string) ( $assoc_args['title'] ?? '' ),
			],
			array_map( static fn( Template $template ): string => $template->id, $templates ),
			AiRequest::tags( $services->manifest->all(), $templates ),
			true
		);

		if ( null === $result['suggestion'] ) {
			\WP_CLI::error( $result['error'] );
		}

		return $result['suggestion'];
	}

	/**
	 * Update Elementor widget N through Elementor's document API (revision + CSS cache handled by Elementor).
	 *
	 * @param int                  $post_id  Page.
	 * @param int                  $index    Widget (1-based).
	 * @param array<string, mixed> $settings Widget settings to set.
	 * @param bool                 $dry_run  Don't save.
	 */
	private function apply_elementor( int $post_id, int $index, array $settings, bool $dry_run ): void {
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			\WP_CLI::error( 'This page is built with Elementor, but Elementor is not active.' );
		}

		$document = \Elementor\Plugin::$instance->documents->get( $post_id );
		if ( ! $document ) {
			\WP_CLI::error( 'Elementor could not load this page.' );
		}

		$result = PlacementEditor::elementor( (array) $document->get_elements_data(), $index, $settings );
		if ( '' !== $result['error'] ) {
			\WP_CLI::error( $result['error'] );
		}

		if ( $dry_run ) {
			\WP_CLI::success( sprintf( 'Dry run: would have updated illustration widget %d of "%s" with %s.', $index, get_the_title( $post_id ), (string) wp_json_encode( $settings ) ) );
			return;
		}

		if ( ! $document->save( [ 'elements' => $result['elements'] ] ) ) {
			\WP_CLI::error( 'Elementor did not save the page.' );
		}

		\WP_CLI::success( sprintf( 'Saved: updated illustration widget %d of "%s" (%s).', $index, get_the_title( $post_id ), get_permalink( $post_id ) ) );
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
