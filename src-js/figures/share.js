/**
 * Share links and autosave: the whole design is a small JSON object, kept in the page address (#figure=…)
 * and in the browser's local storage. The server re-validates everything it is given.
 */
const KEY = 'siFigureStudio';

/**
 * Encode a design for a link.
 *
 * @param {Object} spec Design.
 * @return {string} URL-safe text.
 */
export function encodeSpec( spec ) {
	const json = JSON.stringify( spec );
	return window
		.btoa( unescape( encodeURIComponent( json ) ) )
		.replace( /\+/g, '-' )
		.replace( /\//g, '_' )
		.replace( /=+$/, '' );
}

/**
 * Decode a design from a link.
 *
 * @param {string} text Encoded design.
 * @return {Object|null} Design, or null when it can't be read.
 */
export function decodeSpec( text ) {
	try {
		const padded = text.replace( /-/g, '+' ).replace( /_/g, '/' );
		const json = decodeURIComponent( escape( window.atob( padded ) ) );
		const spec = JSON.parse( json );
		return spec && 'object' === typeof spec ? spec : null;
	} catch ( error ) {
		return null;
	}
}

/**
 * The design in the page address, if any.
 *
 * @return {Object|null} Design.
 */
export function specFromHash() {
	const match = /[#&]figure=([A-Za-z0-9_-]+)/.exec( window.location.hash );
	return match ? decodeSpec( match[ 1 ] ) : null;
}

/**
 * A link that opens this design.
 *
 * @param {Object} spec Design.
 * @return {string} Address.
 */
export function shareLink( spec ) {
	const { origin, pathname, search } = window.location;
	return `${ origin }${ pathname }${ search }#figure=${ encodeSpec( spec ) }`;
}

/**
 * Remember the design in this browser.
 *
 * @param {Object} spec Design.
 */
export function remember( spec ) {
	try {
		window.localStorage.setItem( KEY, JSON.stringify( spec ) );
	} catch ( error ) {
		// Storage can be blocked; the studio works without it.
	}
}

/**
 * The design remembered in this browser.
 *
 * @return {Object|null} Design.
 */
export function recall() {
	try {
		const text = window.localStorage.getItem( KEY );
		return text ? JSON.parse( text ) : null;
	} catch ( error ) {
		return null;
	}
}
