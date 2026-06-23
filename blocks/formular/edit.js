import { __ } from '@wordpress/i18n';
import { useEffect, useRef } from '@wordpress/element';
import {
	InspectorControls,
	useBlockProps,
} from '@wordpress/block-editor';
import {
	PanelBody,
	TextControl,
	TextareaControl,
	ToggleControl,
	SelectControl,
	Button,
} from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { getTemplates } from './templates';
import { reinitDropdowns } from '../../src/js/dropdowns';

const getFieldTypes = () => [
	{ label: __('Text', 'rrze-formular'), value: 'text' },
	{ label: __('E-mail', 'rrze-formular'), value: 'email' },
	{ label: __('Telephone', 'rrze-formular'), value: 'tel' },
	{ label: __('Number', 'rrze-formular'), value: 'number' },
	{ label: __('Date', 'rrze-formular'), value: 'date' },
	{ label: __('Time', 'rrze-formular'), value: 'time' },
	{ label: __('Textarea', 'rrze-formular'), value: 'textarea' },
	{ label: __('Select', 'rrze-formular'), value: 'select' },
	{ label: __('Multiselect', 'rrze-formular'), value: 'multiselect' },
	{ label: __('Radio', 'rrze-formular'), value: 'radio' },
	{ label: __('Checkbox', 'rrze-formular'), value: 'checkbox' },
	{ label: __('Section heading', 'rrze-formular'), value: 'heading' },
];

function cloneFields(fields) {
	return fields.map((field, index) => ({
		...field,
		id: field.id || `field_${index + 1}`,
		options: Array.isArray(field.options) ? field.options.map((option) => ({ ...option })) : [],
	}));
}

function createField(type = 'text') {
	return {
		id: `field_${Date.now()}`,
		type,
		label: __('New field', 'rrze-formular'),
		placeholder: '',
		required: false,
		options: ['select', 'multiselect', 'radio'].includes(type)
			? [{ value: 'option_1', label: __('Option 1', 'rrze-formular') }]
			: [],
	};
}

function moveField(fields, index, direction) {
	const nextIndex = index + direction;
	if (nextIndex < 0 || nextIndex >= fields.length) {
		return fields;
	}
	const next = [...fields];
	const [item] = next.splice(index, 1);
	next.splice(nextIndex, 0, item);
	return next;
}

function FieldEditor({ field, index, total, onChange, onRemove, onMove }) {
	const hasOptions = field.type === 'select' || field.type === 'multiselect' || field.type === 'radio';

	return (
		<div className="rrze-formular-field-editor">
			<strong>{__('Field', 'rrze-formular')} {index + 1}</strong>
			<SelectControl
				label={__('Type', 'rrze-formular')}
				value={field.type}
				options={getFieldTypes()}
				onChange={(value) => onChange(index, {
					...field,
					type: value,
					options: ['select', 'multiselect', 'radio'].includes(value) && (!field.options || field.options.length === 0)
						? [{ value: 'option_1', label: __('Option 1', 'rrze-formular') }]
						: field.options,
				})}
			/>
			<TextControl
				label={__('Label', 'rrze-formular')}
				value={field.label}
				onChange={(value) => onChange(index, { ...field, label: value })}
			/>
			{field.type !== 'heading' && (
				<TextControl
					label={__('Placeholder', 'rrze-formular')}
					value={field.placeholder || ''}
					onChange={(value) => onChange(index, { ...field, placeholder: value })}
				/>
			)}
			{field.type !== 'heading' && (
				<ToggleControl
					label={__('Required', 'rrze-formular')}
					checked={!!field.required}
					onChange={(value) => onChange(index, { ...field, required: value })}
				/>
			)}
			{hasOptions && field.options.map((option, optionIndex) => (
				<div key={`${field.id}-option-${optionIndex}`} className="rrze-formular-field-editor__option">
					<TextControl
						label={__('Option label', 'rrze-formular')}
						value={option.label}
						onChange={(value) => {
							const options = [...field.options];
							options[optionIndex] = { ...options[optionIndex], label: value };
							onChange(index, { ...field, options });
						}}
					/>
					<TextControl
						label={__('Option value', 'rrze-formular')}
						value={option.value}
						onChange={(value) => {
							const options = [...field.options];
							options[optionIndex] = { ...options[optionIndex], value };
							onChange(index, { ...field, options });
						}}
					/>
				</div>
			))}
			{hasOptions && (
				<Button
					variant="secondary"
					onClick={() => onChange(index, {
						...field,
						options: [
							...field.options,
							{ value: `option_${field.options.length + 1}`, label: __('New option', 'rrze-formular') },
						],
					})}
				>
					{__('Add option', 'rrze-formular')}
				</Button>
			)}
			<div className="rrze-formular-field-editor__actions">
				<Button disabled={index === 0} onClick={() => onMove(index, -1)}>{__('Move up', 'rrze-formular')}</Button>
				<Button disabled={index === total - 1} onClick={() => onMove(index, 1)}>{__('Move down', 'rrze-formular')}</Button>
				<Button isDestructive onClick={() => onRemove(index)}>{__('Remove', 'rrze-formular')}</Button>
			</div>
		</div>
	);
}

export default function Edit({ attributes, setAttributes }) {
	const {
		template,
		formTitle,
		formDescription,
		submitLabel,
		successMessage,
		includeSsoInfo,
		sendConfirmation,
		fields,
	} = attributes;

	const blockProps = useBlockProps({ className: 'rrze-formular-block-editor' });
	const previewRef = useRef(null);

	useEffect(() => {
		if (fields && fields.length > 0) {
			return;
		}
		const templates = getTemplates();
		const selected = templates.find((item) => item.value === template) || templates[1];
		setAttributes({
			formTitle: selected.formTitle,
			formDescription: selected.formDescription,
			fields: cloneFields(selected.fields),
		});
	}, []);

	useEffect(() => {
		const timer = window.setTimeout(() => {
			if (!window.RRZEFormular) {
				window.RRZEFormular = {
					i18n: {
						chooseOption: __('Please choose…', 'rrze-formular'),
						confirmSelection: __('Confirm selection', 'rrze-formular'),
					},
				};
			}
			const root = previewRef.current?.querySelector('.rrze-formular');
			if (root) {
				reinitDropdowns(root);
			}
		}, 100);

		return () => window.clearTimeout(timer);
	}, [fields, formTitle, formDescription, submitLabel, template]);

	const applyTemplate = (value) => {
		const templates = getTemplates();
		const selected = templates.find((item) => item.value === value) || templates[0];
		setAttributes({
			template: value,
			formTitle: selected.formTitle,
			formDescription: selected.formDescription,
			fields: cloneFields(selected.fields),
		});
	};

	const updateField = (index, nextField) => {
		const nextFields = [...fields];
		nextFields[index] = nextField;
		setAttributes({ fields: nextFields });
	};

	const removeField = (index) => {
		setAttributes({ fields: fields.filter((_, fieldIndex) => fieldIndex !== index) });
	};

	const moveFieldAt = (index, direction) => {
		setAttributes({ fields: moveField(fields, index, direction) });
	};

	return (
		<>
			<InspectorControls>
				<PanelBody title={__('Template', 'rrze-formular')} initialOpen>
					<SelectControl
						label={__('Form template', 'rrze-formular')}
						value={template}
						options={getTemplates().map((item) => ({ label: item.label, value: item.value }))}
						onChange={applyTemplate}
					/>
				</PanelBody>
				<PanelBody title={__('Form settings', 'rrze-formular')} initialOpen={false}>
					<TextControl
						label={__('Title', 'rrze-formular')}
						value={formTitle}
						onChange={(value) => setAttributes({ formTitle: value })}
					/>
					<TextareaControl
						label={__('Description', 'rrze-formular')}
						value={formDescription}
						onChange={(value) => setAttributes({ formDescription: value })}
					/>
					<TextControl
						label={__('Submit button label', 'rrze-formular')}
						value={submitLabel}
						onChange={(value) => setAttributes({ submitLabel: value })}
					/>
					<TextControl
						label={__('Success message', 'rrze-formular')}
						value={successMessage}
						onChange={(value) => setAttributes({ successMessage: value })}
					/>
					<ToggleControl
						label={__('Include SSO / logged-in user data', 'rrze-formular')}
						checked={!!includeSsoInfo}
						onChange={(value) => setAttributes({ includeSsoInfo: value })}
					/>
					<ToggleControl
						label={__('Send confirmation to submitter', 'rrze-formular')}
						help={__('Sends a copy of the submission to the e-mail address entered in the form.', 'rrze-formular')}
						checked={!!sendConfirmation}
						onChange={(value) => setAttributes({ sendConfirmation: value })}
					/>
				</PanelBody>
				<PanelBody title={__('Fields', 'rrze-formular')} initialOpen>
					{(fields || []).map((field, index) => (
						<FieldEditor
							key={field.id || index}
							field={field}
							index={index}
							total={fields.length}
							onChange={updateField}
							onRemove={removeField}
							onMove={moveFieldAt}
						/>
					))}
					<Button variant="primary" onClick={() => setAttributes({ fields: [...fields, createField()] })}>
						{__('Add field', 'rrze-formular')}
					</Button>
				</PanelBody>
			</InspectorControls>
			<div {...blockProps} ref={previewRef}>
				<ServerSideRender block="rrze-formular/formular" attributes={attributes} />
			</div>
		</>
	);
}
