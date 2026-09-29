<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Compose;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Compose\Animation;
use SprintIllustrations\Compose\SceneSpec;
use SprintIllustrations\Palette\Palette;
use SprintIllustrations\Services;

final class AnimationTest extends TestCase {

	public function test_normalize_cleans_and_drops_empty_entries(): void {
		$this->assertSame(
			[
				'left'   => [
					'enter' => 'rise',
					'loop'  => 'float',
					'delay' => 0.5,
					'speed' => 'normal',
				],
				'item:2' => [
					'enter' => 'none',
					'loop'  => 'spin',
					'delay' => 3.0,
					'speed' => 'fast',
				],
			],
			Animation::normalize(
				[
					'left'   => [
						'enter' => 'rise',
						'loop'  => 'float',
						'delay' => 0.54,
					],
					'item:2' => [
						'loop'  => 'spin',
						'delay' => 9,
						'speed' => 'fast',
					],
					'bg'     => [
						'enter' => 'none',
						'loop'  => 'none',
					],
					'Bad!'   => [ 'loop' => 'float' ],
					'right'  => [ 'loop' => 'explode' ],
					'decor'  => 'float',
				]
			)
		);
	}

	public function test_classes(): void {
		$this->assertSame(
			[
				'outer' => 'si-a-enter-pop si-a-d-12 si-a-s-slow',
				'inner' => 'si-a-loop-sway',
			],
			Animation::classes(
				[
					'enter' => 'pop',
					'loop'  => 'sway',
					'delay' => 1.2,
					'speed' => 'slow',
				]
			)
		);
		$this->assertSame(
			[
				'outer' => 'si-a-d-0 si-a-s-normal',
				'inner' => 'si-a-loop-twinkle',
			],
			Animation::classes(
				[
					'enter' => 'none',
					'loop'  => 'twinkle',
					'delay' => 0.0,
					'speed' => 'normal',
				]
			)
		);
	}

	public function test_spec_keeps_animations_only_when_set(): void {
		$this->assertArrayNotHasKey( 'animations', SceneSpec::from_array( [ 'animations' => [] ] )->to_array() );
		$spec = SceneSpec::from_array( [ 'animations' => [ 'left' => [ 'loop' => 'float' ] ] ] );
		$this->assertSame( $spec->to_array(), SceneSpec::from_array( $spec->to_array() )->to_array() );
		$this->assertSame( 'float', $spec->animations['left']['loop'] );
	}

	public function test_animated_layer_is_wrapped_and_contiguous(): void {
		$services = Services::create( __DIR__ . '/../../fixtures/library' );
		$plain    = $services->composer->compose(
			SceneSpec::from_array(
				[
					'template' => 'fixture-duo',
					'seed'     => 3,
				]
			),
			Palette::default()
		);
		$animated = $services->composer->compose(
			SceneSpec::from_array(
				[
					'template'   => 'fixture-duo',
					'seed'       => 3,
					'animations' => [
						'left' => [
							'enter' => 'rise',
							'loop'  => 'float',
						],
					],
				]
			),
			Palette::default()
		);

		$this->assertStringNotContainsString( 'si-a', $plain->markup );
		$this->assertSame( 1, substr_count( $animated->markup, '<g class="si-a si-a-layer si-a-enter-rise si-a-d-0 si-a-s-normal"><g class="si-a-loop-float">' ) );
		// Left and its prop are both inside the wrapper, one placement group each.
		$doc = new \DOMDocument();
		$doc->loadXML( $animated->markup );
		$inner = ( new \DOMXPath( $doc ) )->query( '//*[@class="si-a-loop-float"]' )->item( 0 );
		$kids  = array_filter( iterator_to_array( $inner->childNodes ), static fn( $n ) => $n instanceof \DOMElement );
		$this->assertCount( 2, $kids );
	}
}
