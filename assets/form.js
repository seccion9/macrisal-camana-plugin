(function () {
	'use strict';

	var start = Date.now();

	function setError(form, name, message) {
		var input = form.querySelector('[name="' + name + '"]');
		if (!input) {
			return;
		}
		var field = input.closest('.mcamp-field');
		var box = field && field.querySelector('.mcamp-error');
		if (field) {
			field.classList.toggle('has-error', !!message);
		}
		if (box) {
			box.textContent = message || '';
		}
		input.setAttribute('aria-invalid', message ? 'true' : 'false');
	}

	function clearErrors(form) {
		form.querySelectorAll('.mcamp-field').forEach(function (field) {
			field.classList.remove('has-error');
			var box = field.querySelector('.mcamp-error');
			if (box) {
				box.textContent = '';
			}
		});
	}

	function init(form) {
		var button = form.querySelector('.mcamp-submit');
		var response = form.querySelector('.mcamp-response');

		form.addEventListener('submit', function (e) {
			e.preventDefault();
			if (form.classList.contains('is-sending')) {
				return;
			}

			clearErrors(form);
			form.classList.remove('is-sent', 'is-invalid', 'is-failed');
			response.textContent = '';
			form.querySelector('[name="mcamp_elapsed"]').value = String(Date.now() - start);
			form.querySelector('[name="mcamp_page"]').value = window.location.href.split('#')[0];

			form.classList.add('is-sending');
			button.disabled = true;

			fetch(window.mcampForm.ajaxUrl, {
				method: 'POST',
				body: new FormData(form),
				credentials: 'same-origin'
			})
				.then(function (res) {
					return res.json();
				})
				.then(function (json) {
					response.textContent = json.message || '';
					// El token de reCAPTCHA solo vale una vez: se pide de nuevo salvo
					// cuando el envío se paró antes de comprobarlo (errores de campos).
					var captchaUsed = json.success || !json.errors || !!json.errors.recaptcha || !Object.keys(json.errors).length;
					if (captchaUsed && window.grecaptcha && form.querySelector('.g-recaptcha iframe')) {
						window.grecaptcha.reset();
					}
					if (json.success) {
						form.classList.add('is-sent');
						form.reset();
						return;
					}
					form.classList.add(json.errors && Object.keys(json.errors).length ? 'is-invalid' : 'is-failed');
					var first = null;
					Object.keys(json.errors || {}).forEach(function (name) {
						setError(form, name, json.errors[name]);
						first = first || form.querySelector('[name="' + name + '"]');
					});
					if (first) {
						first.focus();
					}
				})
				.catch(function () {
					form.classList.add('is-failed');
					response.textContent = 'Hubo un error al enviar el formulario. Por favor inténtalo de nuevo más tarde.';
				})
				.then(function () {
					form.classList.remove('is-sending');
					button.disabled = false;
				});
		});
	}

	document.querySelectorAll('.mcamp-form').forEach(init);
})();
