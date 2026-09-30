/**
 * Drag handles over the character preview: hands and feet bend the arms and legs (two-bone reach),
 * elbows and knees swing a limb, the orange dot leans the whole body and the purple dot tilts the head.
 * Angles are screen angles in degrees (0 points down, 90 right, 180 up), the same the server draws with.
 */
import { useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const RAD = Math.PI / 180;

const angleTo = ( from, to ) =>
	Math.atan2( to[ 0 ] - from[ 0 ], to[ 1 ] - from[ 1 ] ) / RAD;

const wrap = ( degrees ) => ( ( degrees + 540 ) % 360 ) - 180;

const round = ( value ) => Math.round( value * 10 ) / 10;

/**
 * Two bones from a joint (shoulder or hip) reaching a point: returns the angles of the upper and lower
 * bone, bending the same way as the current pose.
 *
 * @param {number[]} root    Shoulder or hip position.
 * @param {number[]} target  Where the hand or foot should go.
 * @param {number}   upper   Upper bone length.
 * @param {number}   lower   Lower bone length.
 * @param {number[]} current Current [upper, lower] angles.
 */
export function reach( root, target, upper, lower, current ) {
	const distance = Math.hypot(
		target[ 0 ] - root[ 0 ],
		target[ 1 ] - root[ 1 ]
	);
	const d = Math.max(
		Math.abs( upper - lower ) + 0.5,
		Math.min( upper + lower - 0.5, distance )
	);
	const base = angleTo( root, target );

	const elbow = [
		root[ 0 ] + upper * Math.sin( current[ 0 ] * RAD ),
		root[ 1 ] + upper * Math.cos( current[ 0 ] * RAD ),
	];
	const hand = [
		elbow[ 0 ] + lower * Math.sin( current[ 1 ] * RAD ),
		elbow[ 1 ] + lower * Math.cos( current[ 1 ] * RAD ),
	];
	const bend = wrap( current[ 0 ] - angleTo( root, hand ) ) >= 0 ? 1 : -1;

	const cosine =
		( upper * upper + d * d - lower * lower ) / ( 2 * upper * d );
	const opening = Math.acos( Math.max( -1, Math.min( 1, cosine ) ) ) / RAD;
	const first = base + bend * opening;
	const joint = [
		root[ 0 ] + upper * Math.sin( first * RAD ),
		root[ 1 ] + upper * Math.cos( first * RAD ),
	];
	const clamped = [
		root[ 0 ] + d * Math.sin( base * RAD ),
		root[ 1 ] + d * Math.cos( base * RAD ),
	];

	return [
		round( wrap( first ) ),
		round( wrap( angleTo( joint, clamped ) ) ),
	];
}

export default function PoseEditor( { pose, custom, onStart, onChange } ) {
	const svg = useRef( null );
	const drag = useRef( null );
	const { joints, lengths, frame } = pose;

	const point = ( event ) => {
		const box = svg.current.getBoundingClientRect();
		return [
			( ( event.clientX - box.left ) / box.width ) * frame,
			( ( event.clientY - box.top ) / box.height ) * frame,
		];
	};

	const apply = ( target ) => {
		const { kind, side, start } = drag.current;
		const next = JSON.parse( JSON.stringify( custom ) );
		const j = start[ side ];

		if ( 'wrist' === kind ) {
			next.arms[ side ] = reach(
				j.shoulder,
				target,
				lengths.arm[ 0 ],
				lengths.arm[ 1 ],
				next.arms[ side ]
			);
		} else if ( 'elbow' === kind ) {
			next.arms[ side ][ 0 ] = round( angleTo( j.shoulder, target ) );
		} else if ( 'ankle' === kind ) {
			const [ upper, lower ] = lengths.legs[ side ];
			const [ a, b ] = reach(
				j.hip,
				target,
				upper,
				lower,
				next.legs[ side ]
			);
			next.legs[ side ] = [ a, b, next.legs[ side ][ 2 ] ];
		} else if ( 'knee' === kind ) {
			next.legs[ side ][ 0 ] = round( angleTo( j.hip, target ) );
		} else if ( 'body' === kind ) {
			const p = start.pivot;
			next.theta = Math.max(
				-180,
				Math.min(
					180,
					round(
						Math.atan2(
							target[ 0 ] - p[ 0 ],
							p[ 1 ] - target[ 1 ]
						) / RAD
					)
				)
			);
		} else if ( 'head' === kind ) {
			const n = start.neck;
			const absolute =
				Math.atan2( target[ 0 ] - n[ 0 ], n[ 1 ] - target[ 1 ] ) / RAD;
			next.head = Math.max(
				-90,
				Math.min( 90, round( wrap( absolute - custom.theta ) ) )
			);
		}
		onChange( next );
	};

	const down = ( kind, side ) => ( event ) => {
		event.preventDefault();
		event.currentTarget.setPointerCapture( event.pointerId );
		drag.current = { kind, side, start: joints };
		onStart();
	};
	const move = ( event ) => {
		if ( drag.current ) {
			apply( point( event ) );
		}
	};
	const up = () => {
		drag.current = null;
	};

	const dots = [];
	[ 'l', 'r' ].forEach( ( side ) => {
		const j = joints[ side ];
		dots.push(
			{ kind: 'wrist', side, at: j.wrist, r: 6.5, tone: 'hand' },
			{ kind: 'ankle', side, at: j.ankle, r: 6.5, tone: 'foot' },
			{ kind: 'elbow', side, at: j.elbow, r: 4, tone: 'joint' },
			{ kind: 'knee', side, at: j.knee, r: 4, tone: 'joint' }
		);
	} );
	dots.push(
		{ kind: 'body', side: 'l', at: joints.chest, r: 7, tone: 'body' },
		{ kind: 'head', side: 'l', at: joints.stalk, r: 7, tone: 'head' }
	);

	const labels = {
		wrist: __( 'Move hand', 'sprint-illustrations' ),
		ankle: __( 'Move foot', 'sprint-illustrations' ),
		elbow: __( 'Bend elbow', 'sprint-illustrations' ),
		knee: __( 'Bend knee', 'sprint-illustrations' ),
		body: __( 'Turn body', 'sprint-illustrations' ),
		head: __( 'Turn head', 'sprint-illustrations' ),
	};

	return (
		<svg
			ref={ svg }
			className="si-c-handles"
			viewBox={ `0 0 ${ frame } ${ frame }` }
			onPointerMove={ move }
			onPointerUp={ up }
			onPointerCancel={ up }
		>
			<line
				className="si-c-stalk"
				x1={ joints.neck[ 0 ] }
				y1={ joints.neck[ 1 ] }
				x2={ joints.stalk[ 0 ] }
				y2={ joints.stalk[ 1 ] }
			/>
			{ [ 'l', 'r' ].map( ( side ) => {
				const j = joints[ side ];
				return (
					<g key={ side } className="si-c-bones">
						<polyline
							points={ [ j.shoulder, j.elbow, j.wrist ]
								.map( ( p ) => p.join( ',' ) )
								.join( ' ' ) }
						/>
						<polyline
							points={ [ j.hip, j.knee, j.ankle ]
								.map( ( p ) => p.join( ',' ) )
								.join( ' ' ) }
						/>
					</g>
				);
			} ) }
			{ dots.map( ( dot ) => (
				<circle
					key={ `${ dot.kind }-${ dot.side }` }
					className={ `si-c-handle si-c-handle--${ dot.tone }` }
					cx={ dot.at[ 0 ] }
					cy={ dot.at[ 1 ] }
					r={ dot.r }
					onPointerDown={ down( dot.kind, dot.side ) }
				>
					<title>{ labels[ dot.kind ] }</title>
				</circle>
			) ) }
		</svg>
	);
}
