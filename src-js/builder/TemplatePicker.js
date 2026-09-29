/**
 * Template cards, each showing that template's own scene.
 */
import { __ } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import { compose } from './api';

export default function TemplatePicker( { templates, selected, onSelect } ) {
	const [ thumbs, setThumbs ] = useState( {} );

	useEffect( () => {
		let live = true;
		templates.forEach( ( template ) => {
			compose( {
				template: template.id,
				seed: 3,
				palette: 'site',
				decorative: true,
			} )
				.then(
					( result ) =>
						live &&
						setThumbs( ( prev ) => ( {
							...prev,
							[ template.id ]: result.svg,
						} ) )
				)
				.catch( () => {} );
		} );
		return () => {
			live = false;
		};
	}, [ templates ] );

	return (
		<div
			className="si-b-templates"
			role="group"
			aria-label={ __( 'Templates', 'sprint-illustrations' ) }
		>
			{ templates.map( ( template ) => (
				<button
					type="button"
					key={ template.id }
					className="si-b-template"
					aria-pressed={ selected === template.id }
					onClick={ () => onSelect( template.id ) }
				>
					<span
						className="si-b-template__art"
						// Sanitized server-side by the composer.
						dangerouslySetInnerHTML={ {
							__html: thumbs[ template.id ] || '',
						} }
					/>
					<span className="si-b-template__label">
						{ template.label }
					</span>
				</button>
			) ) }
		</div>
	);
}
