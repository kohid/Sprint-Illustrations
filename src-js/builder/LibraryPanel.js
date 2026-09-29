/**
 * Every library piece, searchable and filterable; drag one onto the canvas, or activate it to add it
 * to the centre.
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import { useMemo, useState } from '@wordpress/element';
import { SearchControl, SelectControl } from '@wordpress/components';
import { MAX_ITEMS } from './state';

export const DRAG_TYPE = 'application/x-si-piece';

const CATEGORIES = [
	{ value: '', label: __( 'All categories', 'sprint-illustrations' ) },
	{ value: 'characters', label: __( 'Characters', 'sprint-illustrations' ) },
	{ value: 'objects', label: __( 'Objects', 'sprint-illustrations' ) },
	{
		value: 'backgrounds',
		label: __( 'Backgrounds', 'sprint-illustrations' ),
	},
	{ value: 'decor', label: __( 'Decor', 'sprint-illustrations' ) },
];

/**
 * Every search word must appear in the label or a tag (same rule as the Library page).
 *
 * @param {Object} piece Piece.
 * @param {Array}  words Lower-case words.
 * @return {boolean} Match.
 */
function matches( piece, words ) {
	const text = [ piece.label, ...( piece.tags || [] ) ]
		.join( ' ' )
		.toLowerCase();
	return words.every( ( word ) => text.includes( word ) );
}

export default function LibraryPanel( { pieces, count, onAdd, onDrag } ) {
	const [ search, setSearch ] = useState( '' );
	const [ category, setCategory ] = useState( '' );
	const full = count >= MAX_ITEMS;

	const list = useMemo( () => {
		const words = search.toLowerCase().split( /\s+/ ).filter( Boolean );
		return pieces.filter(
			( piece ) =>
				( ! category || piece.category === category ) &&
				matches( piece, words )
		);
	}, [ pieces, search, category ] );

	return (
		<div className="si-b-library">
			<div className="si-b-library__filters">
				<SearchControl
					__nextHasNoMarginBottom
					label={ __( 'Search pieces', 'sprint-illustrations' ) }
					value={ search }
					onChange={ setSearch }
				/>
				<SelectControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ __( 'Category', 'sprint-illustrations' ) }
					hideLabelFromVision
					value={ category }
					options={ CATEGORIES }
					onChange={ setCategory }
				/>
			</div>
			<p className="si-b-muted si-b-library__count" aria-live="polite">
				{ full
					? __(
							'This scene has 30 added pieces, the most it can hold. Remove one to add another.',
							'sprint-illustrations'
					  )
					: sprintf(
							/* translators: %d: number of pieces. */
							_n(
								'%d piece. Drag it onto the canvas, or select it to add it to the middle.',
								'%d pieces. Drag one onto the canvas, or select one to add it to the middle.',
								list.length,
								'sprint-illustrations'
							),
							list.length
					  ) }
			</p>
			{ list.length ? (
				<div className="si-b-library__grid">
					{ list.map( ( piece ) => (
						<button
							type="button"
							key={ piece.id }
							className="si-b-piece si-b-library__piece"
							draggable={ ! full }
							disabled={ full }
							onClick={ () => onAdd( piece ) }
							onDragStart={ ( event ) => {
								event.dataTransfer.setData(
									DRAG_TYPE,
									piece.id
								);
								event.dataTransfer.effectAllowed = 'copy';
								onDrag( piece );
							} }
							onDragEnd={ () => onDrag( null ) }
							title={ piece.label }
						>
							{ piece.custom && (
								<span className="si-b-badge">
									{ __( 'Custom', 'sprint-illustrations' ) }
								</span>
							) }
							<span
								className="si-b-piece__art"
								// Sanitized server-side (piece previews).
								dangerouslySetInnerHTML={ {
									__html: piece.preview,
								} }
							/>
							<span className="si-b-piece__label">
								{ piece.label }
							</span>
						</button>
					) ) }
				</div>
			) : (
				<p className="si-b-muted">
					{ __(
						'No pieces match. Try another word or category.',
						'sprint-illustrations'
					) }
				</p>
			) }
		</div>
	);
}
