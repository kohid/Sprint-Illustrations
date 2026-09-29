/**
 * Editing layer over the rendered scene: drop library pieces, then select, move, resize, flip or
 * remove them. Changes show on the outline at once; the spec (and the render) updates on release.
 */
import { __ } from '@wordpress/i18n';
import { useRef, useState } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { DRAG_TYPE } from './LibraryPanel';
import { itemHeight, landingSize, round } from './geometry';

const CORNERS = [ 'nw', 'ne', 'sw', 'se' ];

export default function CanvasEditor( {
	canvas,
	items,
	byId,
	selected,
	hoverBoxes,
	dragging,
	result,
	dispatch,
} ) {
	const svg = useRef( null );
	const action = useRef( null );
	const [ draft, setDraft ] = useState( null );
	// The latest draft, readable in pointerup even before React re-renders.
	const latest = useRef( null );
	const update = ( next ) => {
		latest.current = next;
		setDraft( next );
	};
	const [ ghost, setGhost ] = useState( null );
	const [ W, H ] = canvas;

	// Canvas units per screen pixel, for handles that stay the same size on screen.
	const width = svg.current?.getBoundingClientRect().width || 600;
	const px = W / width;

	const point = ( event ) => {
		const matrix = svg.current?.getScreenCTM();
		if ( ! matrix ) {
			return { x: 0, y: 0 };
		}
		const p = svg.current.createSVGPoint();
		p.x = event.clientX;
		p.y = event.clientY;
		const local = p.matrixTransform( matrix.inverse() );
		return { x: local.x, y: local.y };
	};

	const boxOf = ( item ) => {
		const current = draft?.key === item.key ? { ...item, ...draft } : item;
		return {
			x: current.x,
			y: current.y,
			w: current.w,
			h: itemHeight( current, byId[ item.piece ] ),
		};
	};

	const start = ( event, item, mode ) => {
		event.stopPropagation();
		event.preventDefault();
		dispatch( { type: 'SELECT', key: item.key } );
		svg.current.setPointerCapture( event.pointerId );
		action.current = {
			key: item.key,
			mode,
			from: point( event ),
			box: boxOf( item ),
		};
	};

	const move = ( event ) => {
		const act = action.current;
		if ( ! act ) {
			return;
		}
		const p = point( event );
		const dx = p.x - act.from.x;
		const dy = p.y - act.from.y;
		const { x, y, w, h } = act.box;

		if ( 'move' === act.mode ) {
			update( {
				key: act.key,
				x: round( x + dx ),
				y: round( y + dy ),
			} );
			return;
		}

		// Resize from a corner, keeping proportions; the opposite corner stays put.
		const sx = act.mode.includes( 'e' ) ? 1 : -1;
		const sy = act.mode.includes( 's' ) ? 1 : -1;
		const ratio = h / w;
		const nextW = Math.max( 12 * px, w + sx * dx, ( h + sy * dy ) / ratio );
		const nextH = nextW * ratio;
		update( {
			key: act.key,
			x: round( sx > 0 ? x : x + w - nextW ),
			y: round( sy > 0 ? y : y + h - nextH ),
			w: round( nextW ),
		} );
	};

	const end = () => {
		if ( action.current && latest.current ) {
			const { key, ...changes } = latest.current;
			dispatch( { type: 'UPDATE_ITEM', key, changes } );
		}
		action.current = null;
		update( null );
	};

	const keys = ( event, item ) => {
		const step = event.shiftKey ? 10 : 1;
		const nudges = {
			ArrowLeft: [ -step, 0 ],
			ArrowRight: [ step, 0 ],
			ArrowUp: [ 0, -step ],
			ArrowDown: [ 0, step ],
		};
		if ( nudges[ event.key ] ) {
			event.preventDefault();
			const [ dx, dy ] = nudges[ event.key ];
			dispatch( {
				type: 'UPDATE_ITEM',
				key: item.key,
				changes: { x: round( item.x + dx ), y: round( item.y + dy ) },
			} );
		} else if ( 'Delete' === event.key || 'Backspace' === event.key ) {
			event.preventDefault();
			dispatch( { type: 'REMOVE_ITEM', key: item.key } );
		} else if ( 'Escape' === event.key ) {
			dispatch( { type: 'SELECT', key: null } );
			event.currentTarget.blur();
		}
	};

	const accepts = ( event ) =>
		Array.from( event.dataTransfer?.types || [] ).includes( DRAG_TYPE );

	const over = ( event ) => {
		if ( ! accepts( event ) ) {
			return;
		}
		event.preventDefault();
		event.dataTransfer.dropEffect = 'copy';
		setGhost( point( event ) );
	};

	const drop = ( event ) => {
		if ( ! accepts( event ) ) {
			return;
		}
		event.preventDefault();
		setGhost( null );
		const piece = byId[ event.dataTransfer.getData( DRAG_TYPE ) ];
		if ( ! piece ) {
			return;
		}
		const p = point( event );
		const [ w, h ] = landingSize( piece, canvas, result );
		dispatch( {
			type: 'ADD_ITEM',
			item: {
				piece: piece.id,
				x: round( p.x - w / 2 ),
				y: round( p.y - h / 2 ),
				w: round( w ),
				flip: false,
			},
		} );
	};

	const current = items.find( ( item ) => item.key === selected );
	const box = current ? boxOf( current ) : null;
	const ghostSize =
		ghost && dragging ? landingSize( dragging, canvas, result ) : null;

	return (
		<>
			<svg
				ref={ svg }
				className="si-b-editor"
				viewBox={ `0 0 ${ W } ${ H }` }
				role="group"
				aria-label={ __( 'Canvas', 'sprint-illustrations' ) }
				onDragOver={ over }
				onDragLeave={ () => setGhost( null ) }
				onDrop={ drop }
				onPointerMove={ move }
				onPointerUp={ end }
				onPointerCancel={ end }
			>
				<rect
					className="si-b-editor__bg"
					width={ W }
					height={ H }
					onPointerDown={ () =>
						dispatch( { type: 'SELECT', key: null } )
					}
				/>
				{ hoverBoxes.map( ( [ x, y, w, h ], i ) => (
					<rect
						key={ `hover-${ i }` }
						className="si-b-editor__hover"
						x={ x }
						y={ y }
						width={ w }
						height={ h }
						rx={ 4 * px }
					/>
				) ) }
				{ items.map( ( item ) => {
					const b = boxOf( item );
					return (
						<rect
							key={ item.key }
							className="si-b-editor__item"
							x={ b.x }
							y={ b.y }
							width={ b.w }
							height={ b.h }
							tabIndex={ 0 }
							role="button"
							aria-pressed={ item.key === selected }
							aria-label={
								byId[ item.piece ]?.label || item.piece
							}
							onPointerDown={ ( event ) =>
								start( event, item, 'move' )
							}
							onFocus={ () =>
								dispatch( { type: 'SELECT', key: item.key } )
							}
							onKeyDown={ ( event ) => keys( event, item ) }
						/>
					);
				} ) }
				{ box && (
					<g className="si-b-editor__selection">
						<rect
							x={ box.x }
							y={ box.y }
							width={ box.w }
							height={ box.h }
						/>
						{ CORNERS.map( ( corner ) => (
							<circle
								key={ corner }
								className={ `si-b-editor__handle is-${ corner }` }
								cx={
									corner.includes( 'e' )
										? box.x + box.w
										: box.x
								}
								cy={
									corner.includes( 's' )
										? box.y + box.h
										: box.y
								}
								r={ 6 * px }
								onPointerDown={ ( event ) =>
									start( event, current, corner )
								}
							/>
						) ) }
					</g>
				) }
				{ ghostSize && (
					<rect
						className="si-b-editor__ghost"
						x={ ghost.x - ghostSize[ 0 ] / 2 }
						y={ ghost.y - ghostSize[ 1 ] / 2 }
						width={ ghostSize[ 0 ] }
						height={ ghostSize[ 1 ] }
						rx={ 4 * px }
					/>
				) }
			</svg>
			{ box && ! draft && (
				<div
					className="si-b-editor__toolbar"
					style={ {
						left: `${ ( box.x / W ) * 100 }%`,
						top: `${ ( box.y / H ) * 100 }%`,
					} }
				>
					<Button
						size="small"
						icon="image-flip-horizontal"
						label={ __( 'Flip', 'sprint-illustrations' ) }
						onClick={ () =>
							dispatch( {
								type: 'UPDATE_ITEM',
								key: current.key,
								changes: { flip: ! current.flip },
							} )
						}
					/>
					<Button
						size="small"
						icon="trash"
						label={ __( 'Remove', 'sprint-illustrations' ) }
						onClick={ () =>
							dispatch( {
								type: 'REMOVE_ITEM',
								key: current.key,
							} )
						}
					/>
				</div>
			) }
		</>
	);
}
