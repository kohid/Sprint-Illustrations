<?php
/**
 * Body positions other than plain standing and sitting on a chair: the joint angles that pose the legs and
 * body, and where the drawing sits on its canvas.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Character;

/**
 * Angles are "screen" angles in degrees: 0 points straight down, 90 to the right, 180 up, negative to the
 * left. A leg is [thigh, shin, toe, direction, thigh length, shin length, extra y offset]: the toe angle
 * tips the shoe (positive = toe down), direction is the way the toes point (-1 left, 1 right).
 *
 * `theta` turns the upper body (clockwise) about the hips, `dx` moves the figure across the canvas, and
 * `contact` lists the parts that touch the ground, from which the figure is dropped onto the floor (or
 * `dy` fixes it). `head` counter-turns the head so the face stays readable, `family` says which built-in
 * arm poses (standing or sitting) the stance may use besides its own.
 */
final class Stances {

	public const DATA = [
		'walking'   => [
			'family'  => 'standing',
			'vb'      => [ 160, 320 ],
			'dx'      => 0,
			'theta'   => 3,
			'accepts' => 'handheld',
			'legs'    => [
				'l' => [ -12, -24, 30, -1 ],
				'r' => [ 16, 6, 0, 1 ],
			],
			'contact' => [ [ 'ankle', 10.5 ] ],
		],
		'running'   => [
			'family'  => 'standing',
			'vb'      => [ 262, 276 ],
			'dx'      => 67,
			'theta'   => 14,
			'accepts' => 'handheld',
			'legs'    => [
				'l' => [ -30, -85, 60, -1 ],
				'r' => [ 75, 8, 0, 1 ],
			],
			'contact' => [ [ 'ankle', 10.5 ] ],
		],
		'leaning'   => [
			'family'  => 'standing',
			'vb'      => [ 190, 320 ],
			'dx'      => 0,
			'theta'   => 10,
			'accepts' => 'handheld',
			'legs'    => [
				'l' => [ -9, -9, 0, -1 ],
				'r' => [ -9, -9, 0, 1 ],
			],
			'contact' => [ [ 'ankle', 10.5 ] ],
		],
		'balancing' => [
			'family'  => 'standing',
			'vb'      => [ 232, 320 ],
			'dx'      => 35,
			'theta'   => 0,
			'accepts' => 'handheld',
			'legs'    => [
				'l' => [ 0, 0, 0, -1 ],
				'r' => [ 62, 5, 20, 1 ],
			],
			'contact' => [ [ 'ankle', 10.5 ] ],
		],
		'bending'   => [
			'family'  => 'standing',
			'vb'      => [ 220, 272 ],
			'dx'      => -10,
			'theta'   => 62,
			'head'    => -35,
			'accepts' => 'handheld',
			'legs'    => [
				'l' => [ -2, -1, 0, -1 ],
				'r' => [ 2, 1, 0, 1 ],
			],
			'contact' => [ [ 'ankle', 10.5 ] ],
		],
		'stooping'  => [
			'family'  => 'standing',
			'vb'      => [ 185, 305 ],
			'dx'      => -10,
			'theta'   => 30,
			'head'    => -18,
			'accepts' => 'handheld',
			'legs'    => [
				'l' => [ -8, -2, 0, -1 ],
				'r' => [ 12, -5, 0, 1 ],
			],
			'contact' => [ [ 'ankle', 10.5 ] ],
		],
		'squatting' => [
			'family'  => 'standing',
			'vb'      => [ 200, 288 ],
			'dx'      => 20,
			'theta'   => 0,
			'accepts' => 'handheld',
			'legs'    => [
				'l' => [ -62, 8, 0, -1 ],
				'r' => [ 62, -8, 0, 1 ],
			],
			'contact' => [ [ 'ankle', 10.5 ] ],
		],
		'crouching' => [
			'family'  => 'standing',
			'vb'      => [ 200, 305 ],
			'dx'      => 20,
			'theta'   => 0,
			'accepts' => 'handheld',
			'legs'    => [
				'l' => [ -40, 4, 0, -1 ],
				'r' => [ 40, -4, 0, 1 ],
			],
			'contact' => [ [ 'ankle', 10.5 ] ],
		],
		'kneeling'  => [
			'family'  => 'sitting',
			'vb'      => [ 200, 255 ],
			'dx'      => 25,
			'theta'   => 0,
			'accepts' => 'handheld',
			'legs'    => [
				'l' => [ 2, -90, 60, -1 ],
				'r' => [ 5, -90, 60, -1, 74, 72, 5 ],
			],
			'contact' => [ [ 'knee', 8 ], [ 'ankle', 9 ] ],
		],
		'sit_cross' => [
			'family'  => 'sitting',
			'vb'      => [ 170, 178 ],
			'dx'      => 5,
			'theta'   => 0,
			'accepts' => 'lap',
			'legs'    => [
				'l' => [ -100, 80, 0, -1, 55, 58 ],
				'r' => [ 100, -80, 0, 1, 55, 58 ],
			],
			'contact' => [ [ 'hip', 12 ], [ 'ankle', 10.5 ] ],
		],
		'sit_legs'  => [
			'family'  => 'sitting',
			'vb'      => [ 230, 190 ],
			'dx'      => -30,
			'theta'   => 0,
			'accepts' => 'lap',
			'legs'    => [
				'l' => [ 88, 89, -35, 1 ],
				'r' => [ 86, 90, -35, 1, 74, 72, 6 ],
			],
			'contact' => [ [ 'hip', 12 ], [ 'ankle', 10 ] ],
		],
		'reclining' => [
			'family'  => 'sitting',
			'vb'      => [ 320, 140 ],
			'dx'      => 62,
			'theta'   => -55,
			'head'    => 25,
			'accepts' => 'lap',
			'legs'    => [
				'l' => [ 86, 88, -35, 1 ],
				'r' => [ 82, 88, -35, 1, 74, 72, 6 ],
			],
			'contact' => [ [ 'hip', 12 ], [ 'ankle', 10 ] ],
		],
		'all_fours' => [
			'family'  => 'standing',
			'vb'      => [ 265, 160 ],
			'dx'      => 30,
			'theta'   => 76,
			'head'    => -55,
			'accepts' => 'lap',
			'legs'    => [
				'l' => [ 1, -90, 60, -1 ],
				'r' => [ 3, -90, 60, -1, 74, 72, 5 ],
			],
			'contact' => [ [ 'knee', 8 ], [ 'wrist', 6 ], [ 'ankle', 10 ] ],
		],
		'crawling'  => [
			'family'  => 'standing',
			'vb'      => [ 265, 170 ],
			'dx'      => 30,
			'theta'   => 74,
			'head'    => -55,
			'accepts' => 'lap',
			'legs'    => [
				'l' => [ 2, -90, 60, -1 ],
				'r' => [ 48, -68, 0, 1, 74, 72, 5 ],
			],
			'contact' => [ [ 'knee', 8 ], [ 'wrist', 6 ], [ 'ankle', 10 ] ],
		],
		'climbing'  => [
			'family'  => 'standing',
			'vb'      => [ 190, 345 ],
			'dx'      => 0,
			'theta'   => 0,
			'accepts' => 'handheld',
			'legs'    => [
				'l' => [ -8, -2, 0, -1 ],
				'r' => [ 45, -25, 0, 1 ],
			],
			'contact' => [ [ 'ankle', 10.5 ] ],
		],
		'hanging'   => [
			'family'  => 'standing',
			'vb'      => [ 160, 350 ],
			'dx'      => 0,
			'theta'   => 0,
			'dy'      => 28,
			'accepts' => 'handheld',
			'legs'    => [
				'l' => [ 3, 2, 25, -1 ],
				'r' => [ -3, -2, 25, 1 ],
			],
			'contact' => [],
		],
		'supine'    => [
			'family'  => 'standing',
			'vb'      => [ 350, 105 ],
			'dx'      => 98,
			'theta'   => -90,
			'accepts' => 'lap',
			'legs'    => [
				'l' => [ 88, 90, -75, 1 ],
				'r' => [ 92, 90, -75, 1 ],
			],
			'contact' => [ [ 'hipside', 28 ] ],
		],
		'prone'     => [
			'family'  => 'standing',
			'vb'      => [ 350, 105 ],
			'dx'      => 98,
			'theta'   => -90,
			'back'    => true,
			'accepts' => 'lap',
			'legs'    => [
				'l' => [ 88, 90, 75, 1 ],
				'r' => [ 92, 90, 75, 1 ],
			],
			'contact' => [ [ 'hipside', 28 ] ],
		],
		'side'      => [
			'family'  => 'standing',
			'vb'      => [ 335, 125 ],
			'dx'      => 86,
			'theta'   => -90,
			'head'    => 0,
			'accepts' => 'lap',
			'legs'    => [
				'l' => [ 55, 105, -20, 1 ],
				'r' => [ 68, 100, -20, 1 ],
			],
			'contact' => [ [ 'knee', 8 ], [ 'hipside', 28 ] ],
		],
		'fetal'     => [
			'family'  => 'standing',
			'vb'      => [ 262, 135 ],
			'dx'      => 70,
			'theta'   => -78,
			'accepts' => 'lap',
			'legs'    => [
				'l' => [ -100, 62, 0, 1, 52, 50 ],
				'r' => [ -105, 66, 0, 1, 52, 50 ],
			],
			'contact' => [ [ 'hipside', 28 ], [ 'ankle', 10 ] ],
		],
	];

	/**
	 * The stance's data, or null for the plain standing and sitting stances (drawn by the classic path).
	 *
	 * @param string $stance Stance.
	 * @return array<string, mixed>|null
	 */
	public static function get( string $stance ): ?array {
		return self::DATA[ $stance ] ?? null;
	}
}
