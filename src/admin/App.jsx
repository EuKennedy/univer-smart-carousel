/**
 * Univer Smart Carousel — Admin App root.
 * Two-pane layout: sidebar list + editor.
 */

import { useEffect, useState, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import CampaignList from './components/CampaignList';
import CampaignEditor from './components/CampaignEditor';
import { ToastHost, EmptyState, Button, Spinner, toast } from './components/ui';
import { Campaigns as API } from './lib/api';
import { emptyCampaign } from './lib/utils';

export default function App() {
	const [campaigns, setCampaigns] = useState([]);
	const [loading, setLoading] = useState(true);
	const [search, setSearch] = useState('');
	const [activeId, setActiveId] = useState(null);
	const [draft, setDraft] = useState(null);
	const [saving, setSaving] = useState(false);

	const refresh = useCallback(
		async (q = '') => {
			setLoading(true);
			try {
				const list = await API.list({ search: q });
				setCampaigns(list);
			} catch (err) {
				toast(err?.message || __('Failed to load campaigns.', 'univer-smart-carousel'), 'error');
			} finally {
				setLoading(false);
			}
		},
		[]
	);

	useEffect(() => {
		refresh();
	}, [refresh]);

	useEffect(() => {
		if (!activeId) {
			return;
		}
		(async () => {
			try {
				const c = await API.get(activeId);
				setDraft(reshape(c));
			} catch (err) {
				toast(err?.message || __('Failed to load campaign.', 'univer-smart-carousel'), 'error');
			}
		})();
	}, [activeId]);

	const onSearch = (q) => {
		setSearch(q);
		refresh(q);
	};

	const onCreate = () => {
		setActiveId(null);
		setDraft(emptyCampaign());
	};

	const onSelect = (id) => {
		setActiveId(id);
	};

	const onSave = async () => {
		setSaving(true);
		try {
			const payload = serialize(draft);
			let saved;
			if (draft.id) {
				saved = await API.update(draft.id, payload);
			} else {
				saved = await API.create(payload);
			}
			const reshaped = reshape(saved);
			setDraft(reshaped);
			setActiveId(reshaped.id);
			await refresh(search);
		} finally {
			setSaving(false);
		}
	};

	const onDelete = async () => {
		if (!draft?.id) return;
		await API.remove(draft.id);
		setDraft(null);
		setActiveId(null);
		await refresh(search);
	};

	return (
		<div className="usc-app">
			<header className="usc-appbar">
				<div className="usc-appbar__brand">
					<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true">
						<rect x="3" y="6" width="18" height="12" rx="2" fill="none" stroke="currentColor" strokeWidth="1.8" />
						<path d="M7 10v4M17 10v4" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" />
					</svg>
					<span className="usc-appbar__title">Univer Smart Carousel</span>
					<span className="usc-appbar__by">by Univerbeauty</span>
				</div>
				<div className="usc-appbar__actions">
					<a
						className="usc-link-soft"
						href="https://github.com/EuKennedy/univer-smart-carousel"
						target="_blank"
						rel="noopener noreferrer"
					>
						{__('GitHub', 'univer-smart-carousel')}
					</a>
				</div>
			</header>

			<div className="usc-app__body">
				<CampaignList
					campaigns={campaigns}
					loading={loading}
					activeId={activeId}
					hasDraft={!!draft}
					onSelect={onSelect}
					onCreate={onCreate}
					onSearch={onSearch}
					search={search}
				/>

				<div className="usc-app__main">
					{!draft && !loading && campaigns.length > 0 && (
						<EmptyState
							icon={
								<svg viewBox="0 0 24 24" width="48" height="48" aria-hidden="true">
									<rect x="3" y="6" width="18" height="12" rx="2" fill="none" stroke="currentColor" strokeWidth="1.4" />
									<path d="M7 10v4M17 10v4" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" />
								</svg>
							}
							title={__('Select a campaign', 'univer-smart-carousel')}
							description={__(
								'Pick a campaign on the left, or create a new one.',
								'univer-smart-carousel'
							)}
							action={
								<Button onClick={onCreate}>{__('New campaign', 'univer-smart-carousel')}</Button>
							}
						/>
					)}

					{!draft && loading && (
						<div className="usc-app__loading">
							<Spinner />
							<span>{__('Loading…', 'univer-smart-carousel')}</span>
						</div>
					)}

					{draft && (
						<CampaignEditor
							campaign={draft}
							onChange={setDraft}
							onSave={onSave}
							onDelete={onDelete}
							saving={saving}
						/>
					)}
				</div>
			</div>

			<ToastHost />
		</div>
	);
}

/* ---------------- helpers ---------------- */

// Take a campaign as-returned from the API and shape it for the editor.
function reshape(c) {
	const desktop = (c.banners || []).filter((b) => b.device === 'desktop');
	const mobile = (c.banners || []).filter((b) => b.device === 'mobile');
	return {
		...c,
		banners: { desktop, mobile },
	};
}

// Take editor state and serialize it for the API.
function serialize(c) {
	return {
		name: c.name,
		slug: c.slug,
		status: c.status,
		settings: c.settings,
		start_date: c.start_date,
		end_date: c.end_date,
		banners: {
			desktop: (c.banners?.desktop || []).map(stripBanner),
			mobile: (c.banners?.mobile || []).map(stripBanner),
		},
	};
}

function stripBanner(b) {
	return {
		image_id: b.image_id,
		link_url: b.link_url || '',
		link_target: b.link_target || '_self',
		link_rel: b.link_rel || '',
		alt_text: b.alt_text || '',
	};
}
