/**
 * Builder state. `picks` holds only locked slots: they survive Shuffle; every other slot re-resolves.
 */
export const initialState = {
	library: null,
	spec: {
		template: '',
		seed: 1,
		palette: 'site',
		keywords: '',
		title: '',
		decorative: false,
	},
	picks: {},
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
 * Start from a person-centred hero when the library has one; otherwise the first template.
 *
 * @param {Object|null} library Library from /library.
 * @return {string} Template ID.
 */
function defaultTemplate( library ) {
	const templates = library?.templates || [];
	return (
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
	return {
		...state.spec,
		template: state.spec.template || undefined,
		picks: state.picks,
	};
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
						defaultTemplate( action.library ),
				},
			};
		case 'SET_SPEC':
			return {
				...state,
				spec: { ...state.spec, ...action.spec },
				dirty: true,
			};
		case 'SET_TEMPLATE':
			return {
				...state,
				spec: { ...state.spec, template: action.template },
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
				},
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
					template: defaultTemplate( state.library ),
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
		case 'NOTICE':
			return { ...state, saving: false, notice: action.notice };
		default:
			return state;
	}
}
