<?php
/**
 * AI settings: on/off, model and the encrypted API key.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Ai;

use SprintIllustrations\Security\SecretStore;
use SprintIllustrations\Selection\AiRequest;

/**
 * Stored as sprint_illustrations_ai = { enabled, model, key_cipher, workspace }. The key itself is never returned to a browser.
 */
final class Settings {

	public const OPTION = 'sprint_illustrations_ai';

	public const CONSTANT = 'SPRINT_ILLUSTRATIONS_API_KEY';

	public const WORKSPACE_CONSTANT = 'SPRINT_ILLUSTRATIONS_WORKSPACE_ID';

	public const WORKSPACE_PATTERN = '/^wrkspc_[A-Za-z0-9]+$/D';

	/**
	 * Secret store.
	 *
	 * @return SecretStore
	 */
	private function store(): SecretStore {
		return new SecretStore( wp_salt( 'auth' ) );
	}

	/**
	 * Normalized stored settings.
	 *
	 * @return array{enabled: bool, model: string, key_cipher: string, workspace: string}
	 */
	public function settings(): array {
		$raw   = get_option( self::OPTION, [] );
		$raw   = is_array( $raw ) ? $raw : [];
		$model = (string) ( $raw['model'] ?? '' );

		return [
			'enabled'    => ! empty( $raw['enabled'] ),
			'model'      => isset( AiRequest::MODELS[ $model ] ) ? $model : AiRequest::DEFAULT_MODEL,
			'key_cipher' => is_string( $raw['key_cipher'] ?? null ) ? $raw['key_cipher'] : '',
			'workspace'  => is_string( $raw['workspace'] ?? null ) && preg_match( self::WORKSPACE_PATTERN, $raw['workspace'] ) ? $raw['workspace'] : '',
		];
	}

	/**
	 * Whether wp-config.php supplies the key.
	 *
	 * @return bool
	 */
	public function from_constant(): bool {
		return defined( self::CONSTANT ) && is_string( constant( self::CONSTANT ) ) && '' !== constant( self::CONSTANT );
	}

	/**
	 * The API key (server-side use only).
	 *
	 * @return string|null
	 */
	public function key(): ?string {
		if ( $this->from_constant() ) {
			return (string) constant( self::CONSTANT );
		}

		$cipher = $this->settings()['key_cipher'];

		return '' === $cipher ? null : $this->store()->decrypt( $cipher );
	}

	/**
	 * Whether wp-config.php supplies the workspace ID.
	 *
	 * @return bool
	 */
	public function workspace_from_constant(): bool {
		return defined( self::WORKSPACE_CONSTANT ) && is_string( constant( self::WORKSPACE_CONSTANT ) ) && 1 === preg_match( self::WORKSPACE_PATTERN, constant( self::WORKSPACE_CONSTANT ) );
	}

	/**
	 * Workspace ID sent as anthropic-workspace-id (needed by multi-workspace keys), or ''.
	 *
	 * @return string
	 */
	public function workspace(): string {
		return $this->workspace_from_constant() ? (string) constant( self::WORKSPACE_CONSTANT ) : $this->settings()['workspace'];
	}

	/**
	 * Whether a usable key exists.
	 *
	 * @return bool
	 */
	public function has_key(): bool {
		return null !== $this->key();
	}

	/**
	 * Whether Suggest should call Claude.
	 *
	 * @return bool
	 */
	public function ready(): bool {
		return $this->settings()['enabled'] && $this->has_key();
	}

	/**
	 * Model ID.
	 *
	 * @return string
	 */
	public function model(): string {
		return $this->settings()['model'];
	}

	/**
	 * Settings API sanitize callback. A new key replaces the old; empty keeps it; "remove" deletes it.
	 * An invalid workspace ID keeps the previous one and reports a settings error.
	 * Also accepts its own output (WordPress sanitizes twice when the option is first added).
	 *
	 * @param mixed $input Submitted value.
	 * @return array{enabled: bool, model: string, key_cipher: string, workspace: string}
	 */
	public function sanitize( mixed $input ): array {
		$input     = is_array( $input ) ? $input : [];
		$model     = sanitize_text_field( (string) ( $input['model'] ?? '' ) );
		$key       = trim( sanitize_text_field( (string) ( $input['key'] ?? '' ) ) );
		$cipher    = $this->settings()['key_cipher'];
		$workspace = trim( sanitize_text_field( (string) ( $input['workspace'] ?? '' ) ) );

		if ( '' !== $workspace && ! preg_match( self::WORKSPACE_PATTERN, $workspace ) ) {
			add_settings_error( self::OPTION, 'workspace', __( 'The workspace ID should look like wrkspc_01Jw… The previous value was kept.', 'sprint-illustrations' ) );
			$workspace = $this->settings()['workspace'];
		}

		if ( '' !== $key ) {
			$cipher = $this->store()->encrypt( $key );
		} elseif ( ! empty( $input['remove_key'] ) ) {
			$cipher = '';
		} elseif ( is_string( $input['key_cipher'] ?? null ) && '' !== $input['key_cipher'] && null !== $this->store()->decrypt( $input['key_cipher'] ) ) {
			$cipher = $input['key_cipher'];
		}

		return [
			'enabled'    => ! empty( $input['enabled'] ),
			'model'      => isset( AiRequest::MODELS[ $model ] ) ? $model : AiRequest::DEFAULT_MODEL,
			'key_cipher' => $cipher,
			'workspace'  => $workspace,
		];
	}
}
