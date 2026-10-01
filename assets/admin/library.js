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
		preview
			.querySelector( '.si-reference__remove' )
			.addEventListener( 'click', function () {
				input.value = '';
				input.dispatchEvent( new window.Event( 'change' ) );
				input.focus();
			} );
	}

	// Reference image from the clipboard: a pasted screenshot (anywhere in the form) or an image
	// address (in the paste field). It is stored through the REST endpoint; the form then sends its name.
	var form = input ? input.form : null;
	var pasteField = document.getElementById( 'si-request-reference-paste' );
	var nameField = document.getElementById( 'si-request-reference-name' );
	var statusLine = document.querySelector( '.si-reference__status' );
	var refConfig = window.sprintIllustrationsLibrary || {};
	if ( form && pasteField && nameField && refConfig.restUrl ) {
		var previewBox = document.querySelector( '.si-reference__preview' );
		var previewImage = previewBox ? previewBox.querySelector( 'img' ) : null;
		var say = function ( message, isError ) {
			statusLine.textContent = message || '';
			statusLine.classList.toggle( 'is-error', !! isError );
		};
		var store = function ( body, json ) {
			say( ( refConfig.text || {} ).working );
			var headers = { 'X-WP-Nonce': refConfig.restNonce };
			if ( json ) {
				headers[ 'Content-Type' ] = 'application/json';
			}
			return window
				.fetch( refConfig.restUrl, {
					method: 'POST',
					headers: headers,
					body: body,
					credentials: 'same-origin',
				} )
				.then( function ( response ) {
					return response.json().then( function ( data ) {
						if ( ! response.ok ) {
							throw new Error( data && data.message ? data.message : '' );
						}
						return data;
					} );
				} )
				.then( function ( data ) {
					nameField.value = data.name;
					input.value = '';
					input.setCustomValidity( '' );
					pasteField.value = '';
					if ( previewBox && previewImage ) {
						previewImage.src = data.url;
						previewBox.hidden = false;
					}
					say( ( refConfig.text || {} ).added );
				} )
				.catch( function ( error ) {
					say( error.message || ( refConfig.text || {} ).failed, true );
				} );
		};
		var sendFile = function ( file ) {
			var data = new window.FormData();
			data.append( 'file', file, file.name || 'screenshot.png' );
			return store( data, false );
		};
		var sendText = function ( text ) {
			return store( JSON.stringify( { text: text } ), true );
		};

		// Choosing a file replaces a pasted image, and clearing the preview clears it.
		input.addEventListener( 'change', function () {
			nameField.value = '';
			say( '' );
		} );

		form.addEventListener( 'paste', function ( event ) {
			var clip = event.clipboardData;
			if ( ! clip ) {
				return;
			}
			var images = Array.prototype.filter.call( clip.files || [], function ( file ) {
				return /^image\//.test( file.type );
			} );
			if ( images.length ) {
				event.preventDefault();
				sendFile( images[ 0 ] );
				return;
			}
			// Text is only taken as an image address in the paste field.
			if ( event.target === pasteField ) {
				var text = clip.getData( 'text' );
				event.preventDefault();
				if ( text ) {
					sendText( text );
				} else {
					say( ( refConfig.text || {} ).nothing, true );
				}
			}
		} );
		pasteField.addEventListener( 'keydown', function ( event ) {
			// Typing an address and pressing Enter also works, without submitting the form.
			if ( 'Enter' === event.key ) {
				event.preventDefault();
				if ( pasteField.value.trim() ) {
					sendText( pasteField.value.trim() );
				}
			}
		} );
	}

	// Request several pieces at once: extra rows are cloned from a template; each is category + description.
	var rows = document.querySelector( '.si-requests__pieces' );
	var addButton = document.querySelector( '.si-requests__add' );
	var template = document.getElementById( 'si-request-row-template' );
	var submit = document.querySelector( '.si-requests__actions .button-primary' );
	if ( rows && addButton && template ) {
		var max = parseInt( rows.getAttribute( 'data-si-max' ), 10 ) || 10;
		var counter = 0;
		var singular = submit ? submit.textContent : '';

		var refresh = function () {
			var count = rows.querySelectorAll( '.si-requests__piece' ).length;
			addButton.hidden = count >= max;
			if ( submit ) {
				submit.textContent =
					count > 1
						? ( submit.getAttribute( 'data-plural' ) || '%d' ).replace( '%d', count )
						: singular;
			}
		};

		addButton.addEventListener( 'click', function () {
			if ( rows.querySelectorAll( '.si-requests__piece' ).length >= max ) {
				return;
			}
			counter += 1;
			var row = template.content.firstElementChild.cloneNode( true );
			// Unique ids so each label points at its own field.
			[ 'category', 'description' ].forEach( function ( name ) {
				var field = row.querySelector( '[id^="si-request-' + name + '"]' );
				var label = row.querySelector( 'label[for^="si-request-' + name + '"]' );
				var id = 'si-request-' + name + '-r' + counter;
				if ( field ) {
					field.id = id;
				}
				if ( label ) {
					label.setAttribute( 'for', id );
				}
			} );
			// New rows start on the same category as the last one.
			var selects = rows.querySelectorAll( 'select' );
			row.querySelector( 'select' ).value = selects[ selects.length - 1 ].value;
			rows.appendChild( row );
			row.querySelector( 'textarea' ).focus();
			refresh();
		} );

		rows.addEventListener( 'click', function ( event ) {
			if ( event.target.classList.contains( 'si-requests__remove-row' ) ) {
				event.target.closest( '.si-requests__piece' ).remove();
				refresh();
				addButton.focus();
			}
		} );

		refresh();
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
