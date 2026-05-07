/**
 * Header Top tab — global vertical-swiper announcement strip.
 *
 * One settings card (enable, height, autoplay delay, transition speed,
 * background) plus a list of slides with drag-to-reorder, click-to-
 * replace thumbnails, per-slide URL/alt/target, duplicate, delete,
 * and active toggle.
 *
 * Mirrors the pattern used by MosaicsPage so the admin chrome feels
 * consistent: optimistic per-field saves, server-refetch after
 * structural mutations.
 */

import { useEffect, useState, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	Badge,
	Button,
	Card,
	IconButton,
	Input,
	Modal,
	SectionHeader,
	Select,
	Spinner,
	Switch,
	toast,
} from './ui';
import { HeaderTop as API } from '../lib/api';
import { pickImages } from '../lib/media';
import { classNames, copyToClipboard } from '../lib/utils';

const TARGET_OPTIONS = [
	{ value: '_self', label: __('Same tab', 'univer-smart-carousel') },
	{ value: '_blank', label: __('New tab', 'univer-smart-carousel') },
];

export default function HeaderTopPage() {
	const [state, setState] = useState(null);
	const [loading, setLoading] = useState(true);
	const [savingSettings, setSavingSettings] = useState(false);

	const refresh = useCallback(async () => {
		setLoading(true);
		try {
			const data = await API.get();
			setState(data);
		} catch (err) {
			toast(err?.message || __('Failed to load Header Top.', 'univer-smart-carousel'), 'error');
		} finally {
			setLoading(false);
		}
	}, []);

	useEffect(() => {
		refresh();
	}, [refresh]);

	if (loading || !state) {
		return (
			<div className="usc-app__loading">
				<Spinner />
				<span>{__('Loading…', 'univer-smart-carousel')}</span>
			</div>
		);
	}

	const settings = state.settings || {};
	const slides = state.slides || [];

	const setSetting = (patch) =>
		setState((prev) => ({ ...prev, settings: { ...prev.settings, ...patch } }));

	const handleSaveSettings = async () => {
		setSavingSettings(true);
		try {
			const saved = await API.updateSettings(settings);
			setState((prev) => ({ ...prev, settings: saved }));
			toast(__('Settings saved.', 'univer-smart-carousel'), 'success');
		} catch (err) {
			toast(err?.message || __('Save failed.', 'univer-smart-carousel'), 'error');
		} finally {
			setSavingSettings(false);
		}
	};

	return (
		<div className="usc-settings">
			<div className="usc-settings__inner">
				<header className="usc-settings__head">
					<div>
						<h1 className="usc-h1">{__('Header Top', 'univer-smart-carousel')}</h1>
						<p className="usc-muted">
							{__(
								'Site-wide vertical-swipe announcement strip. Drop the [header_top] shortcode at the top of your theme — same width as the parent container, vertical auto-rotation between slides.',
								'univer-smart-carousel'
							)}
						</p>
					</div>
					<Button variant="primary" onClick={handleSaveSettings} loading={savingSettings}>
						{__('Save settings', 'univer-smart-carousel')}
					</Button>
				</header>

				<Card>
					<SectionHeader
						eyebrow={__('Embed', 'univer-smart-carousel')}
						title={__('Shortcode', 'univer-smart-carousel')}
						description={__(
							'Paste this once at the very top of your theme (Elementor header, Customizer, code snippet — wherever your theme lets you).',
							'univer-smart-carousel'
						)}
					/>
					<div className="usc-shortcode-row">
						<span className="usc-shortcode-row__label">{__('Header Top', 'univer-smart-carousel')}</span>
						<div className="usc-shortcode-row__code">
							<code>[header_top]</code>
							<IconButton
								label={__('Copy', 'univer-smart-carousel')}
								onClick={() =>
									copyToClipboard('[header_top]').then(() =>
										toast(__('Copied!', 'univer-smart-carousel'), 'success')
									)
								}
							>
								<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true">
									<path
										fill="currentColor"
										d="M16 1H4a2 2 0 0 0-2 2v14h2V3h12zm3 4H8a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2m0 16H8V7h11z"
									/>
								</svg>
							</IconButton>
						</div>
					</div>
				</Card>

				<Card>
					<SectionHeader
						title={__('Behavior', 'univer-smart-carousel')}
						description={__(
							'How the strip rotates and how tall it sits in the page.',
							'univer-smart-carousel'
						)}
					/>
					<div className="usc-stack">
						<Switch
							checked={settings.is_enabled !== false}
							onChange={(v) => setSetting({ is_enabled: v })}
							label={__('Enable Header Top', 'univer-smart-carousel')}
							hint={__(
								'When off, the [header_top] shortcode renders nothing — even if there are active slides.',
								'univer-smart-carousel'
							)}
						/>
						<div className="usc-row-2">
							<Input
								label={__('Height (px)', 'univer-smart-carousel')}
								type="number"
								min={20}
								max={200}
								value={settings.height_px}
								onChange={(e) => setSetting({ height_px: parseInt(e.target.value, 10) || 36 })}
							/>
							<Input
								label={__('Background color', 'univer-smart-carousel')}
								type="text"
								placeholder="#000000"
								value={settings.background}
								onChange={(e) => setSetting({ background: e.target.value })}
							/>
						</div>
						<div className="usc-row-2">
							<Input
								label={__('Autoplay delay (ms)', 'univer-smart-carousel')}
								type="number"
								min={1000}
								max={30000}
								step={250}
								value={settings.autoplay_delay}
								onChange={(e) =>
									setSetting({ autoplay_delay: parseInt(e.target.value, 10) || 4000 })
								}
							/>
							<Input
								label={__('Transition speed (ms)', 'univer-smart-carousel')}
								type="number"
								min={100}
								max={3000}
								step={50}
								value={settings.transition_ms}
								onChange={(e) =>
									setSetting({ transition_ms: parseInt(e.target.value, 10) || 600 })
								}
							/>
						</div>
					</div>
				</Card>

				<Card>
					<SlideManager state={state} setState={setState} onRefresh={refresh} />
				</Card>
			</div>
		</div>
	);
}

/* ============================================================
 * Slide manager (drag + reorder + replace + duplicate + toggle)
 * ============================================================ */

function SlideManager({ state, setState, onRefresh }) {
	const slides = (state.slides || [])
		.slice()
		.sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0));

	const [dragId, setDragId] = useState(null);
	const [dropTargetId, setDropTargetId] = useState(null);
	const [confirmDelete, setConfirmDelete] = useState(null);

	const updateLocally = (id, patch) => {
		setState((prev) => ({
			...prev,
			slides: (prev.slides || []).map((s) => (s.id === id ? { ...s, ...patch } : s)),
		}));
	};

	const onAddSlides = async () => {
		let picks;
		try {
			picks = await pickImages({
				multiple: true,
				title: __('Select Header Top slides', 'univer-smart-carousel'),
			});
		} catch (err) {
			console.error(err);
			return;
		}
		if (!picks || picks.length === 0) return;
		for (const img of picks) {
			try {
				await API.createSlide({ image_id: img.id, alt_text: img.alt || '' });
			} catch (err) {
				toast(err?.message || __('Failed to add slide.', 'univer-smart-carousel'), 'error');
			}
		}
		await onRefresh();
	};

	const onUpdateField = (slide, patch) => {
		updateLocally(slide.id, patch);
		API.updateSlide(slide.id, patch).catch((err) =>
			toast(err?.message || __('Failed to save.', 'univer-smart-carousel'), 'error')
		);
	};

	const onToggleSlide = async (slide) => {
		const next = !slide.is_active;
		updateLocally(slide.id, { is_active: next });
		try {
			await API.updateSlide(slide.id, { is_active: next });
		} catch (err) {
			updateLocally(slide.id, { is_active: slide.is_active });
			toast(err?.message || __('Failed to toggle.', 'univer-smart-carousel'), 'error');
		}
	};

	const onReplaceImage = async (slide) => {
		let picks;
		try {
			picks = await pickImages({
				multiple: false,
				title: __('Replace slide image', 'univer-smart-carousel'),
			});
		} catch (err) {
			console.error(err);
			return;
		}
		if (!picks || picks.length === 0) return;
		const img = picks[0];
		updateLocally(slide.id, {
			image_id: img.id,
			image: { id: img.id, url: img.url, width: img.width || 0, height: img.height || 0, alt: img.alt || '' },
		});
		try {
			await API.updateSlide(slide.id, { image_id: img.id });
			await onRefresh();
		} catch (err) {
			toast(err?.message || __('Failed to replace image.', 'univer-smart-carousel'), 'error');
			await onRefresh();
		}
	};

	const onDuplicate = async (slide) => {
		try {
			await API.duplicateSlide(slide.id);
			await onRefresh();
			toast(__('Slide duplicated.', 'univer-smart-carousel'), 'success');
		} catch (err) {
			toast(err?.message || __('Failed to duplicate.', 'univer-smart-carousel'), 'error');
		}
	};

	const onDeleteSlide = async () => {
		const target = confirmDelete;
		if (!target) return;
		try {
			await API.removeSlide(target.id);
			await onRefresh();
			setConfirmDelete(null);
		} catch (err) {
			toast(err?.message || __('Failed to delete slide.', 'univer-smart-carousel'), 'error');
		}
	};

	/* ---------- Drag-to-reorder ---------- */

	const onDragStart = (slide) => (e) => {
		setDragId(slide.id);
		if (e.dataTransfer) {
			e.dataTransfer.effectAllowed = 'move';
			e.dataTransfer.setData('text/plain', 'ht-slide:' + slide.id);
		}
	};

	const onDragOver = (slide) => (e) => {
		if (!dragId || dragId === slide.id) return;
		e.preventDefault();
		if (e.dataTransfer) e.dataTransfer.dropEffect = 'move';
		if (dropTargetId !== slide.id) setDropTargetId(slide.id);
	};

	const onDragLeave = (slide) => () => {
		if (dropTargetId === slide.id) setDropTargetId(null);
	};

	const onDragEnd = () => {
		setDragId(null);
		setDropTargetId(null);
	};

	const onDrop = (target) => async (e) => {
		e.preventDefault();
		const sourceId = dragId;
		setDragId(null);
		setDropTargetId(null);
		if (!sourceId || sourceId === target.id) return;

		const currentOrder = slides.map((s) => s.id);
		const from = currentOrder.indexOf(sourceId);
		const to = currentOrder.indexOf(target.id);
		if (from === -1 || to === -1) return;

		const nextOrder = [...currentOrder];
		nextOrder.splice(from, 1);
		nextOrder.splice(to, 0, sourceId);

		const sortMap = Object.fromEntries(nextOrder.map((id, idx) => [id, idx]));
		setState((prev) => ({
			...prev,
			slides: (prev.slides || []).map((s) =>
				sortMap[s.id] !== undefined ? { ...s, sort_order: sortMap[s.id] } : s
			),
		}));

		try {
			await API.reorder({ order: nextOrder });
		} catch (err) {
			toast(err?.message || __('Failed to reorder.', 'univer-smart-carousel'), 'error');
			await onRefresh();
		}
	};

	return (
		<div className="usc-mosaic-items">
			<div className="usc-mosaic-items__head">
				<div>
					<h3 className="usc-h3">{__('Slides', 'univer-smart-carousel')}</h3>
					<p className="usc-muted">
						{__(
							'Drag to reorder. Click a thumbnail to replace the image. Toggle to pause without deleting.',
							'univer-smart-carousel'
						)}
					</p>
				</div>
				<Button variant="primary" onClick={onAddSlides}>
					+ {__('Slides', 'univer-smart-carousel')}
				</Button>
			</div>

			{slides.length === 0 && (
				<div className="usc-banner-editor__empty">
					<p>
						{__(
							'No slides yet — add one to make the strip render.',
							'univer-smart-carousel'
						)}
					</p>
				</div>
			)}

			<ul className="usc-mosaic-item-list">
				{slides.map((slide) => (
					<li
						key={slide.id}
						className={classNames(
							'usc-banner-card',
							!slide.is_active && 'is-paused',
							dragId === slide.id && 'is-dragging',
							dropTargetId === slide.id && 'is-drop-target'
						)}
						onDragOver={onDragOver(slide)}
						onDragLeave={onDragLeave(slide)}
						onDrop={onDrop(slide)}
					>
						<span
							className="usc-banner-card__handle"
							draggable
							onDragStart={onDragStart(slide)}
							onDragEnd={onDragEnd}
							title={__('Drag to reorder', 'univer-smart-carousel')}
							aria-label={__('Drag to reorder', 'univer-smart-carousel')}
						>
							<svg viewBox="0 0 24 24" width="12" height="12" aria-hidden="true">
								<path
									fill="currentColor"
									d="M9 4h2v2H9zm0 4h2v2H9zm0 4h2v2H9zm0 4h2v2H9zm4-12h2v2h-2zm0 4h2v2h-2zm0 4h2v2h-2zm0 4h2v2h-2z"
								/>
							</svg>
						</span>

						<button
							type="button"
							className="usc-banner-card__image"
							onClick={() => onReplaceImage(slide)}
							title={__('Click to replace image', 'univer-smart-carousel')}
							aria-label={__('Click to replace image', 'univer-smart-carousel')}
						>
							{slide.image?.url ? (
								<img src={slide.image.url} alt="" loading="lazy" />
							) : (
								<span className="usc-banner-card__placeholder">
									{__('No image', 'univer-smart-carousel')}
								</span>
							)}
						</button>

						<div className="usc-banner-card__fields">
							<Input
								label={__('Destination URL', 'univer-smart-carousel')}
								type="url"
								placeholder="https://"
								value={slide.link_url || ''}
								onChange={(e) => onUpdateField(slide, { link_url: e.target.value })}
							/>
							<div className="usc-row-2">
								<Select
									label={__('Open link in', 'univer-smart-carousel')}
									options={TARGET_OPTIONS}
									value={slide.link_target || '_self'}
									onChange={(v) => onUpdateField(slide, { link_target: v })}
								/>
								<Input
									label={__('Alt text', 'univer-smart-carousel')}
									placeholder={__('Describe the slide…', 'univer-smart-carousel')}
									value={slide.alt_text || ''}
									onChange={(e) => onUpdateField(slide, { alt_text: e.target.value })}
								/>
							</div>
						</div>

						<div className="usc-banner-card__actions">
							<label className="usc-banner-card__toggle" title={__('Active', 'univer-smart-carousel')}>
								<input
									type="checkbox"
									checked={slide.is_active}
									onChange={() => onToggleSlide(slide)}
								/>
								<span className="usc-switch__track">
									<span className="usc-switch__thumb" />
								</span>
							</label>
							<IconButton
								label={__('Duplicate slide', 'univer-smart-carousel')}
								onClick={() => onDuplicate(slide)}
							>
								<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true">
									<path
										fill="currentColor"
										d="M16 1H4a2 2 0 0 0-2 2v14h2V3h12zm3 4H8a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2m0 16H8V7h11z"
									/>
								</svg>
							</IconButton>
							<IconButton
								label={__('Delete slide', 'univer-smart-carousel')}
								onClick={() => setConfirmDelete(slide)}
							>
								<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true">
									<path
										fill="currentColor"
										d="M9 3v1H4v2h16V4h-5V3zm-3 5v12c0 1.1.9 2 2 2h8a2 2 0 0 0 2-2V8zm2 2h2v8H8zm4 0h2v8h-2z"
									/>
								</svg>
							</IconButton>
						</div>
					</li>
				))}
			</ul>

			<Modal
				open={!!confirmDelete}
				onClose={() => setConfirmDelete(null)}
				title={__('Delete slide?', 'univer-smart-carousel')}
				footer={
					<>
						<Button variant="ghost" onClick={() => setConfirmDelete(null)}>
							{__('Cancel', 'univer-smart-carousel')}
						</Button>
						<Button variant="danger" onClick={onDeleteSlide}>
							{__('Delete', 'univer-smart-carousel')}
						</Button>
					</>
				}
			>
				<p>
					{__(
						'The slide will be removed from the Header Top. The image stays in your media library.',
						'univer-smart-carousel'
					)}
				</p>
			</Modal>
		</div>
	);
}
