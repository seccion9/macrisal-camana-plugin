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

	// reCAPTCHA invisible: la API de Google se carga al primer contacto con el
	// formulario (o al enviarlo) para no ralentizar la página.
	var apiCallbacks = null;

	function loadRecaptcha(cb) {
		if (window.grecaptcha && window.grecaptcha.ready) {
			window.grecaptcha.ready(cb);
			return;
		}
		if (apiCallbacks) {
			apiCallbacks.push(cb);
			return;
		}
		apiCallbacks = [cb];
		window.mcampRecaptchaLoaded = function () {
			apiCallbacks.forEach(function (fn) {
				fn();
			});
			apiCallbacks = [];
		};
		var s = document.createElement('script');
		s.src = 'https://www.google.com/recaptcha/api.js?hl=es&render=explicit&onload=mcampRecaptchaLoaded';
		s.async = true;
		s.onerror = function () {
			apiCallbacks.forEach(function (fn) {
				fn(new Error('recaptcha'));
			});
			apiCallbacks = null;
		};
		document.head.appendChild(s);
	}

	function initInvisible(form, box, send, fail) {
		var widget = null;
		var pending = false;

		function render(err) {
			if (err || widget !== null) {
				return;
			}
			widget = window.grecaptcha.render(box, {
				sitekey: box.getAttribute('data-sitekey'),
				size: 'invisible',
				badge: 'bottomright',
				callback: function () {
					if (pending) {
						pending = false;
						send();
					}
				},
				'expired-callback': function () {
					window.grecaptcha.reset(widget);
				},
				'error-callback': function () {
					if (pending) {
						pending = false;
						fail('No se ha podido verificar el reCAPTCHA. Por favor, inténtalo de nuevo.');
					}
				}
			});
		}

		form.addEventListener('focusin', function () {
			loadRecaptcha(render);
		}, { once: true });

		return {
			// Pide el token a Google (si aún no hay uno válido) y luego envía.
			submit: function () {
				pending = true;
				loadRecaptcha(function (err) {
					if (err) {
						pending = false;
						fail('No se ha podido cargar el reCAPTCHA. Revisa tu conexión e inténtalo de nuevo.');
						return;
					}
					render();
					if (window.grecaptcha.getResponse(widget)) {
						pending = false;
						send();
					} else {
						window.grecaptcha.execute(widget);
					}
				});
			},
			reset: function () {
				if (widget !== null) {
					window.grecaptcha.reset(widget);
				}
			}
		};
	}

	function init(form) {
		var button = form.querySelector('.mcamp-submit');
		var response = form.querySelector('.mcamp-response');
		var box = form.querySelector('.mcamp-recaptcha');
		var invisible = box ? initInvisible(form, box, send, fail) : null;

		function fail(message) {
			form.classList.add('is-failed');
			response.textContent = message;
			done();
		}

		function done() {
			form.classList.remove('is-sending');
			button.disabled = false;
		}

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

			// Con el reCAPTCHA invisible el botón no se bloquea hasta tener el token:
			// si Google muestra un desafío y se cierra, se puede volver a pulsar.
			if (invisible) {
				invisible.submit();
			} else {
				send();
			}
		});

		function send() {
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
					if (captchaUsed && invisible) {
						invisible.reset();
					} else if (captchaUsed && window.grecaptcha && form.querySelector('.g-recaptcha iframe')) {
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
				.then(done);
		}
	}

	document.querySelectorAll('.mcamp-form').forEach(init);
})();
