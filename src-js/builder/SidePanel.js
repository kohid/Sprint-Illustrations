/**
 * Suggest, palette, variation and accessibility controls.
 */
import { __ } from '@wordpress/i18n';
import {
	Button,
	RadioControl,
	TextareaControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';

/**
 * Site palette or a preset (shown in the left column).
 *
 * @param {Object}   props             Props.
 * @param {string}   props.palette     Current palette reference.
 * @param {Array}    props.presets     Presets from /library.
 * @param {string}   props.settingsUrl Settings page link, or '' without access.
 * @param {Function} props.dispatch    Dispatch.
 * @return {Element} Palette choices.
 */
export function PalettePanel( { palette, presets, settingsUrl, dispatch } ) {
	return (
		<>
			<RadioControl
				label={ __( 'Palette', 'sprint-illustrations' ) }
				hideLabelFromVision
				selected={ palette }
				options={ [
					{
						label: __( 'Site palette', 'sprint-illustrations' ),
						value: 'site',
					},
				].concat( presets ) }
				onChange={ ( next ) =>
					dispatch( { type: 'SET_SPEC', spec: { palette: next } } )
				}
			/>
			{ settingsUrl && (
				<p className="si-b-muted">
					<a href={ settingsUrl }>
						{ __(
							'Edit the site palette',
							'sprint-illustrations'
						) }
					</a>
				</p>
			) }
		</>
	);
}

export default function SidePanel( {
	state,
	dispatch,
	layers,
	aiReady,
	suggesting,
	describe,
	onDescribe,
	onSuggest,
} ) {
	const set = ( spec ) => dispatch( { type: 'SET_SPEC', spec } );

	return (
		<div className="si-b-side">
			<section className="si-b-panel" aria-labelledby="si-b-suggest">
				<h2 className="si-b-panel__title" id="si-b-suggest">
					{ __( 'Suggest', 'sprint-illustrations' ) }
				</h2>
				<TextareaControl
					__nextHasNoMarginBottom
					label={ __( 'Describe it', 'sprint-illustrations' ) }
					help={ __(
						'What is the page or section about? Suggest picks a template, keywords and alt text.',
						'sprint-illustrations'
					) }
					rows={ 3 }
					value={ describe }
					onChange={ onDescribe }
				/>
				<div className="si-b-suggest__bar">
					<Button
						variant="secondary"
						__next40pxDefaultSize
						isBusy={ suggesting }
						disabled={ suggesting || ! describe.trim() }
						onClick={ () => onSuggest( describe ) }
					>
						{ suggesting
							? __( 'Suggesting…', 'sprint-illustrations' )
							: __( 'Suggest', 'sprint-illustrations' ) }
					</Button>
					<span className="si-b-muted">
						{ aiReady
							? __( 'Uses Claude.', 'sprint-illustrations' )
							: __(
									'Uses keyword matching.',
									'sprint-illustrations'
							  ) }
					</span>
				</div>
			</section>
			{ layers }

			<section className="si-b-panel" aria-labelledby="si-b-variation">
				<h2 className="si-b-panel__title" id="si-b-variation">
					{ __( 'Variation', 'sprint-illustrations' ) }
				</h2>
				<div className="si-b-row">
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Seed', 'sprint-illustrations' ) }
						type="number"
						min={ 0 }
						value={ String( state.spec.seed ) }
						onChange={ ( value ) =>
							set( {
								seed: Math.max( 0, parseInt( value, 10 ) || 0 ),
							} )
						}
					/>
					<Button
						variant="secondary"
						__next40pxDefaultSize
						onClick={ () =>
							set( {
								seed: Math.floor( Math.random() * 100000 ) + 1,
							} )
						}
					>
						{ __( 'Shuffle', 'sprint-illustrations' ) }
					</Button>
				</div>
				<div className="si-b-row">
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Keywords', 'sprint-illustrations' ) }
						value={ state.spec.keywords }
						onChange={ ( keywords ) => set( { keywords } ) }
					/>
					<Button
						variant="secondary"
						__next40pxDefaultSize
						disabled={ ! state.spec.keywords.trim() }
						onClick={ () =>
							dispatch( { type: 'SET_TEMPLATE', template: '' } )
						}
						label={ __(
							'Pick the best template for these keywords',
							'sprint-illustrations'
						) }
						showTooltip
					>
						{ __( 'Match', 'sprint-illustrations' ) }
					</Button>
				</div>
				<p className="si-b-muted si-b-help">
					{ __(
						'Keywords steer which pieces appear, e.g. “coffee, laptop”. Match picks the template too.',
						'sprint-illustrations'
					) }
				</p>
			</section>

			<section className="si-b-panel" aria-labelledby="si-b-a11y">
				<h2 className="si-b-panel__title" id="si-b-a11y">
					{ __( 'Accessibility', 'sprint-illustrations' ) }
				</h2>
				<TextControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ __( 'Alt text', 'sprint-illustrations' ) }
					help={ __(
						'Leave blank to use the template name.',
						'sprint-illustrations'
					) }
					value={ state.spec.title }
					onChange={ ( title ) => set( { title } ) }
				/>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __(
						'Decorative (hide from screen readers)',
						'sprint-illustrations'
					) }
					checked={ state.spec.decorative }
					onChange={ ( decorative ) => set( { decorative } ) }
				/>
			</section>
		</div>
	);
}
