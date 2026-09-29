/**
 * Draw new pieces for a described scene. Suggest lists words the library has nothing for; the
 * user picks what to draw (or types their own), each becomes a piece request, and once the pieces
 * are kept in the Library the scene is built with them placed on the canvas.
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { Button, SelectControl, TextControl } from '@wordpress/components';
import {
	buildPlan,
	createPlan,
	deletePlan,
	discardPiece,
	keepPiece,
	listPlans,
	splitBrief,
} from './api';

const POLL_MS = 8000;

// What a request draws. A character is drawn with skin and hair colours you can change afterwards.
const KINDS = [
	{ value: 'objects', label: __( 'Object', 'sprint-illustrations' ) },
	{ value: 'characters', label: __( 'Character', 'sprint-illustrations' ) },
	{ value: 'decor', label: __( 'Decor', 'sprint-illustrations' ) },
	{ value: 'backgrounds', label: __( 'Background', 'sprint-illustrations' ) },
];

const kindLabel = ( value ) =>
	KINDS.find( ( kind ) => kind.value === value )?.label || value;

const STEPS = [
	{ state: 'queued', label: __( 'Requested', 'sprint-illustrations' ) },
	{ state: 'drawing', label: __( 'Drawing', 'sprint-illustrations' ) },
	{
		state: 'review',
		label: __( 'Ready for review', 'sprint-illustrations' ),
	},
	{ state: 'done', label: __( 'In the library', 'sprint-illustrations' ) },
];

/**
 * Requested → Drawing → Ready for review → In the library, with the current step marked.
 *
 * @param {Object} props       Props.
 * @param {string} props.state Request state.
 * @return {Element|null} Track.
 */
function Track( { state } ) {
	const current = STEPS.findIndex( ( step ) => step.state === state );
	if ( current < 0 ) {
		return null;
	}
	return (
		<ol
			className="si-b-track"
			aria-label={ __( 'Progress', 'sprint-illustrations' ) }
		>
			{ STEPS.map( ( step, index ) => (
				<li
					key={ step.state }
					className={ `si-b-track__step${
						index < current || 'done' === state ? ' is-done' : ''
					}${ index === current ? ' is-current' : '' }` }
					aria-current={ index === current ? 'step' : undefined }
				>
					{ step.label }
				</li>
			) ) }
		</ol>
	);
}

function Request( { request, busy, onKeep, onDiscard } ) {
	const text = request.description.replace( / \(for a scene:.*$/, '' );

	return (
		<li className={ `si-b-req is-${ request.state }` }>
			<span className="si-b-req__text" title={ text }>
				{ text }
			</span>
			<span className="si-b-badge si-b-badge--quiet">
				{ kindLabel( request.category ) }
			</span>
			<Track state={ request.state } />
			{ ( 'discarded' === request.state ||
				'declined' === request.state ) && (
				<span className="si-b-muted">
					{ 'declined' === request.state
						? __( 'Declined.', 'sprint-illustrations' )
						: __( 'Discarded.', 'sprint-illustrations' ) }{ ' ' }
					{ request.note }
				</span>
			) }
			{ 'review' === request.state && (
				<div className="si-b-req__review">
					{ request.preview && (
						<span
							className="si-b-req__art"
							// Sanitized server-side (draft preview).
							dangerouslySetInnerHTML={ {
								__html: request.preview,
							} }
						/>
					) }
					{ request.can_act ? (
						<span className="si-b-req__actions">
							<Button
								variant="primary"
								size="compact"
								isBusy={ busy }
								disabled={ busy || ! request.preview }
								onClick={ () => onKeep( request ) }
							>
								{ __( 'Keep', 'sprint-illustrations' ) }
							</Button>
							<Button
								variant="tertiary"
								size="compact"
								isDestructive
								disabled={ busy }
								onClick={ () => onDiscard( request ) }
							>
								{ __( 'Discard', 'sprint-illustrations' ) }
							</Button>
						</span>
					) : (
						<span className="si-b-muted">
							{ __(
								'Waiting for the requester to keep it.',
								'sprint-illustrations'
							) }
						</span>
					) }
				</div>
			) }
		</li>
	);
}

function Plan( { plan, busy, working, onBuild, onRemove, onKeep, onDiscard } ) {
	const total = plan.requests.length;
	const added = plan.requests.filter( ( r ) => 'done' === r.state ).length;

	return (
		<li className="si-b-plan">
			<p className="si-b-plan__title">{ plan.description }</p>
			<div className="si-b-progress">
				<div
					className="si-b-progress__bar"
					role="progressbar"
					aria-label={ __(
						'Pieces in the library',
						'sprint-illustrations'
					) }
					aria-valuemin={ 0 }
					aria-valuemax={ total }
					aria-valuenow={ added }
				>
					<span
						style={ {
							width: `${ total ? ( added / total ) * 100 : 0 }%`,
						} }
					/>
				</div>
				<span className="si-b-muted">
					{ sprintf(
						/* translators: 1: pieces added, 2: total pieces. */
						__(
							'%1$d of %2$d in the library',
							'sprint-illustrations'
						),
						added,
						total
					) }
				</span>
			</div>
			<ul className="si-b-reqs">
				{ plan.requests.map( ( request ) => (
					<Request
						key={ request.id }
						request={ request }
						busy={ working === request.id }
						onKeep={ onKeep }
						onDiscard={ onDiscard }
					/>
				) ) }
			</ul>
			<div className="si-b-plan__bar">
				<Button
					variant="primary"
					size="compact"
					disabled={ ! plan.ready || busy }
					isBusy={ busy }
					onClick={ () => onBuild( plan ) }
				>
					{ __( 'Build the scene', 'sprint-illustrations' ) }
				</Button>
				<Button
					variant="tertiary"
					size="compact"
					isDestructive
					onClick={ () => onRemove( plan ) }
				>
					{ __( 'Remove', 'sprint-illustrations' ) }
				</Button>
			</div>
		</li>
	);
}

export default function NewPieces( {
	state,
	describe,
	dispatch,
	onBuilt,
	onLibraryChanged,
} ) {
	const missing = state.missing || [];
	const [ picked, setPicked ] = useState( [] );
	const [ custom, setCustom ] = useState( '' );
	const [ kind, setKind ] = useState( 'objects' );
	const [ plans, setPlans ] = useState( [] );
	const [ sending, setSending ] = useState( false );
	const [ building, setBuilding ] = useState( '' );
	const [ working, setWorking ] = useState( 0 );
	// Request IDs already in the library, so a piece kept anywhere refreshes the Library panel once.
	const knownDone = useRef( null );

	const notify = ( status, text ) =>
		dispatch( { type: 'NOTICE', notice: { status, text } } );
	const refresh = useCallback(
		() =>
			listPlans()
				.then( ( list ) => {
					setPlans( list );
					const done = new Set(
						list.flatMap( ( plan ) =>
							plan.requests
								.filter( ( r ) => 'done' === r.state )
								.map( ( r ) => r.id )
						)
					);
					const fresh = [ ...done ].some(
						( id ) =>
							knownDone.current && ! knownDone.current.has( id )
					);
					knownDone.current = done;
					if ( fresh ) {
						onLibraryChanged();
					}
				} )
				.catch( () => {} ),
		[ onLibraryChanged ]
	);

	useEffect( () => {
		refresh();
	}, [ refresh ] );
	useEffect( () => {
		setPicked( [] );
	}, [ missing.join( '|' ) ] ); // eslint-disable-line react-hooks/exhaustive-deps
	useEffect( () => {
		if ( ! plans.some( ( plan ) => plan.waiting > 0 ) ) {
			return undefined;
		}
		const timer = window.setInterval( refresh, POLL_MS );
		return () => window.clearInterval( timer );
	}, [ plans, refresh ] );

	// Picked entries are { text, category }; the suggested words are objects.
	const toggle = ( word ) =>
		setPicked( ( list ) =>
			list.some( ( item ) => item.text === word )
				? list.filter( ( item ) => item.text !== word )
				: [ ...list, { text: word, category: 'objects' } ]
		);
	// A long brief with numbered layers, (1) … (2) …, becomes one short request per layer.
	const looksLikeBrief =
		describe.length > 300 ||
		/\(\s*1\s*\)|(^|\n)\s*1[.)]\s/.test( describe );
	const split = () =>
		splitBrief( describe )
			.then( ( result ) => {
				if ( ! result.layers.length ) {
					notify(
						'info',
						__(
							'No numbered layers found. Number them like (1) …, (2) … or 1. … 2. … and try again.',
							'sprint-illustrations'
						)
					);
					return;
				}
				setPicked(
					result.layers.map( ( text ) => ( {
						text,
						category: 'objects',
					} ) )
				);
				notify(
					'success',
					sprintf(
						/* translators: %d: number of layer requests. */
						__(
							'Split into %d layer requests, each with your style notes. Remove any you do not want, then request the drawings.',
							'sprint-illustrations'
						),
						result.layers.length
					)
				);
			} )
			.catch( ( error ) =>
				notify(
					'error',
					error.message ||
						__(
							'Could not split the brief.',
							'sprint-illustrations'
						)
				)
			);
	const addCustom = () => {
		const text = custom.trim();
		if (
			text.length >= 3 &&
			! picked.some( ( item ) => item.text === text )
		) {
			setPicked( ( list ) => [ ...list, { text, category: kind } ] );
		}
		setCustom( '' );
	};

	const requestDrawings = () => {
		setSending( true );
		createPlan( {
			content: describe.trim(),
			objects: picked.map( ( item ) => ( {
				description: item.text,
				category: item.category,
			} ) ),
			template: state.spec.template || '',
			keywords: state.spec.keywords
				.split( ',' )
				.map( ( word ) => word.trim() )
				.filter( Boolean ),
			title: state.spec.title,
			seed: state.spec.seed,
		} )
			.then( () => {
				setPicked( [] );
				notify(
					'success',
					__(
						'Requested. Claude Code draws each piece, then you keep or discard it in the Library.',
						'sprint-illustrations'
					)
				);
				return refresh();
			} )
			.catch( ( error ) =>
				notify(
					'error',
					error.message ||
						__(
							'Could not request the pieces.',
							'sprint-illustrations'
						)
				)
			)
			.finally( () => setSending( false ) );
	};

	const build = ( plan ) => {
		setBuilding( plan.id );
		buildPlan( plan.id )
			.then( ( scene ) => onBuilt( scene ) )
			.catch( ( error ) =>
				notify(
					'error',
					error.message ||
						__(
							'Could not build the scene.',
							'sprint-illustrations'
						)
				)
			)
			.finally( () => setBuilding( '' ) );
	};

	const keep = ( request ) => {
		setWorking( request.id );
		keepPiece( request.id )
			.then( () => {
				notify(
					'success',
					__(
						'Added to the library. It is now in the Library panel.',
						'sprint-illustrations'
					)
				);
				return refresh();
			} )
			.catch( ( error ) =>
				notify(
					'error',
					error.message ||
						__(
							'Could not keep the piece.',
							'sprint-illustrations'
						)
				)
			)
			.finally( () => setWorking( 0 ) );
	};
	const discard = ( request ) => {
		setWorking( request.id );
		discardPiece( request.id )
			.then( refresh )
			.catch( () => {} )
			.finally( () => setWorking( 0 ) );
	};

	const remove = ( plan ) =>
		deletePlan( plan.id )
			.then( refresh )
			.catch( () => {} );

	// Only the objects are required: the description and template are optional context for the scene.
	const canRequest = picked.length > 0;

	return (
		<section className="si-b-panel" aria-labelledby="si-b-newpieces">
			<h2 className="si-b-panel__title" id="si-b-newpieces">
				{ __( 'Draw new pieces', 'sprint-illustrations' ) }
			</h2>
			<p className="si-b-muted">
				{ missing.length
					? __(
							'The library may not have these yet. Pick what to draw, or add your own.',
							'sprint-illustrations'
					  )
					: __(
							'Describe the scene above and press Suggest, then add any objects you want drawn.',
							'sprint-illustrations'
					  ) }
			</p>
			{ missing.length > 0 && (
				<div
					className="si-b-chips"
					role="group"
					aria-label={ __(
						'Objects to draw',
						'sprint-illustrations'
					) }
				>
					{ missing.map( ( word ) => (
						<button
							type="button"
							key={ word }
							className="si-b-chip"
							aria-pressed={ picked.some(
								( item ) => item.text === word
							) }
							onClick={ () => toggle( word ) }
						>
							{ word }
						</button>
					) ) }
				</div>
			) }
			{ looksLikeBrief && (
				<p>
					<Button variant="secondary" onClick={ split }>
						{ __(
							'Split my description into layer requests',
							'sprint-illustrations'
						) }
					</Button>
				</p>
			) }
			<div className="si-b-row">
				<TextControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ __( 'Your own piece', 'sprint-illustrations' ) }
					placeholder={ __(
						'e.g. a taxi driver in a flat cap, or a delivery drone',
						'sprint-illustrations'
					) }
					value={ custom }
					onChange={ setCustom }
					onKeyDown={ ( event ) => {
						if ( 'Enter' === event.key ) {
							event.preventDefault();
							addCustom();
						}
					} }
				/>
				<SelectControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ __( 'Kind of piece', 'sprint-illustrations' ) }
					hideLabelFromVision
					value={ kind }
					options={ KINDS }
					onChange={ setKind }
				/>
				<Button
					variant="secondary"
					__next40pxDefaultSize
					disabled={ custom.trim().length < 3 }
					onClick={ addCustom }
				>
					{ __( 'Add', 'sprint-illustrations' ) }
				</Button>
			</div>
			{ picked.length > 0 && (
				<ul className="si-b-picked">
					{ picked.map( ( item ) => (
						<li key={ item.text }>
							<span>
								{ item.text }
								<span className="si-b-badge si-b-badge--quiet">
									{ kindLabel( item.category ) }
								</span>
							</span>
							<Button
								size="small"
								icon="no-alt"
								label={ sprintf(
									/* translators: %s: object. */
									__( 'Remove %s', 'sprint-illustrations' ),
									item.text
								) }
								onClick={ () => toggle( item.text ) }
							/>
						</li>
					) ) }
				</ul>
			) }
			<Button
				variant="secondary"
				__next40pxDefaultSize
				isBusy={ sending }
				disabled={ ! canRequest || sending }
				onClick={ requestDrawings }
			>
				{ sprintf(
					/* translators: %d: number of objects. */
					_n(
						'Request %d drawing',
						'Request %d drawings',
						picked.length,
						'sprint-illustrations'
					),
					picked.length
				) }
			</Button>
			{ plans.length > 0 && (
				<ul className="si-b-plans">
					{ plans.map( ( plan ) => (
						<Plan
							key={ plan.id }
							plan={ plan }
							busy={ building === plan.id }
							working={ working }
							onBuild={ build }
							onRemove={ remove }
							onKeep={ keep }
							onDiscard={ discard }
						/>
					) ) }
				</ul>
			) }
		</section>
	);
}
