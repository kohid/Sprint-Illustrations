/**
 * Browser-side exports: a standalone SVG file and a PNG (SVG → <img> → canvas), transparent or on a colour.
 */

/**
 * The SVG with explicit pixel dimensions and a namespace, so every browser rasterizes it the same way.
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
	root.removeAttribute( 'aria-hidden' );
	root.setAttribute( 'width', String( width ) );
	root.setAttribute( 'height', String( height ) );
	return new window.XMLSerializer().serializeToString( root );
}

/**
 * Start a download of a blob.
 *
 * @param {Blob}   blob     Content.
 * @param {string} filename File name.
 */
export function saveBlob( blob, filename ) {
	const url = window.URL.createObjectURL( blob );
	const link = document.createElement( 'a' );
	link.href = url;
	link.download = filename;
	document.body.appendChild( link );
	link.click();
	link.remove();
	window.setTimeout( () => window.URL.revokeObjectURL( url ), 2000 );
}

/**
 * Download the figure as an SVG file.
 *
 * @param {string} svg      Figure markup.
 * @param {string} filename File name.
 */
export function downloadSvg( svg, filename ) {
	const [ , , width, height ] = (
		svg.match( /viewBox="([^"]+)"/ )?.[ 1 ] || '0 0 240 350'
	)
		.split( /\s+/ )
		.map( Number );
	const text = `<?xml version="1.0" encoding="UTF-8"?>\n${ sized(
		svg,
		width,
		height
	) }\n`;
	saveBlob(
		new window.Blob( [ text ], { type: 'image/svg+xml' } ),
		filename
	);
}

/**
 * Download the figure as a PNG.
 *
 * @param {string}      svg        Figure markup.
 * @param {number}      height     Output height in pixels.
 * @param {string|null} background Fill colour, or null for transparent.
 * @param {string}      filename   File name.
 * @return {Promise<void>} Resolves when the download has started.
 */
export async function downloadPng( svg, height, background, filename ) {
	const [ , , vw, vh ] = (
		svg.match( /viewBox="([^"]+)"/ )?.[ 1 ] || '0 0 240 350'
	)
		.split( /\s+/ )
		.map( Number );
	const width = Math.round( ( height * vw ) / vh );
	const markup = sized( svg, width, height );
	const url = window.URL.createObjectURL(
		new window.Blob( [ markup ], { type: 'image/svg+xml' } )
	);
	try {
		const image = await new Promise( ( resolve, reject ) => {
			const img = new window.Image();
			img.onload = () => resolve( img );
			img.onerror = () => reject( new Error( 'image failed to load' ) );
			img.src = url;
		} );
		const canvas = document.createElement( 'canvas' );
		canvas.width = width;
		canvas.height = height;
		const context = canvas.getContext( '2d' );
		if ( background ) {
			context.fillStyle = background;
			context.fillRect( 0, 0, width, height );
		}
		context.drawImage( image, 0, 0, width, height );
		const blob = await new Promise( ( resolve ) =>
			canvas.toBlob( resolve, 'image/png' )
		);
		if ( ! blob ) {
			throw new Error( 'png failed' );
		}
		saveBlob( blob, filename );
	} finally {
		window.URL.revokeObjectURL( url );
	}
}
