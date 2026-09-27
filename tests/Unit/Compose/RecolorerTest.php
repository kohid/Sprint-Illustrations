<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Compose;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Compose\Recolorer;
use SprintIllustrations\Palette\Palette;
use SprintIllustrations\Svg\SvgDom;

final class RecolorerTest extends TestCase {

	/**
	 * @dataProvider tokens
	 */
	public function test_parse_token( string $token, ?array $expected ): void {
		$this->assertSame( $expected, Recolorer::parse_token( $token ) );
	}

	public static function tokens(): array {
		return [
			'fill'             => [
				'slot-primary',
				[
					'prop'    => 'fill',
					'name'    => 'primary',
					'variant' => '',
				],
			],
			'fill variant'     => [
				'slot-primary-dark',
				[
					'prop'    => 'fill',
					'name'    => 'primary',
					'variant' => 'dark',
				],
			],
			'stroke'           => [
				'slot-stroke-accent',
				[
					'prop'    => 'stroke',
					'name'    => 'accent',
					'variant' => '',
				],
			],
			'stroke var.'      => [
				'slot-stroke-neutral-light',
				[
					'prop'    => 'stroke',
					'name'    => 'neutral',
					'variant' => 'light',
				],
			],
			'outline'          => [
				'slot-outline',
				[
					'prop'    => 'stroke',
					'name'    => 'outline',
					'variant' => '',
				],
			],
			'skin'             => [
				'slot-skin-dark',
				[
					'prop'    => 'fill',
					'name'    => 'skin',
					'variant' => 'dark',
				],
			],
			'unknown slot'     => [ 'slot-banana', null ],
			'not a slot'       => [ 'hero', null ],
			'trailing newline' => [ "slot-primary-dark\n", null ],
		];
	}

	public function test_apply_sets_colours_and_strips_slot_classes(): void {
		$doc = SvgDom::parse(
			'<svg xmlns="http://www.w3.org/2000/svg"><g class="slot-primary keep-me"><rect class="slot-stroke-accent slot-secondary-light"/><path class="slot-outline"/><circle class="slot-skin"/><rect class="slot-banana"/></g></svg>'
		);

		( new Recolorer() )->apply( $doc->documentElement, Palette::from_array( [ 'skin' => [ '#111111', '#222222' ] ] ), 1 );

		$xml = $doc->saveXML( $doc->documentElement );

		$this->assertStringContainsString( '<g class="keep-me" fill="#5b5bd6">', $xml );
		$this->assertStringContainsString( 'stroke="#ff6b6b"', $xml );
		$this->assertStringContainsString( 'fill="' . Palette::default()->resolve( 'secondary', 'light' ) . '"', $xml );
		$this->assertStringContainsString( '<path stroke="#2b2d42"/>', $xml );
		$this->assertStringContainsString( '<circle fill="#222222"/>', $xml );
		$this->assertStringNotContainsString( 'slot-', $xml );
	}
}
