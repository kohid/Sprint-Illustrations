/**
 * Character Builder: build a person from parts, see them in your palette, save them to the library.
 * The drawing itself is done by the plugin (pure PHP), so the preview is exactly the saved piece.
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
import { ColourGrid, OptionGrid } from './OptionGrid';

const config = window.sprintIllustrationsCharacters || {};

const TABS = [
	{ id: 'start', label: __( 'Start', 'sprint-illustrations' ) },
	{ id: 'pose', label: __( 'Pose', 'sprint-illustrations' ) },
	{ id: 'body', label: __( 'Body', 'sprint-illustrations' ) },
	{ id: 'outfit', label: __( 'Outfit', 'sprint-illustrations' ) },
	{ id: 'hair', label: __( 'Hair', 'sprint-illustrations' ) },
	{ id: 'head', label: __( 'Head and face', 'sprint-illustrations' ) },
];

// Rows shown on each tab: [field, title, kind].
const ROWS = {
	start: [ [ 'gender', __( 'Character', 'sprint-illustrations' ) ] ],
	pose: [
		[ 'stance', __( 'Standing or sitting', 'sprint-illustrations' ) ],
		[ 'pose', __( 'Pose', 'sprint-illustrations' ) ],
		[ 'legs', __( 'Legs', 'sprint-illustrations' ) ],
	],
	body: [ [ 'build', __( 'Build', 'sprint-illustrations' ) ] ],
	outfit: [
		[ 'top', __( 'Top', 'sprint-illustrations' ) ],
		[ 'top_color', __( 'Top colour', 'sprint-illustrations' ), 'colour' ],
		[ 'outer', __( 'Jacket or layer', 'sprint-illustrations' ) ],
		[
			'outer_color',
			__( 'Layer colour', 'sprint-illustrations' ),
			'colour',
		],
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
		[ 'bag', __( 'Bag', 'sprint-illustrations' ) ],
		[ 'bag_color', __( 'Bag colour', 'sprint-illustrations' ), 'colour' ],
		[ 'extra', __( 'Extra', 'sprint-illustrations' ) ],
		[
			'extra_color',
			__( 'Extra colour', 'sprint-illustrations' ),
			'colour',
		],
	],
	hair: [ [ 'hair', __( 'Hair style', 'sprint-illustrations' ) ] ],
	head: [
		[ 'face', __( 'Face', 'sprint-illustrations' ) ],
		[ 'headwear', __( 'Headwear', 'sprint-illustrations' ) ],
		[
			'headwear_color',
			__( 'Headwear colour', 'sprint-illustrations' ),
			'colour',
		],
		[ 'glasses', __( 'Glasses', 'sprint-illustrations' ) ],
		[ 'facial_hair', __( 'Facial hair', 'sprint-illustrations' ) ],
	],
};

const HISTORY = 50;

function reducer( state, action ) {
	switch ( action.type ) {
		case 'SET': {
			const spec = { ...state.spec, ...action.changes };
			if ( JSON.stringify( spec ) === JSON.stringify( state.spec ) ) {
				return state;
			}
			return {
				...state,
				spec,
				past: [ ...state.past, state.spec ].slice( -HISTORY ),
				future: [],
			};
		}
		case 'REPLACE': {
			// A whole new character (preset, shuffle, suggestion): keep the tones being previewed.
			const spec = {
				...action.spec,
				skin: state.spec?.skin ?? 0,
				hair_tone: state.spec?.hair_tone ?? 0,
			};
			return {
				...state,
				spec,
				// The very first character has nothing before it to undo to.
				past: state.spec
					? [ ...state.past, state.spec ].slice( -HISTORY )
					: [],
				future: [],
			};
		}
		case 'UNDO':
			return state.past.length
				? {
						...state,
						spec: state.past[ state.past.length - 1 ],
						past: state.past.slice( 0, -1 ),
						future: [ state.spec, ...state.future ],
				  }
				: state;
		case 'REDO':
			return state.future.length
				? {
						...state,
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
	const [ notice, setNotice ] = useState( null );
	const [ form, setForm ] = useState( { name: '', label: '', tags: '' } );
	const [ saving, setSaving ] = useState( false );
	const [ saved, setSaved ] = useState( null );
	const seed = useRef( 1 );
	const latest = useRef( 0 );

	useEffect( () => {
		getOptions()
			.then( ( data ) => {
				setOptions( data );
				dispatch( {
					type: 'REPLACE',
					spec: { ...data.defaults, skin: 0, hair_tone: 0 },
				} );
			} )
			.catch( ( error ) =>
				setLoadError(
					error.message ||
						__(
							'The Character Builder could not load.',
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
	const rows = ROWS[ tab ] || [];
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

	const set = useCallback(
		( changes ) => {
			setSaved( null );
			dispatch( { type: 'SET', changes } );
		},
		[ dispatch ]
	);

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

	const field = ( name ) =>
		'pose' === name
			? options.fields.pose[ spec.stance ]
			: options.fields[ name ];
	const colours = shown?.palette?.colors || {};
	const tones = shown?.palette || options.tones;

	const startFrom = ( result ) => {
		setSaved( null );
		// A chosen gender stays when starting from a role or a shuffle.
		dispatch( {
			type: 'REPLACE',
			spec: { gender: spec?.gender, ...result },
		} );
	};

	const doSuggest = () =>
		suggest( describe )
			.then( ( result ) => {
				if ( result.role ) {
					startFrom( result.spec );
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
				setNotice( {
					status: 'error',
					text: error.message,
				} )
			);

	const surprise = () => {
		seed.current += 1 + Math.floor( Math.random() * 1000 );
		shuffle( seed.current, spec?.gender )
			.then( ( result ) => startFrom( result.spec ) )
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
							'The character could not be saved.',
							'sprint-illustrations'
						),
				} )
			)
			.finally( () => setSaving( false ) );
	};

	return (
		<div className="si-c-app">
			<header className="si-c-head">
				<h1>{ __( 'Character Builder', 'sprint-illustrations' ) }</h1>
				<p>
					{ __(
						'Build a person to match your page: pick a starting point, adjust the look, then save them. They join the library, so the Builder and every scene can use them, and their colours follow your palette.',
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

			<div className="si-c-layout">
				<section
					className="si-c-panel si-c-stage"
					aria-label={ __( 'Preview', 'sprint-illustrations' ) }
				>
					<div className="si-c-stage__tools">
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
							variant="secondary"
							icon="randomize"
							onClick={ surprise }
						>
							{ __( 'Surprise me', 'sprint-illustrations' ) }
						</Button>
					</div>
					<div
						className="si-c-stage__art"
						// Sanitized server-side (character preview).
						dangerouslySetInnerHTML={ { __html: shown?.svg || '' } }
					/>
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
					<Swatches
						title={ __(
							'Preview skin tone',
							'sprint-illustrations'
						) }
						colours={ tones.skin }
						value={ spec.skin }
						onChange={ ( skin ) => set( { skin } ) }
					/>
					<Swatches
						title={ __(
							'Preview hair colour',
							'sprint-illustrations'
						) }
						colours={ tones.hair }
						value={ spec.hair_tone }
						onChange={ ( hair ) => set( { hair_tone: hair } ) }
					/>
					<p className="si-c-muted">
						{ __(
							'Tones are for preview. In a scene each character takes skin and hair from your palette; you can change them for one layer in the Builder.',
							'sprint-illustrations'
						) }
					</p>
				</section>

				<section
					className="si-c-panel si-c-controls"
					aria-label={ __( 'Options', 'sprint-illustrations' ) }
				>
					<div
						className="si-c-tabs"
						role="tablist"
						aria-label={ __( 'Options', 'sprint-illustrations' ) }
					>
						{ TABS.map( ( item ) => (
							<button
								type="button"
								role="tab"
								key={ item.id }
								id={ `si-c-tab-${ item.id }` }
								aria-selected={ tab === item.id }
								aria-controls="si-c-tabpanel"
								className="si-c-tab"
								onClick={ () => setTab( item.id ) }
							>
								{ item.label }
							</button>
						) ) }
					</div>

					<div
						id="si-c-tabpanel"
						role="tabpanel"
						aria-labelledby={ `si-c-tab-${ tab }` }
						className="si-c-tabpanel"
					>
						{ 'start' === tab && (
							<div className="si-c-start">
								<div className="si-c-row">
									<TextControl
										__nextHasNoMarginBottom
										__next40pxDefaultSize
										label={ __(
											'Who is on your page?',
											'sprint-illustrations'
										) }
										placeholder={ __(
											'e.g. a taxi driver studying for a test',
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
								<p className="si-c-legend">
									{ __(
										'Or pick a starting point',
										'sprint-illustrations'
									) }
								</p>
								<div className="si-c-chips">
									{ options.roles.map( ( role ) => (
										<button
											type="button"
											key={ role.id }
											className="si-c-chip"
											onClick={ () =>
												startFrom( {
													...options.defaults,
													...role.choices,
												} )
											}
										>
											{ role.label }
										</button>
									) ) }
								</div>
							</div>
						) }

						{ rows.map( ( [ name, title, kind ] ) =>
							'colour' === kind ? (
								<ColourGrid
									key={ name }
									title={ title }
									value={ spec[ name ] }
									options={ field( name ) }
									colors={ colours }
									onChange={ ( value ) =>
										set( { [ name ]: value } )
									}
								/>
							) : (
								<OptionGrid
									key={ name }
									field={ name }
									title={ title }
									value={ spec[ name ] }
									options={ field( name ) }
									images={ thumbs[ name ] }
									onChange={ ( value ) =>
										set( {
											[ name ]: value,
											...( 'gender' === name
												? options.genderLooks?.[ value ]
												: {} ),
										} )
									}
								/>
							)
						) }
					</div>

					<div className="si-c-save">
						<h2>
							{ __(
								'Save to the library',
								'sprint-illustrations'
							) }
						</h2>
						<div className="si-c-row">
							<TextControl
								__nextHasNoMarginBottom
								__next40pxDefaultSize
								label={ __( 'Name', 'sprint-illustrations' ) }
								help={ __(
									'Letters, numbers and dashes. Characters that start with the same word are the same person, e.g. sam-wave and sam-sit.',
									'sprint-illustrations'
								) }
								placeholder="sam-wave"
								value={ form.name }
								onChange={ ( name ) =>
									setForm( { ...form, name } )
								}
							/>
							<TextControl
								__nextHasNoMarginBottom
								__next40pxDefaultSize
								label={ __(
									'Label (optional)',
									'sprint-illustrations'
								) }
								placeholder={ __(
									'Sam waving',
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
							help={ __(
								'Single words, separated by commas, so scenes can find them: taxi, driver, support.',
								'sprint-illustrations'
							) }
							value={ form.tags }
							onChange={ ( tags ) =>
								setForm( { ...form, tags } )
							}
						/>
						<Button
							variant="primary"
							__next40pxDefaultSize
							isBusy={ saving }
							disabled={ saving || ! form.name.trim() }
							onClick={ doSave }
						>
							{ __( 'Save character', 'sprint-illustrations' ) }
						</Button>
						{ saved && (
							<Notice status="success" isDismissible={ false }>
								{ sprintf(
									/* translators: %s: piece ID. */
									__(
										'Saved as %s.',
										'sprint-illustrations'
									),
									saved.piece
								) }{ ' ' }
								{ 'plugin' === saved.where
									? __(
											'It is in the plugin’s library, so it ships with the plugin.',
											'sprint-illustrations'
									  )
									: __(
											'It is in this site’s library.',
											'sprint-illustrations'
									  ) }{ ' ' }
								<a href={ config.builderUrl }>
									{ __(
										'Use it in the Builder',
										'sprint-illustrations'
									) }
								</a>
								{ ' · ' }
								<a href={ config.libraryUrl }>
									{ __(
										'See it in the Library',
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

function Swatches( { title, colours, value, onChange } ) {
	return (
		<fieldset className="si-c-options si-c-options--tones">
			<legend>{ title }</legend>
			<div className="si-c-swatches">
				{ ( colours || [] ).map( ( colour, index ) => (
					<button
						type="button"
						key={ `${ colour }-${ index }` }
						className="si-c-swatch"
						aria-pressed={ value === index }
						aria-label={ `${ title } ${ index + 1 }` }
						style={ { background: colour } }
						onClick={ () => onChange( index ) }
					/>
				) ) }
			</div>
		</fieldset>
	);
}
