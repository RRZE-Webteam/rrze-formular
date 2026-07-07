import { __ } from '@wordpress/i18n';
import { useEffect } from '@wordpress/element';
import { registerPlugin } from '@wordpress/plugins';
import { useDispatch, useSelect } from '@wordpress/data';
import { store as editorStore } from '@wordpress/editor';
import { store as blockEditorStore } from '@wordpress/block-editor';

const BLOCK_NAMES = [ 'rrze-formular/formular' ];
const LOCK_NAME = 'rrze-formular-publish-blocked';
const NOTICE_ID = 'rrze-formular-publish-blocked-notice';

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

function getEffectiveRecipientEmail( blockAttributes ) {
	const config = getEditorConfig();
	const blockEmail = ( blockAttributes?.recipientEmail || '' ).trim();

	if ( blockEmail ) {
		return blockEmail;
	}

	const defaultEmail = ( config.defaultRecipientEmail || '' ).trim();
	if ( defaultEmail ) {
		return defaultEmail;
	}

	return ( config.administratorEmail || '' ).trim();
}

function walkBlocks( blockList, visitor ) {
	blockList.forEach( ( block ) => {
		visitor( block );

		if ( block.innerBlocks?.length ) {
			walkBlocks( block.innerBlocks, visitor );
		}
	} );
}

function hasFormBlocks( blocks ) {
	let found = false;

	walkBlocks( blocks, ( block ) => {
		if ( BLOCK_NAMES.includes( block.name ) ) {
			found = true;
		}
	} );

	return found;
}

function getInvalidRecipientErrors( blocks ) {
	const errors = [];

	walkBlocks( blocks, ( block ) => {
		if ( ! BLOCK_NAMES.includes( block.name ) ) {
			return;
		}

		const error = getRecipientEmailError(
			getEffectiveRecipientEmail( block.attributes )
		);
		if ( error ) {
			errors.push( error );
		}
	} );

	return errors;
}

function getPublishBlockMessage( blocks ) {
	if ( ! hasFormBlocks( blocks ) ) {
		return '';
	}

	const recipientErrors = getInvalidRecipientErrors( blocks );
	if ( recipientErrors.length > 0 ) {
		return recipientErrors[ 0 ];
	}

	return '';
}

function SaveNotice() {
	const { createNotice } = useDispatch( 'core/notices' );
	const saveNotice = useSelect( () => getEditorConfig().saveNotice, [] );

	useEffect( () => {
		if ( ! saveNotice ) {
			return;
		}

		createNotice( 'warning', saveNotice, {
			isDismissible: true,
			type: 'snackbar',
		} );
	}, [ createNotice, saveNotice ] );

	return null;
}

function PublishLock() {
	const blocks = useSelect( ( select ) => select( blockEditorStore ).getBlocks(), [] );
	const { lockPostSaving, unlockPostSaving } = useDispatch( editorStore );
	const { createNotice, removeNotice } = useDispatch( 'core/notices' );

	useEffect( () => {
		const message = getPublishBlockMessage( blocks );

		if ( message ) {
			lockPostSaving( LOCK_NAME );
			createNotice( 'error', message, {
				id: NOTICE_ID,
				isDismissible: false,
			} );
		} else {
			unlockPostSaving( LOCK_NAME );
			removeNotice( NOTICE_ID );
		}

		return () => {
			unlockPostSaving( LOCK_NAME );
			removeNotice( NOTICE_ID );
		};
	}, [ blocks, lockPostSaving, unlockPostSaving, createNotice, removeNotice ] );

	return null;
}

registerPlugin( 'rrze-formular-save-notice', {
	render: SaveNotice,
} );

registerPlugin( 'rrze-formular-publish-lock', {
	render: PublishLock,
} );
