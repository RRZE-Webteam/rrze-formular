import { __ } from '@wordpress/i18n';

function getEditorConfig() {
	if ( window.RRZEFormularEditor ) {
		return window.RRZEFormularEditor;
	}

	const blockEditorSettings = window.wp?.data?.select( 'core/block-editor' )?.getSettings?.();
	if ( blockEditorSettings?.rrzeFormularEditor ) {
		return blockEditorSettings.rrzeFormularEditor;
	}

	const editorSettings = window.wp?.data?.select( 'core/editor' )?.getEditorSettings?.();
	if ( editorSettings?.rrzeFormularEditor ) {
		return editorSettings.rrzeFormularEditor;
	}

	return {};
}

export function getRecipientEmailError( recipientEmail ) {
	const config = getEditorConfig();
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
