document.addEventListener('DOMContentLoaded', () => {
	document.querySelectorAll('.rrze-formular').forEach(initFormular);
});

function initFormular(root) {
	const form = root.querySelector('.rrze-formular__form');
	if (!form) {
		return;
	}

	const messageBox = root.querySelector('.rrze-formular__message');

	form.addEventListener('submit', async (event) => {
		event.preventDefault();
		clearErrors(root);

		if (!validateForm(form)) {
			return;
		}

		const submitButton = form.querySelector('.rrze-formular__submit');
		const attributes = JSON.parse(form.dataset.attributes || '{}');

		if (submitButton) {
			submitButton.disabled = true;
			submitButton.textContent = RRZEFormular.i18n.submitting;
		}

		const payload = {
			attributes,
			values: collectValues(form),
			token: form.querySelector('[name="token"]')?.value || '',
			website: form.querySelector('[name="website"]')?.value || '',
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
			showMessage(messageBox, result.message || RRZEFormular.i18n.success, 'success');
		} catch (error) {
			showMessage(messageBox, RRZEFormular.i18n.error, 'error');
		} finally {
			if (submitButton) {
				submitButton.disabled = false;
				submitButton.textContent = attributes.submitLabel || 'Send';
			}
		}
	});
}

function collectValues(form) {
	const values = {};
	form.querySelectorAll('input[name], textarea[name], select[name]').forEach((field) => {
		if (field.type === 'hidden' || field.name === 'website') {
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

	form.querySelectorAll('[required]').forEach((field) => {
		const value = field.type === 'checkbox' ? field.checked : field.value.trim();
		if (!value) {
			valid = false;
			showFieldError(form.closest('.rrze-formular'), field.name, RRZEFormular.i18n.validation);
		}
		if (field.type === 'email' && field.value && !field.validity.valid) {
			valid = false;
			showFieldError(form.closest('.rrze-formular'), field.name, RRZEFormular.i18n.validation);
		}
	});

	return valid;
}

function clearErrors(root) {
	root.querySelectorAll('.rrze-formular__error').forEach((error) => {
		error.hidden = true;
		error.textContent = '';
	});
}

function showFieldError(root, fieldId, text) {
	const error = root.querySelector(`.rrze-formular__error[data-field="${fieldId}"]`);
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
