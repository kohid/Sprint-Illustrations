<?php
/**
 * Symmetric encryption for stored secrets (the API key).
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Security;

/**
 * Sodium secretbox with a key derived from site key material; output is base64( nonce . box ).
 */
final class SecretStore {

	/**
	 * 32-byte key.
	 *
	 * @var string
	 */
	private string $key;

	/**
	 * Constructor.
	 *
	 * @param string $key_material Site secret (e.g. wp_salt( 'auth' )).
	 */
	public function __construct( string $key_material ) {
		$this->key = sodium_crypto_generichash( 'sprint-illustrations|' . $key_material, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
	}

	/**
	 * Encrypt.
	 *
	 * @param string $plain Plain text.
	 * @return string
	 */
	public function encrypt( string $plain ): string {
		$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );

		return base64_encode( $nonce . sodium_crypto_secretbox( $plain, $nonce, $this->key ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Binary ciphertext storage.
	}

	/**
	 * Decrypt, or null when the input is malformed, tampered with or made with another key.
	 *
	 * @param string $cipher Output of encrypt().
	 * @return string|null
	 */
	public function decrypt( string $cipher ): ?string {
		$raw = base64_decode( $cipher, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Binary ciphertext storage.
		if ( false === $raw || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES ) {
			return null;
		}

		$plain = sodium_crypto_secretbox_open( substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), $this->key );

		return false === $plain ? null : $plain;
	}
}
