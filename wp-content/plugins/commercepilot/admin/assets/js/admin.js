(function () {
	'use strict';

	var select = document.getElementById('cp-avatar-select');
	var remove = document.getElementById('cp-avatar-remove');
	var idField = document.getElementById('cp-avatar-id');
	var urlField = document.getElementById('cp-avatar');
	var preview = document.getElementById('cp-avatar-preview');
	var i18n = (window.commercePilotAdmin && window.commercePilotAdmin.i18n) || {};

	if (!select || !remove || !idField || !urlField || !preview) {
		return;
	}

	var frame = null;

	function paint(url) {
		preview.innerHTML = '';
		if (url) {
			var image = document.createElement('img');
			image.src = url;
			image.alt = '';
			preview.appendChild(image);
		} else {
			var empty = document.createElement('span');
			empty.className = 'cp-avatar-empty';
			empty.textContent = i18n.noImage || 'No image';
			preview.appendChild(empty);
		}
		remove.disabled = !url;
	}

	select.addEventListener('click', function () {
		if (!window.wp || !window.wp.media) {
			window.console && window.console.error('CommercePilot: the WordPress media library is not loaded on this screen.');
			urlField.focus();
			return;
		}

		if (!frame) {
			frame = window.wp.media({
				title: i18n.selectImage || 'Select profile picture',
				button: { text: i18n.useImage || 'Use this image' },
				library: { type: 'image' },
				multiple: false
			});

			frame.on('select', function () {
				var attachment = frame.state().get('selection').first().toJSON();
				var sizes = attachment.sizes || {};
				var url = (sizes.thumbnail && sizes.thumbnail.url) || attachment.url;
				idField.value = attachment.id;
				urlField.value = url;
				paint(url);
			});
		}

		frame.open();
	});

	remove.addEventListener('click', function () {
		idField.value = '';
		urlField.value = '';
		paint('');
	});

	// A manually typed URL is no longer backed by a media library attachment.
	urlField.addEventListener('input', function () {
		idField.value = '';
		paint(urlField.value.trim());
	});
})();
