import { createRoot } from '@wordpress/element';
import App from './App';

const mount = document.getElementById( 'si-characters' );
if ( mount ) {
	createRoot( mount ).render( <App /> );
}
