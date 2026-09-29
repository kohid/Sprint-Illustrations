/* Sprint Illustrations: Library page — show whether Claude Code is watching, and refresh when a piece request changes state. No build step. */
( function () {
	'use strict';

	var panel = document.querySelector( '[data-si-requests]' );
	var config = window.sprintIllustrationsLibrary;
	if ( ! panel || ! config ) {
		return;
	}

	var known = JSON.parse( panel.getAttribute( 'data-si-requests' ) || '{}' );

	function changed( current ) {
		var ids = Object.keys( known ).concat( Object.keys( current ) );
		return ids.some( function ( id ) {
			return known[ id ] !== current[ id ];
		} );
	}

	function check() {
		if ( document.hidden ) {
			return;
		}
		var body = new window.FormData();
		body.append( 'action', config.action );
		body.append( 'nonce', config.nonce );
		window
			.fetch( config.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' } )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( result ) {
				if ( ! result || ! result.success || ! result.data ) {
					return;
				}
				panel.classList.toggle( 'is-drawer-online', !! result.data.online );
				if ( changed( result.data.states || {} ) ) {
					window.location.reload();
				}
			} )
			.catch( function () {} );
	}

	window.setInterval( check, 10000 );
	document.addEventListener( 'visibilitychange', check );
} )();
