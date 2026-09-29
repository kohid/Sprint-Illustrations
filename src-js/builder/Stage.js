/**
 * The live composition: canvas size bar, the rendered scene, and the editing layer over it.
 */
import { __ } from '@wordpress/i18n';
import { Spinner } from '@wordpress/components';
import CanvasEditor from './CanvasEditor';
import CanvasSize from './CanvasSize';
import { canvasOf, itemHeight } from './geometry';
import { BLANK } from './state';

export default function Stage( { state, byId, dragging, dispatch } ) {
	const { result, status, error, hover, spec } = state;
	const canvas = canvasOf( spec, result );

	let hoverBoxes = [];
	if ( hover?.startsWith( 'item:' ) ) {
		const item = spec.items.find( ( i ) => `item:${ i.key }` === hover );
		hoverBoxes = item
			? [
					[
						item.x,
						item.y,
						item.w,
						itemHeight( item, byId[ item.piece ] ),
					],
			  ]
			: [];
	} else if ( hover ) {
		hoverBoxes =
			result?.slots.find( ( slot ) => slot.name === hover )?.boxes || [];
	}

	return (
		<section
			className={ `si-b-stage${
				'composing' === status ? ' is-updating' : ''
			}` }
			aria-labelledby="si-b-stage-title"
		>
			<h2 className="screen-reader-text" id="si-b-stage-title">
				{ __( 'Preview', 'sprint-illustrations' ) }
			</h2>
			<CanvasSize
				canvas={ canvas }
				custom={ !! spec.canvas }
				dispatch={ dispatch }
			/>
			<div
				className={ `si-b-stage__canvas${
					dragging ? ' is-dropping' : ''
				}` }
				style={ { aspectRatio: `${ canvas[ 0 ] } / ${ canvas[ 1 ] }` } }
			>
				{ BLANK === spec.template && ! spec.items.length && (
					<p className="si-b-stage__hint">
						{ __(
							'Drag pieces here from the Library',
							'sprint-illustrations'
						) }
					</p>
				) }
				{ result ? (
					<div
						className="si-b-stage__art"
						// Sanitized server-side by the composer.
						dangerouslySetInnerHTML={ { __html: result.svg } }
					/>
				) : (
					<Spinner />
				) }
				<CanvasEditor
					canvas={ canvas }
					items={ spec.items }
					byId={ byId }
					selected={ state.selected }
					hoverBoxes={ hoverBoxes }
					dragging={ dragging }
					result={ result }
					dispatch={ dispatch }
				/>
			</div>
			<div className="si-b-stage__status" aria-live="polite">
				{ 'failed' === status && (
					<p className="si-b-error">
						{ __( 'Preview unavailable:', 'sprint-illustrations' ) }{ ' ' }
						{ error }
					</p>
				) }
				{ result?.warnings?.map( ( warning ) => (
					<p key={ warning } className="si-b-warning">
						{ warning }
					</p>
				) ) }
			</div>
		</section>
	);
}
