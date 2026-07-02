import { __ } from '@wordpress/i18n';
import { useEffect } from '@wordpress/element';
import { registerPlugin } from '@wordpress/plugins';
import { useDispatch, useSelect } from '@wordpress/data';
import { store as editorStore } from '@wordpress/editor';
import { store as blockEditorStore } from '@wordpress/block-editor';

const BLOCK_NAMES = [ 'rrze-formular/formular', 'rrze-formular/form-wizard' ];
const LOCK_NAME = 'rrze-formular-invalid-recipient';

export function getRecipientEmailError( recipientEmail ) {
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

function walkBlocks( blockList, invalidEmails ) {
	blockList.forEach( ( block ) => {
		if ( BLOCK_NAMES.includes( block.name ) ) {
			const error = getRecipientEmailError( block.attributes?.recipientEmail );
			if ( error ) {
				invalidEmails.push( block.attributes?.recipientEmail || '' );
			}
		}

		if ( block.innerBlocks?.length ) {
			walkBlocks( block.innerBlocks, invalidEmails );
		}
	} );
}

function hasInvalidRecipientBlocks( blocks ) {
	const invalidEmails = [];
	walkBlocks( blocks, invalidEmails );
	return invalidEmails.length > 0;
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

function PublishLock() {
	const blocks = useSelect( ( select ) => select( blockEditorStore ).getBlocks(), [] );
	const { lockPostSaving, unlockPostSaving } = useDispatch( editorStore );
	const { createNotice, removeNotice } = useDispatch( 'core/notices' );
	const noticeId = 'rrze-formular-invalid-recipient-notice';

	useEffect( () => {
		const blocked = hasInvalidRecipientBlocks( blocks );

		if ( blocked ) {
			lockPostSaving( LOCK_NAME );
			createNotice(
				'error',
				window.RRZEFormularEditor?.i18n?.publishBlocked ||
					__(
						'Publishing is blocked until all form recipient addresses use an allowed domain.',
						'rrze-formular'
					),
				{
					id: noticeId,
					isDismissible: false,
				}
			);
		} else {
			unlockPostSaving( LOCK_NAME );
			removeNotice( noticeId );
		}

		return () => {
			unlockPostSaving( LOCK_NAME );
			removeNotice( noticeId );
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
