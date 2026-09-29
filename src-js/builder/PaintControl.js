/**
 * Per-layer colour: replace a palette colour (primary, secondary, accent or neutral) for one layer
 * with a solid colour or a two-colour gradient. Empty means "use the palette".
 */
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	ColorPicker,
	Dropdown,
	RangeControl,
	SelectControl,
	TabPanel,
} from '@wordpress/components';

const SLOTS = [
	{ value: 'primary', label: __( 'Primary', 'sprint-illustrations' ) },
	{ value: 'secondary', label: __( 'Secondary', 'sprint-illustrations' ) },
	{ value: 'accent', label: __( 'Accent', 'sprint-illustrations' ) },
	{ value: 'neutral', label: __( 'Neutral', 'sprint-illustrations' ) },
];

import { useState } from '@wordpress/element';

const isGradient = ( paint ) => !! paint && 'object' === typeof paint;

const css = ( paint ) =>
	isGradient( paint )
		? `linear-gradient(${ paint.angle }deg, ${ paint.from }, ${ paint.to })`
		: paint;

function Swatch( { background } ) {
	return (
		<span
			className="si-b-swatch"
			style={ { background } }
			aria-hidden="true"
		/>
	);
}

function PaintEditor( { slot, paint, fallback, partner, onChange } ) {
	const gradient = isGradient( paint );
	const solid = gradient ? paint.from : paint || fallback;
	const [ end, setEnd ] = useState( 'from' );
	const current = gradient ? paint : { from: solid, to: solid, angle: 90 };

	return (
		<TabPanel
			className="si-b-paint__tabs"
			initialTabName={ gradient ? 'gradient' : 'solid' }
			tabs={ [
				{ name: 'solid', title: __( 'Solid', 'sprint-illustrations' ) },
				{
					name: 'gradient',
					title: __( 'Gradient', 'sprint-illustrations' ),
				},
			] }
			onSelect={ ( name ) => {
				if ( 'gradient' === name && ! gradient ) {
					onChange( { from: solid, to: partner, angle: 90 } );
				} else if ( 'solid' === name && gradient ) {
					onChange( paint.from );
				}
			} }
		>
			{ ( tab ) =>
				'solid' === tab.name ? (
					<ColorPicker
						key={ slot }
						color={ solid }
						enableAlpha={ false }
						defaultValue={ fallback }
						onChange={ ( color ) =>
							onChange( color.slice( 0, 7 ) )
						}
					/>
				) : (
					<div className="si-b-paint__gradient">
						<span
							className="si-b-paint__bar"
							style={ { background: css( current ) } }
							aria-hidden="true"
						/>
						<SelectControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label={ __( 'Edit', 'sprint-illustrations' ) }
							value={ end }
							options={ [
								{
									value: 'from',
									label: __(
										'Start colour',
										'sprint-illustrations'
									),
								},
								{
									value: 'to',
									label: __(
										'End colour',
										'sprint-illustrations'
									),
								},
							] }
							onChange={ setEnd }
						/>
						<ColorPicker
							key={ `${ slot }-${ end }` }
							color={ current[ end ] }
							enableAlpha={ false }
							onChange={ ( color ) =>
								onChange( {
									...current,
									[ end ]: color.slice( 0, 7 ),
								} )
							}
						/>
						<RangeControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label={ __(
								'Angle (degrees)',
								'sprint-illustrations'
							) }
							min={ 0 }
							max={ 360 }
							value={ current.angle }
							onChange={ ( angle ) =>
								onChange( { ...current, angle: angle ?? 90 } )
							}
						/>
					</div>
				)
			}
		</TabPanel>
	);
}

export default function PaintControl( {
	layerKey,
	name,
	value,
	colors,
	dispatch,
} ) {
	const [ slot, setSlot ] = useState( 'primary' );
	const paints = value || {};
	const set = ( next ) => {
		const all = { ...paints };
		if ( next ) {
			all[ slot ] = next;
		} else {
			delete all[ slot ];
		}
		dispatch( { type: 'SET_PAINT', key: layerKey, value: all } );
	};
	const fallback = colors?.[ slot ] || '#2271b1';
	// A new gradient ends on the next palette colour, so it is visible straight away.
	const partner =
		colors?.[
			SLOTS[
				( SLOTS.findIndex( ( o ) => o.value === slot ) + 1 ) %
					SLOTS.length
			].value
		] || '#ffffff';

	return (
		<Dropdown
			popoverProps={ { placement: 'right-start' } }
			renderToggle={ ( { isOpen, onToggle } ) => (
				<Button
					size="small"
					icon="art"
					isPressed={ !! value }
					aria-expanded={ isOpen }
					label={ sprintf(
						/* translators: %s: layer name. */
						__( 'Colour of %s', 'sprint-illustrations' ),
						name
					) }
					onClick={ onToggle }
				/>
			) }
			renderContent={ () => (
				<div className="si-b-paint">
					<div
						className="si-b-paint__slots"
						role="group"
						aria-label={ __(
							'Palette colour to change',
							'sprint-illustrations'
						) }
					>
						{ SLOTS.map( ( option ) => (
							<button
								type="button"
								key={ option.value }
								className="si-b-paint__slot"
								aria-pressed={ slot === option.value }
								onClick={ () => setSlot( option.value ) }
							>
								<Swatch
									background={ css(
										paints[ option.value ] ||
											colors?.[ option.value ]
									) }
								/>
								{ option.label }
							</button>
						) ) }
					</div>
					<PaintEditor
						key={ slot }
						slot={ slot }
						paint={ paints[ slot ] }
						fallback={ fallback }
						partner={ partner }
						onChange={ set }
					/>
					<Button
						variant="tertiary"
						isDestructive
						disabled={ ! paints[ slot ] }
						onClick={ () => set( null ) }
					>
						{ __(
							'Use the palette colour',
							'sprint-illustrations'
						) }
					</Button>
				</div>
			) }
		/>
	);
}
