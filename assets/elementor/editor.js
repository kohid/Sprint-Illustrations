/* Sprint Illustrations: Suggest button in the Elementor widget panel. No build step. */
/* global elementor, $e, jQuery */
( function ( $, wp ) {
	'use strict';

	const { __ } = wp.i18n;

	function notify( message ) {
		if ( elementor.notifications && elementor.notifications.showToast ) {
			elementor.notifications.showToast( { message } );
		}
	}

	function pageTitle() {
		try {
			return (
				elementor.documents
					.getCurrent()
					.container.settings.get( 'post_title' ) || ''
			);
		} catch ( error ) {
			return '';
		}
	}

	// Elementor's button control calls elementor.channels.editor.trigger( event, controlView ).
	function onSuggest( controlView ) {
		const container =
			controlView.container ||
			( controlView.options && controlView.options.container );
		if ( ! container ) {
			return;
		}

		const settings = container.settings;
		const content =
			( settings.get( 'describe' ) || '' ).trim() || pageTitle();
		if ( ! content ) {
			notify(
				__( 'Describe the illustration first.', 'sprint-illustrations' )
			);
			return;
		}

		const button = controlView.$el.find( 'button' );
		button.prop( 'disabled', true );
		wp.apiFetch( {
			path: '/sprint-illustrations/v1/ai/suggest',
			method: 'POST',
			data: {
				content,
				seed: parseInt( settings.get( 'seed' ), 10 ) || 1,
			},
		} )
			.then( function ( result ) {
				const next = {
					illustration_id: '0',
					template: result.template,
					keywords: ( result.keywords || [] ).join( ', ' ),
				};
				if ( result.title ) {
					next.title = result.title;
				}
				$e.run( 'document/elements/settings', {
					container,
					settings: next,
					options: { external: true },
				} );
				notify(
					'ai' === result.source
						? __( 'Suggested by Claude.', 'sprint-illustrations' )
						: result.message
				);
			} )
			.catch( function ( error ) {
				notify(
					( error && error.message ) ||
						__( 'Suggest failed.', 'sprint-illustrations' )
				);
			} )
			.finally( function () {
				button.prop( 'disabled', false );
			} );
	}

	$( window ).on( 'elementor:init', function () {
		elementor.channels.editor.on(
			'sprintIllustrations:suggest',
			onSuggest
		);
	} );
} )( jQuery, window.wp );
