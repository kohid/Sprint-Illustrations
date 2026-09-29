/**
 * Draw new pieces for a described scene. Suggest lists words the library has nothing for; the
 * user picks what to draw (or types their own), each becomes a piece request, and once the pieces
 * are kept in the Library the scene is built with them placed on the canvas.
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import { useCallback, useEffect, useState } from '@wordpress/element';
import { Button, TextControl } from '@wordpress/components';
import {
	buildPlan,
	createPlan,
	deletePlan,
	listPlans,
	splitBrief,
} from './api';

const config = window.sprintIllustrationsBuilder || {};

const STATES = {
	queued: __( 'Waiting to be drawn', 'sprint-illustrations' ),
	drawing: __( 'Being drawn', 'sprint-illustrations' ),
	review: __( 'Ready for you to keep', 'sprint-illustrations' ),
	done: __( 'Added to the library', 'sprint-illustrations' ),
	discarded: __( 'Discarded', 'sprint-illustrations' ),
	declined: __( 'Declined', 'sprint-illustrations' ),
};

const POLL_MS = 8000;

function Plan( { plan, busy, onBuild, onRemove } ) {
	return (
		<li className="si-b-plan">
			<p className="si-b-plan__title">{ plan.description }</p>
			<ul className="si-b-plan__pieces">
				{ plan.requests.map( ( request ) => (
					<li key={ request.id }>
						<span className="si-b-plan__what">
							{ request.description.replace(
								/ \(for a scene:.*$/,
								''
							) }
						</span>
						<span
							className={ `si-b-badge si-b-badge--${
								'done' === request.state ? 'motion' : 'quiet'
							}` }
						>
							{ STATES[ request.state ] || request.state }
						</span>
						{ request.note && (
							<span className="si-b-muted">
								{ ' ' }
								{ request.note }
							</span>
						) }
					</li>
				) ) }
			</ul>
			{ plan.requests.some( ( r ) => 'review' === r.state ) &&
				config.libraryUrl && (
					<p className="si-b-muted">
						<a href={ config.libraryUrl }>
							{ __(
								'Open the Library to keep or discard the drawings.',
								'sprint-illustrations'
							) }
						</a>
					</p>
				) }
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

export default function NewPieces( { state, describe, dispatch, onBuilt } ) {
	const missing = state.missing || [];
	const [ picked, setPicked ] = useState( [] );
	const [ custom, setCustom ] = useState( '' );
	const [ plans, setPlans ] = useState( [] );
	const [ sending, setSending ] = useState( false );
	const [ building, setBuilding ] = useState( '' );

	const notify = ( status, text ) =>
		dispatch( { type: 'NOTICE', notice: { status, text } } );
	const refresh = useCallback(
		() =>
			listPlans()
				.then( setPlans )
				.catch( () => {} ),
		[]
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

	const toggle = ( word ) =>
		setPicked( ( list ) =>
			list.includes( word )
				? list.filter( ( item ) => item !== word )
				: [ ...list, word ]
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
				setPicked( result.layers );
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
		if ( text.length >= 3 && ! picked.includes( text ) ) {
			setPicked( ( list ) => [ ...list, text ] );
		}
		setCustom( '' );
	};

	const request = () => {
		setSending( true );
		createPlan( {
			content: describe,
			objects: picked,
			template: state.spec.template,
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

	const remove = ( plan ) =>
		deletePlan( plan.id )
			.then( refresh )
			.catch( () => {} );

	const canRequest =
		describe.trim() && picked.length > 0 && state.spec.template;

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
							aria-pressed={ picked.includes( word ) }
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
					label={ __( 'Your own object', 'sprint-illustrations' ) }
					placeholder={ __(
						'e.g. a delivery drone with a parcel',
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
						<li key={ item }>
							{ item }
							<Button
								size="small"
								icon="no-alt"
								label={ sprintf(
									/* translators: %s: object. */
									__( 'Remove %s', 'sprint-illustrations' ),
									item
								) }
								onClick={ () => toggle( item ) }
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
				onClick={ request }
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
							onBuild={ build }
							onRemove={ remove }
						/>
					) ) }
				</ul>
			) }
		</section>
	);
}
