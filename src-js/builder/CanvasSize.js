/**
 * Canvas width and height, with presets. "Template" goes back to the template's own size.
 */
import { __ } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import { Button } from '@wordpress/components';

const MIN = 200;
const MAX = 4000;

const PRESETS = [
	{ label: __( 'Social', 'sprint-illustrations' ), size: [ 1200, 630 ] },
	{ label: __( 'Square', 'sprint-illustrations' ), size: [ 1080, 1080 ] },
	{ label: __( 'Wide', 'sprint-illustrations' ), size: [ 1200, 675 ] },
];

const clamp = ( value ) =>
	Math.max( MIN, Math.min( MAX, Math.round( Number( value ) || MIN ) ) );

export default function CanvasSize( { canvas, custom, dispatch } ) {
	const [ w, setW ] = useState( String( canvas[ 0 ] ) );
	const [ h, setH ] = useState( String( canvas[ 1 ] ) );

	useEffect( () => {
		setW( String( canvas[ 0 ] ) );
		setH( String( canvas[ 1 ] ) );
	}, [ canvas[ 0 ], canvas[ 1 ] ] ); // eslint-disable-line react-hooks/exhaustive-deps

	const apply = () => {
		const next = [ clamp( w ), clamp( h ) ];
		setW( String( next[ 0 ] ) );
		setH( String( next[ 1 ] ) );
		if ( next[ 0 ] !== canvas[ 0 ] || next[ 1 ] !== canvas[ 1 ] ) {
			dispatch( { type: 'SET_CANVAS', canvas: next } );
		}
	};

	const field = ( label, id, value, set ) => (
		<span className="si-b-size__field">
			<label htmlFor={ id }>{ label }</label>
			<input
				id={ id }
				type="number"
				min={ MIN }
				max={ MAX }
				step="10"
				value={ value }
				onChange={ ( event ) => set( event.target.value ) }
				onBlur={ apply }
				onKeyDown={ ( event ) => 'Enter' === event.key && apply() }
			/>
		</span>
	);

	const same = ( size ) =>
		custom && size[ 0 ] === canvas[ 0 ] && size[ 1 ] === canvas[ 1 ];

	return (
		<div
			className="si-b-size"
			role="group"
			aria-label={ __( 'Canvas size', 'sprint-illustrations' ) }
		>
			{ field(
				__( 'Width', 'sprint-illustrations' ),
				'si-b-size-w',
				w,
				setW
			) }
			<span className="si-b-size__times" aria-hidden="true">
				×
			</span>
			{ field(
				__( 'Height', 'sprint-illustrations' ),
				'si-b-size-h',
				h,
				setH
			) }
			<div className="si-b-size__presets">
				<Button
					size="small"
					variant="tertiary"
					isPressed={ ! custom }
					onClick={ () =>
						dispatch( { type: 'SET_CANVAS', canvas: null } )
					}
				>
					{ __( 'Template', 'sprint-illustrations' ) }
				</Button>
				{ PRESETS.map( ( preset ) => (
					<Button
						key={ preset.label }
						size="small"
						variant="tertiary"
						isPressed={ same( preset.size ) }
						onClick={ () =>
							dispatch( {
								type: 'SET_CANVAS',
								canvas: preset.size,
							} )
						}
					>
						{ preset.label }
						<span className="si-b-size__dims">
							{ preset.size.join( '×' ) }
						</span>
					</Button>
				) ) }
			</div>
		</div>
	);
}
