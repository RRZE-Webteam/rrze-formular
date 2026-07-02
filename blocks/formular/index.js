import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import './editor-support';

registerBlockType(metadata.name, {
	edit: Edit,
	save: () => null,
});
