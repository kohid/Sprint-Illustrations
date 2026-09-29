/**
 * Canvas geometry shared by the stage, the library and the layer list.
 */

/**
 * The canvas being edited: the requested size, else the rendered one, else the template's.
 *
 * @param {Object}      spec   Builder spec.
 * @param {Object|null} result Last /compose result.
 * @return {number[]} [w, h].
 */
export function canvasOf( spec, result ) {
	return (
		spec.canvas ||
		result?.canvas ||
		result?.template?.canvas || [ 800, 600 ]
	);
}

/**
 * Scale from template units to the edited canvas (the template layout is fitted and centred).
 *
 * @param {number[]}    canvas Edited canvas.
 * @param {Object|null} result Last /compose result.
 * @return {number} Factor.
 */
export function fitFactor( canvas, result ) {
	const base = result?.template?.canvas;
	return base
		? Math.min( canvas[ 0 ] / base[ 0 ], canvas[ 1 ] / base[ 1 ] )
		: 1;
}

/**
 * Landing size of a dropped piece: real-world scale for the template, at most 80% of the canvas.
 *
 * @param {Object}      piece  Library piece (with size).
 * @param {number[]}    canvas Edited canvas.
 * @param {Object|null} result Last /compose result.
 * @return {number[]} [w, h].
 */
export function landingSize( piece, canvas, result ) {
	const [ pw, ph ] = piece.size || [ 100, 100 ];
	const k = ( result?.template?.unit || 1 ) * fitFactor( canvas, result );
	const cap = Math.min(
		1,
		( canvas[ 0 ] * 0.8 ) / ( pw * k ),
		( canvas[ 1 ] * 0.8 ) / ( ph * k )
	);
	return [ pw * k * cap, ph * k * cap ];
}

/**
 * Height of an item from its width and its piece's proportions.
 *
 * @param {Object} item  Item ({ w }).
 * @param {Object} piece Library piece (with size).
 * @return {number} Height.
 */
export function itemHeight( item, piece ) {
	const [ pw, ph ] = piece?.size || [ 1, 1 ];
	return ( item.w * ph ) / pw;
}

export const round = ( value ) => Math.round( value * 100 ) / 100;
