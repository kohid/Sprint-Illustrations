<?php
/**
 * POST /ai/suggest — template, keywords and alt text for some content.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Rest;

use SprintIllustrations\Ai\AnthropicClient;
use SprintIllustrations\Compose\CompositionException;
use SprintIllustrations\Plugin;
use SprintIllustrations\Selection\AiFailure;
use SprintIllustrations\Selection\AiRequest;
use SprintIllustrations\Selection\AiResponse;
use SprintIllustrations\Selection\MissingObjects;

/**
 * Claude when ready and under the rate limit; otherwise (or on any failure) the rules selector.
 */
final class SuggestController {

	public const RATE_LIMIT = 30;

	/**
	 * Constructor.
	 *
	 * @param Plugin          $plugin Plugin.
	 * @param AnthropicClient $client Client.
	 */
	public function __construct( private Plugin $plugin, private AnthropicClient $client = new AnthropicClient() ) {}

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
			'/ai/suggest',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'suggest' ],
				'permission_callback' => [ Permissions::class, 'edit_posts' ],
			]
		);
	}

	/**
	 * Suggest.
	 *
	 * @param \WP_REST_Request $request Request {content, seed?}.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function suggest( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$content = AiRequest::clean( (string) ( $request['content'] ?? '' ) );
		$seed    = max( 1, absint( $request['seed'] ?? 1 ) );

		if ( '' === $content ) {
			return new \WP_Error( 'sprint_illustrations_empty_content', __( 'Describe the illustration or add some text first.', 'sprint-illustrations' ), [ 'status' => 400 ] );
		}

		$ai     = $this->plugin->ai();
		$reason = __( 'AI suggestions are off, so keywords were used.', 'sprint-illustrations' );

		if ( $ai->ready() ) {
			if ( ! $this->take_rate_slot() ) {
				/* translators: %d: hourly limit. */
				$reason = sprintf( __( 'You\'ve reached %d Claude suggestions this hour, so keywords were used.', 'sprint-illustrations' ), self::RATE_LIMIT );
			} else {
				$result = $this->ask_claude( $content, (string) $ai->key(), $ai->model() );
				if ( is_array( $result ) ) {
					return new \WP_REST_Response(
						$result + [
							'source'  => 'ai',
							'message' => '',
							'missing' => $this->missing( $content ),
						]
					);
				}
				$reason = $result;
			}
		} elseif ( $ai->settings()['enabled'] ) {
			$reason = __( 'Add an Anthropic API key in Settings to use Claude. Keywords were used.', 'sprint-illustrations' );
		}

		try {
			$spec = $this->plugin->services()->selector->suggest( $content, $seed );
		} catch ( CompositionException $e ) {
			return new \WP_Error( 'sprint_illustrations_composition_failed', $e->getMessage(), [ 'status' => 422 ] );
		}

		return new \WP_REST_Response(
			[
				'template' => (string) $spec->template,
				'keywords' => array_slice( $spec->keywords, 0, AiResponse::MAX_KEYWORDS ),
				'title'    => '',
				'source'   => 'rules',
				'message'  => $reason,
				'missing'  => $this->missing( $content ),
			]
		);
	}

	/**
	 * Objects in the description the library has nothing for (a starting list the user edits).
	 *
	 * @param string $content Clean content.
	 * @return array<string>
	 */
	private function missing( string $content ): array {
		$services = $this->plugin->services();

		return MissingObjects::find( $content, $services->keywords, AiRequest::tags( $services->manifest->all(), array_values( $services->templates->all() ) ) );
	}

	/**
	 * Call Claude; the suggestion, or a plain-language reason it failed.
	 *
	 * @param string $content Clean content.
	 * @param string $key     API key.
	 * @param string $model   Model.
	 * @return array{template: string, keywords: array<string>, title: string}|string
	 */
	private function ask_claude( string $content, string $key, string $model ): array|string {
		$services  = $this->plugin->services();
		$templates = array_values( $services->templates->all() );
		$tags      = AiRequest::tags( $services->manifest->all(), $templates );
		$response  = $this->client->messages( AiRequest::body( $content, $templates, $tags, $model ), $key, $this->plugin->ai()->workspace() );

		if ( is_wp_error( $response ) ) {
			$this->log( $response->get_error_code() . ': ' . $response->get_error_message() );

			$code = (string) $response->get_error_code();
			$text = self::failure_text( AiFailure::reason( $code, $response->get_error_message() ), $response->get_error_message() );

			return $text . ' ' . __( 'Keywords were used.', 'sprint-illustrations' );
		}

		$spec = AiResponse::spec( $response, array_map( static fn( $template ): string => $template->id, $templates ), $tags );
		if ( null === $spec ) {
			$this->log( 'reply did not match the library' );
			return __( 'Claude\'s answer didn\'t match the library, so keywords were used.', 'sprint-illustrations' );
		}

		return $spec;
	}

	/**
	 * What went wrong, in words (shared with Settings → Test connection).
	 *
	 * @param string $reason AiFailure reason.
	 * @param string $detail Anthropic's error message (plain text).
	 * @return string
	 */
	public static function failure_text( string $reason, string $detail ): string {
		switch ( $reason ) {
			case AiFailure::WORKSPACE:
				return __( 'Your API key needs a workspace ID. Add it in Settings → AI suggestions.', 'sprint-illustrations' );
			case AiFailure::AUTH:
				return __( 'Claude rejected the API key. Check the key in Settings.', 'sprint-illustrations' );
			case AiFailure::BUSY:
				return __( 'Claude is busy right now. Try again in a minute.', 'sprint-illustrations' );
			case AiFailure::NETWORK:
				return __( 'Claude couldn\'t be reached. Check the site\'s internet connection.', 'sprint-illustrations' );
			default:
				/* translators: %s: error message from the Anthropic API. */
				return sprintf( __( 'Claude returned an error: %s', 'sprint-illustrations' ), AiFailure::detail( $detail ) );
		}
	}

	/**
	 * Count one call against the per-user hourly limit.
	 *
	 * @return bool False when the limit is reached.
	 */
	private function take_rate_slot(): bool {
		$key   = 'si_ai_rate_' . get_current_user_id();
		$state = get_transient( $key );
		$state = is_array( $state ) ? $state : [
			'count' => 0,
			'until' => time() + HOUR_IN_SECONDS,
		];

		if ( $state['count'] >= self::RATE_LIMIT ) {
			return false;
		}

		++$state['count'];
		set_transient( $key, $state, max( 1, $state['until'] - time() ) );

		return true;
	}

	/**
	 * Debug log (reason only; never the key or the content).
	 *
	 * @param string $reason Reason.
	 */
	private function log( string $reason ): void {
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( 'Sprint Illustrations AI fallback: ' . $reason ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug-only fallback reason.
		}
	}
}
