<?php
/**
 * Minimal Anthropic Messages API client over the WordPress HTTP API.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Ai;

use SprintIllustrations\Selection\AiFailure;

/**
 * One POST /v1/messages; errors carry Anthropic's message (never the key).
 */
final class AnthropicClient {

	public const URL = 'https://api.anthropic.com/v1/messages';

	public const VERSION = '2023-06-01';

	public const TIMEOUT = 20;

	/**
	 * Send a Messages request.
	 *
	 * @param array<string, mixed> $body      Request body.
	 * @param string               $key       API key.
	 * @param string               $workspace Workspace ID for multi-workspace keys, or ''.
	 * @return array<string, mixed>|\WP_Error Decoded response or an error (code = API error type, http_request_failed or invalid_json; message = API message).
	 */
	public function messages( array $body, string $key, string $workspace = '' ): array|\WP_Error {
		$headers = [
			'x-api-key'         => $key,
			'anthropic-version' => self::VERSION,
			'content-type'      => 'application/json',
		];
		if ( '' !== $workspace ) {
			$headers['anthropic-workspace-id'] = $workspace;
		}

		$response = wp_remote_post(
			self::URL,
			[
				'timeout' => self::TIMEOUT,
				'headers' => $headers,
				'body'    => (string) wp_json_encode( $body ),
			]
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'http_request_failed', $response->get_error_message() );
		}

		$code    = (int) wp_remote_retrieve_response_code( $response );
		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 ) {
			$type    = is_array( $decoded ) && is_string( $decoded['error']['type'] ?? null ) ? $decoded['error']['type'] : 'api_error';
			$message = is_array( $decoded ) && is_string( $decoded['error']['message'] ?? null ) ? $decoded['error']['message'] : sprintf( 'HTTP %d', $code );
			return new \WP_Error( sanitize_key( $type ), AiFailure::detail( $message ) );
		}

		return is_array( $decoded ) ? $decoded : new \WP_Error( 'invalid_json', 'The response was not JSON.' );
	}
}
