/* Sprint Illustrations block editor. No build step: plain JS on wp.* globals. */
( function ( wp ) {
	'use strict';

	const el = wp.element.createElement;
	const Fragment = wp.element.Fragment;
	const { InspectorControls, useBlockProps } = wp.blockEditor;
	const { PanelBody, SelectControl, TextControl, ToggleControl, Button, Flex } = wp.components;
	const ServerSideRender = wp.serverSideRender;
	const { useSelect } = wp.data;
	const { __ } = wp.i18n;
	const choices = window.sprintIllustrationsBlock || { templates: [], presets: [] };

	wp.blocks.registerBlockType( 'sprint-illustrations/illustration', {
		edit: function Edit( props ) {
			const { attributes, setAttributes } = props;
			const blockProps = useBlockProps();
			const postTitle = useSelect( function ( select ) {
				const editor = select( 'core/editor' );
				return editor ? editor.getEditedPostAttribute( 'title' ) || '' : '';
			}, [] );

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Illustration', 'sprint-illustrations' ) },
						el( SelectControl, {
							label: __( 'Saved illustration', 'sprint-illustrations' ),
							help: attributes.illustrationId ? __( 'Uses the saved design. Only the accessibility settings apply.', 'sprint-illustrations' ) : __( 'Or design one here with the settings below.', 'sprint-illustrations' ),
							value: String( attributes.illustrationId || 0 ),
							options: [ { label: __( 'None (design here)', 'sprint-illustrations' ), value: '0' } ].concat( ( choices.illustrations || [] ).map( function ( item ) {
								return { label: item.label, value: String( item.value ) };
							} ) ),
							onChange: function ( value ) {
								setAttributes( { illustrationId: parseInt( value, 10 ) || 0 } );
							},
							__nextHasNoMarginBottom: true,
						} ),
						el( SelectControl, {
							label: __( 'Template', 'sprint-illustrations' ),
							value: attributes.template,
							options: [ { label: __( 'Automatic (from keywords)', 'sprint-illustrations' ), value: '' } ].concat( choices.templates ),
							onChange: function ( value ) {
								setAttributes( { template: value } );
							},
							__nextHasNoMarginBottom: true,
						} ),
						el( TextControl, {
							label: __( 'Keywords', 'sprint-illustrations' ),
							help: __( 'Comma-separated. Picks the template and pieces.', 'sprint-illustrations' ),
							value: attributes.keywords,
							onChange: function ( value ) {
								setAttributes( { keywords: value } );
							},
							__nextHasNoMarginBottom: true,
						} ),
						el( Button, {
							variant: 'secondary',
							disabled: ! postTitle,
							onClick: function () {
								setAttributes( { keywords: postTitle } );
							},
						}, __( 'Use post title', 'sprint-illustrations' ) ),
						el( Flex, { align: 'flex-end', style: { marginTop: '16px' } },
							el( TextControl, {
								label: __( 'Seed', 'sprint-illustrations' ),
								type: 'number',
								min: 0,
								value: String( attributes.seed ),
								onChange: function ( value ) {
									setAttributes( { seed: Math.max( 0, parseInt( value, 10 ) || 0 ) } );
								},
								__nextHasNoMarginBottom: true,
							} ),
							el( Button, {
								variant: 'secondary',
								onClick: function () {
									setAttributes( { seed: Math.floor( Math.random() * 100000 ) + 1 } );
								},
							}, __( 'Shuffle', 'sprint-illustrations' ) )
						),
						el( 'div', { style: { marginTop: '16px' } },
							el( SelectControl, {
								label: __( 'Palette', 'sprint-illustrations' ),
								value: attributes.palette,
								options: [ { label: __( 'Site palette', 'sprint-illustrations' ), value: 'site' } ].concat( choices.presets ),
								onChange: function ( value ) {
									setAttributes( { palette: value } );
								},
								__nextHasNoMarginBottom: true,
							} )
						)
					),
					el(
						PanelBody,
						{ title: __( 'Accessibility', 'sprint-illustrations' ), initialOpen: false },
						el( TextControl, {
							label: __( 'Title (alt text)', 'sprint-illustrations' ),
							help: __( 'Leave blank to use the template name.', 'sprint-illustrations' ),
							value: attributes.title,
							onChange: function ( value ) {
								setAttributes( { title: value } );
							},
							__nextHasNoMarginBottom: true,
						} ),
						el( ToggleControl, {
							label: __( 'Decorative (hide from screen readers)', 'sprint-illustrations' ),
							checked: attributes.decorative,
							onChange: function ( value ) {
								setAttributes( { decorative: value } );
							},
							__nextHasNoMarginBottom: true,
						} )
					)
				),
				el( 'div', blockProps, el( ServerSideRender, { block: 'sprint-illustrations/illustration', attributes: attributes } ) )
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp );
