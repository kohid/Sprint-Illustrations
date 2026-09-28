/**
 * Slots with their piece, a lock toggle and a piece picker.
 */
import { __, sprintf } from '@wordpress/i18n';
import { Button, Dropdown } from '@wordpress/components';

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

export default function SlotList( { result, library, picks, dispatch } ) {
	if ( ! result ) {
		return null;
	}

	const byId = Object.fromEntries(
		library.pieces.map( ( piece ) => [ piece.id, piece ] )
	);

	return (
		<section className="si-b-panel" aria-labelledby="si-b-slots">
			<h2 className="si-b-panel__title" id="si-b-slots">
				{ __( 'Slots', 'sprint-illustrations' ) }
			</h2>
			<ul className="si-b-slots">
				{ result.slots.map( ( slot ) => {
					const locked = Object.prototype.hasOwnProperty.call(
						picks,
						slot.name
					);
					const list = slot.multiple
						? []
						: candidates(
								slot,
								result.slots,
								byId,
								library.pieces
						  );
					let label = __( 'Empty', 'sprint-illustrations' );
					if ( Array.isArray( slot.picked ) ) {
						label = sprintf(
							/* translators: %d: number of pieces. */ __(
								'%d pieces',
								'sprint-illustrations'
							),
							slot.picked.length
						);
					} else if ( slot.picked ) {
						label = byId[ slot.picked ]?.label || slot.picked;
					}

					return (
						<li
							key={ slot.name }
							className="si-b-slot"
							onMouseEnter={ () =>
								dispatch( { type: 'HOVER', slot: slot.name } )
							}
							onMouseLeave={ () =>
								dispatch( { type: 'HOVER', slot: null } )
							}
							onFocus={ () =>
								dispatch( { type: 'HOVER', slot: slot.name } )
							}
							onBlur={ () =>
								dispatch( { type: 'HOVER', slot: null } )
							}
						>
							<span className="si-b-slot__name">
								{ humanize( slot.name ) }
							</span>
							<span className="si-b-slot__piece">{ label }</span>
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
											? {
													type: 'UNPICK',
													slot: slot.name,
											  }
											: {
													type: 'PICK',
													slot: slot.name,
													piece: slot.picked,
											  }
									)
								}
							/>
							{ ! slot.multiple && (
								<Dropdown
									popoverProps={ {
										placement: 'right-start',
									} }
									renderToggle={ ( { isOpen, onToggle } ) => (
										<Button
											size="small"
											variant="tertiary"
											onClick={ onToggle }
											aria-expanded={ isOpen }
											disabled={ 0 === list.length }
										>
											{ __(
												'Change',
												'sprint-illustrations'
											) }
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
												dispatch( {
													type: 'UNPICK',
													slot: slot.name,
												} )
											}
										/>
									) }
								/>
							) }
						</li>
					);
				} ) }
			</ul>
			<p className="si-b-muted">
				{ __(
					'Locked slots keep their piece when you shuffle.',
					'sprint-illustrations'
				) }
			</p>
		</section>
	);
}
