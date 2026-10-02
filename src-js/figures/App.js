/**
 * Cartoon figures: draw a head-and-shoulders figure from parts, see it in your palette, save it to
 * the library. The drawing is done by the plugin (pure PHP), so the preview is exactly the saved piece.
 */
import { __, sprintf } from '@wordpress/i18n';
import {
	useCallback,
	useEffect,
	useReducer,
	useRef,
	useState,
} from '@wordpress/element';
import {
	Button,
	Notice,
	SelectControl,
	Spinner,
	TextControl,
} from '@wordpress/components';
import { getOptions, preview, save, shuffle, suggest, variants } from './api';
import { ColourGrid, OptionGrid, Tones } from './OptionGrid';

const config = window.sprintIllustrationsFigures || {};

const TABS = [
	{ id: 'start', label: __( 'Start', 'sprint-illustrations' ) },
	{ id: 'pose', label: __( 'Pose and body', 'sprint-illustrations' ) },
	{ id: 'face', label: __( 'Face', 'sprint-illustrations' ) },
	{ id: 'hair', label: __( 'Hair', 'sprint-illustrations' ) },
	{ id: 'gear', label: __( 'Head gear', 'sprint-illustrations' ) },
	{ id: 'outfit', label: __( 'Outfit', 'sprint-illustrations' ) },
	{ id: 'scene', label: __( 'Backdrop', 'sprint-illustrations' ) },
];

// Rows shown on each tab: [field, title, kind].
const ROWS = {
	start: [],
	pose: [
		[ 'pose', __( 'Pose', 'sprint-illustrations' ) ],
		[ 'carry', __( 'Holding', 'sprint-illustrations' ) ],
		[ 'build', __( 'Build', 'sprint-illustrations' ) ],
		[ 'backpack', __( 'Backpack', 'sprint-illustrations' ) ],
		[ 'bag_color', __( 'Bag colour', 'sprint-illustrations' ), 'colour' ],
		[ 'tilt', __( 'Tilt of the head', 'sprint-illustrations' ) ],
	],
	face: [
		[ 'face', __( 'Face shape', 'sprint-illustrations' ) ],
		[ 'eyes', __( 'Eyes', 'sprint-illustrations' ) ],
		[ 'brows', __( 'Brows', 'sprint-illustrations' ) ],
		[ 'nose', __( 'Nose', 'sprint-illustrations' ) ],
		[ 'mouth', __( 'Mouth', 'sprint-illustrations' ) ],
		[ 'cheeks', __( 'Cheeks', 'sprint-illustrations' ) ],
	],
	hair: [ [ 'hair', __( 'Hair style', 'sprint-illustrations' ) ] ],
	gear: [
		[ 'headwear', __( 'Hat', 'sprint-illustrations' ) ],
		[
			'headwear_color',
			__( 'Hat colour', 'sprint-illustrations' ),
			'colour',
		],
		[ 'eyewear', __( 'Eyewear', 'sprint-illustrations' ) ],
	],
	outfit: [
		[ 'top', __( 'Top', 'sprint-illustrations' ) ],
		[ 'top_color', __( 'Top colour', 'sprint-illustrations' ), 'colour' ],
		[ 'bottom', __( 'Bottom', 'sprint-illustrations' ) ],
		[
			'bottom_color',
			__( 'Bottom colour', 'sprint-illustrations' ),
			'colour',
		],
		[ 'shoes', __( 'Shoes', 'sprint-illustrations' ) ],
		[
			'shoes_color',
			__( 'Shoe colour', 'sprint-illustrations' ),
			'colour',
		],
	],
	scene: [
		[ 'scene', __( 'Backdrop', 'sprint-illustrations' ) ],
		[
			'scene_color',
			__( 'Backdrop colour', 'sprint-illustrations' ),
			'colour',
		],
	],
};

// Guide lines in the figure's own 240 × 350 coordinates: where the construction lines of a figure sit.
const GUIDES = [
	[ 'v', 120, __( 'centre line', 'sprint-illustrations' ) ],
	[ 'h', 108, __( 'eyes', 'sprint-illustrations' ) ],
	[ 'h', 156, __( 'chin', 'sprint-illustrations' ) ],
	[ 'h', 168, __( 'shoulders', 'sprint-illustrations' ) ],
	[ 'h', 232, __( 'hips', 'sprint-illustrations' ) ],
	[ 'h', 334, __( 'ground', 'sprint-illustrations' ) ],
];

const HISTORY = 50;
const ZOOM_MIN = 0.5;
const ZOOM_MAX = 1.6;
const STAGE_HEIGHT = 560;

const clampZoom = ( value ) =>
	Math.max( ZOOM_MIN, Math.min( ZOOM_MAX, Math.round( value * 100 ) / 100 ) );

function reducer( state, action ) {
	switch ( action.type ) {
		case 'SET': {
			const spec = { ...state.spec, ...action.changes };
			if ( JSON.stringify( spec ) === JSON.stringify( state.spec ) ) {
				return state;
			}
			return {
				spec,
				past: [ ...state.past, state.spec ].slice( -HISTORY ),
				future: [],
			};
		}
		case 'REPLACE': {
			// A whole new figure (starting point, shuffle): the tones being previewed stay.
			const spec = {
				...action.spec,
				skin: action.keepTones
					? state.spec?.skin ?? 0
					: action.spec.skin ?? 0,
				hair_tone: action.keepTones
					? state.spec?.hair_tone ?? 0
					: action.spec.hair_tone ?? 0,
			};
			return {
				spec,
				past: state.spec
					? [ ...state.past, state.spec ].slice( -HISTORY )
					: [],
				future: [],
			};
		}
		case 'UNDO':
			return state.past.length
				? {
						spec: state.past[ state.past.length - 1 ],
						past: state.past.slice( 0, -1 ),
						future: [ state.spec, ...state.future ],
				  }
				: state;
		case 'REDO':
			return state.future.length
				? {
						spec: state.future[ 0 ],
						past: [ ...state.past, state.spec ],
						future: state.future.slice( 1 ),
				  }
				: state;
		default:
			return state;
	}
}

export default function App() {
	const [ options, setOptions ] = useState( null );
	const [ loadError, setLoadError ] = useState( '' );
	const [ state, dispatch ] = useReducer( reducer, {
		spec: null,
		past: [],
		future: [],
	} );
	const [ palette, setPalette ] = useState( 'site' );
	const [ shown, setShown ] = useState( null );
	const [ thumbs, setThumbs ] = useState( {} );
	const [ tab, setTab ] = useState( 'start' );
	const [ describe, setDescribe ] = useState( '' );
	const [ gender, setGender ] = useState( 'any' );
	const [ notice, setNotice ] = useState( null );
	const [ form, setForm ] = useState( { name: '', label: '', tags: '' } );
	const [ saving, setSaving ] = useState( false );
	const [ saved, setSaved ] = useState( null );
	const [ guides, setGuides ] = useState( false );
	const [ zoom, setZoom ] = useState( 1 );
	const seed = useRef( 1 );
	const latest = useRef( 0 );
	const wheelOff = useRef( null );

	// Ctrl or Cmd with the wheel zooms the stage instead of the page.
	const stageRef = useCallback( ( node ) => {
		if ( wheelOff.current ) {
			wheelOff.current();
			wheelOff.current = null;
		}
		if ( ! node ) {
			return;
		}
		const wheel = ( event ) => {
			if ( ! event.ctrlKey && ! event.metaKey ) {
				return;
			}
			event.preventDefault();
			setZoom( ( value ) =>
				clampZoom( value - Math.sign( event.deltaY ) * 0.1 )
			);
		};
		node.addEventListener( 'wheel', wheel, { passive: false } );
		wheelOff.current = () => node.removeEventListener( 'wheel', wheel );
	}, [] );

	useEffect( () => {
		getOptions()
			.then( ( data ) => {
				setOptions( data );
				dispatch( { type: 'REPLACE', spec: data.defaults } );
			} )
			.catch( ( error ) =>
				setLoadError(
					error.message ||
						__(
							'The Cartoon figures could not load.',
							'sprint-illustrations'
						)
				)
			);
	}, [] );

	const spec = state.spec;
	const specKey = JSON.stringify( spec );

	// Live preview: only the newest answer is shown.
	useEffect( () => {
		if ( ! spec ) {
			return undefined;
		}
		const id = ++latest.current;
		const timer = window.setTimeout( () => {
			preview( spec, palette )
				.then( ( data ) => {
					if ( id === latest.current ) {
						setShown( data );
					}
				} )
				.catch( () => {} );
		}, 120 );
		return () => window.clearTimeout( timer );
	}, [ specKey, palette ] ); // eslint-disable-line react-hooks/exhaustive-deps

	// Thumbnails for the rows of the open tab.
	const rows = ( ROWS[ tab ] || [] ).filter(
		( [ name ] ) => 'carry' !== name || 'hold' === spec?.pose
	);
	const rowKey = rows.map( ( row ) => row[ 0 ] ).join( ',' );
	useEffect( () => {
		if ( ! spec || ! rows.length ) {
			return undefined;
		}
		let cancelled = false;
		const timer = window.setTimeout( () => {
			rows.filter( ( row ) => 'colour' !== row[ 2 ] ).forEach(
				( [ field ] ) => {
					variants( spec, field, palette )
						.then( ( data ) => {
							if ( ! cancelled ) {
								setThumbs( ( all ) => ( {
									...all,
									[ field ]: data.images,
								} ) );
							}
						} )
						.catch( () => {} );
				}
			);
		}, 200 );
		return () => {
			cancelled = true;
			window.clearTimeout( timer );
		};
	}, [ tab, rowKey, specKey, palette ] ); // eslint-disable-line react-hooks/exhaustive-deps

	const set = useCallback( ( changes ) => {
		setSaved( null );
		dispatch( { type: 'SET', changes } );
	}, [] );

	if ( loadError ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ loadError }
			</Notice>
		);
	}
	if ( ! options || ! spec ) {
		return <Spinner />;
	}

	const colours = shown?.palette?.colors || {};
	const tones = shown?.palette || options.tones;

	const startFrom = ( next, keepTones ) => {
		setSaved( null );
		dispatch( { type: 'REPLACE', spec: next, keepTones } );
	};

	const doSuggest = () =>
		suggest( describe )
			.then( ( result ) => {
				if ( result.role ) {
					startFrom( result.spec, true );
					setNotice( {
						status: 'success',
						text: sprintf(
							/* translators: %s: role, e.g. "Taxi driver at the wheel". */
							__(
								'Started from “%s”. Change anything you like.',
								'sprint-illustrations'
							),
							result.label
						),
					} );
				} else {
					setNotice( { status: 'info', text: result.message } );
				}
			} )
			.catch( ( error ) =>
				setNotice( { status: 'error', text: error.message } )
			);

	const surprise = () => {
		seed.current += 1 + Math.floor( Math.random() * 1000 );
		shuffle( seed.current, gender, spec )
			.then( ( result ) => startFrom( result.spec, false ) )
			.catch( () => {} );
	};

	const doSave = () => {
		setSaving( true );
		setNotice( null );
		save( {
			spec,
			name: form.name,
			label: form.label,
			tags: form.tags.split( ',' ).map( ( tag ) => tag.trim() ),
		} )
			.then( ( result ) => setSaved( result ) )
			.catch( ( error ) =>
				setNotice( {
					status: 'error',
					text:
						error.message ||
						__(
							'The figure could not be saved.',
							'sprint-illustrations'
						),
				} )
			)
			.finally( () => setSaving( false ) );
	};

	const height = STAGE_HEIGHT * zoom;

	return (
		<div className="si-f-app">
			<header className="si-f-head">
				<h1>{ __( 'Cartoon figures', 'sprint-illustrations' ) }</h1>
				<p>
					{ __(
						'Build a full-body cartoon figure for your page: pick a starting point, adjust the look, then save it. It joins the library, so the Builder and every scene can use it, and its colours follow your palette.',
						'sprint-illustrations'
					) }
				</p>
			</header>

			{ notice && (
				<Notice
					status={ notice.status }
					onRemove={ () => setNotice( null ) }
				>
					{ notice.text }
				</Notice>
			) }

			<div className="si-f-layout">
				<section
					className="si-f-controls"
					aria-label={ __( 'Options', 'sprint-illustrations' ) }
				>
					<div
						className="si-f-tabs"
						role="tablist"
						aria-orientation="vertical"
						aria-label={ __( 'Parts', 'sprint-illustrations' ) }
					>
						{ TABS.map( ( item ) => (
							<button
								type="button"
								role="tab"
								key={ item.id }
								id={ `si-f-tab-${ item.id }` }
								aria-selected={ tab === item.id }
								aria-controls="si-f-tabpanel"
								className="si-f-tab"
								onClick={ () => setTab( item.id ) }
							>
								{ item.label }
							</button>
						) ) }
					</div>

					<div
						id="si-f-tabpanel"
						role="tabpanel"
						aria-labelledby={ `si-f-tab-${ tab }` }
						className="si-f-tabpanel"
					>
						{ 'start' === tab && (
							<div className="si-f-start">
								<div className="si-f-row">
									<TextControl
										__nextHasNoMarginBottom
										__next40pxDefaultSize
										label={ __(
											'Who is on your page?',
											'sprint-illustrations'
										) }
										placeholder={ __(
											'e.g. a builder with a clipboard',
											'sprint-illustrations'
										) }
										value={ describe }
										onChange={ setDescribe }
										onKeyDown={ ( event ) => {
											if ( 'Enter' === event.key ) {
												event.preventDefault();
												doSuggest();
											}
										} }
									/>
									<Button
										variant="secondary"
										__next40pxDefaultSize
										disabled={ ! describe.trim() }
										onClick={ doSuggest }
									>
										{ __(
											'Start from this',
											'sprint-illustrations'
										) }
									</Button>
								</div>
								<p className="si-f-legend">
									{ __(
										'Or pick a starting point',
										'sprint-illustrations'
									) }
								</p>
								<div className="si-f-chips">
									{ options.roles.map( ( role ) => (
										<button
											type="button"
											key={ role.id }
											className="si-f-chip"
											onClick={ () =>
												startFrom(
													{
														...options.defaults,
														...role.choices,
													},
													true
												)
											}
										>
											{ role.label }
										</button>
									) ) }
								</div>
								<SelectControl
									__nextHasNoMarginBottom
									__next40pxDefaultSize
									label={ __(
										'Surprise me with',
										'sprint-illustrations'
									) }
									value={ gender }
									options={ [
										{
											value: 'any',
											label: __(
												'Any look',
												'sprint-illustrations'
											),
										},
										{
											value: 'woman',
											label: __(
												'Longer hair styles',
												'sprint-illustrations'
											),
										},
										{
											value: 'man',
											label: __(
												'Shorter hair styles',
												'sprint-illustrations'
											),
										},
									] }
									onChange={ setGender }
								/>
								<Button
									variant="secondary"
									icon="randomize"
									onClick={ surprise }
								>
									{ __(
										'Surprise me',
										'sprint-illustrations'
									) }
								</Button>
								<Tones
									title={ __(
										'Skin tone',
										'sprint-illustrations'
									) }
									colours={ tones.skin }
									value={ spec.skin }
									onChange={ ( skin ) => set( { skin } ) }
								/>
								<Tones
									title={ __(
										'Hair colour',
										'sprint-illustrations'
									) }
									colours={ tones.hair }
									value={ spec.hair_tone }
									onChange={ ( value ) =>
										set( { hair_tone: value } )
									}
								/>
								<p className="si-f-muted">
									{ __(
										'Tones are for the preview. In a scene each person takes skin and hair from your palette; you can change them for one layer in the Builder.',
										'sprint-illustrations'
									) }
								</p>
							</div>
						) }

						{ rows.map( ( [ name, title, kind ] ) =>
							'colour' === kind ? (
								<ColourGrid
									key={ name }
									title={ title }
									value={ spec[ name ] }
									options={ options.fields[ name ] }
									colors={ colours }
									onChange={ ( value ) =>
										set( { [ name ]: value } )
									}
								/>
							) : (
								<OptionGrid
									key={ name }
									title={ title }
									field={ name }
									value={ spec[ name ] }
									options={ options.fields[ name ] }
									images={ thumbs[ name ] }
									onChange={ ( value ) =>
										set( { [ name ]: value } )
									}
								/>
							)
						) }
					</div>
				</section>

				<section
					className="si-f-stage"
					aria-label={ __( 'Preview', 'sprint-illustrations' ) }
				>
					<div className="si-f-stage__tools">
						<Button
							size="small"
							icon="undo"
							label={ __( 'Undo', 'sprint-illustrations' ) }
							disabled={ ! state.past.length }
							onClick={ () => dispatch( { type: 'UNDO' } ) }
						/>
						<Button
							size="small"
							icon="redo"
							label={ __( 'Redo', 'sprint-illustrations' ) }
							disabled={ ! state.future.length }
							onClick={ () => dispatch( { type: 'REDO' } ) }
						/>
						<Button
							size="small"
							icon="visibility"
							isPressed={ guides }
							onClick={ () => setGuides( ! guides ) }
						>
							{ __( 'Guides', 'sprint-illustrations' ) }
						</Button>
						<Button
							size="small"
							variant="secondary"
							icon="randomize"
							onClick={ surprise }
						>
							{ __( 'Surprise me', 'sprint-illustrations' ) }
						</Button>
					</div>

					<div className="si-f-paper" ref={ stageRef }>
						<div
							className="si-f-sheet"
							style={ { '--si-h': `${ height }px` } }
						>
							<div
								className="si-f-art"
								// Sanitized server-side (figure preview).
								dangerouslySetInnerHTML={ {
									__html: shown?.svg || '',
								} }
							/>
							{ guides && (
								<svg
									className="si-f-guides"
									viewBox="0 0 240 350"
									aria-hidden="true"
									focusable="false"
								>
									{ GUIDES.map( ( [ axis, at, name ] ) =>
										'v' === axis ? (
											<line
												key={ name }
												x1={ at }
												x2={ at }
												y1="0"
												y2="350"
											/>
										) : (
											<g key={ name }>
												<line
													x1="0"
													x2="240"
													y1={ at }
													y2={ at }
												/>
												<text x="6" y={ at - 4 }>
													{ name }
												</text>
											</g>
										)
									) }
								</svg>
							) }
						</div>
					</div>

					<div className="si-f-zoom">
						<Button
							size="small"
							icon="minus"
							label={ __( 'Zoom out', 'sprint-illustrations' ) }
							disabled={ zoom <= ZOOM_MIN }
							onClick={ () =>
								setZoom( clampZoom( zoom - 0.25 ) )
							}
						/>
						<input
							type="range"
							aria-label={ __( 'Zoom', 'sprint-illustrations' ) }
							min={ ZOOM_MIN }
							max={ ZOOM_MAX }
							step="0.05"
							value={ zoom }
							onChange={ ( event ) =>
								setZoom( Number( event.target.value ) )
							}
						/>
						<Button
							size="small"
							icon="plus"
							label={ __( 'Zoom in', 'sprint-illustrations' ) }
							disabled={ zoom >= ZOOM_MAX }
							onClick={ () =>
								setZoom( clampZoom( zoom + 0.25 ) )
							}
						/>
						<Button
							size="small"
							variant="tertiary"
							onClick={ () => setZoom( 1 ) }
						>
							{ `${ Math.round( zoom * 100 ) }%` }
						</Button>
					</div>

					<SelectControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __(
							'Show in palette',
							'sprint-illustrations'
						) }
						value={ palette }
						options={ [
							{
								value: 'site',
								label: __(
									'My site palette',
									'sprint-illustrations'
								),
							},
							...( options.palettes || [] ),
						] }
						onChange={ setPalette }
					/>

					<div className="si-f-save">
						<h2>
							{ __( 'Save to library', 'sprint-illustrations' ) }
						</h2>
						<div className="si-f-row">
							<TextControl
								__nextHasNoMarginBottom
								__next40pxDefaultSize
								label={ __( 'Name', 'sprint-illustrations' ) }
								help={ __(
									'Letters, numbers and dashes. The first part is the person, so sam-figure and sam-wave are one person.',
									'sprint-illustrations'
								) }
								value={ form.name }
								onChange={ ( name ) =>
									setForm( { ...form, name } )
								}
							/>
							<TextControl
								__nextHasNoMarginBottom
								__next40pxDefaultSize
								label={ __( 'Label', 'sprint-illustrations' ) }
								value={ form.label }
								onChange={ ( label ) =>
									setForm( { ...form, label } )
								}
							/>
						</div>
						<TextControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label={ __(
								'Extra tags (optional)',
								'sprint-illustrations'
							) }
							placeholder={ __(
								'driver, friendly',
								'sprint-illustrations'
							) }
							value={ form.tags }
							onChange={ ( tags ) =>
								setForm( { ...form, tags } )
							}
						/>
						<Button
							variant="primary"
							isBusy={ saving }
							disabled={ saving || ! form.name.trim() }
							onClick={ doSave }
						>
							{ __( 'Save figure', 'sprint-illustrations' ) }
						</Button>
						{ saved && (
							<Notice status="success" isDismissible={ false }>
								{ __(
									'Saved. The figure is in the library.',
									'sprint-illustrations'
								) }{ ' ' }
								<a href={ config.libraryUrl }>
									{ __(
										'Open the library',
										'sprint-illustrations'
									) }
								</a>
							</Notice>
						) }
					</div>
				</section>
			</div>
		</div>
	);
}
