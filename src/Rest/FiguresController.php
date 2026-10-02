<?php
/**
 * Cartoon figures REST API: options, live preview, option thumbnails, suggestions, shuffle and Save.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Rest;

use SprintIllustrations\Palette\Palette;
use SprintIllustrations\Plugin;
use SprintIllustrations\Figure\FigureBuilder;
use SprintIllustrations\Figure\FigurePresets;
use SprintIllustrations\Figure\FigurePreview;
use SprintIllustrations\Figure\FigureSpec;
use SprintIllustrations\Storage\PieceDrafts;

/**
 * Figures are drawn by the pure Figure namespace; this controller only resolves the palette,
 * sanitizes what is drawn and installs a saved figure through the same PieceInstaller as Keep.
 */
final class FiguresController {

	/**
	 * Fields a thumbnail row can be made for.
	 */
	private const VARIANT_FIELDS = [ 'pose', 'carry', 'face', 'eyes', 'brows', 'nose', 'mouth', 'cheeks', 'hair', 'headwear', 'eyewear', 'build', 'top', 'bottom', 'shoes', 'backpack', 'tilt', 'flip', 'scene' ];

	/**
	 * Fields a shuffle leaves alone: the frame and pose belong to the page, not to the face.
	 */
	private const KEEP_ON_SHUFFLE = [ 'scene', 'scene_color', 'pose', 'carry', 'tilt', 'flip' ];

	/**
	 * Tags a pose or scene adds to a saved figure.
	 */
	private const TAGS = [
		'wave'      => [ 'waving', 'hello' ],
		'hold'      => [ 'holding' ],
		'thinking'  => [ 'thinking' ],
		'cheer'     => [ 'cheering', 'celebrate' ],
		'walk'      => [ 'walking' ],
		'bag'       => [ 'bag', 'shopping' ],
		'clipboard' => [ 'clipboard', 'work' ],
		'parcel'    => [ 'parcel', 'delivery' ],
		'hardhat'   => [ 'builder', 'hardhat' ],
		'backpack'  => [ 'backpack' ],
	];

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
		$public = [ Permissions::class, 'studio' ];
		$editor = [ Permissions::class, 'edit_posts' ];
		foreach ( [
			'options'  => 'GET',
			'preview'  => 'POST',
			'variants' => 'POST',
			'suggest'  => 'POST',
			'shuffle'  => 'POST',
			'save'     => 'POST',
		] as $route => $method ) {
			register_rest_route(
				Permissions::NAMESPACE,
				'/figures/' . $route,
				[
					'methods'             => $method,
					'callback'            => [ $this, $route ],
					'permission_callback' => 'save' === $route ? $editor : $public,
				]
			);
		}
	}

	/**
	 * Everything the page needs to start: the choices, the presets and the palette's tones.
	 *
	 * @return \WP_REST_Response
	 */
	public function options(): \WP_REST_Response {
		$palette = $this->plugin->site_palette()->palette()->to_array();
		$fields  = [];
		foreach ( FigureSpec::options() as $field => $allowed ) {
			$fields[ $field ] = self::list( $allowed );
		}

		return new \WP_REST_Response(
			[
				'fields'   => $fields,
				'defaults' => FigureSpec::defaults() + [
					'skin'      => 0,
					'hair_tone' => 0,
				],
				'roles'    => FigurePresets::all(),
				'tones'    => [
					'skin' => array_values( (array) ( $palette['skin'] ?? [] ) ),
					'hair' => array_values( (array) ( $palette['hair'] ?? [] ) ),
				],
				'palettes' => $this->plugin->editor_choices()['presets'],
			]
		);
	}

	/**
	 * The figure in a palette.
	 *
	 * @param \WP_REST_Request $request Request {spec, palette}.
	 * @return \WP_REST_Response
	 */
	public function preview( \WP_REST_Request $request ): \WP_REST_Response {
		$spec    = FigureSpec::from_array( (array) ( $request['spec'] ?? [] ) );
		$palette = $this->palette( $request );
		$data    = $palette->to_array();

		return new \WP_REST_Response(
			[
				'svg'     => FigurePreview::render( $spec, $palette, $this->plugin->services()->sanitizer ),
				'spec'    => $spec->to_array(),
				'palette' => [
					'colors' => array_map( static fn( string $slot ): string => (string) $palette->resolve( $slot ), array_combine( array_keys( \SprintIllustrations\Character\CharacterSpec::COLORS ), array_keys( \SprintIllustrations\Character\CharacterSpec::COLORS ) ) ),
					'skin'   => array_values( (array) ( $data['skin'] ?? [] ) ),
					'hair'   => array_values( (array) ( $data['hair'] ?? [] ) ),
				],
			]
		);
	}

	/**
	 * The current figure with each value of one field, for the option thumbnails.
	 *
	 * @param \WP_REST_Request $request Request {spec, field, palette}.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function variants( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$field = (string) ( $request['field'] ?? '' );
		if ( ! in_array( $field, self::VARIANT_FIELDS, true ) ) {
			return new \WP_Error( 'sprint_illustrations_bad_field', __( 'Unknown option.', 'sprint-illustrations' ), [ 'status' => 400 ] );
		}

		$spec    = FigureSpec::from_array( (array) ( $request['spec'] ?? [] ) );
		$palette = $this->palette( $request );
		$images  = [];
		foreach ( array_keys( FigureSpec::options()[ $field ] ) as $value ) {
			$images[ (string) $value ] = FigurePreview::render( $spec->with( [ $field => $value ] ), $palette, $this->plugin->services()->sanitizer );
		}

		return new \WP_REST_Response( [ 'images' => $images ] );
	}

	/**
	 * A starting figure for a description of the page ("a taxi driver at the wheel").
	 *
	 * @param \WP_REST_Request $request Request {text}.
	 * @return \WP_REST_Response
	 */
	public function suggest( \WP_REST_Request $request ): \WP_REST_Response {
		$text = mb_substr( trim( wp_strip_all_tags( (string) ( $request['text'] ?? '' ) ) ), 0, 500 );
		$role = '' === $text ? null : FigurePresets::role_for( $text, $this->plugin->services()->keywords );
		if ( null === $role ) {
			return new \WP_REST_Response(
				[
					'role'    => null,
					'message' => __( 'No matching role found. Try words like taxi driver, passenger, support, teacher or welcome, or pick a starting point below.', 'sprint-illustrations' ),
				]
			);
		}

		$label = '';
		foreach ( FigurePresets::all() as $preset ) {
			if ( $preset['id'] === $role ) {
				$label = $preset['label'];
			}
		}

		return new \WP_REST_Response(
			[
				'role'    => $role,
				'label'   => $label,
				'spec'    => FigurePresets::spec_for( $role )->to_array(),
				'message' => '',
			]
		);
	}

	/**
	 * A new face: everything but the frame, pose and turn of the current figure changes.
	 *
	 * @param \WP_REST_Request $request Request {seed, gender, spec}.
	 * @return \WP_REST_Response
	 */
	public function shuffle( \WP_REST_Request $request ): \WP_REST_Response {
		$seed    = max( 1, absint( $request['seed'] ?? 1 ) );
		$current = FigureSpec::from_array( (array) ( $request['spec'] ?? [] ) );
		$new     = FigurePresets::shuffled( $seed, (string) ( $request['gender'] ?? 'any' ) )->to_array();

		return new \WP_REST_Response( [ 'spec' => $current->with( array_diff_key( $new, array_flip( self::KEEP_ON_SHUFFLE ) ) )->to_array() ] );
	}

	/**
	 * Save the figure as a library piece.
	 *
	 * @param \WP_REST_Request $request Request {spec, name, label, tags}.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function save( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$spec = FigureSpec::from_array( (array) ( $request['spec'] ?? [] ) );
		$name = PieceDrafts::name( (string) ( $request['name'] ?? '' ) );
		if ( ! preg_match( '/^[a-z][a-z0-9-]{1,40}$/D', $name ) ) {
			return new \WP_Error( 'sprint_illustrations_bad_name', __( 'Give the figure a name of 2 to 40 letters, numbers or dashes, for example "sam-figure".', 'sprint-illustrations' ), [ 'status' => 400 ] );
		}

		$label = trim( (string) preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) ( $request['label'] ?? '' ) ) ) );
		$label = mb_substr( '' === $label ? ucfirst( str_replace( '-', ' ', $name ) ) : $label, 0, 80 );
		$tags  = $this->tags( $spec, (array) ( $request['tags'] ?? [] ) );

		// The person is the first part of the name, as for every other character.
		$person = explode( '-', $name )[0];
		$svg    = FigureBuilder::svg( $spec, $label, $tags, $person );

		$installer = $this->plugin->piece_installer();
		if ( $installer->exists( 'characters', $name ) ) {
			return new \WP_Error( 'sprint_illustrations_exists', __( 'A character with that name is already in the library. Choose another name.', 'sprint-illustrations' ), [ 'status' => 409 ] );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		$tmp = wp_tempnam( 'si-figure' );
		if ( '' === $tmp || false === file_put_contents( $tmp, $svg ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- A temporary file inside the system temp dir.
			return new \WP_Error( 'sprint_illustrations_save_failed', __( 'The figure couldn’t be saved.', 'sprint-illustrations' ), [ 'status' => 500 ] );
		}
		$result = $installer->install( 'characters', $name, $tmp );
		wp_delete_file( $tmp );

		if ( ! $result['ok'] ) {
			return new \WP_Error( 'sprint_illustrations_save_failed', implode( ' ', $result['messages'] ), [ 'status' => 422 ] );
		}

		return new \WP_REST_Response(
			[
				'piece'    => $result['piece'],
				'where'    => $result['where'] ?? 'site',
				'warnings' => $result['messages'],
			],
			201
		);
	}

	/**
	 * Tags for a saved figure: the ones typed, plus what the choices say, in the library's single-word form.
	 *
	 * @param FigureSpec   $spec  Spec.
	 * @param array<mixed> $typed Typed tags.
	 * @return array<string>
	 */
	private function tags( FigureSpec $spec, array $typed ): array {
		$auto = array_merge(
			[ 'person', 'cartoon' ],
			self::TAGS[ $spec->get( 'pose' ) ] ?? [],
			'hold' === $spec->get( 'pose' ) ? ( self::TAGS[ $spec->get( 'carry' ) ] ?? [] ) : [],
			self::TAGS[ $spec->get( 'headwear' ) ] ?? [],
			self::TAGS[ $spec->get( 'backpack' ) ] ?? []
		);

		$keywords = $this->plugin->services()->keywords;
		$tags     = [];
		foreach ( array_merge( $auto, $typed ) as $tag ) {
			$tag = is_string( $tag ) ? strtolower( trim( $tag ) ) : '';
			if ( '' !== $tag && [ $tag ] === $keywords->tokenize( $tag ) ) {
				$tags[ $tag ] = true;
			}
		}

		return array_slice( array_keys( $tags ), 0, 12 );
	}

	/**
	 * The palette to draw in.
	 *
	 * @param \WP_REST_Request $request Request {palette}.
	 * @return Palette
	 */
	private function palette( \WP_REST_Request $request ): Palette {
		$ref = is_string( $request['palette'] ?? null ) ? $request['palette'] : 'site';

		return $this->plugin->site_palette()->resolve( $ref );
	}

	/**
	 * Options as [{value, label}] for the client (labels are translated).
	 *
	 * @param array<string, string> $allowed Value => English label.
	 * @return array<int, array{value: string, label: string}>
	 */
	private static function list( array $allowed ): array {
		$list = [];
		foreach ( $allowed as $value => $label ) {
			$list[] = [
				'value' => (string) $value,
				// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- Labels are literals in FigureSpec, translated here.
				'label' => __( $label, 'sprint-illustrations' ),
			];
		}

		return $list;
	}
}
