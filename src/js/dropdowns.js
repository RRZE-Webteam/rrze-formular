export function initDropdowns(root) {
	root.querySelectorAll('.rrze-formular__dropdown').forEach((dropdown) => {
		if (dropdown.dataset.initialized === '1') {
			return;
		}
		dropdown.dataset.initialized = '1';

		const isMultiple = dropdown.dataset.multiple === '1';
		const toggle = dropdown.querySelector('.rrze-formular__dropdown-toggle');
		const panel = dropdown.querySelector('.rrze-formular__dropdown-panel');
		const valueEl = dropdown.querySelector('.rrze-formular__dropdown-value');
		const hiddenInput = dropdown.querySelector('.rrze-formular__dropdown-input');
		const placeholder = window.RRZEFormular?.i18n?.chooseOption || 'Please choose…';

		const close = () => {
			dropdown.classList.remove('is-open');
			toggle.setAttribute('aria-expanded', 'false');
			panel.hidden = true;
		};

		const open = () => {
			root.querySelectorAll('.rrze-formular__dropdown.is-open').forEach((other) => {
				if (other !== dropdown) {
					other.classList.remove('is-open');
					other.querySelector('.rrze-formular__dropdown-toggle')?.setAttribute('aria-expanded', 'false');
					const otherPanel = other.querySelector('.rrze-formular__dropdown-panel');
					if (otherPanel) {
						otherPanel.hidden = true;
					}
				}
			});
			dropdown.classList.add('is-open');
			toggle.setAttribute('aria-expanded', 'true');
			panel.hidden = false;
		};

		const setSingleValue = (value, label) => {
			hiddenInput.value = value;
			valueEl.textContent = label;
			valueEl.classList.toggle('is-placeholder', value === '');
			dropdown.querySelectorAll('.rrze-formular__dropdown-option[data-value]').forEach((option) => {
				option.classList.toggle('is-selected', option.dataset.value === value);
				option.setAttribute('aria-selected', option.dataset.value === value ? 'true' : 'false');
			});
		};

		const getMultiLabels = () => {
			return Array.from(dropdown.querySelectorAll('[data-option-checkbox]:checked'))
				.map((checkbox) => checkbox.closest('.rrze-formular__dropdown-option--multi')?.querySelector('.rrze-formular__dropdown-option-label')?.textContent?.trim() || '')
				.filter(Boolean);
		};

		const getMultiValues = () => {
			return Array.from(dropdown.querySelectorAll('[data-option-checkbox]:checked'))
				.map((checkbox) => checkbox.value)
				.filter(Boolean);
		};

		const applyMultiSelection = () => {
			const values = getMultiValues();
			const labels = getMultiLabels();
			hiddenInput.value = values.join(',');
			if (labels.length === 0) {
				valueEl.textContent = placeholder;
				valueEl.classList.add('is-placeholder');
			} else {
				valueEl.textContent = labels.join(', ');
				valueEl.classList.remove('is-placeholder');
			}
			close();
		};

		toggle.addEventListener('click', (event) => {
			event.preventDefault();
			event.stopPropagation();
			if (dropdown.classList.contains('is-open')) {
				close();
			} else {
				open();
			}
		});

		panel.addEventListener('mousedown', (event) => {
			event.stopPropagation();
		});

		panel.addEventListener('click', (event) => {
			event.stopPropagation();

			const multiRow = event.target.closest('.rrze-formular__dropdown-option--multi');
			if (multiRow) {
				event.preventDefault();
				const checkbox = multiRow.querySelector('[data-option-checkbox]');
				if (checkbox) {
					checkbox.checked = !checkbox.checked;
					multiRow.classList.toggle('is-checked', checkbox.checked);
				}
				return;
			}

			const confirmButton = event.target.closest('.rrze-formular__dropdown-confirm');
			if (confirmButton) {
				event.preventDefault();
				applyMultiSelection();
			}
		});

		if (!isMultiple) {
			dropdown.querySelectorAll('.rrze-formular__dropdown-option[data-value]').forEach((option) => {
				option.addEventListener('click', (event) => {
					event.preventDefault();
					event.stopPropagation();
					const label = option.querySelector('.rrze-formular__dropdown-option-label')?.textContent?.trim()
						|| option.textContent.trim();
					setSingleValue(option.dataset.value, label);
					close();
				});
			});
		}

		const onDocumentClick = (event) => {
			if (!dropdown.contains(event.target)) {
				close();
			}
		};

		const onDocumentKeydown = (event) => {
			if (event.key === 'Escape' && dropdown.classList.contains('is-open')) {
				close();
			}
		};

		document.addEventListener('click', onDocumentClick);
		document.addEventListener('keydown', onDocumentKeydown);

		dropdown._rrzeReset = () => {
			hiddenInput.value = '';
			valueEl.textContent = placeholder;
			valueEl.classList.add('is-placeholder');
			dropdown.querySelectorAll('[data-option-checkbox]').forEach((checkbox) => {
				checkbox.checked = false;
				checkbox.closest('.rrze-formular__dropdown-option--multi')?.classList.remove('is-checked');
			});
			dropdown.querySelectorAll('.rrze-formular__dropdown-option[data-value]').forEach((option) => {
				option.classList.remove('is-selected');
				option.setAttribute('aria-selected', 'false');
			});
			close();
		};

		dropdown._rrzeDestroy = () => {
			document.removeEventListener('click', onDocumentClick);
			document.removeEventListener('keydown', onDocumentKeydown);
			delete dropdown.dataset.initialized;
		};
	});
}

export function resetDropdowns(root) {
	root.querySelectorAll('.rrze-formular__dropdown').forEach((dropdown) => {
		dropdown._rrzeReset?.();
	});
}

export function reinitDropdowns(root) {
	root.querySelectorAll('.rrze-formular__dropdown').forEach((dropdown) => {
		dropdown._rrzeDestroy?.();
	});
	initDropdowns(root);
}
