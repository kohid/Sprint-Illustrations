/**
 * One option row: close-up pictures of the portrait with each choice. Selecting one changes the
 * portrait; the picture shows what it will look like.
 */
import { __ } from '@wordpress/i18n';

// Where to look for each field, as an SVG view box (the portrait is 400 × 440).
const CROPS = {
	eyes: '112 108 176 96',
	iris: '112 108 176 96',
	brows: '112 108 176 96',
	nose: '132 176 136 90',
	mouth: '132 196 136 80',
	cheeks: '120 150 160 100',
	face: '84 40 232 260',
	hair: '60 10 280 300',
	facial_hair: '110 150 180 160',
	glasses: '110 120 180 100',
	earrings: '100 140 200 120',
	headwear: '90 10 220 200',
	look: '70 30 260 270',
	tilt: '60 30 280 300',
};

/**
 * Crop a portrait's SVG to the part being chosen.
 *
 * @param {string} svg   Sanitized SVG markup.
 * @param {string} field Field name.
 * @return {string} SVG with a tighter view box when the field has one.
 */
function crop( svg, field ) {
	return CROPS[ field ]
		? svg.replace( /viewBox="[^"]*"/, `viewBox="${ CROPS[ field ] }"` )
		: svg;
}

export function OptionGrid( {
	title,
	field,
	value,
	options,
	images,
	onChange,
} ) {
	return (
		<fieldset className="si-p-options">
			<legend>{ title }</legend>
			<div className="si-p-options__grid">
				{ options.map( ( option ) => {
					const image = images?.[ option.value ];
					return (
						<button
							type="button"
							key={ option.value }
							className="si-p-card"
							aria-pressed={ value === option.value }
							onClick={ () => onChange( option.value ) }
						>
							<span
								className="si-p-card__art"
								aria-hidden="true"
								// Sanitized server-side (portrait preview).
								dangerouslySetInnerHTML={ {
									__html: image ? crop( image, field ) : '',
								} }
							/>
							<span className="si-p-card__label">
								{ option.label }
							</span>
						</button>
					);
				} ) }
			</div>
		</fieldset>
	);
}

/**
 * A palette colour as swatches: the four slots of the current palette.
 *
 * @param {Object}   props          Props.
 * @param {string}   props.title    Legend.
 * @param {string}   props.value    Selected slot.
 * @param {Array}    props.options  Slot options.
 * @param {Object}   props.colors   Slot => hex of the current palette.
 * @param {Function} props.onChange Change handler.
 * @return {Element} Swatches.
 */
export function ColourGrid( { title, value, options, colors, onChange } ) {
	return (
		<fieldset className="si-p-options">
			<legend>{ title }</legend>
			<div className="si-p-swatches">
				{ options.map( ( option ) => (
					<button
						type="button"
						key={ option.value }
						className="si-p-swatch"
						aria-pressed={ value === option.value }
						aria-label={ option.label }
						title={ option.label }
						style={ { background: colors?.[ option.value ] } }
						onClick={ () => onChange( option.value ) }
					/>
				) ) }
			</div>
			<p className="si-p-muted">
				{ options.find( ( o ) => o.value === value )?.label ||
					__( 'Palette colour', 'sprint-illustrations' ) }
			</p>
		</fieldset>
	);
}

/**
 * Tones of the palette (skin or hair) as swatches.
 *
 * @param {Object}   props          Props.
 * @param {string}   props.title    Legend.
 * @param {Array}    props.colours  Hex colours.
 * @param {number}   props.value    Selected index.
 * @param {Function} props.onChange Change handler.
 * @return {Element} Swatches.
 */
export function Tones( { title, colours, value, onChange } ) {
	return (
		<fieldset className="si-p-options">
			<legend>{ title }</legend>
			<div className="si-p-swatches">
				{ ( colours || [] ).map( ( colour, index ) => (
					<button
						type="button"
						key={ `${ colour }-${ index }` }
						className="si-p-swatch"
						aria-pressed={ value === index }
						aria-label={ `${ title } ${ index + 1 }` }
						style={ { background: colour } }
						onClick={ () => onChange( index ) }
					/>
				) ) }
			</div>
		</fieldset>
	);
}
