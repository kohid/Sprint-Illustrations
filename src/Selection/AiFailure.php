<?php
/**
 * Classify Anthropic API failures so users see what actually went wrong.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Selection;

/**
 * Error type + message → one of a few reasons; the caller turns a reason into words.
 */
final class AiFailure {

	public const WORKSPACE = 'workspace';

	public const AUTH = 'auth';

	public const BUSY = 'busy';

	public const NETWORK = 'network';

	public const REQUEST = 'request';

	public const MAX_DETAIL = 300;

	/**
	 * Reason for a failure.
	 *
	 * @param string $code    API error type (e.g. invalid_request_error) or http_request_failed / invalid_json.
	 * @param string $message API error message.
	 * @return string One of the reason constants.
	 */
	public static function reason( string $code, string $message ): string {
		if ( str_contains( strtolower( $message ), 'anthropic-workspace-id' ) ) {
			return self::WORKSPACE;
		}

		return match ( $code ) {
			'authentication_error', 'permission_error' => self::AUTH,
			'rate_limit_error', 'overloaded_error'     => self::BUSY,
			'http_request_failed', 'invalid_json'      => self::NETWORK,
			default                                    => self::REQUEST,
		};
	}

	/**
	 * API message as plain, single-line text of bounded length.
	 *
	 * @param string $message API error message.
	 * @return string
	 */
	public static function detail( string $message ): string {
		$text = trim( (string) preg_replace( '/\s+/u', ' ', strip_tags( $message ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Pure namespace; no WordPress functions.

		return mb_substr( $text, 0, self::MAX_DETAIL );
	}
}
