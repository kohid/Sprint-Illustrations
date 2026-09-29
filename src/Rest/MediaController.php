<?php
/**
 * POST /media/svg and /media/png — save illustrations to the Media Library.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Rest;

use SprintIllustrations\Compose\Animation;
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
		// Animated export: the file carries its own motion CSS, so it moves as a plain image too.
		$animated = rest_sanitize_boolean( $request['animated'] ?? false ) && [] !== $result->spec->animations;
		if ( $animated ) {
			$markup = SvgFile::with_style( $markup, Animation::css( $result->spec->animations ) );
		}
		$size  = SvgFile::size( $markup ) ?? [ 0, 0 ];
		$title = $this->title( $request, (string) $result->spec->template );

		$allow  = static fn( array $mimes ): array => $mimes + [ 'svg' => 'image/svg+xml' ];
		add_filter( 'upload_mimes', $allow );
		$upload = wp_upload_bits( sanitize_file_name( $title . ( $animated ? '-animated' : '' ) . '-' . gmdate( 'Ymd-His' ) . '.svg' ), null, $markup );
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
		$size    = (int) filesize( $file['tmp_name'] );
		$tail    = (string) file_get_contents( $file['tmp_name'], false, null, max( 0, $size - 12 ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local temp file trailer.
		$problem = PngCheck::problem( $head, $size, $tail );
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
