import { createRoot } from '@wordpress/element';
import App from './App';

const mount = document.getElementById( 'si-portraits' );
if ( mount ) {
	createRoot( mount ).render( <App /> );
}
