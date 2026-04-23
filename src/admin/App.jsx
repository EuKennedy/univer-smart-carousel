/**
 * Univer Smart Carousel — Admin App root.
 *
 * Top-level tab switcher: Campaigns (list + editor) and Settings.
 */

import { useEffect, useState, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import CampaignList from './components/CampaignList';
import CampaignEditor from './components/CampaignEditor';
import SettingsPage from './components/SettingsPage';
import { ToastHost, EmptyState, Button, Spinner, toast } from './components/ui';
import { Campaigns as API } from './lib/api';
import { emptyCampaign, classNames } from './lib/utils';

const TAB_CAMPAIGNS = 'campaigns';
const TAB_SETTINGS = 'settings';

export default function App() {
	const [tab, setTab] = useState(TAB_CAMPAIGNS);

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

				<nav className="usc-appbar__tabs" role="tablist">
					<button
						type="button"
						role="tab"
						aria-selected={tab === TAB_CAMPAIGNS}
						className={classNames('usc-appbar__tab', tab === TAB_CAMPAIGNS && 'is-active')}
						onClick={() => setTab(TAB_CAMPAIGNS)}
					>
						{__('Campaigns', 'univer-smart-carousel')}
					</button>
					<button
						type="button"
						role="tab"
						aria-selected={tab === TAB_SETTINGS}
						className={classNames('usc-appbar__tab', tab === TAB_SETTINGS && 'is-active')}
						onClick={() => setTab(TAB_SETTINGS)}
					>
						{__('Settings', 'univer-smart-carousel')}
					</button>
				</nav>

				<div className="usc-appbar__actions" />

			</header>

			{tab === TAB_CAMPAIGNS && (
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
			)}

			{tab === TAB_SETTINGS && <SettingsPage />}

			<ToastHost />
		</div>
	);
}

/* ---------------- helpers ---------------- */

// Take a campaign as-returned from the API and shape it for the editor.
// Banners are split by device for the tab UI; groups + the flat banner list
// stay alongside in case GroupedBannerEditor needs them (it does).
function reshape(c) {
	const desktop = (c.banners || []).filter((b) => b.device === 'desktop');
	const mobile = (c.banners || []).filter((b) => b.device === 'mobile');
	return {
		...c,
		banners: { desktop, mobile },
		groups: c.groups || [],
	};
}

// Save only the carousel-level metadata. Banners and groups have their
// own granular endpoints now and are mutated eagerly by
// GroupedBannerEditor — including them here would let a stale local
// state stomp the server.
function serialize(c) {
	return {
		name: c.name,
		slug: c.slug,
		status: c.status,
		settings: c.settings,
		start_date: c.start_date,
		end_date: c.end_date,
	};
}
