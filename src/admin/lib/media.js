/**
 * WordPress media library uploader wrapper.
 *
 * `pickImages({ multiple, title })` returns a Promise that resolves to an
 * array of attachment payloads (id, url, width, height, alt, srcset, sizes).
 */

export function pickImages({ multiple = true, title = 'Select banners' } = {}) {
	return new Promise((resolve, reject) => {
		const wpMedia = window.wp && window.wp.media;
		if (!wpMedia) {
			reject(new Error('WordPress media library is not available on this page.'));
			return;
		}

		const frame = wpMedia({
			title,
			button: { text: 'Use selected' },
			library: { type: 'image' },
			multiple,
		});

		frame.on('select', () => {
			const selection = frame.state().get('selection').toJSON();
			const items = selection.map((att) => {
				const sizesObj = att.sizes || {};
				const full = sizesObj.full || {
					url: att.url,
					width: att.width,
					height: att.height,
				};
				return {
					id: att.id,
					url: full.url || att.url,
					width: full.width || att.width || 0,
					height: full.height || att.height || 0,
					alt: att.alt || '',
				};
			});
			resolve(items);
		});

		frame.on('close', () => {
			// noop; resolved by 'select' if user picked.
		});

		frame.open();
	});
}
