/* Sprint Illustrations: Library page — refresh when a piece request changes state. No build step. */
( function () {
	'use strict';

	var panel = document.querySelector( '[data-si-requests]' );
	var config = window.sprintIllustrationsLibrary;
	if ( ! panel || ! config ) {
		return;
	}

	var known = JSON.parse( panel.getAttribute( 'data-si-requests' ) || '{}' );
	var waiting = Object.keys( known ).some( function ( id ) {
		return 'queued' === known[ id ] || 'drawing' === known[ id ];
	} );
	if ( ! waiting ) {
		return;
	}

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
				if ( result && result.success && changed( result.data || {} ) ) {
					window.location.reload();
				}
			} )
			.catch( function () {} );
	}

	window.setInterval( check, 10000 );
	document.addEventListener( 'visibilitychange', check );
} )();
