/**
 * Contextual setup tour for RRZE Formular settings.
 */
import { createPortal, useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { SetupTourStepPanel } from './setup-tour-step';

function getSetupSteps() {
	return [
		{
			id: 'tab-general',
			tab: 'general',
			target: '[data-rrze-tour="tab-general"]',
			title: __( 'General tab', 'rrze-formular' ),
			text: __(
				'Open the General tab to configure default form behaviour.',
				'rrze-formular'
			),
		},
		{
			id: 'sso-default',
			tab: 'general',
			target: '[data-rrze-tour="sso-default"]',
			title: __( 'Include SSO data by default', 'rrze-formular' ),
			text: __(
				'When enabled, name and e-mail of logged-in users are appended to operator mails.',
				'rrze-formular'
			),
			optional: true,
		},
		{
			id: 'save-general',
			tab: 'general',
			target: '[data-rrze-tour="save-settings"]',
			title: __( 'Save your settings', 'rrze-formular' ),
			text: __(
				'Click Save changes after editing settings on each tab.',
				'rrze-formular'
			),
		},
		{
			id: 'tab-spam',
			tab: 'spam',
			target: '[data-rrze-tour="tab-spam"]',
			title: __( 'Spam Protection tab', 'rrze-formular' ),
			text: __(
				'Adjust limits for honeypot, minimum fill time and rate limiting.',
				'rrze-formular'
			),
		},
		{
			id: 'min-submit-seconds',
			tab: 'spam',
			target: '[data-rrze-tour="min-submit-seconds"]',
			title: __( 'Minimum fill time (seconds)', 'rrze-formular' ),
			text: __(
				'Reject submissions that arrive faster than this threshold.',
				'rrze-formular'
			),
		},
		{
			id: 'rate-limit',
			tab: 'spam',
			target: '[data-rrze-tour="rate-limit"]',
			title: __( 'Submissions per IP per hour', 'rrze-formular' ),
			text: __(
				'Maximum number of accepted submissions from one IP address per hour.',
				'rrze-formular'
			),
		},
		{
			id: 'save-spam',
			tab: 'spam',
			target: '[data-rrze-tour="save-settings"]',
			title: __( 'Save your settings', 'rrze-formular' ),
			text: __(
				'Click Save changes after editing settings on each tab.',
				'rrze-formular'
			),
		},
	];
}

function dismissSetupTour() {
	if ( typeof rrzeFormularGuide === 'undefined' ) {
		return Promise.resolve();
	}

	const body = new FormData();
	body.append( 'action', 'rrze_formular_dismiss_setup_tour' );
	body.append( 'nonce', rrzeFormularGuide.setupTourNonce );

	return fetch( rrzeFormularGuide.ajaxUrl, {
		method: 'POST',
		body,
		credentials: 'same-origin',
	} );
}

function buildSettingsUrl( tab, stepId ) {
	const url = new URL( rrzeFormularGuide.settingsUrl, window.location.origin );
	url.searchParams.set( 'tab', tab );
	url.searchParams.set( 'rrze_setup_tour', '1' );
	url.searchParams.set( 'rrze_setup_tour_step', stepId );
	return url.toString();
}

function findStepTarget( step ) {
	return document.querySelector( step.target );
}

const SPOTLIGHT_PADDING = 8;
const TOUR_TARGET_CLASS = 'rrze-formular-setup-tour__target';

function clearTourTargetMarkers() {
	document
		.querySelectorAll( `.${ TOUR_TARGET_CLASS }` )
		.forEach( ( element ) => {
			element.classList.remove( TOUR_TARGET_CLASS );
		} );
}

function markTourTarget( element ) {
	clearTourTargetMarkers();

	if ( element ) {
		element.classList.add( TOUR_TARGET_CLASS );
	}
}

function getCutoutClipPath( rect ) {
	const right = rect.left + rect.width;
	const bottom = rect.top + rect.height;
	const viewportWidth = window.innerWidth;
	const viewportHeight = window.innerHeight;

	return `polygon(
		0px 0px,
		${ viewportWidth }px 0px,
		${ viewportWidth }px ${ viewportHeight }px,
		0px ${ viewportHeight }px,
		0px 0px,
		${ rect.left }px ${ rect.top }px,
		${ rect.left }px ${ bottom }px,
		${ right }px ${ bottom }px,
		${ right }px ${ rect.top }px,
		${ rect.left }px ${ rect.top }px
	)`;
}

function getSpotlightRect( element ) {
	if ( ! element ) {
		return null;
	}

	const rect = element.getBoundingClientRect();
	if ( rect.width <= 0 || rect.height <= 0 ) {
		return null;
	}

	const pad = SPOTLIGHT_PADDING;

	return {
		top: Math.max( 0, rect.top - pad ),
		left: Math.max( 0, rect.left - pad ),
		width: rect.width + pad * 2,
		height: rect.height + pad * 2,
	};
}

function SetupTourSpotlight( { rect, onClose, closeLabel } ) {
	if ( ! rect ) {
		return (
			<button
				type="button"
				className="rrze-formular-setup-tour__overlay"
				aria-label={ closeLabel }
				onClick={ onClose }
			/>
		);
	}

	return (
		<>
			<button
				type="button"
				className="rrze-formular-setup-tour__overlay-panel rrze-formular-setup-tour__overlay-panel--cutout"
				style={ { clipPath: getCutoutClipPath( rect ) } }
				aria-label={ closeLabel }
				onClick={ onClose }
			/>
			<div
				className="rrze-formular-setup-tour__spotlight"
				style={ {
					top: rect.top,
					left: rect.left,
					width: rect.width,
					height: rect.height,
				} }
				aria-hidden="true"
			/>
		</>
	);
}

function resolveGlobalStepIndex( steps, stepId ) {
	if ( stepId ) {
		const resolved = steps.findIndex( ( step ) => step.id === stepId );

		if ( resolved >= 0 ) {
			return skipRedundantTabSteps( steps, resolved );
		}

		return 0;
	}

	return 0;
}

function isTabStep( step ) {
	return step.id.startsWith( 'tab-' );
}

function isStepOnActiveTab( step ) {
	return step.tab === rrzeFormularGuide.activeTab;
}

function needsTabSwitchForStep( step ) {
	if ( isTabStep( step ) ) {
		return false;
	}

	return ! isStepOnActiveTab( step );
}

function isStepTargetVisible( step ) {
	if ( isTabStep( step ) ) {
		return Boolean( findStepTarget( step ) );
	}

	if ( ! isStepOnActiveTab( step ) ) {
		return false;
	}

	return Boolean( findStepTarget( step ) );
}

function skipRedundantTabSteps( steps, startIndex ) {
	let index = startIndex;

	while ( index < steps.length ) {
		const step = steps[ index ];

		if ( ! step.id.startsWith( 'tab-' ) || ! isStepOnActiveTab( step ) ) {
			break;
		}

		index++;
	}

	return index;
}

function findNextStepIndex( steps, fromIndex ) {
	let index = fromIndex + 1;

	while ( index < steps.length ) {
		const step = steps[ index ];

		if ( ! step.optional || isStepTargetVisible( step ) ) {
			return index;
		}

		index++;
	}

	return fromIndex;
}

function findPreviousStepIndex( steps, fromIndex ) {
	let index = fromIndex - 1;

	while ( index >= 0 ) {
		const step = steps[ index ];

		if ( ! step.optional || isStepTargetVisible( step ) ) {
			return index;
		}

		index--;
	}

	return fromIndex;
}

export function SetupTour( { initialStepId = '', onClose } ) {
	const allSteps = useMemo( getSetupSteps, [] );
	const [ globalStepIndex, setGlobalStepIndex ] = useState( () =>
		resolveGlobalStepIndex( allSteps, initialStepId )
	);
	const [ anchor, setAnchor ] = useState( null );
	const [ spotlightRect, setSpotlightRect ] = useState( null );

	const currentStep = allSteps[ globalStepIndex ];
	const totalSteps = allSteps.length;
	const stepNumber = globalStepIndex + 1;

	const goToGlobalStep = useCallback(
		( index, { switchTab = false } = {} ) => {
			if ( index < 0 || index >= allSteps.length ) {
				return;
			}

			const step = allSteps[ index ];

			if ( step.tab !== rrzeFormularGuide.activeTab ) {
				if ( isTabStep( step ) && ! switchTab ) {
					setGlobalStepIndex( index );
					return;
				}

				window.location.href = buildSettingsUrl( step.tab, step.id );
				return;
			}

			setGlobalStepIndex( index );
		},
		[ allSteps ]
	);

	const syncAnchor = useCallback( () => {
		if ( ! currentStep ) {
			clearTourTargetMarkers();
			setAnchor( null );
			setSpotlightRect( null );
			return;
		}

		const target = findStepTarget( currentStep );

		if ( ! target ) {
			clearTourTargetMarkers();
			setAnchor( null );
			setSpotlightRect( null );
			return;
		}

		if (
			! isTabStep( currentStep ) &&
			! isStepOnActiveTab( currentStep )
		) {
			clearTourTargetMarkers();
			setAnchor( null );
			setSpotlightRect( null );
			return;
		}

		markTourTarget( target );
		setAnchor( target );
		setSpotlightRect( getSpotlightRect( target ) );

		target.scrollIntoView( { block: 'nearest', inline: 'nearest' } );
	}, [ currentStep ] );

	useEffect( () => {
		let frameId = window.requestAnimationFrame( () => {
			syncAnchor();
		} );

		const onLayoutChange = () => {
			window.cancelAnimationFrame( frameId );
			frameId = window.requestAnimationFrame( () => {
				syncAnchor();
			} );
		};

		window.addEventListener( 'resize', onLayoutChange );
		window.addEventListener( 'scroll', onLayoutChange, true );

		return () => {
			window.cancelAnimationFrame( frameId );
			window.removeEventListener( 'resize', onLayoutChange );
			window.removeEventListener( 'scroll', onLayoutChange, true );
			clearTourTargetMarkers();
		};
	}, [ syncAnchor, globalStepIndex ] );

	useEffect( () => {
		if ( ! anchor || ! currentStep || ! isTabStep( currentStep ) ) {
			return undefined;
		}

		const onTabClick = ( event ) => {
			event.preventDefault();
			goToGlobalStep( globalStepIndex, { switchTab: true } );
		};

		anchor.addEventListener( 'click', onTabClick );

		return () => {
			anchor.removeEventListener( 'click', onTabClick );
		};
	}, [ anchor, currentStep, globalStepIndex, goToGlobalStep ] );

	const finishTour = () => {
		dismissSetupTour();
		onClose?.();

		const url = new URL( window.location.href );
		url.searchParams.delete( 'rrze_setup_tour' );
		url.searchParams.delete( 'rrze_setup_tour_step' );
		window.history.replaceState( {}, '', url.toString() );
	};

	if ( ! currentStep || totalSteps === 0 ) {
		return null;
	}

	const needsTabSwitch = needsTabSwitchForStep( currentStep );
	const nextStepIndex = findNextStepIndex( allSteps, globalStepIndex );
	const isLast = nextStepIndex === globalStepIndex;
	const stepText =
		needsTabSwitch && ! spotlightRect
			? __(
					'Continue to the next tab to see the highlighted field.',
					'rrze-formular'
			  )
			: currentStep.text;
	const nextLabel =
		isTabStep( currentStep ) && ! isStepOnActiveTab( currentStep )
			? __( 'Open tab', 'rrze-formular' )
			: needsTabSwitch
			? __( 'Open tab', 'rrze-formular' )
			: __( 'Next', 'rrze-formular' );

	const handleNext = () => {
		if ( isLast ) {
			finishTour();
			return;
		}

		if ( needsTabSwitch ) {
			goToGlobalStep( globalStepIndex, { switchTab: true } );
			return;
		}

		if ( isTabStep( currentStep ) && ! isStepOnActiveTab( currentStep ) ) {
			if ( currentStep.id === 'tab-spam' ) {
				goToGlobalStep(
					findNextStepIndex( allSteps, globalStepIndex ),
					{ switchTab: true }
				);
				return;
			}

			goToGlobalStep( globalStepIndex, { switchTab: true } );
			return;
		}

		goToGlobalStep( nextStepIndex );
	};

	return createPortal(
		<>
			<SetupTourSpotlight
				rect={ spotlightRect }
				onClose={ finishTour }
				closeLabel={ __( 'Close setup tour', 'rrze-formular' ) }
			/>
			<div
				className="rrze-formular-setup-tour__card"
				role="dialog"
				aria-modal="true"
				aria-label={ currentStep.title }
			>
				<SetupTourStepPanel
					stepNumber={ stepNumber }
					totalSteps={ totalSteps }
					title={ currentStep.title }
					text={ stepText }
					showPrevious={ globalStepIndex > 0 }
					isLast={ isLast }
					nextLabel={ nextLabel }
					onPrevious={ () =>
						goToGlobalStep(
							findPreviousStepIndex( allSteps, globalStepIndex )
						)
					}
					onSkip={ finishTour }
					onNext={ handleNext }
				/>
			</div>
		</>,
		document.body
	);
}
