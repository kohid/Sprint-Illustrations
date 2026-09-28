<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Security\SecretStore;

final class SecretStoreTest extends TestCase {

	public function test_round_trip_with_fresh_nonce(): void {
		$store = new SecretStore( 'salt-one' );
		$a     = $store->encrypt( 'sk-ant-secret' );
		$b     = $store->encrypt( 'sk-ant-secret' );

		$this->assertNotSame( $a, $b );
		$this->assertStringNotContainsString( 'sk-ant', $a );
		$this->assertSame( 'sk-ant-secret', $store->decrypt( $a ) );
	}

	public function test_rejects_tampered_short_and_foreign_ciphertext(): void {
		$store   = new SecretStore( 'salt-one' );
		$cipher  = $store->encrypt( 'sk-ant-secret' );
		$raw     = base64_decode( $cipher ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Tamper test.
		$raw[30] = chr( ord( $raw[30] ) ^ 1 );

		$this->assertNull( $store->decrypt( base64_encode( $raw ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Tamper test.
		$this->assertNull( $store->decrypt( 'c2hvcnQ=' ) );
		$this->assertNull( $store->decrypt( 'not base64 !!' ) );
		$this->assertNull( ( new SecretStore( 'salt-two' ) )->decrypt( $cipher ) );
	}
}
