/**
 * The live composition, with the hovered slot outlined.
 */
import { __ } from '@wordpress/i18n';
import { Spinner } from '@wordpress/components';

export default function Stage( { state } ) {
	const { result, status, error, hover } = state;
	const canvas = result?.template?.canvas || [ 800, 600 ];
	const boxes =
		( hover &&
			result?.slots.find( ( slot ) => slot.name === hover )?.boxes ) ||
		[];

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
			<div
				className="si-b-stage__canvas"
				style={ { aspectRatio: `${ canvas[ 0 ] } / ${ canvas[ 1 ] }` } }
			>
				{ result ? (
					<div
						className="si-b-stage__art"
						// Sanitized server-side by the composer.
						dangerouslySetInnerHTML={ { __html: result.svg } }
					/>
				) : (
					<Spinner />
				) }
				<svg
					className="si-b-stage__overlay"
					viewBox={ `0 0 ${ canvas[ 0 ] } ${ canvas[ 1 ] }` }
					aria-hidden="true"
					focusable="false"
				>
					{ boxes.map( ( [ x, y, w, h ], i ) => (
						<rect
							key={ i }
							x={ x }
							y={ y }
							width={ w }
							height={ h }
							rx="6"
						/>
					) ) }
				</svg>
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
