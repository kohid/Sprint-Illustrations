/**
 * Browser-side PNG rendering (the server has no Imagick): SVG → <img> → canvas → PNG blob.
 */

/**
 * The SVG with explicit pixel dimensions, so every browser rasterizes it at the drawn size.
 *
 * @param {string} svg    SVG markup.
 * @param {number} width  Width in pixels.
 * @param {number} height Height in pixels.
 * @return {string} Serialized SVG.
 */
function sized( svg, width, height ) {
	const doc = new window.DOMParser().parseFromString( svg, 'image/svg+xml' );
	const root = doc.documentElement;
	if ( ! root || 'svg' !== root.localName ) {
		throw new Error( 'invalid svg' );
	}
	if ( ! root.getAttribute( 'xmlns' ) ) {
		root.setAttribute( 'xmlns', 'http://www.w3.org/2000/svg' );
	}
	root.setAttribute( 'width', String( width ) );
	root.setAttribute( 'height', String( height ) );
	return new window.XMLSerializer().serializeToString( root );
}

/**
 * Load an SVG string as an image.
 *
 * @param {string} svg SVG markup.
 * @return {Promise<HTMLImageElement>} Loaded image.
 */
function loadImage( svg ) {
	const url = window.URL.createObjectURL(
		new window.Blob( [ svg ], { type: 'image/svg+xml' } )
	);
	return new Promise( ( resolve, reject ) => {
		const image = new window.Image();
		image.onload = () => resolve( image );
		image.onerror = () => reject( new Error( 'image failed to load' ) );
		image.src = url;
	} ).finally( () => window.URL.revokeObjectURL( url ) );
}

/**
 * Render an SVG scene to a PNG blob, fitted and centred in the target size.
 *
 * @param {string}      svg        SVG markup.
 * @param {number[]}    canvas     Scene size [width, height] (template canvas).
 * @param {number[]}    target     Output size [width, height] in pixels.
 * @param {string|null} background Fill colour, or null for transparent.
 * @return {Promise<Blob>} PNG blob.
 */
export async function svgToPngBlob( svg, canvas, target, background = null ) {
	const [ cw, ch ] = canvas;
	const [ tw, th ] = target;
	const scale = Math.min( tw / cw, th / ch );
	const dw = Math.round( cw * scale );
	const dh = Math.round( ch * scale );

	const image = await loadImage( sized( svg, dw, dh ) );
	const element = document.createElement( 'canvas' );
	element.width = tw;
	element.height = th;
	const context = element.getContext( '2d' );
	if ( background ) {
		context.fillStyle = background;
		context.fillRect( 0, 0, tw, th );
	}
	context.drawImage(
		image,
		Math.round( ( tw - dw ) / 2 ),
		Math.round( ( th - dh ) / 2 ),
		dw,
		dh
	);

	return new Promise( ( resolve, reject ) =>
		element.toBlob(
			( blob ) =>
				blob ? resolve( blob ) : reject( new Error( 'no blob' ) ),
			'image/png'
		)
	);
}
