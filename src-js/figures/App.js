/**
 * Figure studio: build a full-body cartoon figure from parts, see it in your palette, download it, share a
 * link to it and (for editors) save it to the library. The drawing is done by the plugin (pure PHP), so the
 * preview is exactly the saved or downloaded piece.
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
import Icon from './icons';
import { downloadPng, downloadSvg } from './exportPng';
import { recall, remember, shareLink, specFromHash } from './share';

const config = window.sprintIllustrationsFigures || {};

const TABS = [
	{
		id: 'start',
		label: __( 'Start', 'sprint-illustrations' ),
		icon: 'start',
	},
	{ id: 'pose', label: __( 'Pose', 'sprint-illustrations' ), icon: 'pose' },
	{ id: 'face', label: __( 'Face', 'sprint-illustrations' ), icon: 'face' },
	{ id: 'hair', label: __( 'Hair', 'sprint-illustrations' ), icon: 'hair' },
	{
		id: 'gear',
		label: __( 'Head gear', 'sprint-illustrations' ),
		icon: 'gear',
	},
	{
		id: 'outfit',
		label: __( 'Outfit', 'sprint-illustrations' ),
		icon: 'outfit',
	},
	{
		id: 'scene',
		label: __( 'Backdrop', 'sprint-illustrations' ),
		icon: 'scene',
	},
];

const TAB_HELP = {
	start: __(
		'Pick a starting point, or describe who is on your page.',
		'sprint-illustrations'
	),
	pose: __(
		'How they stand, what they hold and how they are built.',
		'sprint-illustrations'
	),
	face: __(
		'Face shape, eyes, mouth and skin tone.',
		'sprint-illustrations'
	),
	hair: __( 'Hair style and colour.', 'sprint-illustrations' ),
	gear: __( 'Hats and glasses.', 'sprint-illustrations' ),
	outfit: __( 'Clothes, shoes and their colours.', 'sprint-illustrations' ),
	scene: __( 'The soft splash behind the figure.', 'sprint-illustrations' ),
	save: __(
		'Add this figure to the plugin library.',
		'sprint-illustrations'
	),
};

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
		[ 'skin', __( 'Skin tone', 'sprint-illustrations' ), 'skin' ],
		[ 'face', __( 'Face shape', 'sprint-illustrations' ) ],
		[ 'eyes', __( 'Eyes', 'sprint-illustrations' ) ],
		[ 'brows', __( 'Brows', 'sprint-illustrations' ) ],
		[ 'nose', __( 'Nose', 'sprint-illustrations' ) ],
		[ 'mouth', __( 'Mouth', 'sprint-illustrations' ) ],
		[ 'cheeks', __( 'Cheeks', 'sprint-illustrations' ) ],
	],
	hair: [
		[ 'hair_tone', __( 'Hair colour', 'sprint-illustrations' ), 'hair' ],
		[ 'hair', __( 'Hair style', 'sprint-illustrations' ) ],
	],
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

// Which design fields each randomise lock protects.
const LOCKS = [
	[
		'pose',
		__( 'Pose and build', 'sprint-illustrations' ),
		[ 'pose', 'carry', 'build', 'backpack', 'bag_color', 'tilt', 'flip' ],
	],
	[
		'face',
		__( 'Face and skin', 'sprint-illustrations' ),
		[ 'face', 'eyes', 'brows', 'nose', 'mouth', 'cheeks', 'skin' ],
	],
	[ 'hair', __( 'Hair', 'sprint-illustrations' ), [ 'hair', 'hair_tone' ] ],
	[
		'gear',
		__( 'Head gear', 'sprint-illustrations' ),
		[ 'headwear', 'headwear_color', 'eyewear' ],
	],
	[
		'outfit',
		__( 'Outfit', 'sprint-illustrations' ),
		[
			'top',
			'top_color',
			'bottom',
			'bottom_color',
			'shoes',
			'shoes_color',
		],
	],
	[
		'scene',
		__( 'Backdrop', 'sprint-illustrations' ),
		[ 'scene', 'scene_color' ],
	],
];

// Construction lines in the figure's own 240 × 350 coordinates.
const GUIDES = [
	[ 'v', 120, __( 'centre line', 'sprint-illustrations' ) ],
	[ 'h', 106, __( 'eyes', 'sprint-illustrations' ) ],
	[ 'h', 154, __( 'chin', 'sprint-illustrations' ) ],
	[ 'h', 168, __( 'shoulders', 'sprint-illustrations' ) ],
	[ 'h', 222, __( 'hips', 'sprint-illustrations' ) ],
	[ 'h', 336, __( 'ground', 'sprint-illustrations' ) ],
];

const HISTORY = 50;
const GALLERY = 8;
const ZOOM_MIN = 0.6;
const ZOOM_MAX = 1.6;

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
		case 'REPLACE':
			return {
				spec: action.spec,
				past: state.spec
					? [ ...state.past, state.spec ].slice( -HISTORY )
					: [],
				future: [],
			};
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
	const [ locks, setLocks ] = useState( {} );
	const [ gallery, setGallery ] = useState( [] );
	const [ notice, setNotice ] = useState( null );
	const [ form, setForm ] = useState( { name: '', label: '', tags: '' } );
	const [ saving, setSaving ] = useState( false );
	const [ saved, setSaved ] = useState( null );
	const [ guides, setGuides ] = useState( false );
	const [ zoom, setZoom ] = useState( 1 );
	const [ menu, setMenu ] = useState( false );
	const [ toast, setToast ] = useState( '' );
	const seed = useRef( 1 );
	const latest = useRef( 0 );
	const wheelOff = useRef( null );

	const tabs = config.canSave
		? [
				...TABS,
				{
					id: 'save',
					label: __( 'Save', 'sprint-illustrations' ),
					icon: 'save',
				},
		  ]
		: TABS;

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

	// The starting design: a shared link, then what this browser remembers, then the defaults.
	useEffect( () => {
		getOptions()
			.then( ( data ) => {
				setOptions( data );
				dispatch( {
					type: 'REPLACE',
					spec: {
						...data.defaults,
						...( specFromHash() || recall() || {} ),
					},
				} );
			} )
			.catch( ( error ) =>
				setLoadError(
					error.message ||
						__(
							'The figure studio could not load.',
							'sprint-illustrations'
						)
				)
			);
	}, [] );

	const spec = state.spec;
	const specKey = JSON.stringify( spec );

	// Live preview: only the newest answer is shown, and the design is remembered in this browser.
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
						remember( data.spec );
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
			rows.filter( ( row ) => ! row[ 2 ] ).forEach( ( [ field ] ) => {
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
			} );
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

	const flash = useCallback( ( text ) => {
		setToast( text );
		window.setTimeout( () => setToast( '' ), 2200 );
	}, [] );

	// What the studio was showing before a bigger change, kept as a tile to come back to.
	const rememberLook = useCallback( () => {
		if ( ! shown?.svg ) {
			return;
		}
		setGallery( ( tiles ) =>
			[
				{ spec: shown.spec, svg: shown.svg },
				...tiles.filter(
					( tile ) =>
						JSON.stringify( tile.spec ) !==
						JSON.stringify( shown.spec )
				),
			].slice( 0, GALLERY )
		);
	}, [ shown ] );

	const startFrom = useCallback(
		( next ) => {
			rememberLook();
			setSaved( null );
			dispatch( { type: 'REPLACE', spec: next } );
		},
		[ rememberLook ]
	);

	const surprise = useCallback( () => {
		if ( ! options || ! spec ) {
			return;
		}
		seed.current += 1 + Math.floor( Math.random() * 1000 );
		shuffle( seed.current, gender, spec )
			.then( ( result ) => {
				const next = { ...result.spec };
				// What is locked keeps its current value.
				LOCKS.filter( ( [ id ] ) => locks[ id ] ).forEach(
					( [ , , fields ] ) =>
						fields.forEach( ( field ) => {
							next[ field ] = spec[ field ];
						} )
				);
				startFrom( next );
			} )
			.catch( () => {} );
	}, [ options, spec, gender, locks, startFrom ] );

	// Keyboard: undo, redo and R for a new look (not while typing).
	useEffect( () => {
		const onKey = ( event ) => {
			const typing = /^(INPUT|TEXTAREA|SELECT)$/.test(
				event.target?.tagName || ''
			);
			if (
				( event.ctrlKey || event.metaKey ) &&
				'z' === event.key.toLowerCase()
			) {
				event.preventDefault();
				dispatch( { type: event.shiftKey ? 'REDO' : 'UNDO' } );
			} else if (
				( event.ctrlKey || event.metaKey ) &&
				'y' === event.key.toLowerCase()
			) {
				event.preventDefault();
				dispatch( { type: 'REDO' } );
			} else if (
				! typing &&
				! event.ctrlKey &&
				! event.metaKey &&
				'r' === event.key.toLowerCase()
			) {
				surprise();
			}
		};
		window.addEventListener( 'keydown', onKey );
		return () => window.removeEventListener( 'keydown', onKey );
	}, [ surprise ] );

	if ( loadError ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ loadError }
			</Notice>
		);
	}
	if ( ! options || ! spec ) {
		return (
			<div className="si-f-loading">
				<Spinner />
			</div>
		);
	}

	const colours = shown?.palette?.colors || {};
	const tones = shown?.palette || options.tones;

	const doSuggest = () =>
		suggest( describe )
			.then( ( result ) => {
				if ( result.role ) {
					startFrom( {
						...options.defaults,
						skin: spec.skin,
						hair_tone: spec.hair_tone,
						...result.spec,
					} );
					setNotice( {
						status: 'success',
						text: sprintf(
							/* translators: %s: role, e.g. "Taxi driver". */
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

	// When the browser won't copy for us, show the link to copy by hand.
	const showLink = ( link ) =>
		setNotice( {
			status: 'info',
			text: sprintf(
				/* translators: %s: address of the shared figure. */
				__( 'Copy this link: %s', 'sprint-illustrations' ),
				link
			),
		} );

	const copyLink = () => {
		const link = shareLink( shown?.spec || spec );
		const done = () =>
			flash(
				__(
					'Link copied. Anyone with it can open this figure.',
					'sprint-illustrations'
				)
			);
		if ( window.navigator.clipboard?.writeText ) {
			window.navigator.clipboard
				.writeText( link )
				.then( done, () => showLink( link ) );
		} else {
			showLink( link );
		}
	};

	const exportAs = ( kind ) => {
		setMenu( false );
		if ( ! shown?.svg ) {
			return;
		}
		if ( 'svg' === kind ) {
			downloadSvg( shown.svg, 'figure.svg' );
			return;
		}
		downloadPng(
			shown.svg,
			1400,
			'solid' === kind ? colours.background || '#ffffff' : null,
			'figure.png'
		).catch( () =>
			setNotice( {
				status: 'error',
				text: __(
					'The PNG could not be made in this browser. Try the SVG.',
					'sprint-illustrations'
				),
			} )
		);
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

	const setLock = ( id, on ) =>
		setLocks( ( all ) => ( { ...all, [ id ]: on } ) );

	return (
		<div className={ `si-f-app${ config.front ? ' is-front' : '' }` }>
			<header className="si-f-bar">
				<div className="si-f-brand">
					<span className="si-f-mark" aria-hidden="true" />
					<div>
						<strong>
							{ config.title ||
								__( 'Figure studio', 'sprint-illustrations' ) }
						</strong>
						{ config.shortcode ? (
							<code
								className="si-f-code"
								title={ __(
									'Put this shortcode on any page',
									'sprint-illustrations'
								) }
							>
								{ config.shortcode }
							</code>
						) : null }
					</div>
				</div>
				<div
					className="si-f-tools"
					role="toolbar"
					aria-label={ __( 'Studio tools', 'sprint-illustrations' ) }
				>
					<button
						type="button"
						className="si-f-tool"
						disabled={ ! state.past.length }
						onClick={ () => dispatch( { type: 'UNDO' } ) }
						title={ __( 'Undo (Ctrl+Z)', 'sprint-illustrations' ) }
					>
						<Icon name="undo" />
						<span>{ __( 'Undo', 'sprint-illustrations' ) }</span>
					</button>
					<button
						type="button"
						className="si-f-tool"
						disabled={ ! state.future.length }
						onClick={ () => dispatch( { type: 'REDO' } ) }
						title={ __(
							'Redo (Ctrl+Shift+Z)',
							'sprint-illustrations'
						) }
					>
						<Icon name="redo" />
						<span>{ __( 'Redo', 'sprint-illustrations' ) }</span>
					</button>
					<span className="si-f-sep" aria-hidden="true" />
					<button
						type="button"
						className="si-f-tool is-strong"
						onClick={ surprise }
						title={ __( 'A new look (R)', 'sprint-illustrations' ) }
					>
						<Icon name="random" />
						<span>
							{ __( 'Randomise', 'sprint-illustrations' ) }
						</span>
					</button>
					<button
						type="button"
						className="si-f-tool"
						aria-pressed={ 'yes' === spec.flip }
						onClick={ () =>
							set( { flip: 'yes' === spec.flip ? 'no' : 'yes' } )
						}
					>
						<Icon name="mirror" />
						<span>{ __( 'Mirror', 'sprint-illustrations' ) }</span>
					</button>
					<button
						type="button"
						className="si-f-tool"
						aria-pressed={ guides }
						onClick={ () => setGuides( ! guides ) }
					>
						<Icon name="grid" />
						<span>{ __( 'Guides', 'sprint-illustrations' ) }</span>
					</button>
					<span className="si-f-sep" aria-hidden="true" />
					<button
						type="button"
						className="si-f-tool"
						onClick={ copyLink }
					>
						<Icon name="share" />
						<span>
							{ __( 'Share link', 'sprint-illustrations' ) }
						</span>
					</button>
					<div className="si-f-menu">
						<button
							type="button"
							className="si-f-tool is-accent"
							aria-expanded={ menu }
							aria-haspopup="menu"
							onClick={ () => setMenu( ! menu ) }
						>
							<Icon name="download" />
							<span>
								{ __( 'Download', 'sprint-illustrations' ) }
							</span>
						</button>
						{ menu && (
							<div className="si-f-menu__list" role="menu">
								<button
									type="button"
									role="menuitem"
									onClick={ () => exportAs( 'svg' ) }
								>
									<strong>
										{ __( 'SVG', 'sprint-illustrations' ) }
									</strong>
									<small>
										{ __(
											'Vector, any size',
											'sprint-illustrations'
										) }
									</small>
								</button>
								<button
									type="button"
									role="menuitem"
									onClick={ () => exportAs( 'png' ) }
								>
									<strong>
										{ __(
											'PNG, transparent',
											'sprint-illustrations'
										) }
									</strong>
									<small>
										{ __(
											'1400 px tall',
											'sprint-illustrations'
										) }
									</small>
								</button>
								<button
									type="button"
									role="menuitem"
									onClick={ () => exportAs( 'solid' ) }
								>
									<strong>
										{ __(
											'PNG, on colour',
											'sprint-illustrations'
										) }
									</strong>
									<small>
										{ __(
											'Your palette background',
											'sprint-illustrations'
										) }
									</small>
								</button>
							</div>
						) }
					</div>
				</div>
			</header>

			{ notice && (
				<Notice
					status={ notice.status }
					onRemove={ () => setNotice( null ) }
				>
					{ notice.text }
				</Notice>
			) }

			<div className="si-f-body">
				<div
					className="si-f-rail"
					role="tablist"
					aria-orientation="vertical"
					aria-label={ __( 'Parts', 'sprint-illustrations' ) }
				>
					{ tabs.map( ( item ) => (
						<button
							type="button"
							role="tab"
							key={ item.id }
							id={ `si-f-tab-${ item.id }` }
							aria-selected={ tab === item.id }
							aria-controls="si-f-tabpanel"
							className="si-f-railbtn"
							onClick={ () => setTab( item.id ) }
						>
							<Icon name={ item.icon } size={ 24 } />
							<span>{ item.label }</span>
						</button>
					) ) }
				</div>

				<section
					id="si-f-tabpanel"
					role="tabpanel"
					aria-labelledby={ `si-f-tab-${ tab }` }
					className="si-f-panel"
				>
					<div className="si-f-panel__head">
						<h2>
							{
								( tabs.find( ( t ) => t.id === tab ) || {} )
									.label
							}
						</h2>
						<p>{ TAB_HELP[ tab ] }</p>
					</div>

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
									'Starting points',
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
											startFrom( {
												...options.defaults,
												skin: spec.skin,
												hair_tone: spec.hair_tone,
												...role.choices,
											} )
										}
									>
										{ role.label }
									</button>
								) ) }
							</div>

							<p className="si-f-legend">
								{ __( 'Randomise', 'sprint-illustrations' ) }
							</p>
							<SelectControl
								__nextHasNoMarginBottom
								__next40pxDefaultSize
								label={ __(
									'Hair styles to pick from',
									'sprint-illustrations'
								) }
								value={ gender }
								options={ [
									{
										value: 'any',
										label: __(
											'All styles',
											'sprint-illustrations'
										),
									},
									{
										value: 'woman',
										label: __(
											'Longer styles',
											'sprint-illustrations'
										),
									},
									{
										value: 'man',
										label: __(
											'Shorter styles',
											'sprint-illustrations'
										),
									},
								] }
								onChange={ setGender }
							/>
							<fieldset className="si-f-locks">
								<legend>
									{ __(
										'Keep these as they are',
										'sprint-illustrations'
									) }
								</legend>
								{ LOCKS.map( ( [ id, label ] ) => (
									<label
										key={ id }
										htmlFor={ `si-f-lock-${ id }` }
										className={ locks[ id ] ? 'is-on' : '' }
									>
										<input
											id={ `si-f-lock-${ id }` }
											type="checkbox"
											checked={ !! locks[ id ] }
											onChange={ ( event ) =>
												setLock(
													id,
													event.target.checked
												)
											}
										/>
										<Icon name="lock" size={ 14 } />
										{ label }
									</label>
								) ) }
							</fieldset>
							<Button
								variant="primary"
								icon="randomize"
								onClick={ surprise }
							>
								{ __( 'Randomise', 'sprint-illustrations' ) }
							</Button>
						</div>
					) }

					{ 'save' === tab && (
						<div className="si-f-save">
							<div className="si-f-row is-two">
								<TextControl
									__nextHasNoMarginBottom
									__next40pxDefaultSize
									label={ __(
										'Name',
										'sprint-illustrations'
									) }
									help={ __(
										'Letters, numbers and dashes. The first part is the person, so sam-wave and sam-sit are one person.',
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
									label={ __(
										'Label',
										'sprint-illustrations'
									) }
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
									'builder, friendly',
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
								{ __(
									'Save to library',
									'sprint-illustrations'
								) }
							</Button>
							{ saved && (
								<Notice
									status="success"
									isDismissible={ false }
								>
									{ __(
										'Saved. The figure is in the library.',
										'sprint-illustrations'
									) }{ ' ' }
									{ config.libraryUrl ? (
										<a href={ config.libraryUrl }>
											{ __(
												'Open the library',
												'sprint-illustrations'
											) }
										</a>
									) : null }
								</Notice>
							) }
						</div>
					) }

					{ rows.map( ( [ name, title, kind ] ) => {
						if ( 'skin' === kind || 'hair' === kind ) {
							return (
								<Tones
									key={ name }
									title={ title }
									colours={
										'skin' === kind
											? tones.skin
											: tones.hair
									}
									value={ spec[ name ] }
									onChange={ ( value ) =>
										set( { [ name ]: value } )
									}
								/>
							);
						}
						if ( 'colour' === kind ) {
							return (
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
							);
						}
						return (
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
						);
					} ) }
				</section>

				<section
					className="si-f-stage"
					aria-label={ __( 'Preview', 'sprint-illustrations' ) }
				>
					<div className="si-f-paper" ref={ stageRef }>
						<div
							className="si-f-sheet"
							style={ { '--si-zoom': zoom } }
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
												<text x="4" y={ at - 3 }>
													{ name }
												</text>
											</g>
										)
									) }
								</svg>
							) }
						</div>
						<div className="si-f-zoom">
							<button
								type="button"
								aria-label={ __(
									'Zoom out',
									'sprint-illustrations'
								) }
								disabled={ zoom <= ZOOM_MIN }
								onClick={ () =>
									setZoom( clampZoom( zoom - 0.1 ) )
								}
							>
								<Icon name="minus" size={ 16 } />
							</button>
							<button
								type="button"
								className="is-level"
								onClick={ () => setZoom( 1 ) }
								title={ __(
									'Reset zoom',
									'sprint-illustrations'
								) }
							>
								{ `${ Math.round( zoom * 100 ) }%` }
							</button>
							<button
								type="button"
								aria-label={ __(
									'Zoom in',
									'sprint-illustrations'
								) }
								disabled={ zoom >= ZOOM_MAX }
								onClick={ () =>
									setZoom( clampZoom( zoom + 0.1 ) )
								}
							>
								<Icon name="plus" size={ 16 } />
							</button>
						</div>
						{ toast && (
							<div className="si-f-toast" role="status">
								{ toast }
							</div>
						) }
					</div>

					<div className="si-f-foot">
						<div
							className="si-f-gallery"
							aria-label={ __(
								'Earlier looks',
								'sprint-illustrations'
							) }
						>
							<button
								type="button"
								className="si-f-pin"
								onClick={ () => {
									rememberLook();
									flash(
										__(
											'Pinned this look.',
											'sprint-illustrations'
										)
									);
								} }
								title={ __(
									'Pin this look to come back to it',
									'sprint-illustrations'
								) }
							>
								<Icon name="pin" size={ 18 } />
								<span>
									{ __( 'Pin look', 'sprint-illustrations' ) }
								</span>
							</button>
							{ gallery.map( ( tile, index ) => (
								<button
									type="button"
									key={ index }
									className="si-f-tile"
									onClick={ () => {
										rememberLook();
										dispatch( {
											type: 'REPLACE',
											spec: tile.spec,
										} );
									} }
									aria-label={ sprintf(
										/* translators: %d: number of the earlier look. */ __(
											'Go back to look %d',
											'sprint-illustrations'
										),
										index + 1
									) }
									// Sanitized server-side (figure preview).
									dangerouslySetInnerHTML={ {
										__html: tile.svg,
									} }
								/>
							) ) }
							{ ! gallery.length && (
								<span className="si-f-muted">
									{ __(
										'Looks you leave behind appear here.',
										'sprint-illustrations'
									) }
								</span>
							) }
						</div>
						<SelectControl
							__nextHasNoMarginBottom
							label={ __( 'Palette', 'sprint-illustrations' ) }
							value={ palette }
							options={ [
								{
									value: 'site',
									label: __(
										'This site',
										'sprint-illustrations'
									),
								},
								...( options.palettes || [] ),
							] }
							onChange={ setPalette }
						/>
					</div>
				</section>
			</div>
		</div>
	);
}
