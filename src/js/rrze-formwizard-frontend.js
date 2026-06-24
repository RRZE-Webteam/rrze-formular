document.addEventListener('DOMContentLoaded', () => {
	document.querySelectorAll('.rrze-formular').forEach(initFormular);
});

function initFormular(root) {
	const form = root.querySelector('.rrze-formular__form');
	if (!form) {
		return;
	}

	const steps = Array.from(root.querySelectorAll('.rrze-formular__step'));
	const progressItems = Array.from(root.querySelectorAll('.rrze-formular__progress-item'));
	const messageBox = root.querySelector('.rrze-formular__message');
	let currentStep = 1;

	root.querySelectorAll('.rrze-formular__next').forEach((button) => {
		button.addEventListener('click', () => {
			if (!validateStep(root, currentStep)) {
				return;
			}
			showStep(currentStep + 1);
		});
	});

	root.querySelectorAll('.rrze-formular__prev').forEach((button) => {
		button.addEventListener('click', () => {
			showStep(currentStep - 1);
		});
	});

	form.addEventListener('submit', async (event) => {
		event.preventDefault();
		clearErrors(root);

		if (!validateStep(root, currentStep)) {
			return;
		}

		const submitButton = form.querySelector('.rrze-formular__submit');
		const defaultSubmitLabel = submitButton?.textContent || '';
		if (submitButton) {
			submitButton.disabled = true;
			submitButton.textContent = RRZEFormular.i18n.submitting;
		}

		const values = collectValues(form);
		const payload = {
			formConfig: form.querySelector('[name="formConfig"]')?.value || '',
			formConfigSig: form.querySelector('[name="formConfigSig"]')?.value || '',
			values,
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
			showStep(1);
			showMessage(messageBox, result.message || RRZEFormular.i18n.success, 'success');
		} catch (error) {
			showMessage(messageBox, RRZEFormular.i18n.error, 'error');
		} finally {
			if (submitButton) {
				submitButton.disabled = false;
				submitButton.textContent = defaultSubmitLabel;
			}
		}
	});

	function showStep(stepNumber) {
		currentStep = stepNumber;
		steps.forEach((step) => {
			const isActive = Number(step.dataset.step) === stepNumber;
			step.hidden = !isActive;
			step.classList.toggle('is-active', isActive);
		});
		progressItems.forEach((item) => {
			item.classList.toggle('is-active', Number(item.dataset.step) === stepNumber);
			item.classList.toggle('is-complete', Number(item.dataset.step) < stepNumber);
		});
	}
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

function validateStep(root, stepNumber) {
	clearErrors(root);
	let valid = true;
	const step = root.querySelector(`.rrze-formular__step[data-step="${stepNumber}"]`);
	if (!step) {
		return true;
	}

	step.querySelectorAll('[required]').forEach((field) => {
		const value = field.type === 'checkbox' ? field.checked : field.value.trim();
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
