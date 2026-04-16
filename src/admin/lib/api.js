/**
 * Thin REST client for the Univer Smart Carousel admin app.
 * Uses @wordpress/api-fetch so the nonce + cookie auth are handled for us.
 */

import apiFetch from '@wordpress/api-fetch';

const cfg = window.USC_CFG || {};

if (cfg.nonce) {
	apiFetch.use(apiFetch.createNonceMiddleware(cfg.nonce));
}
if (cfg.restUrl) {
	apiFetch.use(apiFetch.createRootURLMiddleware(cfg.restUrl));
}

const request = (path, options = {}) =>
	apiFetch({
		path,
		...options,
	});

export const Campaigns = {
	list: (params = {}) => {
		const qs = new URLSearchParams();
		if (params.search) qs.set('search', params.search);
		if (params.status) qs.set('status', params.status);
		const suffix = qs.toString() ? `?${qs.toString()}` : '';
		return request(`campaigns${suffix}`);
	},
	get: (id) => request(`campaigns/${id}`),
	create: (data) => request('campaigns', { method: 'POST', data }),
	update: (id, data) => request(`campaigns/${id}`, { method: 'PUT', data }),
	remove: (id) => request(`campaigns/${id}`, { method: 'DELETE' }),
};
