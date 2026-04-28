/**
 * Mosaics tab — list + editor + items manager.
 *
 * Mirrors the structure of the Campaigns tab: sidebar list on the
 * left, editor on the right. The editor is split into a main column
 * (the grid of items — drag to reorder, click the thumb to replace,
 * per-item col/row span, URL, alt, active toggle, duplicate, delete)
 * and a sidebar (shortcode panel + layout settings + image
 * optimization).
 */

import { useEffect, useState, useCallback } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Badge,
	Button,
	Card,
	EmptyState,
	IconButton,
	Input,
	Modal,
	SectionHeader,
	Select,
	Spinner,
	Switch,
	toast,
} from './ui';
import { Mosaics as API, MosaicItems as ItemsAPI } from '../lib/api';
import { pickImages } from '../lib/media';
import {
	classNames,
	copyToClipboard,
	defaultMosaicSettings,
	emptyMosaic,
	formatDate,
	slugify,
} from '../lib/utils';

const STATUS_TONES = {
	active: 'success',
	draft: 'neutral',
	paused: 'warning',
};

const STATUS_OPTIONS = [
	{ value: 'draft', label: __('Draft', 'univer-smart-carousel') },
	{ value: 'active', label: __('Active', 'univer-smart-carousel') },
	{ value: 'paused', label: __('Paused', 'univer-smart-carousel') },
];

const TARGET_OPTIONS = [
	{ value: '_self', label: __('Same tab', 'univer-smart-carousel') },
	{ value: '_blank', label: __('New tab', 'univer-smart-carousel') },
];

const SPAN_OPTIONS = [1, 2, 3, 4, 5, 6].map((n) => ({ value: String(n), label: String(n) }));

// Layout presets. Each entry maps to Mosaic_Repository::LAYOUT_* in PHP
// and drives how the renderer computes per-cell col_span/row_span from
// item position. `custom` is the escape hatch that keeps manual spans.
const LAYOUT_OPTIONS = [
	{
		value: 'hero-top',
		label: __('Hero on top', 'univer-smart-carousel'),
		hint: __('First item full-width, the rest are equal cards below.', 'univer-smart-carousel'),
	},
	{
		value: 'hero-bottom',
		label: __('Hero at the bottom', 'univer-smart-carousel'),
		hint: __('Equal cards on top, the last item takes the full bottom row.', 'univer-smart-carousel'),
	},
	{
		value: 'featured-left',
		label: __('Featured on the left', 'univer-smart-carousel'),
		hint: __('First item is a 2×2 feature, the rest stack on the right.', 'univer-smart-carousel'),
	},
	{
		value: 'featured-right',
		label: __('Featured on the right', 'univer-smart-carousel'),
		hint: __('Last item is a 2×2 feature, the rest stack on the left.', 'univer-smart-carousel'),
	},
	{
		value: 'alternating',
		label: __('Alternating rhythm', 'univer-smart-carousel'),
		hint: __('Full-width band, then two small cards, repeating.', 'univer-smart-carousel'),
	},
	{
		value: 'uniform',
		label: __('Side by side', 'univer-smart-carousel'),
		hint: __('Images line up in equal-size cards, side by side — [] [] [].', 'univer-smart-carousel'),
	},
	{
		value: 'aligned-row',
		label: __('Aligned row (squares)', 'univer-smart-carousel'),
		hint: __(
			'Forces every card into a 1:1 square so the row stays perfectly aligned no matter what aspect the originals had.',
			'univer-smart-carousel'
		),
	},
	{
		value: 'custom',
		label: __('Free (manual)', 'univer-smart-carousel'),
		hint: __('Set col / row span on every item yourself.', 'univer-smart-carousel'),
	},
];

const ASPECT_PRESETS = [
	{ value: 'auto', label: 'Auto' },
	{ value: '1/1', label: '1:1' },
	{ value: '4/5', label: '4:5' },
	{ value: '3/4', label: '3:4' },
	{ value: '4/3', label: '4:3' },
	{ value: '16/9', label: '16:9' },
	{ value: '21/9', label: '21:9' },
];

export default function MosaicsPage() {
	const [mosaics, setMosaics] = useState([]);
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
				setMosaics(list);
			} catch (err) {
				toast(err?.message || __('Failed to load mosaics.', 'univer-smart-carousel'), 'error');
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
		if (!activeId) return;
		(async () => {
			try {
				const m = await API.get(activeId);
				setDraft(m);
			} catch (err) {
				toast(err?.message || __('Failed to load mosaic.', 'univer-smart-carousel'), 'error');
			}
		})();
	}, [activeId]);

	const onSearch = (q) => {
		setSearch(q);
		refresh(q);
	};

	const onCreate = () => {
		setActiveId(null);
		setDraft(emptyMosaic());
	};

	const onSelect = (id) => {
		setActiveId(id);
	};

	const onSave = async () => {
		setSaving(true);
		try {
			const payload = {
				name: draft.name,
				slug: draft.slug,
				status: draft.status,
				settings: draft.settings,
			};
			let saved;
			if (draft.id) {
				saved = await API.update(draft.id, payload);
			} else {
				saved = await API.create(payload);
			}
			setDraft(saved);
			setActiveId(saved.id);
			await refresh(search);
			toast(__('Mosaic saved.', 'univer-smart-carousel'), 'success');
		} catch (err) {
			toast(err?.message || __('Save failed.', 'univer-smart-carousel'), 'error');
		} finally {
			setSaving(false);
		}
	};

	const onDelete = async () => {
		if (!draft?.id) return;
		try {
			await API.remove(draft.id);
			setDraft(null);
			setActiveId(null);
			await refresh(search);
			toast(__('Mosaic deleted.', 'univer-smart-carousel'), 'success');
		} catch (err) {
			toast(err?.message || __('Delete failed.', 'univer-smart-carousel'), 'error');
		}
	};

	return (
		<div className="usc-app__body">
			<MosaicList
				mosaics={mosaics}
				loading={loading}
				activeId={activeId}
				hasDraft={!!draft}
				onSelect={onSelect}
				onCreate={onCreate}
				onSearch={onSearch}
			/>

			<div className="usc-app__main">
				{!draft && !loading && mosaics.length > 0 && (
					<EmptyState
						icon={
							<svg viewBox="0 0 24 24" width="48" height="48" aria-hidden="true">
								<rect x="3" y="3" width="8" height="8" rx="1" fill="none" stroke="currentColor" strokeWidth="1.4" />
								<rect x="13" y="3" width="8" height="8" rx="1" fill="none" stroke="currentColor" strokeWidth="1.4" />
								<rect x="3" y="13" width="8" height="8" rx="1" fill="none" stroke="currentColor" strokeWidth="1.4" />
								<rect x="13" y="13" width="8" height="8" rx="1" fill="none" stroke="currentColor" strokeWidth="1.4" />
							</svg>
						}
						title={__('Select a mosaic', 'univer-smart-carousel')}
						description={__('Pick a mosaic on the left, or create a new one.', 'univer-smart-carousel')}
						action={<Button onClick={onCreate}>{__('New mosaic', 'univer-smart-carousel')}</Button>}
					/>
				)}

				{!draft && loading && (
					<div className="usc-app__loading">
						<Spinner />
						<span>{__('Loading…', 'univer-smart-carousel')}</span>
					</div>
				)}

				{draft && (
					<MosaicEditor
						mosaic={draft}
						onChange={setDraft}
						onSave={onSave}
						onDelete={onDelete}
						saving={saving}
					/>
				)}
			</div>
		</div>
	);
}

/* ============================================================
 * Sidebar list
 * ============================================================ */

function MosaicList({ mosaics, loading, activeId, hasDraft, onSelect, onCreate, onSearch }) {
	const [query, setQuery] = useState('');
	return (
		<aside className="usc-list">
			<div className="usc-list__head">
				<div className="usc-list__head-row">
					<h2 className="usc-h3">{__('Mosaics', 'univer-smart-carousel')}</h2>
					<Button
						variant="primary"
						size="sm"
						onClick={onCreate}
						leftIcon={
							<svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true">
								<path fill="currentColor" d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6z" />
							</svg>
						}
					>
						{__('New', 'univer-smart-carousel')}
					</Button>
				</div>
				<form
					className="usc-list__search"
					onSubmit={(e) => {
						e.preventDefault();
						onSearch(query);
					}}
				>
					<Input
						placeholder={__('Search mosaics…', 'univer-smart-carousel')}
						value={query}
						onChange={(e) => {
							setQuery(e.target.value);
							onSearch(e.target.value);
						}}
					/>
				</form>
			</div>

			<div className="usc-list__items">
				{loading && <div className="usc-list__loading">{__('Loading…', 'univer-smart-carousel')}</div>}

				{!loading && mosaics.length === 0 && !hasDraft && (
					<EmptyState
						title={__('No mosaics yet', 'univer-smart-carousel')}
						description={__('Create your first mosaic to get a shareable shortcode.', 'univer-smart-carousel')}
						action={<Button onClick={onCreate}>{__('Create mosaic', 'univer-smart-carousel')}</Button>}
					/>
				)}

				{!loading &&
					mosaics.map((m) => (
						<button
							type="button"
							key={m.id}
							className={classNames('usc-list-item', activeId === m.id && 'is-active')}
							onClick={() => onSelect(m.id)}
						>
							<div className="usc-list-item__top">
								<span className="usc-list-item__name">{m.name}</span>
								<Badge tone={STATUS_TONES[m.status] || 'neutral'}>{m.status}</Badge>
							</div>
							<div className="usc-list-item__meta">
								<span className="usc-list-item__slug">{m.slug}</span>
								<span className="usc-list-item__date">{formatDate(m.updated_at)}</span>
							</div>
						</button>
					))}
			</div>
		</aside>
	);
}

/* ============================================================
 * Editor (topbar + items grid + sidebar cards)
 * ============================================================ */

function MosaicEditor({ mosaic, onChange, onSave, onDelete, saving }) {
	const [confirmDelete, setConfirmDelete] = useState(false);
	const [autoSlug, setAutoSlug] = useState(!mosaic?.id);

	const settings = { ...defaultMosaicSettings(), ...(mosaic?.settings || {}) };

	const setField = (patch) => onChange({ ...mosaic, ...patch });
	const setSetting = (patch) => setField({ settings: { ...settings, ...patch } });

	const refreshFromServer = async () => {
		if (!mosaic.id) return;
		try {
			const fresh = await API.get(mosaic.id);
			onChange(fresh);
		} catch (err) {
			toast(err?.message || __('Failed to refresh.', 'univer-smart-carousel'), 'error');
		}
	};

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

	const handleDelete = async () => {
		try {
			await onDelete();
			setConfirmDelete(false);
		} catch (err) {
			toast(err?.message || __('Delete failed.', 'univer-smart-carousel'), 'error');
		}
	};

	const spvOptions = [1, 2, 3, 4, 5, 6].map((n) => ({ value: String(n), label: String(n) }));

	return (
		<main className="usc-editor">
			<div className="usc-editor__topbar">
				<div className="usc-editor__title">
					<input
						type="text"
						className="usc-h1-input"
						placeholder={__('Untitled mosaic', 'univer-smart-carousel')}
						value={mosaic.name || ''}
						onChange={handleNameChange}
					/>
					<div className="usc-editor__meta">
						<Badge tone={STATUS_TONES[mosaic.status] || 'neutral'}>{mosaic.status}</Badge>
						<span className="usc-muted">{mosaic.slug ? `/${mosaic.slug}` : ''}</span>
					</div>
				</div>

				<div className="usc-editor__actions">
					{mosaic.id && (
						<Button variant="ghost" onClick={() => setConfirmDelete(true)}>
							{__('Delete', 'univer-smart-carousel')}
						</Button>
					)}
					<Button variant="primary" onClick={onSave} loading={saving}>
						{mosaic.id
							? __('Save changes', 'univer-smart-carousel')
							: __('Create mosaic', 'univer-smart-carousel')}
					</Button>
				</div>
			</div>

			<div className="usc-editor__grid">
				<div className="usc-editor__col-main">
					<Card>
						<MosaicItemsManager
							mosaicId={mosaic.id}
							items={mosaic.items || []}
							onItemsChange={(items) => onChange({ ...mosaic, items })}
							onRefresh={refreshFromServer}
							cols={settings.cols_desktop}
							layoutDesktop={settings.layout_desktop || settings.layout || 'hero-top'}
							layoutMobile={settings.layout_mobile || settings.layout || 'hero-top'}
						/>
					</Card>
				</div>

				<aside className="usc-editor__col-side">
					{mosaic.id && (
						<Card className="usc-shortcode-card">
							<SectionHeader
								eyebrow={__('Embed', 'univer-smart-carousel')}
								title={__('Shortcode', 'univer-smart-carousel')}
								description={__(
									'Paste anywhere on your site. The mosaic renders only while the status is Active.',
									'univer-smart-carousel'
								)}
							/>
							<div className="usc-shortcode-row">
								<span className="usc-shortcode-row__label">{__('Mosaic', 'univer-smart-carousel')}</span>
								<div className="usc-shortcode-row__code">
									<code>{`[mosaic_${mosaic.slug}]`}</code>
									<IconButton
										label={__('Copy', 'univer-smart-carousel')}
										onClick={() =>
											copyToClipboard(`[mosaic_${mosaic.slug}]`).then(() =>
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
					)}

					<Card>
						<SectionHeader title={__('Mosaic', 'univer-smart-carousel')} />
						<div className="usc-stack">
							<Select
								label={__('Status', 'univer-smart-carousel')}
								options={STATUS_OPTIONS}
								value={mosaic.status || 'draft'}
								onChange={(v) => setField({ status: v })}
							/>
							<Input
								label={__('Slug', 'univer-smart-carousel')}
								hint={__('Used in the shortcode. Letters, numbers, dashes only.', 'univer-smart-carousel')}
								value={mosaic.slug || ''}
								onChange={handleSlugChange}
							/>
						</div>
					</Card>

					<Card>
						<SectionHeader
							title={__('Layout', 'univer-smart-carousel')}
							description={__(
								'Desktop and mobile pick their format independently — pair a wide hero on desktop with a stacked hero on mobile, or any mix.',
								'univer-smart-carousel'
							)}
						/>
						<div className="usc-stack">
							<div className="usc-row-2">
								<Select
									label={__('Format (desktop)', 'univer-smart-carousel')}
									hint={
										LAYOUT_OPTIONS.find(
											(o) =>
												o.value ===
												(settings.layout_desktop || settings.layout || 'hero-top')
										)?.hint
									}
									options={LAYOUT_OPTIONS.map(({ value, label }) => ({ value, label }))}
									value={settings.layout_desktop || settings.layout || 'hero-top'}
									onChange={(v) => setSetting({ layout_desktop: v })}
								/>
								<Select
									label={__('Format (mobile)', 'univer-smart-carousel')}
									hint={
										LAYOUT_OPTIONS.find(
											(o) =>
												o.value ===
												(settings.layout_mobile || settings.layout || 'hero-top')
										)?.hint
									}
									options={LAYOUT_OPTIONS.map(({ value, label }) => ({ value, label }))}
									value={settings.layout_mobile || settings.layout || 'hero-top'}
									onChange={(v) => setSetting({ layout_mobile: v })}
								/>
							</div>
							<div className="usc-row-2">
								<Select
									label={__('Columns (desktop)', 'univer-smart-carousel')}
									options={spvOptions}
									value={String(settings.cols_desktop)}
									onChange={(v) => setSetting({ cols_desktop: parseInt(v, 10) || 3 })}
								/>
								<Select
									label={__('Columns (mobile)', 'univer-smart-carousel')}
									options={[1, 2, 3, 4].map((n) => ({ value: String(n), label: String(n) }))}
									value={String(settings.cols_mobile)}
									onChange={(v) => setSetting({ cols_mobile: parseInt(v, 10) || 2 })}
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
									hint={__('0 = sharp corners.', 'univer-smart-carousel')}
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
						<SectionHeader
							eyebrow={__('Performance', 'univer-smart-carousel')}
							title={__('Image optimization', 'univer-smart-carousel')}
							description={__(
								'Resize, recompress, and serve WebP. Settings apply the next time each image is rendered.',
								'univer-smart-carousel'
							)}
						/>
						<div className="usc-stack">
							<Switch
								checked={settings.image_optimization !== false}
								onChange={(v) => setSetting({ image_optimization: v })}
								label={__('Optimize images for this mosaic', 'univer-smart-carousel')}
								hint={__(
									'When off, the original upload is served as-is.',
									'univer-smart-carousel'
								)}
							/>
							{settings.image_optimization !== false && (
								<>
									<Input
										label={__('JPEG quality', 'univer-smart-carousel')}
										hint={__('40 = aggressive, 82 = default, 95 = near-lossless.', 'univer-smart-carousel')}
										type="number"
										min={40}
										max={95}
										value={settings.image_quality ?? 82}
										onChange={(e) =>
											setSetting({ image_quality: parseInt(e.target.value, 10) || 82 })
										}
									/>
									<div className="usc-row-2">
										<Input
											label={__('Max width (desktop)', 'univer-smart-carousel')}
											hint={__('px', 'univer-smart-carousel')}
											type="number"
											min={400}
											max={3840}
											step={20}
											value={settings.image_max_width_desktop ?? 1600}
											onChange={(e) =>
												setSetting({
													image_max_width_desktop:
														parseInt(e.target.value, 10) || 1600,
												})
											}
										/>
										<Input
											label={__('Max width (mobile)', 'univer-smart-carousel')}
											hint={__('px', 'univer-smart-carousel')}
											type="number"
											min={320}
											max={1536}
											step={10}
											value={settings.image_max_width_mobile ?? 750}
											onChange={(e) =>
												setSetting({
													image_max_width_mobile:
														parseInt(e.target.value, 10) || 750,
												})
											}
										/>
									</div>
									<Switch
										checked={settings.image_webp !== false}
										onChange={(v) => setSetting({ image_webp: v })}
										label={__('Serve WebP when the browser supports it', 'univer-smart-carousel')}
									/>
								</>
							)}
						</div>
					</Card>
				</aside>
			</div>

			<Modal
				open={confirmDelete}
				onClose={() => setConfirmDelete(false)}
				title={__('Delete this mosaic?', 'univer-smart-carousel')}
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
						'This will remove the mosaic and all of its items. The shortcode will stop rendering anything.',
						'univer-smart-carousel'
					)}
				</p>
			</Modal>
		</main>
	);
}

/* ============================================================
 * Items manager (drag + reorder + replace + duplicate)
 * ============================================================ */

function MosaicItemsManager({
	mosaicId,
	items,
	onItemsChange,
	onRefresh,
	cols,
	layoutDesktop = 'hero-top',
	layoutMobile = 'hero-top',
}) {
	// Per-item col/row span controls only surface when at least one
	// of the two breakpoints is in "Free (manual)" mode — otherwise
	// the chosen presets compute spans from position and the inputs
	// would be no-ops.
	const isCustomLayout = layoutDesktop === 'custom' || layoutMobile === 'custom';
	const [dragItem, setDragItem] = useState(null);
	const [dropTargetId, setDropTargetId] = useState(null);
	const [confirmDelete, setConfirmDelete] = useState(null);

	const sorted = (items || []).slice().sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0));

	const onAddItems = async () => {
		if (!mosaicId) {
			toast(__('Save the mosaic first, then add items.', 'univer-smart-carousel'), 'error');
			return;
		}
		let picks;
		try {
			picks = await pickImages({
				multiple: true,
				title: __('Select mosaic images', 'univer-smart-carousel'),
			});
		} catch (err) {
			console.error(err);
			return;
		}
		if (!picks || picks.length === 0) return;
		for (const img of picks) {
			try {
				await API.addItem(mosaicId, {
					image_id: img.id,
					alt_text: img.alt || '',
				});
			} catch (err) {
				toast(err?.message || __('Failed to add item.', 'univer-smart-carousel'), 'error');
			}
		}
		await onRefresh();
	};

	const updateLocally = (id, patch) => {
		onItemsChange((items || []).map((i) => (i.id === id ? { ...i, ...patch } : i)));
	};

	const onUpdateField = (item, patch) => {
		updateLocally(item.id, patch);
		ItemsAPI.update(item.id, patch).catch((err) =>
			toast(err?.message || __('Failed to save.', 'univer-smart-carousel'), 'error')
		);
	};

	const onToggleItem = async (item) => {
		const next = !item.is_active;
		updateLocally(item.id, { is_active: next });
		try {
			await ItemsAPI.update(item.id, { is_active: next });
		} catch (err) {
			updateLocally(item.id, { is_active: item.is_active });
			toast(err?.message || __('Failed to toggle.', 'univer-smart-carousel'), 'error');
		}
	};

	const onReplaceImage = async (item) => {
		let picks;
		try {
			picks = await pickImages({
				multiple: false,
				title: __('Replace item image', 'univer-smart-carousel'),
			});
		} catch (err) {
			console.error(err);
			return;
		}
		if (!picks || picks.length === 0) return;
		const img = picks[0];
		updateLocally(item.id, {
			image_id: img.id,
			image: { id: img.id, url: img.url, width: img.width || 0, height: img.height || 0, alt: img.alt || '' },
		});
		try {
			await ItemsAPI.update(item.id, { image_id: img.id });
			await onRefresh();
		} catch (err) {
			toast(err?.message || __('Failed to replace image.', 'univer-smart-carousel'), 'error');
			await onRefresh();
		}
	};

	const onDuplicate = async (item) => {
		try {
			await ItemsAPI.duplicate(item.id);
			await onRefresh();
			toast(__('Item duplicated.', 'univer-smart-carousel'), 'success');
		} catch (err) {
			toast(err?.message || __('Failed to duplicate.', 'univer-smart-carousel'), 'error');
		}
	};

	const onDeleteItem = async () => {
		const target = confirmDelete;
		if (!target) return;
		try {
			await ItemsAPI.remove(target.id);
			await onRefresh();
			setConfirmDelete(null);
		} catch (err) {
			toast(err?.message || __('Failed to delete item.', 'univer-smart-carousel'), 'error');
		}
	};

	/* ---------- Drag-to-reorder ---------- */

	const onDragStart = (item) => (e) => {
		setDragItem(item);
		if (e.dataTransfer) {
			e.dataTransfer.effectAllowed = 'move';
			e.dataTransfer.setData('text/plain', 'item:' + item.id);
		}
	};

	const onDragOver = (item) => (e) => {
		if (!dragItem || dragItem.id === item.id) return;
		e.preventDefault();
		if (e.dataTransfer) e.dataTransfer.dropEffect = 'move';
		if (dropTargetId !== item.id) setDropTargetId(item.id);
	};

	const onDragLeave = (item) => () => {
		if (dropTargetId === item.id) setDropTargetId(null);
	};

	const onDragEnd = () => {
		setDragItem(null);
		setDropTargetId(null);
	};

	const onDrop = (target) => async (e) => {
		e.preventDefault();
		const source = dragItem;
		setDragItem(null);
		setDropTargetId(null);
		if (!source || source.id === target.id) return;

		const currentOrder = sorted.map((i) => i.id);
		const from = currentOrder.indexOf(source.id);
		const to = currentOrder.indexOf(target.id);
		if (from === -1 || to === -1) return;

		const nextOrder = [...currentOrder];
		nextOrder.splice(from, 1);
		nextOrder.splice(to, 0, source.id);

		const sortMap = Object.fromEntries(nextOrder.map((id, idx) => [id, idx]));
		onItemsChange(
			(items || []).map((i) =>
				sortMap[i.id] !== undefined ? { ...i, sort_order: sortMap[i.id] } : i
			)
		);

		try {
			await API.reorderItems(mosaicId, { order: nextOrder });
		} catch (err) {
			toast(err?.message || __('Failed to reorder.', 'univer-smart-carousel'), 'error');
			await onRefresh();
		}
	};

	return (
		<div className="usc-mosaic-items">
			<div className="usc-mosaic-items__head">
				<div>
					<h3 className="usc-h3">{__('Items', 'univer-smart-carousel')}</h3>
					<p className="usc-muted">
						{isCustomLayout
							? __(
									'Drag to reorder. Click a thumbnail to replace the image. Use Col / Row to make a cell bigger (bento layout).',
									'univer-smart-carousel'
							  )
							: __(
									'Drag to reorder. Click a thumbnail to replace the image. The chosen Format handles sizes for you.',
									'univer-smart-carousel'
							  )}
					</p>
				</div>
				<Button variant="primary" onClick={onAddItems}>
					+ {__('Items', 'univer-smart-carousel')}
				</Button>
			</div>

			{(!items || items.length === 0) && (
				<div className="usc-banner-editor__empty">
					<p>
						{mosaicId
							? __('No items yet — add one to start building the grid.', 'univer-smart-carousel')
							: __('Save the mosaic first, then add items.', 'univer-smart-carousel')}
					</p>
				</div>
			)}

			<ul className="usc-mosaic-item-list">
				{sorted.map((item) => (
					<li
						key={item.id}
						className={classNames(
							'usc-banner-card',
							!item.is_active && 'is-paused',
							dragItem?.id === item.id && 'is-dragging',
							dropTargetId === item.id && 'is-drop-target'
						)}
						onDragOver={onDragOver(item)}
						onDragLeave={onDragLeave(item)}
						onDrop={onDrop(item)}
					>
						<span
							className="usc-banner-card__handle"
							draggable
							onDragStart={onDragStart(item)}
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
							onClick={() => onReplaceImage(item)}
							title={__('Click to replace image', 'univer-smart-carousel')}
							aria-label={__('Click to replace image', 'univer-smart-carousel')}
						>
							{item.image?.url ? (
								<img src={item.image.url} alt="" loading="lazy" />
							) : (
								<span className="usc-banner-card__placeholder">
									{__('No image', 'univer-smart-carousel')}
								</span>
							)}
							<span className="usc-banner-card__image-overlay" aria-hidden="true">
								<svg viewBox="0 0 24 24" width="18" height="18">
									<path
										fill="currentColor"
										d="M19 3h-4.18C14.4 1.84 13.3 1 12 1s-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2m-7 0c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1m2 14H7v-2h7zm3-4H7v-2h10zm0-4H7V7h10z"
									/>
								</svg>
							</span>
						</button>

						<div className="usc-banner-card__fields">
							<Input
								label={__('Item name (optional)', 'univer-smart-carousel')}
								placeholder={__('Internal label — e.g. "Revendedor oficial"', 'univer-smart-carousel')}
								value={item.name || ''}
								onChange={(e) => onUpdateField(item, { name: e.target.value })}
							/>
							<Input
								label={__('Destination URL', 'univer-smart-carousel')}
								type="url"
								placeholder="https://"
								value={item.link_url || ''}
								onChange={(e) => onUpdateField(item, { link_url: e.target.value })}
							/>
							<div className="usc-row-2">
								<Select
									label={__('Open link in', 'univer-smart-carousel')}
									options={TARGET_OPTIONS}
									value={item.link_target || '_self'}
									onChange={(v) => onUpdateField(item, { link_target: v })}
								/>
								<Input
									label={__('Alt text', 'univer-smart-carousel')}
									placeholder={__('Describe the image…', 'univer-smart-carousel')}
									value={item.alt_text || ''}
									onChange={(e) => onUpdateField(item, { alt_text: e.target.value })}
								/>
							</div>
							{isCustomLayout ? (
								<div className="usc-row-3">
									<Select
										label={sprintf(
											/* translators: %d: max columns */
											__('Col span (1–%d)', 'univer-smart-carousel'),
											cols || 3
										)}
										options={SPAN_OPTIONS}
										value={String(item.col_span || 1)}
										onChange={(v) => onUpdateField(item, { col_span: parseInt(v, 10) || 1 })}
									/>
									<Select
										label={__('Row span', 'univer-smart-carousel')}
										options={SPAN_OPTIONS}
										value={String(item.row_span || 1)}
										onChange={(v) => onUpdateField(item, { row_span: parseInt(v, 10) || 1 })}
									/>
									<Select
										label={__('Aspect', 'univer-smart-carousel')}
										options={ASPECT_PRESETS}
										value={item.aspect_ratio || 'auto'}
										onChange={(v) => onUpdateField(item, { aspect_ratio: v })}
									/>
								</div>
							) : (
								<Select
									label={__('Aspect', 'univer-smart-carousel')}
									options={ASPECT_PRESETS}
									value={item.aspect_ratio || 'auto'}
									onChange={(v) => onUpdateField(item, { aspect_ratio: v })}
								/>
							)}
						</div>

						<div className="usc-banner-card__actions">
							<label className="usc-banner-card__toggle" title={__('Active', 'univer-smart-carousel')}>
								<input
									type="checkbox"
									checked={item.is_active}
									onChange={() => onToggleItem(item)}
								/>
								<span className="usc-switch__track">
									<span className="usc-switch__thumb" />
								</span>
							</label>
							<IconButton
								label={__('Duplicate item', 'univer-smart-carousel')}
								onClick={() => onDuplicate(item)}
							>
								<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true">
									<path
										fill="currentColor"
										d="M16 1H4a2 2 0 0 0-2 2v14h2V3h12zm3 4H8a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2m0 16H8V7h11z"
									/>
								</svg>
							</IconButton>
							<IconButton
								label={__('Delete item', 'univer-smart-carousel')}
								onClick={() => setConfirmDelete(item)}
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
				title={__('Delete item?', 'univer-smart-carousel')}
				footer={
					<>
						<Button variant="ghost" onClick={() => setConfirmDelete(null)}>
							{__('Cancel', 'univer-smart-carousel')}
						</Button>
						<Button variant="danger" onClick={onDeleteItem}>
							{__('Delete', 'univer-smart-carousel')}
						</Button>
					</>
				}
			>
				<p>
					{__(
						'The item will be removed from this mosaic. The image stays in your media library.',
						'univer-smart-carousel'
					)}
				</p>
			</Modal>
		</div>
	);
}
