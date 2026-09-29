/**
 * Collapsible left-column sections; each one's open state is remembered in this browser.
 */
import { useState } from '@wordpress/element';
import { PanelBody } from '@wordpress/components';

const KEY = 'si-builder-panels';

function read() {
	try {
		return JSON.parse( window.localStorage.getItem( KEY ) || '{}' ) || {};
	} catch ( error ) {
		return {};
	}
}

function write( name, open ) {
	try {
		window.localStorage.setItem(
			KEY,
			JSON.stringify( { ...read(), [ name ]: open } )
		);
	} catch ( error ) {
		// Storage can be unavailable (private windows); the panel still works.
	}
}

export default function Section( {
	name,
	title,
	initialOpen = true,
	children,
} ) {
	const [ open, setOpen ] = useState( () => {
		const saved = read()[ name ];
		return 'boolean' === typeof saved ? saved : initialOpen;
	} );

	return (
		<div className="si-b-panel si-b-panel--fold">
			<PanelBody
				title={ title }
				opened={ open }
				onToggle={ () => {
					write( name, ! open );
					setOpen( ! open );
				} }
			>
				{ children }
			</PanelBody>
		</div>
	);
}
