import { __ } from '@wordpress/i18n';
import { useEffect } from '@wordpress/element';
import { registerPlugin } from '@wordpress/plugins';
import { useDispatch } from '@wordpress/data';

export function getRecipientEmailError(recipientEmail) {
	const config = window.RRZEFormularEditor || {};
	const value = ( recipientEmail || '' ).trim();

	if ( ! value ) {
		return '';
	}

	if ( ! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( value ) ) {
		return (
			config.i18n?.recipientInvalidEmail ||
			__( 'Please enter a valid e-mail address.', 'rrze-formular' )
		);
	}

	const domains = Array.isArray( config.allowedDomains )
		? config.allowedDomains
		: [];

	if ( ! domains.length ) {
		return (
			config.i18n?.recipientDomainsRequired ||
			__(
				'A recipient e-mail requires configured allowed domains.',
				'rrze-formular'
			)
		);
	}

	const domain = value.split( '@' ).pop().toLowerCase();
	const isAllowed = domains.some( ( allowedDomain ) => {
		const normalized = String( allowedDomain ).toLowerCase();
		return domain === normalized || domain.endsWith( '.' + normalized );
	} );

	if ( ! isAllowed ) {
		return (
			config.i18n?.recipientDomainNotAllowed ||
			__( 'The recipient e-mail domain is not allowed.', 'rrze-formular' )
		);
	}

	return '';
}

function SaveNotice() {
	const { createNotice } = useDispatch( 'core/notices' );

	useEffect( () => {
		const message = window.RRZEFormularEditor?.saveNotice;
		if ( ! message ) {
			return;
		}

		createNotice( 'warning', message, {
			isDismissible: true,
			type: 'snackbar',
		} );
	}, [ createNotice ] );

	return null;
}

registerPlugin( 'rrze-formular-save-notice', {
	render: SaveNotice,
} );
