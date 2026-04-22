/**
 * Thin REST client for the Univer Smart Carousel admin app.
 *
 * apiFetch already knows the WP REST root and the nonce — both wired up
 * by core when the script is enqueued. We just pass full namespaced paths.
 *
 * Earlier versions tried to swap in a custom root URL middleware. Don't.
 * Core's middleware wins and the path ends up resolved against the wrong
 * root, hitting /wp-json/{path} instead of /wp-json/usc/v1/{path}.
 */

import apiFetch from '@wordpress/api-fetch';

const cfg = window.USC_CFG || {};

// Belt-and-suspenders: if WP didn't already register the nonce middleware
// (older core, custom auth setups), do it ourselves. Idempotent enough.
if (cfg.nonce) {
	apiFetch.use(apiFetch.createNonceMiddleware(cfg.nonce));
}

const NS = '/usc/v1';

const request = (path, options = {}) =>
	apiFetch({
		path: `${NS}${path}`,
		...options,
	});

export const Campaigns = {
	list: (params = {}) => {
		const qs = new URLSearchParams();
		if (params.search) qs.set('search', params.search);
		if (params.status) qs.set('status', params.status);
		const suffix = qs.toString() ? `?${qs.toString()}` : '';
		return request(`/campaigns${suffix}`);
	},
	get: (id) => request(`/campaigns/${id}`),
	create: (data) => request('/campaigns', { method: 'POST', data }),
	update: (id, data) => request(`/campaigns/${id}`, { method: 'PUT', data }),
	remove: (id) => request(`/campaigns/${id}`, { method: 'DELETE' }),
};

export const Settings = {
	get: () => request('/settings'),
	update: (data) => request('/settings', { method: 'PUT', data }),
};

export const ApiKeys = {
	list: () => request('/api-keys'),
	create: (data) => request('/api-keys', { method: 'POST', data }),
	revoke: (id) => request(`/api-keys/${id}/revoke`, { method: 'POST' }),
	remove: (id) => request(`/api-keys/${id}`, { method: 'DELETE' }),
};

export const Groups = {
	create: (campaignId, data) =>
		request(`/campaigns/${campaignId}/groups`, { method: 'POST', data }),
	update: (gid, data) => request(`/groups/${gid}`, { method: 'PUT', data }),
	remove: (gid) => request(`/groups/${gid}`, { method: 'DELETE' }),
	addBanner: (gid, data) => request(`/groups/${gid}/banners`, { method: 'POST', data }),
	reorder: (campaignId, data) =>
		request(`/campaigns/${campaignId}/groups/reorder`, { method: 'POST', data }),
	reorderBanners: (gid, data) =>
		request(`/groups/${gid}/banners/reorder`, { method: 'POST', data }),
};

export const Banners = {
	update: (bid, data) => request(`/banners/${bid}`, { method: 'PUT', data }),
	remove: (bid) => request(`/banners/${bid}`, { method: 'DELETE' }),
	duplicate: (bid) => request(`/banners/${bid}/duplicate`, { method: 'POST' }),
};

export const Badges = {
	list: (taxonomy = 'pa_badge-ofertas') =>
		request(`/badges?taxonomy=${encodeURIComponent(taxonomy)}`),
	update: (termId, data) => request(`/badges/${termId}`, { method: 'PUT', data }),
};
