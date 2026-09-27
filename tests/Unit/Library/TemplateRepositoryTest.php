<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Library;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Library\LibraryException;
use SprintIllustrations\Library\Template;
use SprintIllustrations\Library\TemplateRepository;

final class TemplateRepositoryTest extends TestCase {

	private const DIR = __DIR__ . '/../../fixtures/library/assets/templates';

	public function test_loads_and_sorts_templates(): void {
		$repository = TemplateRepository::from_directories( [ self::DIR, '/missing/dir' ] );

		$this->assertSame( [ 'fixture-duo', 'fixture-hero', 'fixture-object' ], $repository->ids() );
		$this->assertNull( $repository->get( 'nope' ) );
	}

	public function test_slot_defaults_and_parsing(): void {
		$hero = TemplateRepository::from_directories( [ self::DIR ] )->get( 'fixture-hero' );

		$this->assertSame( [ 400.0, 300.0 ], $hero->canvas );
		$this->assertSame( 1.0, $hero->unit );

		[ $bg, $subject, $prop, $side, $decor ] = $hero->slots;

		$this->assertSame( 'contain', $bg->fit );
		$this->assertTrue( $bg->required );
		$this->assertSame( 'natural', $subject->fit );
		$this->assertSame( [ 0.9, 1.1 ], $subject->scale );
		$this->assertSame( 'subject', $prop->attach_to );
		$this->assertSame( 'hold', $prop->attach_anchor );
		$this->assertNull( $prop->box );
		$this->assertSame( [ 'floor' ], $side->require_tags );
		$this->assertSame( [ 2, 3 ], $decor->count );
		$this->assertTrue( $decor->scatter );
		$this->assertTrue( $decor->is_multiple() );
		$this->assertFalse( $subject->is_multiple() );
	}

	/**
	 * @dataProvider invalid_templates
	 */
	public function test_invalid_templates_throw( array $data ): void {
		$this->expectException( LibraryException::class );
		Template::from_array( $data );
	}

	public static function invalid_templates(): array {
		$slot = [
			'name'     => 'a',
			'category' => 'objects',
			'box'      => [ 0, 0, 10, 10 ],
		];
		$base = [
			'id'     => 't',
			'canvas' => [ 100, 100 ],
			'slots'  => [ $slot ],
		];

		return [
			'bad id'           => [ [ 'id' => 'Bad ID' ] + $base ],
			'bad canvas'       => [ [ 'canvas' => [ 100 ] ] + $base ],
			'no slots'         => [ [ 'slots' => [] ] + $base ],
			'duplicate slot'   => [ [ 'slots' => [ $slot, $slot ] ] + $base ],
			'box and attach'   => [
				[
					'slots' => [
						$slot + [
							'attach' => [
								'to'     => 'x',
								'anchor' => 'y',
							],
						],
					],
				] + $base,
			],
			'neither'          => [ [ 'slots' => [ array_diff_key( $slot, [ 'box' => 1 ] ) ] ] + $base ],
			'attach to later'  => [
				[
					'slots' => [
						[
							'name'     => 'p',
							'category' => 'objects',
							'attach'   => [
								'to'     => 'a',
								'anchor' => 'hold',
							],
						],
						$slot,
					],
				] + $base,
			],
			'unknown category' => [ [ 'slots' => [ [ 'category' => 'monsters' ] + $slot ] ] + $base ],
			'zero-size box'    => [ [ 'slots' => [ [ 'box' => [ 0, 0, 0, 10 ] ] + $slot ] ] + $base ],
		];
	}
}
