/**
 * Builder root: state, the debounced compose loop, open, save and export.
 */
import { __ } from '@wordpress/i18n';
import { useEffect, useReducer, useRef, useState } from '@wordpress/element';
import { Notice, Spinner } from '@wordpress/components';
import { addQueryArgs } from '@wordpress/url';
import {
	compose,
	exportPng,
	exportSvg,
	getIllustration,
	getLibrary,
	saveIllustration,
} from './api';
import { svgToPngBlob } from './exportPng';
import { initialState, reducer, specBody } from './state';
import TopBar from './TopBar';
import TemplatePicker from './TemplatePicker';
import SlotList from './SlotList';
import Stage from './Stage';
import SidePanel from './SidePanel';

const config = window.sprintIllustrationsBuilder || {};

const SOCIAL_SIZE = [ 1200, 630 ];

export default function App() {
	const [ state, dispatch ] = useReducer( reducer, initialState );
	const [ exporting, setExporting ] = useState( false );
	const latest = useRef( 0 );
	const body = specBody( state );
	const bodyKey = JSON.stringify( body );

	const open = ( id ) =>
		getIllustration( id )
			.then( ( item ) => dispatch( { type: 'LOADED', item } ) )
			.catch( ( error ) =>
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
				} )
			);

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
					text:
						asNew || ! state.id
							? __(
									'Saved as a new illustration.',
									'sprint-illustrations'
							  )
							: __( 'Changes saved.', 'sprint-illustrations' ),
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
			if ( 'svg' === format ) {
				media = await exportSvg( { ...meta, spec } );
			} else {
				const canvas = result.template?.canvas || [ 800, 600 ];
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
				<TemplatePicker
					templates={ state.library.templates }
					selected={
						state.spec.template || state.result?.spec?.template
					}
					onSelect={ ( template ) =>
						dispatch( { type: 'SET_TEMPLATE', template } )
					}
				/>
				<SlotList
					result={ state.result }
					library={ state.library }
					picks={ state.picks }
					dispatch={ dispatch }
				/>
			</div>
			<Stage state={ state } />
			<SidePanel
				state={ state }
				dispatch={ dispatch }
				presets={ state.library.presets }
				settingsUrl={ config.settingsUrl }
			/>
		</div>
	);
}
