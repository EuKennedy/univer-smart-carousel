/**
 * Right-pane editor: campaign metadata + settings + banners + shortcode panel.
 */

import { useState, useEffect, useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	Button,
	Input,
	Select,
	Switch,
	Card,
	SectionHeader,
	Badge,
	Modal,
	toast,
} from './ui';
import BannerEditor from './BannerEditor';
import ShortcodePanel from './ShortcodePanel';
import { Campaigns as API } from '../lib/api';
import { copyToClipboard, slugify, defaultSettings } from '../lib/utils';

const STATUS_OPTIONS = [
	{ value: 'draft', label: __('Draft', 'univer-smart-carousel') },
	{ value: 'active', label: __('Active', 'univer-smart-carousel') },
	{ value: 'paused', label: __('Paused', 'univer-smart-carousel') },
];

const TRANSITIONS = [
	{ value: 'slide', label: __('Slide', 'univer-smart-carousel') },
	{ value: 'fade', label: __('Fade', 'univer-smart-carousel') },
];

const NAVIGATION_OPTIONS = [
	{ value: 'none', label: __('None', 'univer-smart-carousel') },
	{ value: 'dots', label: __('Dots', 'univer-smart-carousel') },
	{ value: 'arrows', label: __('Arrows', 'univer-smart-carousel') },
];

const RATIOS = [
	{ value: 'auto', label: __('Auto — match image', 'univer-smart-carousel') },
	{ value: '21/9', label: '21 / 9 — cinematic wide' },
	{ value: '16/9', label: '16 / 9 — widescreen' },
	{ value: '4/3', label: '4 / 3 — classic' },
	{ value: '1/1', label: '1 / 1 — square' },
	{ value: '4/5', label: '4 / 5 — vertical (mobile)' },
	{ value: '9/16', label: '9 / 16 — story' },
];

export default function CampaignEditor({ campaign, onChange, onSave, onDelete, saving }) {
	const [tab, setTab] = useState('desktop');
	const [confirmDelete, setConfirmDelete] = useState(false);
	const [autoSlug, setAutoSlug] = useState(!campaign?.id);

	const settings = useMemo(
		() => ({ ...defaultSettings(), ...(campaign?.settings || {}) }),
		[campaign?.settings]
	);

	useEffect(() => {
		setTab('desktop');
	}, [campaign?.id]);

	if (!campaign) return null;

	const setField = (patch) => onChange({ ...campaign, ...patch });
	const setSetting = (patch) =>
		setField({ settings: { ...settings, ...patch } });

	const handleNameChange = (e) => {
		const name = e.target.value;
		const next = { name };
		if (autoSlug) next.slug = slugify(name);
		setField(next);
	};

	const handleSlugChange = (e) => {
		setAutoSlug(false);
		setField({ slug: slugify(e.target.value) });
	};

	const handleBannersChange = (device) => (banners) => {
		setField({
			banners: { ...campaign.banners, [device]: banners },
		});
	};

	const handleSave = async () => {
		try {
			await onSave();
			toast(__('Campaign saved.', 'univer-smart-carousel'), 'success');
		} catch (err) {
			toast(err?.message || __('Save failed.', 'univer-smart-carousel'), 'error');
		}
	};

	const handleDelete = async () => {
		try {
			await onDelete();
			setConfirmDelete(false);
			toast(__('Campaign deleted.', 'univer-smart-carousel'), 'success');
		} catch (err) {
			toast(err?.message || __('Delete failed.', 'univer-smart-carousel'), 'error');
		}
	};

	const desktopBanners = campaign.banners?.desktop || [];
	const mobileBanners = campaign.banners?.mobile || [];

	const spvOptions = (window.USC_CFG?.spvOptions || [1, 1.5, 2, 2.5, 3, 3.5, 4, 4.5, 5]).map(
		(v) => ({ value: String(v), label: String(v) })
	);

	return (
		<main className="usc-editor">
			<div className="usc-editor__topbar">
				<div className="usc-editor__title">
					<input
						type="text"
						className="usc-h1-input"
						placeholder={__('Untitled campaign', 'univer-smart-carousel')}
						value={campaign.name || ''}
						onChange={handleNameChange}
					/>
					<div className="usc-editor__meta">
						<Badge tone={campaign.status === 'active' ? 'success' : campaign.status === 'paused' ? 'warning' : 'neutral'}>
							{campaign.status}
						</Badge>
						<span className="usc-muted">{campaign.slug ? `/${campaign.slug}` : ''}</span>
					</div>
				</div>

				<div className="usc-editor__actions">
					{campaign.id && (
						<Button variant="ghost" onClick={() => setConfirmDelete(true)}>
							{__('Delete', 'univer-smart-carousel')}
						</Button>
					)}
					<Button variant="primary" onClick={handleSave} loading={saving}>
						{campaign.id ? __('Save changes', 'univer-smart-carousel') : __('Create campaign', 'univer-smart-carousel')}
					</Button>
				</div>
			</div>

			<div className="usc-editor__grid">
				<div className="usc-editor__col-main">
					<Card>
						<div className="usc-tabs" role="tablist">
							<button
								type="button"
								role="tab"
								aria-selected={tab === 'desktop'}
								className={`usc-tab ${tab === 'desktop' ? 'is-active' : ''}`}
								onClick={() => setTab('desktop')}
							>
								<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true">
									<path fill="currentColor" d="M21 16H3V4h18zm0-14H3a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h7v2H8v2h8v-2h-2v-2h7a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2" />
								</svg>
								{__('Desktop', 'univer-smart-carousel')}
								<span className="usc-tab__count">{desktopBanners.length}</span>
							</button>
							<button
								type="button"
								role="tab"
								aria-selected={tab === 'mobile'}
								className={`usc-tab ${tab === 'mobile' ? 'is-active' : ''}`}
								onClick={() => setTab('mobile')}
							>
								<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true">
									<path fill="currentColor" d="M17 1.01 7 1c-1.1 0-2 .9-2 2v18c0 1.1.9 2 2 2h10c1.1 0 2-.9 2-2V3c0-1.1-.9-1.99-2-1.99M17 19H7V5h10z" />
								</svg>
								{__('Mobile', 'univer-smart-carousel')}
								<span className="usc-tab__count">{mobileBanners.length}</span>
							</button>
						</div>

						{tab === 'desktop' ? (
							<BannerEditor
								device="desktop"
								banners={desktopBanners}
								onChange={handleBannersChange('desktop')}
							/>
						) : (
							<BannerEditor
								device="mobile"
								banners={mobileBanners}
								onChange={handleBannersChange('mobile')}
							/>
						)}
					</Card>
				</div>

				<aside className="usc-editor__col-side">
					{campaign.id && (
						<ShortcodePanel
							desktop={`[carouseldesktop_${campaign.slug}]`}
							mobile={`[carouselmobile_${campaign.slug}]`}
							onCopy={(text) => {
								copyToClipboard(text).then(() =>
									toast(__('Copied!', 'univer-smart-carousel'), 'success')
								);
							}}
						/>
					)}

					<Card>
						<SectionHeader title={__('Campaign', 'univer-smart-carousel')} />
						<div className="usc-stack">
							<Select
								label={__('Status', 'univer-smart-carousel')}
								options={STATUS_OPTIONS}
								value={campaign.status || 'draft'}
								onChange={(v) => setField({ status: v })}
							/>
							<Input
								label={__('Slug', 'univer-smart-carousel')}
								hint={__('Used in the shortcode. Letters, numbers, dashes only.', 'univer-smart-carousel')}
								value={campaign.slug || ''}
								onChange={handleSlugChange}
							/>
							<div className="usc-row-2">
								<Input
									label={__('Start date', 'univer-smart-carousel')}
									type="datetime-local"
									value={toLocalInput(campaign.start_date)}
									onChange={(e) =>
										setField({ start_date: fromLocalInput(e.target.value) })
									}
								/>
								<Input
									label={__('End date', 'univer-smart-carousel')}
									type="datetime-local"
									value={toLocalInput(campaign.end_date)}
									onChange={(e) =>
										setField({ end_date: fromLocalInput(e.target.value) })
									}
								/>
							</div>
						</div>
					</Card>

					<Card>
						<SectionHeader title={__('Layout', 'univer-smart-carousel')} />
						<div className="usc-stack">
							<div className="usc-row-2">
								<Select
									label={__('Slides per view (desktop)', 'univer-smart-carousel')}
									options={spvOptions}
									value={String(settings.slides_per_view_desktop)}
									onChange={(v) =>
										setSetting({ slides_per_view_desktop: parseFloat(v) })
									}
								/>
								<Select
									label={__('Slides per view (mobile)', 'univer-smart-carousel')}
									options={spvOptions}
									value={String(settings.slides_per_view_mobile)}
									onChange={(v) =>
										setSetting({ slides_per_view_mobile: parseFloat(v) })
									}
								/>
							</div>
							<div className="usc-row-2">
								<Select
									label={__('Aspect ratio (desktop)', 'univer-smart-carousel')}
									options={RATIOS}
									value={settings.aspect_ratio_desktop}
									onChange={(v) => setSetting({ aspect_ratio_desktop: v })}
								/>
								<Select
									label={__('Aspect ratio (mobile)', 'univer-smart-carousel')}
									options={RATIOS}
									value={settings.aspect_ratio_mobile}
									onChange={(v) => setSetting({ aspect_ratio_mobile: v })}
								/>
							</div>
							<div className="usc-row-2">
								<Input
									label={__('Gap (px)', 'univer-smart-carousel')}
									type="number"
									min={0}
									max={80}
									value={settings.gap}
									onChange={(e) => setSetting({ gap: parseInt(e.target.value, 10) || 0 })}
								/>
								<Input
									label={__('Border radius (px)', 'univer-smart-carousel')}
									type="number"
									min={0}
									max={64}
									value={settings.border_radius}
									onChange={(e) =>
										setSetting({ border_radius: parseInt(e.target.value, 10) || 0 })
									}
								/>
							</div>
						</div>
					</Card>

					<Card>
						<SectionHeader title={__('Behavior', 'univer-smart-carousel')} />
						<div className="usc-stack">
							<Switch
								checked={settings.autoplay}
								onChange={(v) => setSetting({ autoplay: v })}
								label={__('Autoplay', 'univer-smart-carousel')}
								hint={__('Disabled automatically for users with reduced-motion preference.', 'univer-smart-carousel')}
							/>
							{settings.autoplay && (
								<Input
									label={__('Autoplay delay (ms)', 'univer-smart-carousel')}
									type="number"
									min={1000}
									max={30000}
									step={500}
									value={settings.autoplay_delay}
									onChange={(e) =>
										setSetting({ autoplay_delay: parseInt(e.target.value, 10) || 5000 })
									}
								/>
							)}
							<Switch
								checked={settings.loop}
								onChange={(v) => setSetting({ loop: v })}
								label={__('Loop', 'univer-smart-carousel')}
							/>
							<Select
								label={__('Navigation', 'univer-smart-carousel')}
								hint={__('How visitors move between slides.', 'univer-smart-carousel')}
								options={NAVIGATION_OPTIONS}
								value={settings.navigation || 'arrows'}
								onChange={(v) => setSetting({ navigation: v })}
							/>
							<Switch
								checked={settings.show_progress}
								onChange={(v) => setSetting({ show_progress: v })}
								label={__('Show progress bar', 'univer-smart-carousel')}
							/>
							<Switch
								checked={settings.pause_on_hover}
								onChange={(v) => setSetting({ pause_on_hover: v })}
								label={__('Pause on hover', 'univer-smart-carousel')}
							/>
						</div>
					</Card>
				</aside>
			</div>

			<Modal
				open={confirmDelete}
				onClose={() => setConfirmDelete(false)}
				title={__('Delete this campaign?', 'univer-smart-carousel')}
				footer={
					<>
						<Button variant="ghost" onClick={() => setConfirmDelete(false)}>
							{__('Cancel', 'univer-smart-carousel')}
						</Button>
						<Button variant="danger" onClick={handleDelete}>
							{__('Delete permanently', 'univer-smart-carousel')}
						</Button>
					</>
				}
			>
				<p>
					{__(
						'This will remove the campaign and all of its banners. The shortcode will stop rendering anything.',
						'univer-smart-carousel'
					)}
				</p>
			</Modal>
		</main>
	);
}

function toLocalInput(value) {
	if (!value) return '';
	// API returns "YYYY-MM-DD HH:MM:SS" in UTC. Convert to local for the input.
	const d = new Date(value.replace(' ', 'T') + 'Z');
	if (Number.isNaN(d.getTime())) return '';
	const pad = (n) => String(n).padStart(2, '0');
	return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function fromLocalInput(value) {
	if (!value) return null;
	const d = new Date(value);
	if (Number.isNaN(d.getTime())) return null;
	const pad = (n) => String(n).padStart(2, '0');
	// Send back as UTC string the API can re-parse.
	return `${d.getUTCFullYear()}-${pad(d.getUTCMonth() + 1)}-${pad(d.getUTCDate())} ${pad(d.getUTCHours())}:${pad(d.getUTCMinutes())}:00`;
}
