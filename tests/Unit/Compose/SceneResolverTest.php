<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Compose;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Compose\CompositionException;
use SprintIllustrations\Compose\Placement;
use SprintIllustrations\Compose\ResolvedScene;
use SprintIllustrations\Compose\SceneResolver;
use SprintIllustrations\Compose\SceneSpec;
use SprintIllustrations\Library\Manifest;
use SprintIllustrations\Library\Template;
use SprintIllustrations\Library\TemplateRepository;

final class SceneResolverTest extends TestCase {

	private const ASSETS = __DIR__ . '/../../fixtures/library/assets';

	private Manifest $manifest;
	private TemplateRepository $templates;

	protected function setUp(): void {
		$this->manifest  = Manifest::from_files( [ self::ASSETS . '/manifest.json' ] );
		$this->templates = TemplateRepository::from_directories( [ self::ASSETS . '/templates' ] );
	}

	private function resolve( string $template, array $spec = [], array $tokens = [] ): ResolvedScene {
		return ( new SceneResolver( $this->manifest ) )->resolve(
			$this->templates->get( $template ),
			SceneSpec::from_array( $spec + [ 'template' => $template ] ),
			$tokens
		);
	}

	private function by_slot( ResolvedScene $scene, string $slot ): array {
		return array_values( array_filter( $scene->placements, static fn( Placement $p ) => $p->slot === $slot ) );
	}

	private function signature( ResolvedScene $scene ): array {
		return array_map( static fn( Placement $p ) => [ $p->slot, $p->piece->id, $p->transform(), $p->z ], $scene->placements );
	}

	public function test_same_seed_is_deterministic(): void {
		$this->assertSame(
			$this->signature( $this->resolve( 'fixture-hero', [ 'seed' => 42 ] ) ),
			$this->signature( $this->resolve( 'fixture-hero', [ 'seed' => 42 ] ) )
		);
	}

	public function test_different_seeds_vary(): void {
		$signatures = [];
		for ( $seed = 1; $seed <= 10; $seed++ ) {
			$signatures[] = json_encode( $this->signature( $this->resolve( 'fixture-hero', [ 'seed' => $seed ] ) ) );
		}

		$this->assertGreaterThan( 5, count( array_unique( $signatures ) ) );
	}

	public function test_placements_sorted_back_to_front(): void {
		$z      = array_map( static fn( Placement $p ) => $p->z, $this->resolve( 'fixture-hero', [ 'seed' => 3 ] )->placements );
		$sorted = $z;
		sort( $sorted );

		$this->assertSame( $sorted, $z );
	}

	public function test_background_contains_and_subject_is_bottom_aligned(): void {
		$scene   = $this->resolve( 'fixture-hero', [ 'seed' => 9 ] );
		$bg      = $this->by_slot( $scene, 'bg' )[0];
		$subject = $this->by_slot( $scene, 'subject' )[0];

		$this->assertEqualsWithDelta( 2.0, $bg->scale, 0.0001 );
		$this->assertEqualsWithDelta( 300.0, $subject->y + $subject->height(), 0.0001 );
		$this->assertGreaterThanOrEqual( 0.9, $subject->scale );
		$this->assertLessThanOrEqual( 1.1, $subject->scale );
	}

	public function test_attached_prop_only_uses_compatible_mounts_and_lands_on_anchor(): void {
		for ( $seed = 1; $seed <= 20; $seed++ ) {
			$scene   = $this->resolve( 'fixture-hero', [ 'seed' => $seed ] );
			$subject = $this->by_slot( $scene, 'subject' )[0];
			$prop    = $this->by_slot( $scene, 'prop' )[0];

			$this->assertSame( 'obj-mug', $prop->piece->id, 'Only the mug mounts as handheld.' );
			$this->assertSame( $subject->z + 1, $prop->z );

			$hand = $subject->anchor_point( 'hold' );
			$grip = $prop->anchor_point( 'grip' );
			$this->assertEqualsWithDelta( $hand[0], $grip[0], 0.0001 );
			$this->assertEqualsWithDelta( $hand[1], $grip[1], 0.0001 );
		}
	}

	public function test_require_tags_filter_candidates(): void {
		for ( $seed = 1; $seed <= 20; $seed++ ) {
			$side = $this->by_slot( $this->resolve( 'fixture-hero', [ 'seed' => $seed ] ), 'side' )[0];
			$this->assertContains( $side->piece->id, [ 'obj-plant', 'obj-orb' ] );
		}
	}

	public function test_keyword_tokens_steer_selection(): void {
		for ( $seed = 1; $seed <= 20; $seed++ ) {
			$subject = $this->by_slot( $this->resolve( 'fixture-hero', [ 'seed' => $seed ], [ 'running', 'sport' ] ), 'subject' )[0];
			$this->assertSame( 'char-runner', $subject->piece->id );
		}
	}

	public function test_scatter_count_range_and_avoid_box(): void {
		for ( $seed = 1; $seed <= 20; $seed++ ) {
			$decor = $this->by_slot( $this->resolve( 'fixture-hero', [ 'seed' => $seed ] ), 'decor' );

			$this->assertGreaterThanOrEqual( 2, count( $decor ) );
			$this->assertLessThanOrEqual( 3, count( $decor ) );

			foreach ( $decor as $item ) {
				$cx = $item->x + $item->width() / 2;
				$cy = $item->y + $item->height() / 2;
				$this->assertFalse( $cx >= 40 && $cx <= 200 && $cy >= 60 && $cy <= 300, "Decor centre inside avoid box (seed $seed)." );
			}
		}
	}

	public function test_explicit_picks_are_honoured_and_echoed(): void {
		$scene = $this->resolve(
			'fixture-hero',
			[
				'seed'  => 4,
				'picks' => [
					'subject' => 'char-runner',
					'decor'   => [ 'decor-dot', 'decor-dot', 'decor-dot', 'decor-dot' ],
				],
			]
		);

		$this->assertSame( 'char-runner', $scene->picks['subject'] );
		$this->assertCount( 4, $this->by_slot( $scene, 'decor' ) );
		$this->assertSame( [ 'decor-dot', 'decor-dot', 'decor-dot', 'decor-dot' ], $scene->picks['decor'] );
		$this->assertSame( [], $scene->warnings );
	}

	public function test_invalid_picks_warn_and_fall_back(): void {
		$scene = $this->resolve(
			'fixture-hero',
			[
				'seed'  => 4,
				'picks' => [
					'subject' => 'obj-mug',
					'prop'    => 'obj-plant',
					'side'    => 'nope',
				],
			]
		);

		$this->assertCount( 3, $scene->warnings );
		$this->assertStringStartsWith( 'char-', $scene->picks['subject'] );
		$this->assertSame( 'obj-mug', $scene->picks['prop'] );
	}

	public function test_locking_one_slot_does_not_disturb_others(): void {
		$free   = $this->resolve( 'fixture-hero', [ 'seed' => 11 ] );
		$locked = $this->resolve(
			'fixture-hero',
			[
				'seed'  => 11,
				'picks' => [ 'side' => 'obj-orb' ],
			]
		);

		foreach ( [ 'bg', 'subject', 'decor' ] as $slot ) {
			$this->assertSame(
				array_map( static fn( Placement $p ) => [ $p->piece->id, $p->transform() ], $this->by_slot( $free, $slot ) ),
				array_map( static fn( Placement $p ) => [ $p->piece->id, $p->transform() ], $this->by_slot( $locked, $slot ) ),
				$slot
			);
		}
	}

	public function test_flipped_slot_mirrors_piece_and_attachment(): void {
		$scene = $this->resolve( 'fixture-duo', [ 'seed' => 2 ] );
		$right = $this->by_slot( $scene, 'right' )[0];
		$prop  = $this->by_slot( $scene, 'right-prop' )[0];

		$this->assertTrue( $right->flip );
		$this->assertTrue( $prop->flip );
		$this->assertStringContainsString( 'scale(-', $right->transform() );

		$hand = $right->anchor_point( 'hold' );
		$grip = $prop->anchor_point( 'grip' );
		$this->assertEqualsWithDelta( $hand[0], $grip[0], 0.0001 );
		$this->assertEqualsWithDelta( $hand[1], $grip[1], 0.0001 );

		$box_centre = 220 + 160 / 2;
		$this->assertEqualsWithDelta( $box_centre, $right->x + $right->width() / 2, 0.0001 );
	}

	public function test_two_character_slots_never_share_a_person(): void {
		for ( $seed = 1; $seed <= 50; $seed++ ) {
			$scene = $this->resolve( 'fixture-duo', [ 'seed' => $seed ] );
			$left  = $this->by_slot( $scene, 'left' )[0]->piece->person;
			$right = $this->by_slot( $scene, 'right' )[0]->piece->person;

			$this->assertNotSame( $left, $right, "seed $seed" );
		}
	}

	public function test_explicit_pick_person_is_avoided_by_auto_slots(): void {
		for ( $seed = 1; $seed <= 20; $seed++ ) {
			$scene = $this->resolve(
				'fixture-duo',
				[
					'seed'  => $seed,
					'picks' => [ 'left' => 'char-stick' ],
				]
			);
			$this->assertSame( 'char-stick', $this->by_slot( $scene, 'left' )[0]->piece->id );
			$this->assertSame( 'char-runner', $this->by_slot( $scene, 'right' )[0]->piece->id, "seed $seed" );
		}
	}

	public function test_single_person_library_repeats_instead_of_failing(): void {
		$file           = tempnam( sys_get_temp_dir(), 'si-manifest' );
		$data           = json_decode( (string) file_get_contents( self::ASSETS . '/manifest.json' ), true );
		$data['pieces'] = array_values( array_filter( $data['pieces'], static fn( $p ) => 'char-runner' !== $p['id'] ) );
		file_put_contents( $file, (string) json_encode( $data ) );

		$scene = ( new SceneResolver( Manifest::from_files( [ $file ] ) ) )->resolve( $this->templates->get( 'fixture-duo' ), SceneSpec::from_array( [ 'template' => 'fixture-duo' ] ), [] );
		unlink( $file );

		$this->assertSame( 'char-stick', $this->by_slot( $scene, 'left' )[0]->piece->id );
		$this->assertSame( 'char-stick', $this->by_slot( $scene, 'right' )[0]->piece->id );
		$this->assertSame( [], $scene->warnings );
	}

	public function test_required_slot_without_candidates_throws(): void {
		$template = Template::from_array(
			[
				'id'     => 'impossible',
				'canvas' => [ 100, 100 ],
				'slots'  => [
					[
						'name'         => 'x',
						'category'     => 'objects',
						'box'          => [ 0, 0, 100, 100 ],
						'require_tags' => [ 'unicorn' ],
						'required'     => true,
					],
				],
			]
		);

		$this->expectException( CompositionException::class );
		( new SceneResolver( $this->manifest ) )->resolve( $template, SceneSpec::from_array( [] ), [] );
	}

	public function test_optional_empty_slot_is_skipped(): void {
		$template = Template::from_array(
			[
				'id'     => 'optional',
				'canvas' => [ 100, 100 ],
				'slots'  => [
					[
						'name'     => 'bg',
						'category' => 'backgrounds',
						'box'      => [ 0, 0, 100, 100 ],
					],
					[
						'name'         => 'x',
						'category'     => 'objects',
						'box'          => [ 0, 0, 100, 100 ],
						'require_tags' => [ 'unicorn' ],
					],
				],
			]
		);

		$scene = ( new SceneResolver( $this->manifest ) )->resolve( $template, SceneSpec::from_array( [] ), [] );

		$this->assertCount( 1, $scene->placements );
		$this->assertArrayNotHasKey( 'x', $scene->picks );
	}
}
