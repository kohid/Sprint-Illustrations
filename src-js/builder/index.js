/**
 * Mounts the Builder on #si-builder.
 */
import { createRoot } from '@wordpress/element';
import domReady from '@wordpress/dom-ready';
import App from './App';

domReady( () => {
	const root = document.getElementById( 'si-builder' );
	if ( root ) {
		createRoot( root ).render( <App /> );
	}
} );
