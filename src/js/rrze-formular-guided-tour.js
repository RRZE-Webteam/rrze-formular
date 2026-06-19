/**
 * RRZE Formular admin tours: overview Guide + contextual setup tour.
 */
import { useEffect, useState } from '@wordpress/element';
import { render } from '@wordpress/element';
import { Guide } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { SetupTour } from './setup-tour';

function GuideIcon( { dashicon } ) {
	return (
		<div className="rrze-formular-guided-tour__icon" aria-hidden="true">
			<span className={ `dashicons ${ dashicon }` } />
		</div>
	);
}

function dismissTour() {
	if ( typeof rrzeFormularGuide === 'undefined' ) {
		return Promise.resolve();
	}

	const body = new FormData();
	body.append( 'action', 'rrze_formular_dismiss_guided_tour' );
	body.append( 'nonce', rrzeFormularGuide.nonce );

	return fetch( rrzeFormularGuide.ajaxUrl, {
		method: 'POST',
		body,
		credentials: 'same-origin',
	} );
}

function ToursApp( { autoStartGuide, autoStartSetup, setupTourStepId } ) {
	const setupTourActive =
		Boolean( autoStartSetup ) || setupTourStepId.length > 0;
	const [ guideOpen, setGuideOpen ] = useState(
		Boolean( autoStartGuide ) && ! setupTourActive
	);
	const [ setupOpen, setSetupOpen ] = useState( setupTourActive );
	const [ setupTourKey, setSetupTourKey ] = useState( 0 );
	const [ setupStepId, setSetupStepId ] = useState( setupTourStepId );

	useEffect( () => {
		const guideButton = document.getElementById(
			'rrze-formular-start-guided-tour'
		);
		const setupButton = document.getElementById(
			'rrze-formular-start-setup-tour'
		);

		const openGuide = () => {
			setSetupOpen( false );
			setGuideOpen( true );
		};
		const openSetup = () => {
			setGuideOpen( false );
			setSetupStepId( '' );
			setSetupTourKey( ( key ) => key + 1 );
			setSetupOpen( true );
		};

		guideButton?.addEventListener( 'click', openGuide );
		setupButton?.addEventListener( 'click', openSetup );

		return () => {
			guideButton?.removeEventListener( 'click', openGuide );
			setupButton?.removeEventListener( 'click', openSetup );
		};
	}, [] );

	const finishGuide = () => {
		setGuideOpen( false );
		dismissTour();
	};

	const guidePages = [
		{
			image: <GuideIcon dashicon="dashicons-welcome-learn-more" />,
			content: (
				<>
					<h1 className="rrze-formular-guided-tour__heading">
						{ __( 'Welcome to RRZE Formular', 'rrze-formular' ) }
					</h1>
					<p className="rrze-formular-guided-tour__text">
						{ __(
							'Create contact and enquiry forms in the block editor — without HTML, with spam protection and secure mail delivery.',
							'rrze-formular'
						) }
					</p>
				</>
			),
		},
		{
			image: <GuideIcon dashicon="dashicons-admin-settings" />,
			content: (
				<>
					<h1 className="rrze-formular-guided-tour__heading">
						{ __( 'Configure settings', 'rrze-formular' ) }
					</h1>
					<p className="rrze-formular-guided-tour__text">
						{ __(
							'Under Settings → RRZE Formular you define the default recipient, allowed domains and spam protection limits.',
							'rrze-formular'
						) }
					</p>
				</>
			),
		},
		{
			image: <GuideIcon dashicon="dashicons-feedback" />,
			content: (
				<>
					<h1 className="rrze-formular-guided-tour__heading">
						{ __( 'Use the block in the editor', 'rrze-formular' ) }
					</h1>
					<p className="rrze-formular-guided-tour__text">
						{ __(
							'Insert the “RRZE Formular” block on any page, choose a template and adjust fields in the sidebar.',
							'rrze-formular'
						) }
					</p>
				</>
			),
		},
		{
			image: <GuideIcon dashicon="dashicons-admin-users" />,
			content: (
				<>
					<h1 className="rrze-formular-guided-tour__heading">
						{ __( 'Setup tour for first steps', 'rrze-formular' ) }
					</h1>
					<p className="rrze-formular-guided-tour__text">
						{ __(
							'The setup tour walks you through the recommended settings step by step — start it any time with the button above.',
							'rrze-formular'
						) }
					</p>
				</>
			),
		},
	];

	return (
		<>
			{ guideOpen && (
				<Guide
					className="rrze-formular-guided-tour"
					contentLabel={ __(
						'RRZE Formular guided tour',
						'rrze-formular'
					) }
					finishButtonText={ __( 'Get started', 'rrze-formular' ) }
					onFinish={ finishGuide }
					pages={ guidePages }
				/>
			) }
			{ setupOpen && (
				<SetupTour
					key={ setupTourKey }
					initialStepId={ setupStepId }
					onClose={ () => setSetupOpen( false ) }
				/>
			) }
		</>
	);
}

const root = document.getElementById( 'rrze-formular-guided-tour-root' );

if ( root && typeof rrzeFormularGuide !== 'undefined' ) {
	render(
		<ToursApp
			autoStartGuide={ rrzeFormularGuide.autoStart }
			autoStartSetup={ rrzeFormularGuide.autoStartSetup }
			setupTourStepId={ rrzeFormularGuide.setupTourStepId }
		/>,
		root
	);
}
