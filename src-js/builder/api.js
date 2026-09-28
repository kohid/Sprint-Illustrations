/**
 * REST calls for the Builder. The nonce comes from core's api-fetch middleware.
 */
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';

const NS = '/sprint-illustrations/v1';

export const getLibrary = () => apiFetch( { path: `${ NS }/library` } );

export const compose = ( body ) =>
	apiFetch( { path: `${ NS }/compose`, method: 'POST', data: body } );

export const listIllustrations = ( search = '' ) =>
	apiFetch( {
		path: addQueryArgs( `${ NS }/illustrations`, { per_page: 50, search } ),
	} );

export const getIllustration = ( id ) =>
	apiFetch( { path: `${ NS }/illustrations/${ id }` } );

export const saveIllustration = ( { id, title, spec } ) =>
	apiFetch( {
		path: id ? `${ NS }/illustrations/${ id }` : `${ NS }/illustrations`,
		method: id ? 'PUT' : 'POST',
		data: { title, spec },
	} );

export const exportSvg = ( { spec, illustrationId, title } ) =>
	apiFetch( {
		path: `${ NS }/media/svg`,
		method: 'POST',
		data: { spec, illustration_id: illustrationId || 0, title },
	} );

export const exportPng = ( blob, { illustrationId, title, alt } ) => {
	const body = new window.FormData();
	body.append( 'file', blob, 'illustration.png' );
	body.append( 'illustration_id', String( illustrationId || 0 ) );
	body.append( 'title', title || '' );
	body.append( 'alt', alt || '' );
	return apiFetch( { path: `${ NS }/media/png`, method: 'POST', body } );
};
