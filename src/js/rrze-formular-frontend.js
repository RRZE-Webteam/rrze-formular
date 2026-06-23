import { initDropdowns, resetDropdowns } from './dropdowns';

document.addEventListener('DOMContentLoaded', () => {
	document.querySelectorAll('.rrze-formular').forEach(initFormular);
});

function initFormular(root) {
	const form = root.querySelector('.rrze-formular__form');
	if (!form) {
		return;
	}

	initDropdowns(root);

	const messageBox = root.querySelector('.rrze-formular__message');

	form.addEventListener('submit', async (event) => {
		event.preventDefault();
		clearErrors(root);

		if (!validateForm(form)) {
			return;
		}

		const submitButton = form.querySelector('.rrze-formular__submit');
		const submitLabel = submitButton?.querySelector('.rrze-formular__submit-text');
		const attributes = JSON.parse(form.dataset.attributes || '{}');

		if (submitButton) {
			submitButton.disabled = true;
			if (submitLabel) {
				submitLabel.textContent = RRZEFormular.i18n.submitting;
			}
		}

		const payload = {
			attributes,
			values: collectValues(form),
			token: form.querySelector('[name="token"]')?.value || '',
			website: form.querySelector('[name="website"]')?.value || '',
			pageUrl: window.location.href,
			locale: RRZEFormular.siteLocale || document.documentElement.lang || '',
		};

		try {
			const response = await fetch(RRZEFormular.restUrl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': RRZEFormular.nonce,
				},
				body: JSON.stringify(payload),
			});

			const result = await response.json();

			if (!response.ok || !result.success) {
				showMessage(messageBox, result.message || RRZEFormular.i18n.error, 'error');
				if (result.errors) {
					Object.entries(result.errors).forEach(([fieldId, text]) => {
						showFieldError(root, fieldId, text);
					});
				}
				return;
			}

			form.reset();
			resetDropdowns(root);
			showMessage(messageBox, result.message || RRZEFormular.i18n.success, 'success');
		} catch (error) {
			showMessage(messageBox, RRZEFormular.i18n.error, 'error');
		} finally {
			if (submitButton) {
				submitButton.disabled = false;
				if (submitLabel) {
					submitLabel.textContent = attributes.submitLabel || 'Send';
				}
			}
		}
	});
}

function collectValues(form) {
	const values = {};

	form.querySelectorAll('input[name], textarea[name], select[name]').forEach((field) => {
		if (field.type === 'hidden' && field.classList.contains('rrze-formular__dropdown-input')) {
			const name = field.name;
			if (field.value.includes(',')) {
				values[name] = field.value.split(',').map((part) => part.trim()).filter(Boolean);
			} else {
				values[name] = field.value;
			}
			return;
		}

		if (field.type === 'hidden' || field.name === 'website' || field.name === 'token' || field.name === 'issuedAt') {
			return;
		}

		if (field.dataset.optionCheckbox !== undefined) {
			return;
		}

		if (field.type === 'checkbox') {
			values[field.name] = field.checked ? '1' : '';
			return;
		}

		if (field.type === 'radio') {
			if (field.checked) {
				values[field.name] = field.value;
			}
			return;
		}

		values[field.name] = field.value;
	});

	return values;
}

function validateForm(form) {
	let valid = true;
	const root = form.closest('.rrze-formular');

	form.querySelectorAll('[required]').forEach((field) => {
		let value = field.type === 'checkbox' ? field.checked : String(field.value).trim();

		if (field.classList.contains('rrze-formular__dropdown-input')) {
			value = String(field.value).trim();
		}

		if (!value) {
			valid = false;
			showFieldError(root, field.name, RRZEFormular.i18n.validation);
		}

		if (field.type === 'email' && field.value && !field.validity.valid) {
			valid = false;
			showFieldError(root, field.name, RRZEFormular.i18n.validation);
		}
	});

	return valid;
}

function clearErrors(root) {
	root.querySelectorAll('.rrze-formular__error').forEach((error) => {
		error.hidden = true;
		error.textContent = '';
	});
	root.querySelectorAll('.is-invalid').forEach((field) => {
		field.classList.remove('is-invalid');
	});
	root.querySelectorAll('.rrze-formular__dropdown.is-invalid').forEach((dropdown) => {
		dropdown.classList.remove('is-invalid');
	});
}

function showFieldError(root, fieldId, text) {
	const error = root.querySelector(`.rrze-formular__error[data-field="${fieldId}"]`);
	const field = root.querySelector(`[name="${fieldId}"]`);
	const dropdown = field?.closest('.rrze-formular__dropdown');

	if (field) {
		field.classList.add('is-invalid');
	}

	if (dropdown) {
		dropdown.classList.add('is-invalid');
	}

	if (error) {
		error.hidden = false;
		error.textContent = text;
	}
}

function showMessage(element, text, type) {
	if (!element) {
		return;
	}
	element.hidden = false;
	element.textContent = text;
	element.classList.remove('is-success', 'is-error');
	element.classList.add(type === 'success' ? 'is-success' : 'is-error');
}
