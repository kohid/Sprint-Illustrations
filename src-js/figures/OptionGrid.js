/**
 * One option row: close-up pictures of the figure with each choice. Selecting one changes the
 * figure; the picture shows what it will look like.
 */
import { __ } from '@wordpress/i18n';

// Where to look for each field, as an SVG view box (the figure is 240 × 350).
const CROPS = {
	face: '50 34 140 140',
	eyes: '66 84 108 48',
	brows: '66 70 108 48',
	nose: '70 100 100 60',
	mouth: '70 108 100 60',
	cheeks: '56 90 128 72',
	hair: '30 4 180 190',
	headwear: '30 0 180 150',
	eyewear: '56 82 128 60',
	tilt: '30 4 180 190',
	top: '30 130 180 130',
	bottom: '36 220 168 126',
	shoes: '44 296 152 54',
	backpack: '20 130 200 130',
	carry: '40 150 160 110',
};

/**
 * Crop a figure's SVG to the part being chosen.
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
		<fieldset className="si-f-options">
			<legend>{ title }</legend>
			<div className="si-f-options__grid">
				{ options.map( ( option ) => {
					const image = images?.[ option.value ];
					return (
						<button
							type="button"
							key={ option.value }
							className="si-f-card"
							aria-pressed={ value === option.value }
							onClick={ () => onChange( option.value ) }
						>
							<span
								className="si-f-card__art"
								aria-hidden="true"
								// Sanitized server-side (figure preview).
								dangerouslySetInnerHTML={ {
									__html: image ? crop( image, field ) : '',
								} }
							/>
							<span className="si-f-card__label">
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
		<fieldset className="si-f-options">
			<legend>{ title }</legend>
			<div className="si-f-swatches">
				{ options.map( ( option ) => (
					<button
						type="button"
						key={ option.value }
						className="si-f-swatch"
						aria-pressed={ value === option.value }
						aria-label={ option.label }
						title={ option.label }
						style={ { background: colors?.[ option.value ] } }
						onClick={ () => onChange( option.value ) }
					/>
				) ) }
			</div>
			<p className="si-f-muted">
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
		<fieldset className="si-f-options">
			<legend>{ title }</legend>
			<div className="si-f-swatches">
				{ ( colours || [] ).map( ( colour, index ) => (
					<button
						type="button"
						key={ `${ colour }-${ index }` }
						className="si-f-swatch"
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
