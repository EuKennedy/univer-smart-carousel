/**
 * Grouped banner editor.
 *
 * Three-level tree:
 *   Carousel
 *     └── Group (named, can be paused)
 *           └── Banner (image + link, can be paused)
 *
 * Each group is a self-contained accordion with its own header
 * (toggle, name, banner count, delete) and a banner list inside.
 *
 * Mutations are eager — clicking the toggle hits the API immediately
 * so marketing can pause a group during a swap without juggling a
 * "save" button. Banner edits batch into the parent CampaignEditor's
 * save (still partial, still preserves untouched fields).
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
	const [dragGid, setDragGid] = useState(null);
	const [dropTargetGid, setDropTargetGid] = useState(null);

	const deviceGroups = (groups || []).filter((g) => g.device === device);
	const bannersByGroup = (groupId) =>
		(banners || []).filter((b) => b.device === device && b.group_id === groupId);

	// Reload the authoritative state from the server. We call this after
	// any structural mutation (adding/deleting a banner, deleting a group,
	// reordering) rather than juggling fragile local diffs. Keeps state
	// impossible to desync, at the cost of one extra round trip per op —
	// which is fine, they're infrequent. Toggle / rename / text-field
	// edits stay eager-optimistic.
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

	const removeGroupLocally = (gid) => {
		onChange({
			groups: (groups || []).filter((g) => g.id !== gid),
			banners: (banners || []).filter((b) => b.group_id !== gid),
		});
	};

	const removeBannerLocally = (bid) => {
		onChange({ banners: (banners || []).filter((b) => b.id !== bid) });
	};

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
		updateGroupLocally(group.id, { is_active: next }); // optimistic
		try {
			await GroupsAPI.update(group.id, { is_active: next });
		} catch (err) {
			updateGroupLocally(group.id, { is_active: group.is_active }); // rollback
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

		// Fire all adds sequentially — server needs them in order to get
		// the sort_order increments right. Any individual failure surfaces
		// via toast but doesn't block the rest.
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

		// Single authoritative refresh — the server already holds the full
		// truth (every insert ran against a fresh MAX(sort_order)).
		// This replaces the previous approach of diff-ing local state,
		// which could duplicate banners when the addBanner response was
		// re-folded in across iterations.
		await refreshCampaign();
	};

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

	// ---------- Drag-to-reorder groups ----------
	//
	// HTML5 drag API. We track the id being dragged and the id we're
	// hovering over, then on drop we recompute the ordered list of gids
	// for the current device and ship it to the reorder endpoint.
	const onGroupDragStart = (group) => (e) => {
		setDragGid(group.id);
		// `effectAllowed = 'move'` plus an explicit text payload keeps
		// Firefox from swallowing the drag.
		if (e.dataTransfer) {
			e.dataTransfer.effectAllowed = 'move';
			e.dataTransfer.setData('text/plain', String(group.id));
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

		// Optimistic local update — reorder the groups array to match.
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

	const onUpdateBannerField = (banner, patch) => {
		// Optimistic local + debounce-ish save: just fire-and-forget the
		// PUT. Worst case it errors and the next action will reload.
		updateBannerLocally(banner.id, patch);
		BannersAPI.update(banner.id, patch).catch((err) =>
			toast(err?.message || __('Failed to save.', 'univer-smart-carousel'), 'error')
		);
	};

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
												className={classNames('usc-banner-card', !b.is_active && 'is-paused')}
											>
												<div className="usc-banner-card__image">
													{b.image?.url ? (
														<img src={b.image.url} alt="" loading="lazy" />
													) : (
														<span className="usc-banner-card__placeholder">
															{__('No image', 'univer-smart-carousel')}
														</span>
													)}
												</div>

												<div className="usc-banner-card__fields">
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
