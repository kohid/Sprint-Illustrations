/**
 * Open / Name / Save / Save as new / Copy shortcode / Export.
 */
import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import {
	Button,
	Dropdown,
	MenuGroup,
	MenuItem,
	SearchControl,
	Spinner,
	TextControl,
} from '@wordpress/components';
import { listIllustrations } from './api';

function OpenMenu( { onOpen } ) {
	const [ search, setSearch ] = useState( '' );
	const [ items, setItems ] = useState( null );
	const [ failed, setFailed ] = useState( false );

	useEffect( () => {
		let live = true;
		const timer = window.setTimeout( () => {
			listIllustrations( search )
				.then( ( data ) => live && setItems( data.items ) )
				.catch( () => live && setFailed( true ) );
		}, 200 );
		return () => {
			live = false;
			window.clearTimeout( timer );
		};
	}, [ search ] );

	return (
		<div className="si-b-open">
			<SearchControl
				__nextHasNoMarginBottom
				label={ __(
					'Search saved illustrations',
					'sprint-illustrations'
				) }
				value={ search }
				onChange={ setSearch }
			/>
			{ failed && (
				<p className="si-b-muted">
					{ __(
						'Saved illustrations couldn’t be loaded.',
						'sprint-illustrations'
					) }
				</p>
			) }
			{ ! failed && null === items && <Spinner /> }
			{ items && 0 === items.length && (
				<p className="si-b-muted">
					{ search
						? __(
								'No saved illustration matches that search.',
								'sprint-illustrations'
						  )
						: __(
								'Nothing saved yet. Design one and press Save.',
								'sprint-illustrations'
						  ) }
				</p>
			) }
			{ items && items.length > 0 && (
				<MenuGroup>
					{ items.map( ( item ) => (
						<MenuItem
							key={ item.id }
							onClick={ () => onOpen( item.id ) }
							info={ item.template || '' }
						>
							{ item.title || sprintf( '#%d', item.id ) }
						</MenuItem>
					) ) }
				</MenuGroup>
			) }
		</div>
	);
}

const EXPORTS = [
	{
		format: 'svg',
		label: __( 'SVG to Media Library', 'sprint-illustrations' ),
		info: __( 'Sharp at any size', 'sprint-illustrations' ),
	},
	{
		format: 'png',
		label: __( 'PNG, 2× canvas', 'sprint-illustrations' ),
		info: __( 'Transparent background', 'sprint-illustrations' ),
	},
	{
		format: 'social',
		label: __(
			'PNG for social sharing, 1200 × 630',
			'sprint-illustrations'
		),
		info: __( 'On the palette background', 'sprint-illustrations' ),
	},
];

function ExportMenu( { canExport, animated, exporting, onExport } ) {
	return (
		<Dropdown
			className="si-b-export"
			contentClassName="si-b-export__menu"
			popoverProps={ { placement: 'bottom-end' } }
			renderToggle={ ( { isOpen, onToggle } ) => (
				<Button
					variant="secondary"
					onClick={ onToggle }
					aria-expanded={ isOpen }
					aria-haspopup="true"
					isBusy={ exporting }
					disabled={ ! canExport || exporting }
				>
					{ exporting
						? __( 'Exporting…', 'sprint-illustrations' )
						: __( 'Export', 'sprint-illustrations' ) }
				</Button>
			) }
			renderContent={ ( { onClose } ) => (
				<MenuGroup
					label={ __(
						'Save to Media Library',
						'sprint-illustrations'
					) }
				>
					{ EXPORTS.map( ( item ) => (
						<MenuItem
							key={ item.format }
							info={ item.info }
							onClick={ () => {
								onClose();
								onExport( item.format );
							} }
						>
							{ item.label }
						</MenuItem>
					) ) }
					<MenuItem
						info={
							animated
								? __(
										'Moves anywhere, even as a plain image',
										'sprint-illustrations'
								  )
								: __(
										'Animate a layer first (Layers → ▶)',
										'sprint-illustrations'
								  )
						}
						disabled={ ! animated }
						onClick={ () => {
							onClose();
							onExport( 'animated-svg' );
						} }
					>
						{ __(
							'Animated SVG to Media Library',
							'sprint-illustrations'
						) }
					</MenuItem>
				</MenuGroup>
			) }
		/>
	);
}

export default function TopBar( {
	state,
	dispatch,
	onOpen,
	onSave,
	onExport,
	exporting,
} ) {
	const [ copied, setCopied ] = useState( false );
	const shortcode = state.id
		? `[sprint_illustration id="${ state.id }"]`
		: '';

	let saveState = '';
	if ( state.dirty ) {
		saveState = __( 'Unsaved changes', 'sprint-illustrations' );
	} else if ( state.id ) {
		saveState = __( 'Saved', 'sprint-illustrations' );
	}

	const copy = () => {
		window.navigator.clipboard?.writeText( shortcode ).then( () => {
			setCopied( true );
			window.setTimeout( () => setCopied( false ), 2000 );
		} );
	};

	return (
		<div className="si-b-top">
			<Dropdown
				popoverProps={ { placement: 'bottom-start' } }
				renderToggle={ ( { isOpen, onToggle } ) => (
					<Button
						variant="secondary"
						onClick={ onToggle }
						aria-expanded={ isOpen }
					>
						{ __( 'Open', 'sprint-illustrations' ) }
					</Button>
				) }
				renderContent={ ( { onClose } ) => (
					<OpenMenu
						onOpen={ ( id ) => {
							onClose();
							onOpen( id );
						} }
					/>
				) }
			/>
			<Dropdown
				popoverProps={ { placement: 'bottom-start' } }
				renderToggle={ ( { isOpen, onToggle } ) => (
					<Button
						variant="tertiary"
						onClick={ onToggle }
						aria-expanded={ isOpen }
					>
						{ __( 'New', 'sprint-illustrations' ) }
					</Button>
				) }
				renderContent={ ( { onClose } ) => (
					<MenuGroup className="si-b-export__menu">
						<MenuItem
							info={ __(
								'Start empty and add pieces from the Library',
								'sprint-illustrations'
							) }
							onClick={ () => {
								onClose();
								dispatch( { type: 'NEW', blank: true } );
							} }
						>
							{ __( 'Blank canvas', 'sprint-illustrations' ) }
						</MenuItem>
						<MenuItem
							info={ __(
								'Start from a ready-made scene',
								'sprint-illustrations'
							) }
							onClick={ () => {
								onClose();
								dispatch( { type: 'NEW' } );
							} }
						>
							{ __( 'From a template', 'sprint-illustrations' ) }
						</MenuItem>
					</MenuGroup>
				) }
			/>
			<div className="si-b-top__name">
				<TextControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ __( 'Name', 'sprint-illustrations' ) }
					hideLabelFromVision
					placeholder={ __(
						'Name this illustration',
						'sprint-illustrations'
					) }
					value={ state.name }
					onChange={ ( name ) => dispatch( { type: 'NAME', name } ) }
				/>
			</div>
			<span className="si-b-top__state" aria-live="polite">
				{ saveState }
			</span>
			<Button
				variant="primary"
				isBusy={ state.saving }
				disabled={ state.saving }
				onClick={ () => onSave( false ) }
			>
				{ __( 'Save', 'sprint-illustrations' ) }
			</Button>
			<Button
				variant="secondary"
				disabled={ ! state.id || state.saving }
				onClick={ () => onSave( true ) }
			>
				{ __( 'Save as new', 'sprint-illustrations' ) }
			</Button>
			<Button
				variant="secondary"
				disabled={ ! state.id }
				onClick={ copy }
				label={ shortcode || undefined }
				showTooltip={ !! shortcode }
			>
				{ copied
					? __( 'Copied', 'sprint-illustrations' )
					: __( 'Copy shortcode', 'sprint-illustrations' ) }
			</Button>
			<ExportMenu
				canExport={ !! state.result?.svg }
				animated={ Object.keys( state.spec.animations ).length > 0 }
				exporting={ exporting }
				onExport={ onExport }
			/>
		</div>
	);
}
