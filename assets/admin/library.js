/* Sprint Illustrations: Library page — show whether Claude Code is watching, and refresh when a piece request changes state. No build step. */
( function () {
	'use strict';

	// Reference image: show what was chosen, with a way to clear it.
	var input = document.getElementById( 'si-request-reference' );
	var preview = document.querySelector( '.si-reference__preview' );
	if ( input && preview && window.URL ) {
		var image = preview.querySelector( 'img' );
		input.addEventListener( 'change', function () {
			var file = input.files && input.files[ 0 ];
			// Same limits as the server, checked before a large upload is sent.
			var ok =
				! file ||
				( /^image\/(png|jpeg|webp)$/.test( file.type ) &&
					file.size <= 5242880 );
			input.setCustomValidity(
				ok ? '' : input.getAttribute( 'data-invalid' ) || ''
			);
			if ( ! ok ) {
				input.reportValidity();
				file = null;
			}
			if ( image.src ) {
				window.URL.revokeObjectURL( image.src );
			}
			preview.hidden = ! file;
			image.src = file ? window.URL.createObjectURL( file ) : '';
		} );
		// Paste a copied image or screenshot anywhere in the request form (Ctrl+V / Cmd+V).
		var form = input.form;
		if ( form && window.DataTransfer ) {
			form.addEventListener( 'paste', function ( event ) {
				var clip = event.clipboardData;
				// Text on the clipboard means a normal text paste; only take a bare image.
				if ( ! clip || ! clip.files || ! clip.files.length || clip.getData( 'text/plain' ) ) {
					return;
				}
				var pasted = null;
				for ( var i = 0; i < clip.files.length; i++ ) {
					if ( /^image\//.test( clip.files[ i ].type ) ) {
						pasted = clip.files[ i ];
						break;
					}
				}
				if ( ! pasted ) {
					return;
				}
				event.preventDefault();
				try {
					var files = new window.DataTransfer();
					files.items.add( pasted );
					input.files = files.files;
				} catch ( e ) {
					return;
				}
				input.dispatchEvent( new window.Event( 'change' ) );
			} );
		}
		preview
			.querySelector( '.si-reference__remove' )
			.addEventListener( 'click', function () {
				input.value = '';
				input.dispatchEvent( new window.Event( 'change' ) );
				input.focus();
			} );
	}

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
