jQuery(function ($) {
	var frame;
	var $id = $('#mcamp-pdf-id');
	var $current = $('#mcamp-pdf-current');
	var $remove = $('#mcamp-pdf-remove');

	$('#mcamp-pdf-select').on('click', function (e) {
		e.preventDefault();
		if (!frame) {
			frame = wp.media({
				title: 'Elegir el PDF de la campaña',
				button: { text: 'Usar este PDF' },
				library: { type: 'application/pdf' },
				multiple: false
			});
			frame.on('select', function () {
				var file = frame.state().get('selection').first().toJSON();
				if (file.mime !== 'application/pdf') {
					window.alert('El archivo debe ser un PDF.');
					return;
				}
				$id.val(file.id);
				$current.empty().append(
					$('<span class="dashicons dashicons-pdf"></span>'),
					' ',
					$('<a target="_blank" rel="noopener"></a>').attr('href', file.url).text(file.filename),
					' ',
					$('<em></em>').text('(guarda los cambios para aplicarlo)')
				);
				$remove.show();
			});
		}
		frame.open();
	});

	$remove.on('click', function (e) {
		e.preventDefault();
		$id.val('0');
		$current.html('<em>Ningún PDF seleccionado (guarda los cambios para aplicarlo).</em>');
		$remove.hide();
	});
});
