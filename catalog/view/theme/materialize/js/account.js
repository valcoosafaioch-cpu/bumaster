(function () {
	function initAccountTelephoneGroup(config) {
		if (!config || !config.container) {
			return;
		}

		var container = document.querySelector(config.container);

		if (!container) {
			return;
		}

		var countrySelect = container.querySelector(config.countrySelect);
		var codeInput = container.querySelector(config.codeInput);
		var numberInput = container.querySelector(config.numberInput);
		var hiddenInput = container.querySelector(config.hiddenInput);
		var hintNode = container.querySelector(config.hintNode);
		var form = document.querySelector(config.form);
		var digitsTemplateNode = document.querySelector(config.digitsTemplateNode);

		if (!countrySelect || !codeInput || !numberInput || !hiddenInput) {
			return;
		}

		function getSelectedOption() {
			return countrySelect.options[countrySelect.selectedIndex] || null;
		}

		function normalizeTelephone() {
			var option = getSelectedOption();
			var digits = numberInput.value.replace(/\D/g, '');
			var phoneCode = option ? (option.getAttribute('data-phone-code') || '') : '';
			var phoneDigits = option ? parseInt(option.getAttribute('data-phone-digits') || '0', 10) : 0;

			if (phoneDigits > 0) {
				digits = digits.substring(0, phoneDigits);
			}

			numberInput.value = digits;
			codeInput.textContent = phoneCode;

			if (digits.length > 0 && phoneCode) {
				hiddenInput.value = phoneCode + digits;
			} else {
				hiddenInput.value = '';
			}

			if (hintNode) {
				if (digitsTemplateNode && digitsTemplateNode.value && phoneDigits > 0) {
					hintNode.textContent = digitsTemplateNode.value.replace('%s', phoneDigits);
				} else {
					hintNode.textContent = '';
				}
			}
		}

		countrySelect.addEventListener('change', normalizeTelephone);
		numberInput.addEventListener('input', normalizeTelephone);

		if (form) {
			form.addEventListener('submit', normalizeTelephone);
		}

		normalizeTelephone();
	}

	document.addEventListener('DOMContentLoaded', function () {
		initAccountTelephoneGroup({
			container: '#account-register',
			form: '#account-register form',
			countrySelect: '#input-country-id',
			codeInput: '#input-telephone-code',
			numberInput: '#input-telephone-number',
			hiddenInput: '#input-telephone-hidden',
			hintNode: '#input-telephone-hint',
			digitsTemplateNode: '#register-phone-text-digits'
		});

        initAccountTelephoneGroup({
			container: '#account-edit',
			form: '#account-edit form',
			countrySelect: '#input-country-id',
			codeInput: '#input-telephone-code',
			numberInput: '#input-telephone-number',
			hiddenInput: '#input-telephone-hidden',
			hintNode: '#input-telephone-hint',
			digitsTemplateNode: '#edit-phone-text-digits'
		});
	});
})();