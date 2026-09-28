<?php
/**
 * Library browser: pieces and templates, server-rendered.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Admin;

use SprintIllustrations\Compose\CompositionException;
use SprintIllustrations\Compose\PieceLoader;
use SprintIllustrations\Compose\SceneSpec;
use SprintIllustrations\Library\LibraryFilter;
use SprintIllustrations\Library\Piece;
use SprintIllustrations\Library\Template;
use SprintIllustrations\Palette\Palette;
use SprintIllustrations\Plugin;
use SprintIllustrations\Rest\PiecePreviews;

/**
 * Tabs per category plus Templates, with a word search over labels and tags.
 */
final class LibraryPage {

	public const CAPABILITY = 'edit_posts';

	private const TEMPLATES_TAB = 'templates';

	private const TEMPLATE_SEED = 3;

	private const ATTACH_LABELS = [
		'handheld' => 'Held in a hand',
		'lap'      => 'Rests on a lap',
		'surface'  => 'Stands on a surface',
	];

	private const HOLD_LABELS = [
		'handheld' => 'Holds things in a hand',
		'lap'      => 'Holds a laptop on the lap',
	];

	/**
	 * Piece request panel.
	 *
	 * @var PieceRequestPanel
	 */
	private PieceRequestPanel $requests;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( private Plugin $plugin ) {
		$this->requests = new PieceRequestPanel( $plugin );
	}

	/**
	 * Register hooks (the request form handlers).
	 */
	public function register(): void {
		$this->requests->register();
	}

	/**
	 * Enqueue styles.
	 */
	public function enqueue(): void {
		wp_enqueue_style( 'sprint-illustrations-library', plugins_url( 'assets/admin/library.css', SPRINT_ILLUSTRATIONS_FILE ), [], SPRINT_ILLUSTRATIONS_VERSION );
	}

	/**
	 * Tab labels keyed by tab ID.
	 *
	 * @return array<string, string>
	 */
	private function tabs(): array {
		return [
			'characters'        => __( 'Characters', 'sprint-illustrations' ),
			'objects'           => __( 'Objects', 'sprint-illustrations' ),
			'backgrounds'       => __( 'Backgrounds', 'sprint-illustrations' ),
			'decor'             => __( 'Decor', 'sprint-illustrations' ),
			self::TEMPLATES_TAB => __( 'Templates', 'sprint-illustrations' ),
		];
	}

	/**
	 * Render the page.
	 */
	public function render(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'sprint-illustrations' ) );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only browsing: tab and search.
		$tab    = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'characters';
		$search = isset( $_GET['s'] ) ? trim( sanitize_text_field( wp_unslash( $_GET['s'] ) ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$tabs = $this->tabs();
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'characters';
		}

		$services  = $this->plugin->services();
		$pieces    = $services->manifest->all();
		$templates = array_values( $services->templates->all() );

		echo '<div class="wrap si-library">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Library', 'sprint-illustrations' ) . '</h1>';
		echo '<p class="si-library__intro">' . esc_html__( 'Every piece and template the Builder and the shortcode can use, shown in your site palette.', 'sprint-illustrations' ) . '</p>';

		$this->requests->render( $tab );
		$this->render_search( $tab, $search );

		echo '<nav class="nav-tab-wrapper si-library__tabs" aria-label="' . esc_attr__( 'Library sections', 'sprint-illustrations' ) . '">';
		foreach ( $tabs as $id => $label ) {
			$count = self::TEMPLATES_TAB === $id ? count( LibraryFilter::templates( $templates, $search ) ) : count( LibraryFilter::pieces( $pieces, $id, $search ) );
			$args  = [
				'page' => Menu::LIBRARY_SLUG,
				'tab'  => $id,
			];
			if ( '' !== $search ) {
				$args['s'] = $search;
			}
			printf(
				'<a href="%1$s" class="nav-tab%2$s"%3$s>%4$s <span class="si-library__count">%5$d</span></a>',
				esc_url( add_query_arg( $args, admin_url( 'admin.php' ) ) ),
				$id === $tab ? ' nav-tab-active' : '',
				$id === $tab ? ' aria-current="page"' : '',
				esc_html( $label ),
				(int) $count
			);
		}
		echo '</nav>';

		if ( self::TEMPLATES_TAB === $tab ) {
			$this->render_templates( LibraryFilter::templates( $templates, $search ), $search );
		} else {
			$this->render_pieces( LibraryFilter::pieces( $pieces, $tab, $search ), $tab, $search );
		}

		echo '</div>';
	}

	/**
	 * Search form (GET, keeps the tab).
	 *
	 * @param string $tab    Current tab.
	 * @param string $search Current search.
	 */
	private function render_search( string $tab, string $search ): void {
		echo '<form class="search-box si-library__search" method="get" role="search">';
		echo '<input type="hidden" name="page" value="' . esc_attr( Menu::LIBRARY_SLUG ) . '">';
		echo '<input type="hidden" name="tab" value="' . esc_attr( $tab ) . '">';
		echo '<label class="screen-reader-text" for="si-library-search">' . esc_html__( 'Search the library', 'sprint-illustrations' ) . '</label>';
		echo '<input type="search" id="si-library-search" name="s" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'laptop, team, coffee…', 'sprint-illustrations' ) . '">';
		echo '<input type="submit" class="button" value="' . esc_attr__( 'Search', 'sprint-illustrations' ) . '">';
		if ( '' !== $search ) {
			$clear = add_query_arg(
				[
					'page' => Menu::LIBRARY_SLUG,
					'tab'  => $tab,
				],
				admin_url( 'admin.php' )
			);
			echo ' <a class="si-library__clear" href="' . esc_url( $clear ) . '">' . esc_html__( 'Clear', 'sprint-illustrations' ) . '</a>';
		}
		echo '</form>';
	}

	/**
	 * Piece grid.
	 *
	 * @param array<Piece> $pieces   Filtered pieces.
	 * @param string       $category Category.
	 * @param string       $search   Search.
	 */
	private function render_pieces( array $pieces, string $category, string $search ): void {
		if ( [] === $pieces ) {
			$this->render_empty( $search, sprintf( 'assets/pieces-src/%s', $category ) );
			return;
		}

		$services = $this->plugin->services();
		$previews = new PiecePreviews( new PieceLoader( $services->sanitizer ), $services->sanitizer );
		$palette  = $this->plugin->site_palette()->palette();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only: which card to highlight.
		$highlight = isset( $_GET['piece'] ) ? sanitize_text_field( wp_unslash( $_GET['piece'] ) ) : '';
		$custom    = wp_normalize_path( $this->plugin->user_library_dir() );

		echo '<ul class="si-library__grid">';
		foreach ( $pieces as $piece ) {
			$is_custom = str_starts_with( wp_normalize_path( $piece->path ), $custom );
			printf( '<li class="si-card%1$s" id="piece-%2$s">', $highlight === $piece->id ? ' is-highlighted' : '', esc_attr( $piece->id ) );
			if ( $is_custom ) {
				echo '<span class="si-card__badge">' . esc_html__( 'Custom', 'sprint-illustrations' ) . '</span>';
			}
			echo '<div class="si-card__art">' . $this->preview( $previews, $piece, $palette ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitizer output.
			echo '<div class="si-card__body">';
			echo '<h2 class="si-card__title">' . esc_html( $piece->label ) . '</h2>';

			$facts = $this->facts( $piece );
			if ( [] !== $facts ) {
				echo '<p class="si-card__meta">' . esc_html( implode( ' · ', $facts ) ) . '</p>';
			}

			$this->render_tags( $piece->tags );
			echo '<p class="si-card__id"><code>' . esc_html( $piece->id ) . '</code></p>';
			echo '</div></li>';
		}
		echo '</ul>';
	}

	/**
	 * Template grid.
	 *
	 * @param array<Template> $templates Filtered templates.
	 * @param string          $search    Search.
	 */
	private function render_templates( array $templates, string $search ): void {
		if ( [] === $templates ) {
			$this->render_empty( $search, 'assets/templates', true );
			return;
		}

		$can_build = current_user_can( BuilderPage::CAPABILITY );

		echo '<ul class="si-library__grid si-library__grid--templates">';
		foreach ( $templates as $template ) {
			echo '<li class="si-card">';
			echo '<div class="si-card__art si-card__art--scene">' . $this->scene( $template ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Composer output.
			echo '<div class="si-card__body">';
			echo '<h2 class="si-card__title">' . esc_html( $template->label ) . '</h2>';
			/* translators: %s: comma-separated slot names. */
			echo '<p class="si-card__meta">' . esc_html( sprintf( __( 'Slots: %s', 'sprint-illustrations' ), implode( ', ', array_map( static fn( $slot ): string => $slot->name, $template->slots ) ) ) ) . '</p>';
			$this->render_tags( $template->tags );

			if ( $can_build ) {
				$this->render_builder_link( $template );
			}

			echo '</div></li>';
		}
		echo '</ul>';
	}

	/**
	 * "Open in Builder" link for a template.
	 *
	 * @param Template $template Template.
	 */
	private function render_builder_link( Template $template ): void {
		$url = add_query_arg(
			[
				'page'     => Menu::SLUG,
				'template' => $template->id,
			],
			admin_url( 'admin.php' )
		);

		printf(
			'<p class="si-card__action"><a class="button" href="%1$s">%2$s<span class="screen-reader-text"> %3$s</span></a></p>',
			esc_url( $url ),
			esc_html__( 'Open in Builder', 'sprint-illustrations' ),
			esc_html( $template->label )
		);
	}

	/**
	 * Empty state.
	 *
	 * @param string $search    Search.
	 * @param string $folder    Where new items go.
	 * @param bool   $templates Whether this is the Templates tab.
	 */
	private function render_empty( string $search, string $folder, bool $templates = false ): void {
		echo '<div class="si-library__empty">';
		if ( '' !== $search ) {
			/* translators: %s: search text. */
			$message = $templates ? __( 'No templates match “%s”. Try a tag like laptop or team.', 'sprint-illustrations' ) : __( 'No pieces match “%s”. Try a tag like laptop or team.', 'sprint-illustrations' );
			echo '<p>' . esc_html( sprintf( $message, $search ) ) . '</p>';
		} else {
			/* translators: %s: plugin folder. */
			echo '<p>' . wp_kses( sprintf( __( 'Nothing here yet. Add SVGs to %s and rebuild the manifest.', 'sprint-illustrations' ), '<code>' . esc_html( $folder ) . '</code>' ), [ 'code' => [] ] ) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * Tag chips.
	 *
	 * @param array<string> $tags Tags.
	 */
	private function render_tags( array $tags ): void {
		if ( [] === $tags ) {
			return;
		}

		echo '<ul class="si-tags" aria-label="' . esc_attr__( 'Tags', 'sprint-illustrations' ) . '">';
		foreach ( $tags as $tag ) {
			printf( '<li class="si-tag">%s</li>', esc_html( $tag ) );
		}
		echo '</ul>';
	}

	/**
	 * Person and attachment facts.
	 *
	 * @param Piece $piece Piece.
	 * @return array<string>
	 */
	private function facts( Piece $piece ): array {
		$facts = [];

		if ( null !== $piece->person ) {
			$facts[] = ucwords( str_replace( '-', ' ', $piece->person ) );
		}

		$hold = $piece->accepts['hold'] ?? null;
		if ( 'characters' === $piece->category && null !== $hold && isset( self::HOLD_LABELS[ $hold ] ) ) {
			$facts[] = self::HOLD_LABELS[ $hold ];
		}

		foreach ( array_keys( $piece->mounts ) as $type ) {
			if ( isset( self::ATTACH_LABELS[ $type ] ) ) {
				$facts[] = self::ATTACH_LABELS[ $type ];
			}
		}

		return $facts;
	}

	/**
	 * Piece preview SVG, or an empty string when it fails.
	 *
	 * @param PiecePreviews $previews Previews.
	 * @param Piece         $piece    Piece.
	 * @param Palette       $palette  Palette.
	 * @return string
	 */
	private function preview( PiecePreviews $previews, Piece $piece, Palette $palette ): string {
		try {
			return $previews->svg( $piece, $palette );
		} catch ( \Throwable $e ) {
			return '';
		}
	}

	/**
	 * Decorative template scene through the caching composer.
	 *
	 * @param Template $template Template.
	 * @return string
	 */
	private function scene( Template $template ): string {
		$spec = SceneSpec::from_array(
			[
				'template'   => $template->id,
				'seed'       => self::TEMPLATE_SEED,
				'decorative' => true,
			]
		);

		try {
			return $this->plugin->composer()->compose( $spec, $this->plugin->site_palette()->resolve( 'site' ) )->with_instance_id( 'si-l-' . $template->id );
		} catch ( CompositionException $e ) {
			return '';
		}
	}
}
