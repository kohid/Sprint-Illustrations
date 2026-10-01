/**
 * Layers, front at the top: template slots (with a lock toggle and a piece picker, and the slots
 * attached to them) and pieces added from the library. Drag a row, or use Forward and Back, to
 * change what is in front.
 */
import { __, sprintf } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { Button, Dropdown } from '@wordpress/components';
import AnimateControl, { summary } from './AnimateControl';
import PaintControl from './PaintControl';

const LAYER_TYPE = 'application/x-si-layer';

const humanize = ( name ) =>
	( name.charAt( 0 ).toUpperCase() + name.slice( 1 ) ).replace(
		/[-_]/g,
		' '
	);

/**
 * Pieces that fit a slot: same category; for attached slots, a mount for the parent anchor's type.
 *
 * @param {Object} slot   Slot from /compose.
 * @param {Array}  slots  All slots from /compose.
 * @param {Object} byId   Library pieces by ID.
 * @param {Array}  pieces Library pieces.
 * @return {Array} Candidates.
 */
function candidates( slot, slots, byId, pieces ) {
	let list = pieces.filter( ( piece ) => piece.category === slot.category );

	if ( slot.attach ) {
		const parent = slots.find( ( s ) => s.name === slot.attach.to );
		const type =
			parent && byId[ parent.picked ]?.accepts?.[ slot.attach.anchor ];
		list = type
			? list.filter( ( piece ) => piece.mounts && piece.mounts[ type ] )
			: [];
	}

	return list;
}

/**
 * Layer keys front to back: the rendered order, plus pieces added since the last render.
 *
 * @param {Object} result Last /compose result.
 * @param {Array}  items  Spec items.
 * @return {Array} Keys, front first.
 */
export function frontToBack( result, items ) {
	const itemKeys = items.map( ( item ) => `item:${ item.key }` );
	const known = ( result?.layers || [] ).filter(
		( key ) => ! key.startsWith( 'item:' ) || itemKeys.includes( key )
	);
	const added = itemKeys.filter( ( key ) => ! known.includes( key ) );
	return [ ...known, ...added ].reverse();
}

function PieceGrid( { slot, list, onPick, onAuto, onClose } ) {
	return (
		<div
			className="si-b-pieces"
			role="group"
			aria-label={ sprintf(
				/* translators: %s: slot name. */ __(
					'Pieces for %s',
					'sprint-illustrations'
				),
				humanize( slot.name )
			) }
		>
			<Button
				variant="secondary"
				className="si-b-pieces__auto"
				onClick={ () => {
					onAuto();
					onClose();
				} }
			>
				{ __( 'Automatic', 'sprint-illustrations' ) }
			</Button>
			<div className="si-b-pieces__grid">
				{ list.map( ( piece ) => (
					<button
						type="button"
						key={ piece.id }
						className="si-b-piece"
						aria-pressed={ slot.picked === piece.id }
						onClick={ () => {
							onPick( piece.id );
							onClose();
						} }
					>
						<span
							className="si-b-piece__art"
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
		</div>
	);
}

/**
 * Lock toggle and piece picker for one template slot.
 *
 * @param {Object}   props          Props.
 * @param {Object}   props.slot     Slot from /compose.
 * @param {Object}   props.result   Last /compose result.
 * @param {Object}   props.library  Library.
 * @param {Object}   props.byId     Pieces by ID.
 * @param {Object}   props.picks    Locked picks.
 * @param {Function} props.dispatch Dispatch.
 * @return {Element} Controls.
 */
function SlotControls( { slot, result, library, byId, picks, dispatch } ) {
	const locked = Object.prototype.hasOwnProperty.call( picks, slot.name );
	const list = slot.multiple
		? []
		: candidates( slot, result.slots, byId, library.pieces );

	return (
		<>
			<Button
				size="small"
				icon={ locked ? 'lock' : 'unlock' }
				isPressed={ locked }
				disabled={ ! slot.picked }
				label={
					locked
						? __(
								'Unlock: let Shuffle change this slot',
								'sprint-illustrations'
						  )
						: __(
								'Lock: keep this piece when shuffling',
								'sprint-illustrations'
						  )
				}
				onClick={ () =>
					dispatch(
						locked
							? { type: 'UNPICK', slot: slot.name }
							: {
									type: 'PICK',
									slot: slot.name,
									piece: slot.picked,
							  }
					)
				}
			/>
			{ slot.multiple ? (
				<span />
			) : (
				<Dropdown
					popoverProps={ { placement: 'right-start' } }
					renderToggle={ ( { isOpen, onToggle } ) => (
						<Button
							size="small"
							variant="tertiary"
							onClick={ onToggle }
							aria-expanded={ isOpen }
							disabled={ 0 === list.length }
						>
							{ __( 'Change', 'sprint-illustrations' ) }
						</Button>
					) }
					renderContent={ ( { onClose } ) => (
						<PieceGrid
							slot={ slot }
							list={ list }
							onClose={ onClose }
							onPick={ ( piece ) =>
								dispatch( {
									type: 'PICK',
									slot: slot.name,
									piece,
								} )
							}
							onAuto={ () =>
								dispatch( { type: 'UNPICK', slot: slot.name } )
							}
						/>
					) }
				/>
			) }
		</>
	);
}

/**
 * What a slot currently shows.
 *
 * @param {Object} slot Slot.
 * @param {Object} byId Pieces by ID.
 * @return {string} Label.
 */
function pickedLabel( slot, byId ) {
	if ( Array.isArray( slot.picked ) ) {
		return sprintf(
			/* translators: %d: number of pieces. */ __(
				'%d pieces',
				'sprint-illustrations'
			),
			slot.picked.length
		);
	}
	return slot.picked
		? byId[ slot.picked ]?.label || slot.picked
		: __( 'Empty', 'sprint-illustrations' );
}

export default function SlotList( { state, library, byId, dispatch } ) {
	const { result, picks, spec } = state;
	const [ dragFrom, setDragFrom ] = useState( null );
	const [ dropAt, setDropAt ] = useState( null );

	if ( ! result ) {
		return null;
	}

	const order = frontToBack( result, spec.items );
	const reorder = ( from, to ) => {
		if ( from === to || to < 0 || to >= order.length ) {
			return;
		}
		const next = [ ...order ];
		const [ moved ] = next.splice( from, 1 );
		next.splice( to, 0, moved );
		dispatch( { type: 'SET_LAYERS', layers: next.reverse() } );
	};
	const hover = ( key ) => ( {
		onMouseEnter: () => dispatch( { type: 'HOVER', slot: key } ),
		onMouseLeave: () => dispatch( { type: 'HOVER', slot: null } ),
		onFocus: () => dispatch( { type: 'HOVER', slot: key } ),
		onBlur: () => dispatch( { type: 'HOVER', slot: null } ),
	} );
	const attached = ( name ) =>
		result.slots.filter( ( slot ) => slot.attach?.to === name );

	return (
		<>
			<ol className="si-b-layers">
				{ order.map( ( key, index ) => {
					const isItem = key.startsWith( 'item:' );
					const item = isItem
						? spec.items.find( ( i ) => `item:${ i.key }` === key )
						: null;
					const slot = isItem
						? null
						: result.slots.find( ( s ) => s.name === key );
					if ( ! item && ! slot ) {
						return null;
					}
					const name = isItem
						? byId[ item.piece ]?.label || item.piece
						: humanize( key );

					return (
						<li
							key={ key }
							className={ `si-b-layer${
								dropAt === index && dragFrom !== index
									? ' is-drop-target'
									: ''
							}${
								state.selected === item?.key && isItem
									? ' is-selected'
									: ''
							}` }
							draggable
							onDragStart={ ( event ) => {
								event.dataTransfer.setData( LAYER_TYPE, key );
								event.dataTransfer.effectAllowed = 'move';
								setDragFrom( index );
							} }
							onDragOver={ ( event ) => {
								if ( null !== dragFrom ) {
									event.preventDefault();
									setDropAt( index );
								}
							} }
							onDrop={ ( event ) => {
								event.preventDefault();
								reorder( dragFrom, index );
								setDragFrom( null );
								setDropAt( null );
							} }
							onDragEnd={ () => {
								setDragFrom( null );
								setDropAt( null );
							} }
						>
							<div className="si-b-slot" { ...hover( key ) }>
								<span
									className="si-b-layer__grip"
									aria-hidden="true"
								/>
								<span className="si-b-slot__name">
									<span
										className="si-b-slot__label"
										title={ name }
									>
										{ name }
									</span>
									{ isItem && (
										<span className="si-b-badge si-b-badge--quiet">
											{ __(
												'Added',
												'sprint-illustrations'
											) }
										</span>
									) }
									{ summary( spec.animations[ key ] ) && (
										<span className="si-b-badge si-b-badge--motion">
											{ summary(
												spec.animations[ key ]
											) }
										</span>
									) }
								</span>
								{ ! isItem && (
									<span className="si-b-slot__piece">
										{ pickedLabel( slot, byId ) }
									</span>
								) }
								<div className="si-b-layer__tools">
									<PaintControl
										layerKey={ key }
										name={ name }
										value={ spec.paints[ key ] }
										colors={ result.colors }
										people={ result.people }
										character={
											isItem
												? 'characters' ===
												  byId[ item.piece ]?.category
												: 'characters' === slot.category
										}
										dispatch={ dispatch }
									/>
									<AnimateControl
										layerKey={ key }
										name={ name }
										value={ spec.animations[ key ] }
										dispatch={ dispatch }
									/>
									<Button
										size="small"
										icon="arrow-up-alt2"
										label={ __(
											'Bring forward',
											'sprint-illustrations'
										) }
										disabled={ 0 === index }
										onClick={ () =>
											reorder( index, index - 1 )
										}
									/>
									<Button
										size="small"
										icon="arrow-down-alt2"
										label={ __(
											'Send back',
											'sprint-illustrations'
										) }
										disabled={ order.length - 1 === index }
										onClick={ () =>
											reorder( index, index + 1 )
										}
									/>
									{ isItem ? (
										<>
											<Button
												size="small"
												icon="edit"
												label={ __(
													'Select on the canvas',
													'sprint-illustrations'
												) }
												onClick={ () =>
													dispatch( {
														type: 'SELECT',
														key: item.key,
													} )
												}
											/>
											<Button
												size="small"
												icon="trash"
												isDestructive
												label={ __(
													'Remove',
													'sprint-illustrations'
												) }
												onClick={ () =>
													dispatch( {
														type: 'REMOVE_ITEM',
														key: item.key,
													} )
												}
											/>
										</>
									) : (
										<SlotControls
											slot={ slot }
											result={ result }
											library={ library }
											byId={ byId }
											picks={ picks }
											dispatch={ dispatch }
										/>
									) }
								</div>
							</div>
							{ slot &&
								attached( slot.name ).map( ( child ) => (
									<div
										key={ child.name }
										className="si-b-slot si-b-slot--child"
										{ ...hover( child.name ) }
									>
										<span
											className="si-b-layer__grip"
											aria-hidden="true"
										/>
										<span className="si-b-slot__name">
											{ humanize( child.name ) }
										</span>
										<span className="si-b-slot__piece">
											{ pickedLabel( child, byId ) }
										</span>
										<div className="si-b-layer__tools">
											<SlotControls
												slot={ child }
												result={ result }
												library={ library }
												byId={ byId }
												picks={ picks }
												dispatch={ dispatch }
											/>
										</div>
									</div>
								) ) }
						</li>
					);
				} ) }
			</ol>
			<p className="si-b-muted">
				{ __(
					'Top of the list is in front. Drag a row or use the arrows to reorder. Locked slots keep their piece when you shuffle.',
					'sprint-illustrations'
				) }
			</p>
		</>
	);
}
