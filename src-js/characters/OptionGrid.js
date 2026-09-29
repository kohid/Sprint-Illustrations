/**
 * One option row: pictures of the character with each choice (or, for colours, swatches of the
 * palette). Selecting one changes the character; the picture shows what it will look like.
 */
import { __ } from '@wordpress/i18n';

// Faces, hair and hats are easier to compare close up.
const HEAD_FIELDS = [ 'hair', 'headwear', 'glasses', 'facial_hair' ];

/**
 * Zoom a character's SVG to its head.
 *
 * @param {string} svg Sanitized SVG markup.
 * @return {string} SVG with a head-and-shoulders view box.
 */
function cropToHead( svg ) {
	return svg.replace( /viewBox="[^"]*"/, 'viewBox="34 12 92 92"' );
}

/**
 * The picture for one option, zoomed to the head when that helps.
 *
 * @param {string|undefined} image Sanitized SVG, if loaded.
 * @param {boolean}          close Whether to zoom.
 * @return {string} Markup.
 */
function markup( image, close ) {
	if ( ! image ) {
		return '';
	}
	return close ? cropToHead( image ) : image;
}

export function OptionGrid( {
	title,
	field,
	value,
	options,
	images,
	onChange,
} ) {
	const close = HEAD_FIELDS.includes( field );

	return (
		<fieldset className="si-c-options">
			<legend>{ title }</legend>
			<div className="si-c-options__grid">
				{ options.map( ( option ) => {
					const image = images?.[ option.value ];
					return (
						<button
							type="button"
							key={ option.value }
							className={ `si-c-option${
								close ? ' is-close' : ''
							}` }
							aria-pressed={ value === option.value }
							onClick={ () => onChange( option.value ) }
						>
							<span
								className="si-c-option__art"
								aria-hidden="true"
								// Sanitized server-side (character preview).
								dangerouslySetInnerHTML={ {
									__html: markup( image, close ),
								} }
							/>
							<span className="si-c-option__label">
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
 * Which palette colour a garment uses: the four slots as swatches of the current palette.
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
		<fieldset className="si-c-options si-c-options--colours">
			<legend>{ title }</legend>
			<div className="si-c-swatches">
				{ options.map( ( option ) => (
					<button
						type="button"
						key={ option.value }
						className="si-c-swatch"
						aria-pressed={ value === option.value }
						aria-label={ option.label }
						title={ option.label }
						style={ { background: colors?.[ option.value ] } }
						onClick={ () => onChange( option.value ) }
					/>
				) ) }
			</div>
			<p className="si-c-muted">
				{ options.find( ( o ) => o.value === value )?.label ||
					__( 'Palette colour', 'sprint-illustrations' ) }
			</p>
		</fieldset>
	);
}
