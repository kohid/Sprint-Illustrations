/**
 * REST calls for the Character Builder. The nonce comes from core's api-fetch middleware.
 */
import apiFetch from '@wordpress/api-fetch';

const NS = '/sprint-illustrations/v1/characters';

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

export const shuffle = ( seed ) =>
	apiFetch( { path: `${ NS }/shuffle`, method: 'POST', data: { seed } } );

export const save = ( data ) =>
	apiFetch( { path: `${ NS }/save`, method: 'POST', data } );
