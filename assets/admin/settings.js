/* Sprint Illustrations: Settings page live preview. No build step; progressive enhancement only. */
( function () {
	'use strict';

	const root = document.querySelector( '[data-si-settings]' );
	const config = window.sprintIllustrationsSettings;

	if ( ! root || ! config ) {
		return;
	}

	const form = root.querySelector( '[data-si-form]' );
	const stage = root.querySelector( '[data-si-stage]' );
	const status = root.querySelector( '[data-si-status]' );
	const aside = stage.closest( '.si-stage' );
	const HEX = /^#[0-9a-f]{6}$/i;
	let seed = Number( config.seed ) || 1;
	let timer = 0;
	let latest = 0;

	function setSource( value ) {
		const radio = form.querySelector( '[data-si-source][value="' + value + '"]' );
		if ( radio ) {
			radio.checked = true;
		}
	}

	function setColor( slot, hex ) {
		const text = form.querySelector( '[data-si-hex="' + slot + '"]' );
		if ( ! text || ! HEX.test( hex ) ) {
			return;
		}
		text.value = hex.toLowerCase();
		const picker = form.querySelector( '[data-si-picker="' + text.id + '"]' );
		if ( picker ) {
			picker.value = text.value;
		}
	}

	function payload() {
		const data = new FormData();
		data.append( 'action', 'sprint_illustrations_preview' );
		data.append( 'nonce', config.nonce );
		data.append( 'seed', String( seed ) );
		form.querySelectorAll( '[data-si-hex]' ).forEach( function ( input ) {
			data.append( 'palette[colors][' + input.dataset.siHex + ']', input.value.trim() );
		} );
		form.querySelectorAll( '[data-si-list]' ).forEach( function ( input ) {
			if ( HEX.test( input.value.trim() ) ) {
				data.append( 'palette[' + input.dataset.siList + '][]', input.value.trim() );
			}
		} );
		return data;
	}

	function updateSlots( warnings, variants ) {
		form.querySelectorAll( '[data-si-slot]' ).forEach( function ( row ) {
			const slot = row.dataset.siSlot;
			row.querySelector( '[data-si-warning]' ).textContent = warnings[ slot ] || '';
			row.querySelectorAll( '[data-si-variant]' ).forEach( function ( chip ) {
				const hex = variants[ slot ] && variants[ slot ][ chip.dataset.siVariant ];
				if ( hex ) {
					chip.style.backgroundColor = hex;
					chip.title = hex;
				}
			} );
		} );
	}

	function refresh() {
		const id = ++latest;
		aside.classList.add( 'is-updating' );
		status.textContent = config.i18n.updating;

		fetch( config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: payload() } )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( json ) {
				if ( id !== latest ) {
					return;
				}
				if ( ! json || ! json.success ) {
					throw new Error( 'preview failed' );
				}
				stage.innerHTML = json.data.svg; // Sanitized server-side by the composer.
				updateSlots( json.data.warnings || {}, json.data.variants || {} );
				status.textContent = '';
			} )
			.catch( function () {
				if ( id === latest ) {
					status.textContent = config.i18n.unavailable;
				}
			} )
			.finally( function () {
				if ( id === latest ) {
					aside.classList.remove( 'is-updating' );
				}
			} );
	}

	function schedule() {
		window.clearTimeout( timer );
		timer = window.setTimeout( refresh, 150 );
	}

	function applyElementor() {
		form.querySelectorAll( '[data-si-map]' ).forEach( function ( select ) {
			const option = select.options[ select.selectedIndex ];
			if ( option && option.dataset.siHex ) {
				setColor( select.dataset.siMap, option.dataset.siHex );
			}
		} );
	}

	// Colour picker <-> hex field. Editing a slot colour means "Custom".
	form.querySelectorAll( '[data-si-picker]' ).forEach( function ( picker ) {
		const text = document.getElementById( picker.dataset.siPicker );
		if ( ! text ) {
			return;
		}
		picker.addEventListener( 'input', function () {
			text.value = picker.value;
			if ( text.dataset.siHex ) {
				setSource( 'custom' );
			}
			schedule();
		} );
		text.addEventListener( 'input', function () {
			if ( HEX.test( text.value.trim() ) ) {
				picker.value = text.value.trim().toLowerCase();
			}
			if ( text.dataset.siHex ) {
				setSource( 'custom' );
			}
			schedule();
		} );
	} );

	form.querySelectorAll( '[data-si-preset]' ).forEach( function ( radio ) {
		radio.addEventListener( 'change', function () {
			const colors = JSON.parse( radio.dataset.siColors || '{}' );
			Object.keys( colors ).forEach( function ( slot ) {
				setColor( slot, colors[ slot ] );
			} );
			setSource( 'preset' );
			schedule();
		} );
	} );

	form.querySelectorAll( '[data-si-source]' ).forEach( function ( radio ) {
		radio.addEventListener( 'change', function () {
			if ( 'elementor' === radio.value ) {
				applyElementor();
				schedule();
			}
		} );
	} );

	form.querySelectorAll( '[data-si-map]' ).forEach( function ( select ) {
		select.addEventListener( 'change', function () {
			setSource( 'elementor' );
			applyElementor();
			schedule();
		} );
	} );

	root.querySelector( '[data-si-shuffle]' ).addEventListener( 'click', function () {
		seed = Math.floor( Math.random() * 100000 ) + 1;
		refresh();
	} );
} )();
