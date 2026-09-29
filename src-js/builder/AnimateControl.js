/**
 * Per-layer animation: an entrance (plays once) and/or a loop, with delay and speed.
 * The motion itself is CSS in assets/front/illustration.css, so the stage shows it live.
 */
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	Dropdown,
	RangeControl,
	SelectControl,
} from '@wordpress/components';

export const ENTRANCES = [
	{ value: 'none', label: __( 'None', 'sprint-illustrations' ) },
	{ value: 'fade', label: __( 'Fade in', 'sprint-illustrations' ) },
	{ value: 'rise', label: __( 'Rise', 'sprint-illustrations' ) },
	{ value: 'pop', label: __( 'Pop', 'sprint-illustrations' ) },
];

export const LOOPS = [
	{ value: 'none', label: __( 'None', 'sprint-illustrations' ) },
	{ value: 'float', label: __( 'Float', 'sprint-illustrations' ) },
	{ value: 'sway', label: __( 'Sway', 'sprint-illustrations' ) },
	{ value: 'pulse', label: __( 'Pulse', 'sprint-illustrations' ) },
	{ value: 'spin', label: __( 'Spin', 'sprint-illustrations' ) },
	{ value: 'twinkle', label: __( 'Twinkle', 'sprint-illustrations' ) },
];

const SPEEDS = [
	{ value: 'slow', label: __( 'Slow', 'sprint-illustrations' ) },
	{ value: 'normal', label: __( 'Normal', 'sprint-illustrations' ) },
	{ value: 'fast', label: __( 'Fast', 'sprint-illustrations' ) },
];

const DEFAULTS = { enter: 'none', loop: 'none', delay: 0, speed: 'normal' };

const labelOf = ( list, value ) =>
	list.find( ( option ) => option.value === value )?.label || value;

/**
 * Short summary for the layer row, e.g. "Rise + Float".
 *
 * @param {Object|undefined} value Animation.
 * @return {string} Summary, or '' when not animated.
 */
export function summary( value ) {
	if ( ! value ) {
		return '';
	}
	const parts = [];
	if ( 'none' !== value.enter ) {
		parts.push( labelOf( ENTRANCES, value.enter ) );
	}
	if ( 'none' !== value.loop ) {
		parts.push( labelOf( LOOPS, value.loop ) );
	}
	return parts.join( ' + ' );
}

export default function AnimateControl( { layerKey, name, value, dispatch } ) {
	const current = { ...DEFAULTS, ...( value || {} ) };
	const update = ( changes ) => {
		const next = { ...current, ...changes };
		dispatch( {
			type: 'SET_ANIMATION',
			key: layerKey,
			value: 'none' === next.enter && 'none' === next.loop ? null : next,
		} );
	};

	return (
		<Dropdown
			popoverProps={ { placement: 'right-start' } }
			renderToggle={ ( { isOpen, onToggle } ) => (
				<Button
					size="small"
					icon="controls-play"
					isPressed={ !! value }
					aria-expanded={ isOpen }
					label={ sprintf(
						/* translators: %s: layer name. */
						__( 'Animate %s', 'sprint-illustrations' ),
						name
					) }
					onClick={ onToggle }
				/>
			) }
			renderContent={ () => (
				<div className="si-b-animate">
					<SelectControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Entrance', 'sprint-illustrations' ) }
						help={ __(
							'Plays once when the page loads.',
							'sprint-illustrations'
						) }
						value={ current.enter }
						options={ ENTRANCES }
						onChange={ ( enter ) => update( { enter } ) }
					/>
					<SelectControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Loop', 'sprint-illustrations' ) }
						help={ __(
							'Keeps going after the entrance.',
							'sprint-illustrations'
						) }
						value={ current.loop }
						options={ LOOPS }
						onChange={ ( loop ) => update( { loop } ) }
					/>
					<RangeControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __(
							'Delay (seconds)',
							'sprint-illustrations'
						) }
						min={ 0 }
						max={ 3 }
						step={ 0.1 }
						value={ current.delay }
						onChange={ ( delay ) =>
							update( { delay: delay || 0 } )
						}
					/>
					<SelectControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Speed', 'sprint-illustrations' ) }
						value={ current.speed }
						options={ SPEEDS }
						onChange={ ( speed ) => update( { speed } ) }
					/>
					{ value && (
						<Button
							variant="tertiary"
							isDestructive
							onClick={ () =>
								dispatch( {
									type: 'SET_ANIMATION',
									key: layerKey,
									value: null,
								} )
							}
						>
							{ __( 'Remove animation', 'sprint-illustrations' ) }
						</Button>
					) }
				</div>
			) }
		/>
	);
}
