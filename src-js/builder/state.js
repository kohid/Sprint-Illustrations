/**
 * Builder state. `picks` holds only locked slots: they survive Shuffle; every other slot re-resolves.
 */
import { __ } from '@wordpress/i18n';

export const MAX_ITEMS = 30;

// Built-in empty template on the server (Template::BLANK_ID).
export const BLANK = 'blank';

export const initialState = {
	library: null,
	spec: {
		template: '',
		seed: 1,
		palette: 'site',
		keywords: '',
		title: '',
		decorative: false,
		canvas: null,
		items: [],
		layers: [],
		animations: {},
		paints: {},
	},
	picks: {},
	selected: null,
	result: null,
	status: 'loading',
	error: '',
	hover: null,
	name: '',
	id: 0,
	dirty: false,
	saving: false,
	notice: null,
};

/**
 * Start on the requested template, else a person-centred hero, else the first template.
 *
 * @param {Object|null} library   Library from /library.
 * @param {string}      preferred Template to start on, when it exists.
 * @return {string} Template ID.
 */
function defaultTemplate( library, preferred = '' ) {
	const templates = library?.templates || [];
	return (
		templates.find( ( t ) => preferred && preferred === t.id )?.id ||
		templates.find( ( t ) => 'hero-left-character' === t.id )?.id ||
		templates[ 0 ]?.id ||
		''
	);
}

/**
 * The request body for /compose and for saving.
 *
 * @param {Object} state Builder state.
 * @return {Object} SceneSpec-shaped body.
 */
export function specBody( state ) {
	const { canvas, items, layers, animations, paints, ...spec } = state.spec;
	return {
		...spec,
		template: spec.template || undefined,
		picks: state.picks,
		...( canvas ? { canvas } : {} ),
		...( items.length ? { items } : {} ),
		...( layers.length ? { layers } : {} ),
		...( Object.keys( animations ).length ? { animations } : {} ),
		...( Object.keys( paints ).length ? { paints } : {} ),
	};
}

/**
 * Animations (or paints) of added pieces only: slot ones belong to the template being left.
 *
 * @param {Object} animations Entries by layer key.
 * @return {Object} Item entries.
 */
function itemAnimations( animations ) {
	return Object.fromEntries(
		Object.entries( animations ).filter( ( [ key ] ) =>
			key.startsWith( 'item:' )
		)
	);
}

/**
 * Lowest free item key (0–99), so a dropped piece keeps its look when others change.
 *
 * @param {Array} items Items.
 * @return {number} Key.
 */
function freeKey( items ) {
	let key = 0;
	while ( items.some( ( item ) => item.key === key ) ) {
		key++;
	}
	return key;
}

/**
 * Copy of the state with new items (and layers), marked dirty.
 *
 * @param {Object} state  State.
 * @param {Array}  items  Items.
 * @param {Array}  layers Layers.
 * @return {Object} State.
 */
function withItems( state, items, layers = state.spec.layers ) {
	return { ...state, spec: { ...state.spec, items, layers }, dirty: true };
}

export function reducer( state, action ) {
	switch ( action.type ) {
		case 'LIBRARY':
			return {
				...state,
				library: action.library,
				spec: {
					...state.spec,
					template:
						state.spec.template ||
						defaultTemplate( action.library, action.preferred ),
				},
			};
		case 'SET_SPEC':
			return {
				...state,
				spec: { ...state.spec, ...action.spec },
				dirty: true,
			};
		case 'SET_TEMPLATE':
			// Dropped pieces and the canvas size stay; the layer order belonged to the old template.
			return {
				...state,
				spec: {
					...state.spec,
					template: action.template,
					layers: [],
					animations: itemAnimations( state.spec.animations ),
					paints: itemAnimations( state.spec.paints ),
				},
				picks: {},
				dirty: true,
			};
		case 'PICK':
			return {
				...state,
				picks: { ...state.picks, [ action.slot ]: action.piece },
				dirty: true,
			};
		case 'UNPICK': {
			const picks = { ...state.picks };
			delete picks[ action.slot ];
			return { ...state, picks, dirty: true };
		}
		case 'COMPOSING':
			return { ...state, status: 'composing' };
		case 'RESULT':
			return {
				...state,
				result: action.result,
				status: 'ready',
				error: '',
				spec: state.spec.template
					? state.spec
					: { ...state.spec, template: action.result.spec.template },
			};
		case 'FAILED':
			return { ...state, status: 'failed', error: action.message };
		case 'ADD_ITEM': {
			const items = state.spec.items;
			if ( items.length >= MAX_ITEMS ) {
				return state;
			}
			const key = freeKey( items );
			// With an explicit order, a new piece goes on top; without one it is on top anyway.
			const layers = state.spec.layers.length
				? [ ...state.spec.layers, `item:${ key }` ]
				: state.spec.layers;
			return {
				...withItems(
					state,
					[ ...items, { ...action.item, key } ],
					layers
				),
				selected: key,
			};
		}
		case 'UPDATE_ITEM':
			return withItems(
				state,
				state.spec.items.map( ( item ) =>
					item.key === action.key
						? { ...item, ...action.changes }
						: item
				)
			);
		case 'REMOVE_ITEM': {
			const animations = { ...state.spec.animations };
			delete animations[ `item:${ action.key }` ];
			const paints = { ...state.spec.paints };
			delete paints[ `item:${ action.key }` ];
			const next = withItems(
				state,
				state.spec.items.filter( ( item ) => item.key !== action.key ),
				state.spec.layers.filter(
					( layer ) => layer !== `item:${ action.key }`
				)
			);
			return {
				...next,
				spec: { ...next.spec, animations, paints },
				selected: state.selected === action.key ? null : state.selected,
			};
		}
		case 'SET_ANIMATION': {
			const animations = { ...state.spec.animations };
			if ( action.value ) {
				animations[ action.key ] = action.value;
			} else {
				delete animations[ action.key ];
			}
			return {
				...state,
				spec: { ...state.spec, animations },
				dirty: true,
			};
		}
		case 'SET_PAINT': {
			const paints = { ...state.spec.paints };
			if ( action.value && Object.keys( action.value ).length ) {
				paints[ action.key ] = action.value;
			} else {
				delete paints[ action.key ];
			}
			return { ...state, spec: { ...state.spec, paints }, dirty: true };
		}
		case 'SELECT':
			return { ...state, selected: action.key };
		case 'SET_LAYERS':
			return {
				...state,
				spec: { ...state.spec, layers: action.layers },
				dirty: true,
			};
		case 'SET_CANVAS':
			return {
				...state,
				spec: { ...state.spec, canvas: action.canvas },
				dirty: true,
			};
		case 'HOVER':
			return { ...state, hover: action.slot };
		case 'NAME':
			return { ...state, name: action.name, dirty: true };
		case 'LOADED': {
			const s = action.item.spec;
			return {
				...state,
				id: action.item.id,
				name: action.item.title,
				spec: {
					template: s.template || '',
					seed: s.seed,
					palette: typeof s.palette === 'string' ? s.palette : 'site',
					keywords: ( s.keywords || [] ).join( ', ' ),
					title: s.title || '',
					decorative: !! s.decorative,
					canvas: Array.isArray( s.canvas ) ? s.canvas : null,
					items: Array.isArray( s.items ) ? s.items : [],
					layers: Array.isArray( s.layers ) ? s.layers : [],
					animations:
						s.animations && ! Array.isArray( s.animations )
							? s.animations
							: {},
					paints:
						s.paints && ! Array.isArray( s.paints ) ? s.paints : {},
				},
				selected: null,
				picks: Array.isArray( s.picks ) ? {} : s.picks || {},
				dirty: false,
				notice: null,
			};
		}
		case 'NEW':
			return {
				...initialState,
				library: state.library,
				spec: {
					...initialState.spec,
					template: action.blank
						? BLANK
						: defaultTemplate( state.library ),
				},
			};
		case 'SAVING':
			return { ...state, saving: true, notice: null };
		case 'SAVED':
			return {
				...state,
				saving: false,
				id: action.item.id,
				name: action.item.title,
				dirty: false,
				notice: { status: 'success', text: action.text },
			};
		case 'SUGGESTED':
			return {
				...state,
				spec: {
					...state.spec,
					template: action.suggestion.template || state.spec.template,
					keywords: ( action.suggestion.keywords || [] ).join( ', ' ),
					title: action.suggestion.title || state.spec.title,
					layers: [],
					animations: itemAnimations( state.spec.animations ),
					paints: itemAnimations( state.spec.paints ),
				},
				picks: {},
				dirty: true,
				notice: {
					status:
						'ai' === action.suggestion.source ? 'success' : 'info',
					text:
						'ai' === action.suggestion.source
							? __(
									'Suggested by Claude.',
									'sprint-illustrations'
							  )
							: action.suggestion.message,
				},
			};
		case 'NOTICE':
			return { ...state, saving: false, notice: action.notice };
		default:
			return state;
	}
}
