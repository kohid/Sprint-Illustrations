import { createRoot } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import App from './App';

const config = window.sprintIllustrationsFigures || {};

// On a public page the REST address and nonce come from the shortcode; in wp-admin core has set them up.
if ( config.front && config.restUrl ) {
	apiFetch.use( apiFetch.createRootURLMiddleware( config.restUrl ) );
	if ( config.nonce ) {
		apiFetch.use( apiFetch.createNonceMiddleware( config.nonce ) );
	}
}
if ( config.front && config.fullscreen ) {
	document.documentElement.classList.add( 'si-figures-fullscreen' );
}

const mount = document.getElementById( 'si-figures' );
if ( mount ) {
	createRoot( mount ).render( <App /> );
}
