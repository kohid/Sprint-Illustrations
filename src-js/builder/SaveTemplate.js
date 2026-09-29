/**
 * "Save as template": turns what is on the canvas into a reusable layout (admins only, because it
 * writes into the plugin so the template ships with it).
 */
import { __, sprintf } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { Button, Modal, TextControl } from '@wordpress/components';
import { getLibrary, saveTemplate } from './api';
import { specBody } from './state';

export default function SaveTemplate( { state, dispatch } ) {
	const [ open, setOpen ] = useState( false );
	const [ name, setName ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );

	const start = () => {
		setName(
			state.name ||
				sprintf(
					/* translators: %s: template label. */
					__( '%s layout', 'sprint-illustrations' ),
					state.result?.template?.label ||
						__( 'My', 'sprint-illustrations' )
				)
		);
		setError( '' );
		setOpen( true );
	};

	const save = async ( event ) => {
		event.preventDefault();
		setBusy( true );
		setError( '' );
		try {
			const saved = await saveTemplate( name.trim(), specBody( state ) );
			const library = await getLibrary();
			dispatch( { type: 'LIBRARY', library } );
			dispatch( {
				type: 'NOTICE',
				notice: {
					status: 'success',
					text:
						'plugin' === saved.where
							? sprintf(
									/* translators: %s: template name. */
									__(
										'Saved “%s” as a template in the plugin, so it ships with it. It’s in the Template panel; ask Claude Code to commit it.',
										'sprint-illustrations'
									),
									saved.label
							  )
							: sprintf(
									/* translators: %s: template name. */
									__(
										'Saved “%s” as a template on this site. It’s in the Template panel.',
										'sprint-illustrations'
									),
									saved.label
							  ),
				},
			} );
			setOpen( false );
		} catch ( e ) {
			setError(
				e.message ||
					__(
						'The template couldn’t be saved.',
						'sprint-illustrations'
					)
			);
		} finally {
			setBusy( false );
		}
	};

	return (
		<>
			<Button
				variant="tertiary"
				disabled={ ! state.result }
				onClick={ start }
			>
				{ __( 'Save as template', 'sprint-illustrations' ) }
			</Button>
			{ open && (
				<Modal
					title={ __( 'Save as template', 'sprint-illustrations' ) }
					onRequestClose={ () => ! busy && setOpen( false ) }
					size="small"
				>
					<form onSubmit={ save } className="si-b-template-form">
						<p className="si-b-muted">
							{ __(
								'Every layer becomes a slot at its current place and size, in the same front-to-back order. Shuffle and keywords then swap in similar pieces.',
								'sprint-illustrations'
							) }
						</p>
						<TextControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label={ __(
								'Template name',
								'sprint-illustrations'
							) }
							value={ name }
							onChange={ setName }
							required
						/>
						{ error && <p className="si-b-error">{ error }</p> }
						<div className="si-b-template-form__actions">
							<Button
								variant="tertiary"
								onClick={ () => setOpen( false ) }
								disabled={ busy }
							>
								{ __( 'Cancel', 'sprint-illustrations' ) }
							</Button>
							<Button
								variant="primary"
								type="submit"
								isBusy={ busy }
								disabled={ busy || ! name.trim() }
							>
								{ __(
									'Save template',
									'sprint-illustrations'
								) }
							</Button>
						</div>
					</form>
				</Modal>
			) }
		</>
	);
}
