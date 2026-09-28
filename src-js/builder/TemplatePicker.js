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
		<section className="si-b-panel" aria-labelledby="si-b-templates">
			<h2 className="si-b-panel__title" id="si-b-templates">
				{ __( 'Template', 'sprint-illustrations' ) }
			</h2>
			<div className="si-b-templates">
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
		</section>
	);
}
