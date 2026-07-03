import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
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

function hasInvalidRecipientBlocks( blocks ) {
	const invalidEmails = [];

	walkBlocks( blocks, ( block ) => {
		if ( ! BLOCK_NAMES.includes( block.name ) ) {
			return;
		}

		const error = getRecipientEmailError( block.attributes?.recipientEmail );
		if ( error ) {
			invalidEmails.push( block.attributes?.recipientEmail || '' );
		}
	} );

	return invalidEmails.length > 0;
}

function getPrivacyPublishBlockedMessage( config ) {
	const label = config.privacyLabel || __( 'Privacy', 'rrze-formular' );
	const url = config.privacyUrl || '';

	return sprintf(
		config.i18n?.privacyPublishBlockedFormat ||
			__(
				'This page cannot be published because no published %1$s page exists at %2$s.',
				'rrze-formular'
			),
		label,
		url
	);
}

async function checkPrivacyUrlReachable( privacyUrl ) {
	if ( ! privacyUrl ) {
		return false;
	}

	try {
		let response = await fetch( privacyUrl, {
			method: 'HEAD',
			credentials: 'same-origin',
		} );

		if ( response.ok ) {
			return true;
		}

		if ( response.status !== 405 && response.status !== 501 ) {
			return false;
		}

		response = await fetch( privacyUrl, {
			method: 'GET',
			credentials: 'same-origin',
		} );

		return response.ok;
	} catch {
		return null;
	}
}

function usePrivacyReachable() {
	const config = getEditorConfig();
	const privacyUrl = config.privacyUrl || '';
	const [ reachable, setReachable ] = useState( () => {
		if ( config.privacyPublished === true ) {
			return true;
		}

		return privacyUrl ? null : false;
	} );

	useEffect( () => {
		if ( ! privacyUrl ) {
			setReachable( false );
			return undefined;
		}

		let cancelled = false;

		checkPrivacyUrlReachable( privacyUrl ).then( ( result ) => {
			if ( cancelled ) {
				return;
			}

			if ( result === null ) {
				setReachable( config.privacyPublished === true );
				return;
			}

			setReachable( result );
		} );

		return () => {
			cancelled = true;
		};
	}, [ privacyUrl, config.privacyPublished ] );

	return reachable;
}

function getPublishBlockMessage( blocks, privacyReachable ) {
	if ( ! hasFormBlocks( blocks ) ) {
		return '';
	}

	const config = getEditorConfig();

	if ( privacyReachable === false ) {
		return getPrivacyPublishBlockedMessage( config );
	}

	if ( privacyReachable !== false && hasInvalidRecipientBlocks( blocks ) ) {
		return (
			config.i18n?.publishBlocked ||
			__(
				'Publishing is blocked until all form recipient addresses use an allowed domain.',
				'rrze-formular'
			)
		);
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
	const privacyReachable = usePrivacyReachable();
	const { lockPostSaving, unlockPostSaving } = useDispatch( editorStore );
	const { createNotice, removeNotice } = useDispatch( 'core/notices' );

	useEffect( () => {
		const message = getPublishBlockMessage( blocks, privacyReachable );

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
	}, [ blocks, privacyReachable, lockPostSaving, unlockPostSaving, createNotice, removeNotice ] );

	return null;
}

registerPlugin( 'rrze-formular-save-notice', {
	render: SaveNotice,
} );

registerPlugin( 'rrze-formular-publish-lock', {
	render: PublishLock,
} );
