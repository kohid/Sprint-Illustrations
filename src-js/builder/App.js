/**
 * Builder root: state, the debounced compose loop, open, save and export.
 */
import { __ } from '@wordpress/i18n';
import { useEffect, useReducer, useRef, useState } from '@wordpress/element';
import { Notice, Spinner } from '@wordpress/components';
import { addQueryArgs, removeQueryArgs } from '@wordpress/url';
import {
	compose,
	exportPng,
	exportSvg,
	getIllustration,
	getLibrary,
	saveIllustration,
	suggest,
} from './api';
import { svgToPngBlob } from './exportPng';
import { initialState, reducer, specBody } from './state';
import { canvasOf, landingSize, round } from './geometry';
import TopBar from './TopBar';
import Section from './panels';
import LibraryPanel from './LibraryPanel';
import TemplatePicker from './TemplatePicker';
import SlotList from './SlotList';
import Stage from './Stage';
import SidePanel, { PalettePanel } from './SidePanel';

const config = window.sprintIllustrationsBuilder || {};

const SOCIAL_SIZE = [ 1200, 630 ];

export default function App() {
	const [ state, dispatch ] = useReducer( reducer, initialState );
	const [ exporting, setExporting ] = useState( false );
	const [ suggesting, setSuggesting ] = useState( false );
	const [ dragging, setDragging ] = useState( null );
	const latest = useRef( 0 );
	const body = specBody( state );
	const bodyKey = JSON.stringify( body );

	const open = ( id ) =>
		getIllustration( id )
			.then( ( item ) => dispatch( { type: 'LOADED', item } ) )
			.catch( ( error ) => {
				if ( 'sprint_illustrations_not_found' === error.code ) {
					// A deleted design left in the address would fail on every reload: drop it.
					window.history.replaceState(
						null,
						'',
						removeQueryArgs( window.location.href, 'illustration' )
					);
					dispatch( { type: 'NEW' } );
					dispatch( {
						type: 'NOTICE',
						notice: {
							status: 'info',
							text: __(
								'That illustration no longer exists, so a new one was started.',
								'sprint-illustrations'
							),
						},
					} );
					return;
				}
				dispatch( {
					type: 'NOTICE',
					notice: {
						status: 'error',
						text:
							error.message ||
							__(
								'That illustration couldn’t be opened.',
								'sprint-illustrations'
							),
					},
				} );
			} );

	// Library (starting on ?template=ID), then the illustration from ?illustration=ID.
	useEffect( () => {
		getLibrary()
			.then( ( library ) => {
				dispatch( {
					type: 'LIBRARY',
					library,
					preferred: config.initialTemplate,
				} );
				if ( config.initialId ) {
					open( config.initialId );
				}
			} )
			.catch( ( error ) =>
				dispatch( { type: 'FAILED', message: error.message || '' } )
			);
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	// Debounced compose; only the latest response is applied.
	useEffect( () => {
		if ( ! state.library ) {
			return undefined;
		}
		const id = ++latest.current;
		dispatch( { type: 'COMPOSING' } );
		const timer = window.setTimeout( () => {
			compose( JSON.parse( bodyKey ) )
				.then(
					( result ) =>
						id === latest.current &&
						dispatch( { type: 'RESULT', result } )
				)
				.catch(
					( error ) =>
						id === latest.current &&
						dispatch( {
							type: 'FAILED',
							message: error.message || '',
						} )
				);
		}, 150 );
		return () => window.clearTimeout( timer );
	}, [ state.library, bodyKey ] );

	// Warn before leaving with unsaved changes.
	useEffect( () => {
		if ( ! state.dirty ) {
			return undefined;
		}
		const warn = ( event ) => {
			event.preventDefault();
			event.returnValue = '';
		};
		window.addEventListener( 'beforeunload', warn );
		return () => window.removeEventListener( 'beforeunload', warn );
	}, [ state.dirty ] );

	const save = ( asNew ) => {
		if ( ! state.name.trim() ) {
			dispatch( {
				type: 'NOTICE',
				notice: {
					status: 'error',
					text: __(
						'Give the illustration a name before saving.',
						'sprint-illustrations'
					),
				},
			} );
			return;
		}
		dispatch( { type: 'SAVING' } );
		saveIllustration( {
			id: asNew ? 0 : state.id,
			title: state.name.trim(),
			spec: body,
		} )
			.then( ( item ) => {
				dispatch( {
					type: 'SAVED',
					item,
					text: [
						asNew || ! state.id
							? __(
									'Saved as a new illustration.',
									'sprint-illustrations'
							  )
							: __( 'Changes saved.', 'sprint-illustrations' ),
						Object.keys( state.spec.animations ).length
							? __(
									'The animation plays wherever you place it: shortcode, block or Elementor.',
									'sprint-illustrations'
							  )
							: '',
					]
						.filter( Boolean )
						.join( ' ' ),
				} );
				window.history.replaceState(
					null,
					'',
					addQueryArgs( window.location.href, {
						illustration: item.id,
					} )
				);
			} )
			.catch( ( error ) =>
				dispatch( {
					type: 'NOTICE',
					notice: {
						status: 'error',
						text:
							error.message ||
							__(
								'The illustration couldn’t be saved.',
								'sprint-illustrations'
							),
					},
				} )
			);
	};

	// Suggest from a description: Claude when enabled, keyword matching otherwise.
	const suggestFrom = ( describe ) => {
		setSuggesting( true );
		suggest( describe, state.spec.seed )
			.then( ( suggestion ) =>
				dispatch( { type: 'SUGGESTED', suggestion } )
			)
			.catch( ( error ) =>
				dispatch( {
					type: 'NOTICE',
					notice: {
						status: 'error',
						text:
							error.message ||
							__( 'Suggest failed.', 'sprint-illustrations' ),
					},
				} )
			)
			.finally( () => setSuggesting( false ) );
	};

	// A scene plan whose pieces were kept: refresh the library and show the scene with them placed.
	const planBuilt = ( scene ) =>
		getLibrary()
			.then( ( library ) => dispatch( { type: 'LIBRARY', library } ) )
			.catch( () => {} )
			.then( () =>
				dispatch( {
					type: 'APPLY_PLAN',
					scene,
					text: __(
						'Scene built with your new pieces. Move them where you like, then save it, or use Save as template.',
						'sprint-illustrations'
					),
				} )
			);

	// Export the scene on the stage; the illustration is linked as the source only once saved.
	const exportAs = async ( format ) => {
		const result = state.result;
		if ( ! result?.svg || exporting ) {
			return;
		}
		const spec = result.spec || body;
		const title = state.name.trim() || result.template?.label || '';
		const alt = spec.decorative
			? ''
			: spec.title || result.template?.label || '';
		const meta = { illustrationId: state.id, title, alt };

		setExporting( true );
		try {
			let media;
			if ( 'svg' === format || 'animated-svg' === format ) {
				media = await exportSvg( {
					...meta,
					spec,
					animated: 'animated-svg' === format,
				} );
			} else {
				const canvas = result.canvas ||
					result.template?.canvas || [ 800, 600 ];
				let blob;
				try {
					blob =
						'social' === format
							? await svgToPngBlob(
									result.svg,
									canvas,
									SOCIAL_SIZE,
									result.background || '#ffffff'
							  )
							: await svgToPngBlob( result.svg, canvas, [
									canvas[ 0 ] * 2,
									canvas[ 1 ] * 2,
							  ] );
				} catch ( error ) {
					throw new Error(
						__(
							'The PNG couldn’t be created in this browser.',
							'sprint-illustrations'
						)
					);
				}
				media = await exportPng( blob, meta );
			}
			dispatch( {
				type: 'NOTICE',
				notice: {
					status: 'success',
					text: __(
						'Saved to Media Library.',
						'sprint-illustrations'
					),
					actions: media.edit_url
						? [
								{
									label: __( 'Edit', 'sprint-illustrations' ),
									url: media.edit_url,
								},
						  ]
						: [],
				},
			} );
		} catch ( error ) {
			dispatch( {
				type: 'NOTICE',
				notice: {
					status: 'error',
					text:
						error.message ||
						__(
							'The export couldn’t be saved to the Media Library.',
							'sprint-illustrations'
						),
				},
			} );
		} finally {
			setExporting( false );
		}
	};

	if ( ! state.library ) {
		return 'failed' === state.status ? (
			<Notice status="error" isDismissible={ false }>
				{ __(
					'The Builder couldn’t load the illustration library.',
					'sprint-illustrations'
				) }{ ' ' }
				{ state.error }
			</Notice>
		) : (
			<div className="si-b-loading">
				<Spinner />
			</div>
		);
	}

	const byId = Object.fromEntries(
		state.library.pieces.map( ( piece ) => [ piece.id, piece ] )
	);

	// Library → canvas without dragging: the piece lands in the middle.
	const addToCentre = ( piece ) => {
		const canvas = canvasOf( state.spec, state.result );
		const [ w, h ] = landingSize( piece, canvas, state.result );
		dispatch( {
			type: 'ADD_ITEM',
			item: {
				piece: piece.id,
				x: round( ( canvas[ 0 ] - w ) / 2 ),
				y: round( ( canvas[ 1 ] - h ) / 2 ),
				w: round( w ),
				flip: false,
			},
		} );
	};

	return (
		<div className="si-builder">
			<TopBar
				state={ state }
				dispatch={ dispatch }
				onOpen={ open }
				onSave={ save }
				onExport={ exportAs }
				exporting={ exporting }
			/>
			{ state.notice && (
				<Notice
					className="si-b-notice"
					status={ state.notice.status }
					actions={ state.notice.actions }
					onRemove={ () =>
						dispatch( { type: 'NOTICE', notice: null } )
					}
				>
					{ state.notice.text }
				</Notice>
			) }
			<div className="si-b-left">
				<Section
					name="library"
					title={ __( 'Library', 'sprint-illustrations' ) }
				>
					<LibraryPanel
						pieces={ state.library.pieces }
						count={ state.spec.items.length }
						onAdd={ addToCentre }
						onDrag={ setDragging }
					/>
				</Section>
				<Section
					name="template"
					title={ __( 'Template', 'sprint-illustrations' ) }
				>
					<TemplatePicker
						templates={ state.library.templates }
						selected={
							state.spec.template || state.result?.spec?.template
						}
						onSelect={ ( template ) =>
							dispatch( { type: 'SET_TEMPLATE', template } )
						}
					/>
				</Section>
				<Section
					name="palette"
					title={ __( 'Palette', 'sprint-illustrations' ) }
				>
					<PalettePanel
						palette={ state.spec.palette }
						presets={ state.library.presets }
						settingsUrl={ config.settingsUrl }
						dispatch={ dispatch }
					/>
				</Section>
			</div>
			<Stage
				state={ state }
				byId={ byId }
				dragging={ dragging }
				dispatch={ dispatch }
			/>
			<SidePanel
				state={ state }
				dispatch={ dispatch }
				layers={
					<Section
						name="slots"
						title={ __( 'Layers', 'sprint-illustrations' ) }
					>
						<SlotList
							state={ state }
							library={ state.library }
							byId={ byId }
							dispatch={ dispatch }
						/>
					</Section>
				}
				aiReady={ !! config.aiReady }
				suggesting={ suggesting }
				onSuggest={ suggestFrom }
				onPlanBuilt={ planBuilt }
			/>
		</div>
	);
}
