/**
 * REST calls for the Cartoon figures. The nonce comes from core's api-fetch middleware.
 */
import apiFetch from '@wordpress/api-fetch';

const NS = '/sprint-illustrations/v1/figures';

export const getOptions = () => apiFetch( { path: `${ NS }/options` } );

export const preview = ( spec, palette ) =>
	apiFetch( {
		path: `${ NS }/preview`,
		method: 'POST',
		data: { spec, palette },
	} );

export const variants = ( spec, field, palette ) =>
	apiFetch( {
		path: `${ NS }/variants`,
		method: 'POST',
		data: { spec, field, palette },
	} );

export const suggest = ( text ) =>
	apiFetch( { path: `${ NS }/suggest`, method: 'POST', data: { text } } );

export const shuffle = ( seed, gender, spec ) =>
	apiFetch( {
		path: `${ NS }/shuffle`,
		method: 'POST',
		data: { seed, gender, spec },
	} );

export const save = ( data ) =>
	apiFetch( { path: `${ NS }/save`, method: 'POST', data } );
