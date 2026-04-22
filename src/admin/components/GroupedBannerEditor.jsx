/**
 * Grouped banner editor.
 *
 * Three-level tree:
 *   Carousel
 *     └── Group (named, drag-to-reorder, can be paused)
 *           └── Banner (image + name + link + alt, drag-to-reorder,
 *                       can be paused, click image to replace, duplicate)
 *
 * Each group is a self-contained accordion with its own header (drag
 * handle, toggle, name, banner count, "+ Banners", delete) and a
 * banner list inside.
 *
 * State discipline:
 *   - Structural changes (create/delete/duplicate/reorder) reload the
 *     campaign from the server via refreshCampaign() when done. One
 *     extra round trip, zero chance of state drift.
 *   - Per-field edits (toggle, name, link, alt, target) are eager-
 *     optimistic — mutate local state immediately, fire-and-forget the
 *     PUT, toast on error.
 */

import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Button, IconButton, Input, Select, Modal, toast } from './ui';
import { pickImages } from '../lib/media';
import { classNames } from '../lib/utils';
import { Groups as GroupsAPI, Banners as BannersAPI, Campaigns as CampaignsAPI } from '../lib/api';

const TARGET_OPTIONS = [
	{ value: '_self', label: __('Same tab', 'univer-smart-carousel') },
	{ value: '_blank', label: __('New tab', 'univer-smart-carousel') },
];

export default function GroupedBannerEditor({
	campaignId,
	device,
	groups,
	banners,
	onChange,
}) {
	const [collapsed, setCollapsed] = useState({}); // gid → bool
	const [renaming, setRenaming] = useState(null); // group obj or null
	const [draftName, setDraftName] = useState('');
	const [confirmDeleteGroup, setConfirmDeleteGroup] = useState(null);
	const [confirmDeleteBanner, setConfirmDeleteBanner] = useState(null);
	const [creating, setCreating] = useState(false);

	// Group drag state
	const [dragGid, setDragGid] = useState(null);
	const [dropTargetGid, setDropTargetGid] = useState(null);

	// Banner drag state (scoped to a single group so dragging between
	// different groups doesn't accidentally land a banner in the wrong
	// list — a future feature if we want it, but out of scope today).
	const [dragBanner, setDragBanner] = useState(null); // { id, groupId } | null
	const [dropTargetBid, setDropTargetBid] = useState(null);

	const deviceGroups = (groups || []).filter((g) => g.device === device);
	const bannersByGroup = (groupId) =>
		(banners || [])
			.filter((b) => b.device === device && b.group_id === groupId)
			.slice()
			.sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0));

	// ---------- Server refresh + local state helpers ----------

	const refreshCampaign = async () => {
		if (!campaignId) return;
		try {
			const fresh = await CampaignsAPI.get(campaignId);
			onChange({
				groups: fresh.groups || [],
				banners: fresh.banners || [],
			});
		} catch (err) {
			toast(err?.message || __('Failed to refresh.', 'univer-smart-carousel'), 'error');
		}
	};

	const updateGroupLocally = (gid, patch) => {
		onChange({
			groups: (groups || []).map((g) => (g.id === gid ? { ...g, ...patch } : g)),
		});
	};

	const updateBannerLocally = (bid, patch) => {
		onChange({
			banners: (banners || []).map((b) => (b.id === bid ? { ...b, ...patch } : b)),
		});
	};

	// ---------- Group actions ----------

	const onCreateGroup = async () => {
		if (!campaignId) {
			toast(__('Save the carousel first, then add groups.', 'univer-smart-carousel'), 'error');
			return;
		}
		setCreating(true);
		try {
			await GroupsAPI.create(campaignId, {
				device,
				name: __('New group', 'univer-smart-carousel'),
			});
			await refreshCampaign();
		} catch (err) {
			toast(err?.message || __('Failed to create group.', 'univer-smart-carousel'), 'error');
		} finally {
			setCreating(false);
		}
	};

	const onToggleGroup = async (group) => {
		const next = !group.is_active;
		updateGroupLocally(group.id, { is_active: next });
		try {
			await GroupsAPI.update(group.id, { is_active: next });
		} catch (err) {
			updateGroupLocally(group.id, { is_active: group.is_active });
			toast(err?.message || __('Failed to toggle group.', 'univer-smart-carousel'), 'error');
		}
	};

	const onRenameGroup = async () => {
		if (!renaming) return;
		const name = draftName.trim();
		if (!name) return;
		try {
			const saved = await GroupsAPI.update(renaming.id, { name });
			updateGroupLocally(renaming.id, { name: saved.name });
			setRenaming(null);
		} catch (err) {
			toast(err?.message || __('Failed to rename.', 'univer-smart-carousel'), 'error');
		}
	};

	const onDeleteGroup = async () => {
		const target = confirmDeleteGroup;
		if (!target) return;
		try {
			await GroupsAPI.remove(target.id);
			await refreshCampaign();
			setConfirmDeleteGroup(null);
			toast(__('Group deleted.', 'univer-smart-carousel'), 'success');
		} catch (err) {
			toast(err?.message || __('Failed to delete group.', 'univer-smart-carousel'), 'error');
		}
	};

	const onAddBannersToGroup = async (group) => {
		let items;
		try {
			items = await pickImages({
				multiple: true,
				title:
					device === 'desktop'
						? __('Select desktop banners', 'univer-smart-carousel')
						: __('Select mobile banners', 'univer-smart-carousel'),
			});
		} catch (err) {
			console.error(err);
			return;
		}
		if (!items || items.length === 0) return;

		for (const img of items) {
			try {
				await GroupsAPI.addBanner(group.id, {
					image_id: img.id,
					alt_text: img.alt || '',
				});
			} catch (err) {
				toast(
					err?.message || __('Failed to add banner.', 'univer-smart-carousel'),
					'error'
				);
			}
		}
		await refreshCampaign();
	};

	// ---------- Banner actions ----------

	const onToggleBanner = async (banner) => {
		const next = !banner.is_active;
		updateBannerLocally(banner.id, { is_active: next });
		try {
			await BannersAPI.update(banner.id, { is_active: next });
		} catch (err) {
			updateBannerLocally(banner.id, { is_active: banner.is_active });
			toast(err?.message || __('Failed to toggle banner.', 'univer-smart-carousel'), 'error');
		}
	};

	const onDeleteBanner = async () => {
		const target = confirmDeleteBanner;
		if (!target) return;
		try {
			await BannersAPI.remove(target.id);
			await refreshCampaign();
			setConfirmDeleteBanner(null);
		} catch (err) {
			toast(err?.message || __('Failed to delete banner.', 'univer-smart-carousel'), 'error');
		}
	};

	const onDuplicateBanner = async (banner) => {
		try {
			await BannersAPI.duplicate(banner.id);
			await refreshCampaign();
			toast(__('Banner duplicated.', 'univer-smart-carousel'), 'success');
		} catch (err) {
			toast(err?.message || __('Failed to duplicate banner.', 'univer-smart-carousel'), 'error');
		}
	};

	// Click the thumbnail → media library → swap image in place.
	// Same banner row, new image_id. Way faster than delete + re-add.
	const onReplaceBannerImage = async (banner) => {
		let items;
		try {
			items = await pickImages({
				multiple: false,
				title: __('Replace banner image', 'univer-smart-carousel'),
			});
		} catch (err) {
			console.error(err);
			return;
		}
		if (!items || items.length === 0) return;
		const img = items[0];

		// Optimistic image swap in local state so the thumbnail updates
		// without waiting for the round trip.
		updateBannerLocally(banner.id, {
			image_id: img.id,
			image: { id: img.id, url: img.url, width: img.width || 0, height: img.height || 0, alt: img.alt || '' },
		});

		try {
			await BannersAPI.update(banner.id, { image_id: img.id });
			// Refetch to pick up the fully hydrated image payload
			// (srcset, sizes, etc.) that the server computes.
			await refreshCampaign();
		} catch (err) {
			toast(err?.message || __('Failed to replace image.', 'univer-smart-carousel'), 'error');
			await refreshCampaign(); // resync to truth
		}
	};

	const onUpdateBannerField = (banner, patch) => {
		updateBannerLocally(banner.id, patch);
		BannersAPI.update(banner.id, patch).catch((err) =>
			toast(err?.message || __('Failed to save.', 'univer-smart-carousel'), 'error')
		);
	};

	// ---------- Drag-to-reorder groups ----------

	const onGroupDragStart = (group) => (e) => {
		setDragGid(group.id);
		if (e.dataTransfer) {
			e.dataTransfer.effectAllowed = 'move';
			e.dataTransfer.setData('text/plain', 'group:' + group.id);
		}
	};

	const onGroupDragOver = (group) => (e) => {
		if (dragGid === null || dragGid === group.id) return;
		e.preventDefault();
		if (e.dataTransfer) e.dataTransfer.dropEffect = 'move';
		if (dropTargetGid !== group.id) setDropTargetGid(group.id);
	};

	const onGroupDragLeave = (group) => () => {
		if (dropTargetGid === group.id) setDropTargetGid(null);
	};

	const onGroupDragEnd = () => {
		setDragGid(null);
		setDropTargetGid(null);
	};

	const onGroupDrop = (target) => async (e) => {
		e.preventDefault();
		const sourceId = dragGid;
		setDragGid(null);
		setDropTargetGid(null);
		if (!sourceId || sourceId === target.id) return;

		const current = deviceGroups.map((g) => g.id);
		const from = current.indexOf(sourceId);
		const to = current.indexOf(target.id);
		if (from === -1 || to === -1) return;

		const reordered = [...current];
		reordered.splice(from, 1);
		reordered.splice(to, 0, sourceId);

		const newGroups = [...(groups || [])];
		const byId = Object.fromEntries(newGroups.map((g) => [g.id, g]));
		const otherDevice = newGroups.filter((g) => g.device !== device);
		const thisDevice = reordered.map((id, idx) => ({ ...byId[id], sort_order: idx }));
		onChange({ groups: [...otherDevice, ...thisDevice] });

		try {
			await GroupsAPI.reorder(campaignId, { device, order: reordered });
		} catch (err) {
			toast(err?.message || __('Failed to reorder.', 'univer-smart-carousel'), 'error');
			await refreshCampaign();
		}
	};

	// ---------- Drag-to-reorder banners (inside one group) ----------

	const onBannerDragStart = (banner) => (e) => {
		// Stop propagation so the banner drag doesn't also fire the
		// group drag. They're independent gestures.
		e.stopPropagation();
		setDragBanner({ id: banner.id, groupId: banner.group_id });
		if (e.dataTransfer) {
			e.dataTransfer.effectAllowed = 'move';
			e.dataTransfer.setData('text/plain', 'banner:' + banner.id);
		}
	};

	const onBannerDragOver = (banner) => (e) => {
		if (!dragBanner) return;
		// Only allow drops within the same group — cross-group moves
		// aren't supported yet.
		if (dragBanner.groupId !== banner.group_id) return;
		if (dragBanner.id === banner.id) return;
		e.preventDefault();
		e.stopPropagation();
		if (e.dataTransfer) e.dataTransfer.dropEffect = 'move';
		if (dropTargetBid !== banner.id) setDropTargetBid(banner.id);
	};

	const onBannerDragLeave = (banner) => () => {
		if (dropTargetBid === banner.id) setDropTargetBid(null);
	};

	const onBannerDragEnd = () => {
		setDragBanner(null);
		setDropTargetBid(null);
	};

	const onBannerDrop = (target) => async (e) => {
		e.preventDefault();
		e.stopPropagation();
		const source = dragBanner;
		setDragBanner(null);
		setDropTargetBid(null);
		if (!source || source.id === target.id) return;
		if (source.groupId !== target.group_id) return;

		const groupBanners = bannersByGroup(target.group_id);
		const currentOrder = groupBanners.map((b) => b.id);
		const from = currentOrder.indexOf(source.id);
		const to = currentOrder.indexOf(target.id);
		if (from === -1 || to === -1) return;

		const nextOrder = [...currentOrder];
		nextOrder.splice(from, 1);
		nextOrder.splice(to, 0, source.id);

		// Optimistic local reorder: patch sort_order on the affected rows.
		const sortMap = Object.fromEntries(nextOrder.map((id, idx) => [id, idx]));
		onChange({
			banners: (banners || []).map((b) =>
				b.group_id === target.group_id && sortMap[b.id] !== undefined
					? { ...b, sort_order: sortMap[b.id] }
					: b
			),
		});

		try {
			await GroupsAPI.reorderBanners(target.group_id, { order: nextOrder });
		} catch (err) {
			toast(err?.message || __('Failed to reorder.', 'univer-smart-carousel'), 'error');
			await refreshCampaign();
		}
	};

	// ---------- Render ----------

	return (
		<div className="usc-grouped-editor">
			<div className="usc-grouped-editor__head">
				<div>
					<h3 className="usc-h3">
						{device === 'desktop'
							? __('Desktop groups', 'univer-smart-carousel')
							: __('Mobile groups', 'univer-smart-carousel')}
					</h3>
					<p className="usc-muted">
						{__(
							'Each group can be paused without losing its banners. Toggle the switch on the group header to hide a whole sub-campaign.',
							'univer-smart-carousel'
						)}
					</p>
				</div>
				<Button variant="primary" onClick={onCreateGroup} loading={creating}>
					+ {__('New group', 'univer-smart-carousel')}
				</Button>
			</div>

			{deviceGroups.length === 0 && (
				<div className="usc-banner-editor__empty">
					<p>
						{campaignId
							? __('No groups yet — create one to start adding banners.', 'univer-smart-carousel')
							: __('Save the carousel first, then add groups.', 'univer-smart-carousel')}
					</p>
				</div>
			)}

			<div className="usc-groups-list">
				{deviceGroups.map((group) => {
					const grpBanners = bannersByGroup(group.id);
					const isCollapsed = !!collapsed[group.id];
					return (
						<div
							key={group.id}
							className={classNames(
								'usc-group',
								!group.is_active && 'is-paused',
								isCollapsed && 'is-collapsed',
								dragGid === group.id && 'is-dragging',
								dropTargetGid === group.id && 'is-drop-target'
							)}
							onDragOver={onGroupDragOver(group)}
							onDragLeave={onGroupDragLeave(group)}
							onDrop={onGroupDrop(group)}
						>
							<header className="usc-group__head">
								<span
									className="usc-group__handle"
									draggable
									onDragStart={onGroupDragStart(group)}
									onDragEnd={onGroupDragEnd}
									title={__('Drag to reorder', 'univer-smart-carousel')}
									aria-label={__('Drag to reorder', 'univer-smart-carousel')}
								>
									<svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true">
										<path
											fill="currentColor"
											d="M9 4h2v2H9zm0 4h2v2H9zm0 4h2v2H9zm0 4h2v2H9zm4-12h2v2h-2zm0 4h2v2h-2zm0 4h2v2h-2zm0 4h2v2h-2z"
										/>
									</svg>
								</span>
								<button
									type="button"
									className="usc-group__chev"
									onClick={() =>
										setCollapsed((s) => ({ ...s, [group.id]: !s[group.id] }))
									}
									aria-label={__('Toggle group', 'univer-smart-carousel')}
								>
									<svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true">
										<path
											fill="currentColor"
											d={isCollapsed ? 'M9 18l6-6-6-6 1.41-1.41L17.83 12l-7.42 7.41z' : 'M7.41 8.59 12 13.17l4.59-4.58L18 10l-6 6-6-6z'}
										/>
									</svg>
								</button>
								<label className="usc-group__toggle">
									<input
										type="checkbox"
										checked={group.is_active}
										onChange={() => onToggleGroup(group)}
									/>
									<span className="usc-switch__track">
										<span className="usc-switch__thumb" />
									</span>
								</label>
								<button
									type="button"
									className="usc-group__name"
									onClick={() => {
										setRenaming(group);
										setDraftName(group.name);
									}}
								>
									{group.name}
								</button>
								<span className="usc-group__count">
									{sprintf(
										/* translators: %d: number of banners */
										__('%d banners', 'univer-smart-carousel'),
										grpBanners.length
									)}
								</span>
								<div className="usc-group__actions">
									<Button
										variant="secondary"
										size="sm"
										onClick={() => onAddBannersToGroup(group)}
									>
										+ {__('Banners', 'univer-smart-carousel')}
									</Button>
									<IconButton
										label={__('Delete group', 'univer-smart-carousel')}
										onClick={() => setConfirmDeleteGroup(group)}
									>
										<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true">
											<path
												fill="currentColor"
												d="M9 3v1H4v2h16V4h-5V3zm-3 5v12c0 1.1.9 2 2 2h8a2 2 0 0 0 2-2V8zm2 2h2v8H8zm4 0h2v8h-2z"
											/>
										</svg>
									</IconButton>
								</div>
							</header>

							{!isCollapsed && (
								<div className="usc-group__body">
									{grpBanners.length === 0 && (
										<div className="usc-group__empty">
											<p>{__('No banners in this group yet.', 'univer-smart-carousel')}</p>
											<Button
												variant="secondary"
												size="sm"
												onClick={() => onAddBannersToGroup(group)}
											>
												{__('Pick from media library', 'univer-smart-carousel')}
											</Button>
										</div>
									)}

									<ul className="usc-banner-list">
										{grpBanners.map((b) => (
											<li
												key={b.id}
												className={classNames(
													'usc-banner-card',
													!b.is_active && 'is-paused',
													dragBanner?.id === b.id && 'is-dragging',
													dropTargetBid === b.id && 'is-drop-target'
												)}
												onDragOver={onBannerDragOver(b)}
												onDragLeave={onBannerDragLeave(b)}
												onDrop={onBannerDrop(b)}
											>
												<span
													className="usc-banner-card__handle"
													draggable
													onDragStart={onBannerDragStart(b)}
													onDragEnd={onBannerDragEnd}
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
													onClick={() => onReplaceBannerImage(b)}
													title={__('Click to replace image', 'univer-smart-carousel')}
													aria-label={__('Click to replace image', 'univer-smart-carousel')}
												>
													{b.image?.url ? (
														<img src={b.image.url} alt="" loading="lazy" />
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
														label={__('Banner name (optional)', 'univer-smart-carousel')}
														placeholder={__(
															'Internal label — e.g. "Black Friday hero"',
															'univer-smart-carousel'
														)}
														value={b.name || ''}
														onChange={(e) =>
															onUpdateBannerField(b, { name: e.target.value })
														}
													/>
													<Input
														label={__('Destination URL', 'univer-smart-carousel')}
														type="url"
														placeholder="https://"
														value={b.link_url || ''}
														onChange={(e) =>
															onUpdateBannerField(b, { link_url: e.target.value })
														}
													/>
													<div className="usc-row-2">
														<Select
															label={__('Open link in', 'univer-smart-carousel')}
															options={TARGET_OPTIONS}
															value={b.link_target || '_self'}
															onChange={(v) =>
																onUpdateBannerField(b, { link_target: v })
															}
														/>
														<Input
															label={__('Alt text', 'univer-smart-carousel')}
															placeholder={__(
																'Describe the banner…',
																'univer-smart-carousel'
															)}
															value={b.alt_text || ''}
															onChange={(e) =>
																onUpdateBannerField(b, { alt_text: e.target.value })
															}
														/>
													</div>
												</div>

												<div className="usc-banner-card__actions">
													<label className="usc-banner-card__toggle" title={__('Active', 'univer-smart-carousel')}>
														<input
															type="checkbox"
															checked={b.is_active}
															onChange={() => onToggleBanner(b)}
														/>
														<span className="usc-switch__track">
															<span className="usc-switch__thumb" />
														</span>
													</label>
													<IconButton
														label={__('Duplicate banner', 'univer-smart-carousel')}
														onClick={() => onDuplicateBanner(b)}
													>
														<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true">
															<path
																fill="currentColor"
																d="M16 1H4a2 2 0 0 0-2 2v14h2V3h12zm3 4H8a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2m0 16H8V7h11z"
															/>
														</svg>
													</IconButton>
													<IconButton
														label={__('Delete banner', 'univer-smart-carousel')}
														onClick={() => setConfirmDeleteBanner(b)}
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
								</div>
							)}
						</div>
					);
				})}
			</div>

			{/* Rename group modal */}
			<Modal
				open={!!renaming}
				onClose={() => setRenaming(null)}
				title={__('Rename group', 'univer-smart-carousel')}
				footer={
					<>
						<Button variant="ghost" onClick={() => setRenaming(null)}>
							{__('Cancel', 'univer-smart-carousel')}
						</Button>
						<Button variant="primary" onClick={onRenameGroup}>
							{__('Save', 'univer-smart-carousel')}
						</Button>
					</>
				}
			>
				<Input
					label={__('Group name', 'univer-smart-carousel')}
					value={draftName}
					onChange={(e) => setDraftName(e.target.value)}
					autoFocus
				/>
			</Modal>

			{/* Delete group confirm */}
			<Modal
				open={!!confirmDeleteGroup}
				onClose={() => setConfirmDeleteGroup(null)}
				title={
					confirmDeleteGroup
						? sprintf(
								/* translators: %s: group name */
								__('Delete group "%s"?', 'univer-smart-carousel'),
								confirmDeleteGroup.name
						  )
						: ''
				}
				footer={
					<>
						<Button variant="ghost" onClick={() => setConfirmDeleteGroup(null)}>
							{__('Cancel', 'univer-smart-carousel')}
						</Button>
						<Button variant="danger" onClick={onDeleteGroup}>
							{__('Delete permanently', 'univer-smart-carousel')}
						</Button>
					</>
				}
			>
				<p>
					{__(
						'All banners inside this group will be deleted too. If you just want to hide them temporarily, use the toggle on the group header instead.',
						'univer-smart-carousel'
					)}
				</p>
			</Modal>

			{/* Delete banner confirm */}
			<Modal
				open={!!confirmDeleteBanner}
				onClose={() => setConfirmDeleteBanner(null)}
				title={__('Delete banner?', 'univer-smart-carousel')}
				footer={
					<>
						<Button variant="ghost" onClick={() => setConfirmDeleteBanner(null)}>
							{__('Cancel', 'univer-smart-carousel')}
						</Button>
						<Button variant="danger" onClick={onDeleteBanner}>
							{__('Delete', 'univer-smart-carousel')}
						</Button>
					</>
				}
			>
				<p>
					{__(
						'The banner will be removed from this group. The image stays in your media library.',
						'univer-smart-carousel'
					)}
				</p>
			</Modal>
		</div>
	);
}
