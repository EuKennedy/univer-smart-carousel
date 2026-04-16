/**
 * Small utilities shared across the admin app.
 */

export function classNames(...parts) {
	return parts.filter(Boolean).join(' ');
}

export function slugify(value) {
	return String(value || '')
		.toLowerCase()
		.normalize('NFD')
		.replace(/[\u0300-\u036f]/g, '')
		.replace(/[^a-z0-9]+/g, '-')
		.replace(/(^-|-$)/g, '')
		.slice(0, 60);
}

export function copyToClipboard(text) {
	if (navigator.clipboard && navigator.clipboard.writeText) {
		return navigator.clipboard.writeText(text);
	}
	return new Promise((resolve, reject) => {
		try {
			const ta = document.createElement('textarea');
			ta.value = text;
			ta.style.position = 'fixed';
			ta.style.opacity = '0';
			document.body.appendChild(ta);
			ta.select();
			document.execCommand('copy');
			document.body.removeChild(ta);
			resolve();
		} catch (err) {
			reject(err);
		}
	});
}

export function formatDate(value) {
	if (!value) return '—';
	const date = new Date(value.replace(' ', 'T') + 'Z');
	if (Number.isNaN(date.getTime())) return value;
	return date.toLocaleString();
}

export function defaultSettings() {
	return {
		slides_per_view_desktop: 1,
		slides_per_view_mobile: 1,
		gap: 16,
		autoplay: true,
		autoplay_delay: 5000,
		loop: true,
		navigation: 'arrows',
		show_progress: false,
		pause_on_hover: true,
		border_radius: 0,
		transition: 'slide',
		aspect_ratio_desktop: 'auto',
		aspect_ratio_mobile: 'auto',
		image_optimization: true,
		image_quality: 82,
		image_webp: true,
		image_max_width_desktop: 1920,
		image_max_width_mobile: 750,
	};
}

export function emptyCampaign() {
	return {
		name: '',
		slug: '',
		status: 'draft',
		settings: defaultSettings(),
		start_date: null,
		end_date: null,
		banners: { desktop: [], mobile: [] },
	};
}
