/**
 * Banner editor for one device tab. Lets the user:
 *   - Pick images (multi-select via WP Media Library)
 *   - Drag to reorder
 *   - Edit per-banner link, target, alt
 *   - Remove
 */

import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { Button, IconButton, Input, Select } from './ui';
import { pickImages } from '../lib/media';
import { classNames } from '../lib/utils';

const TARGET_OPTIONS = [
	{ value: '_self', label: __('Same tab', 'univer-smart-carousel') },
	{ value: '_blank', label: __('New tab', 'univer-smart-carousel') },
];

export default function BannerEditor({ device, banners, onChange }) {
	const [dragId, setDragId] = useState(null);

	const update = (idx, patch) => {
		onChange(banners.map((b, i) => (i === idx ? { ...b, ...patch } : b)));
	};

	const remove = (idx) => {
		onChange(banners.filter((_, i) => i !== idx));
	};

	const onDragStart = (idx) => () => setDragId(idx);

	const onDrop = (overIdx) => (e) => {
		e.preventDefault();
		if (dragId === null || dragId === overIdx) return;
		const next = [...banners];
		const [moved] = next.splice(dragId, 1);
		next.splice(overIdx, 0, moved);
		onChange(next);
		setDragId(null);
	};

	const onDragOver = (e) => e.preventDefault();

	const handlePick = async () => {
		try {
			const items = await pickImages({
				multiple: true,
				title:
					device === 'desktop'
						? __('Select desktop banners', 'univer-smart-carousel')
						: __('Select mobile banners', 'univer-smart-carousel'),
			});
			const newOnes = items.map((img) => ({
				image_id: img.id,
				image: img,
				link_url: '',
				link_target: '_self',
				link_rel: '',
				alt_text: img.alt || '',
			}));
			onChange([...banners, ...newOnes]);
		} catch (err) {
			console.error(err);
		}
	};

	return (
		<div className="usc-banner-editor">
			<div className="usc-banner-editor__head">
				<div>
					<h3 className="usc-h3">
						{device === 'desktop'
							? __('Desktop banners', 'univer-smart-carousel')
							: __('Mobile banners', 'univer-smart-carousel')}
					</h3>
					<p className="usc-muted">
						{__(
							'Drag a card to reorder. Click an image to replace.',
							'univer-smart-carousel'
						)}
					</p>
				</div>
				<Button
					variant="secondary"
					onClick={handlePick}
					leftIcon={
						<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true">
							<path
								fill="currentColor"
								d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6z"
							/>
						</svg>
					}
				>
					{__('Add banners', 'univer-smart-carousel')}
				</Button>
			</div>

			{banners.length === 0 && (
				<div className="usc-banner-editor__empty">
					<p>{__('No banners added yet.', 'univer-smart-carousel')}</p>
					<Button onClick={handlePick}>
						{__('Pick from media library', 'univer-smart-carousel')}
					</Button>
				</div>
			)}

			<ul className="usc-banner-list">
				{banners.map((b, idx) => (
					<li
						key={`${b.image_id}-${idx}`}
						className={classNames('usc-banner-card', dragId === idx && 'is-dragging')}
						draggable
						onDragStart={onDragStart(idx)}
						onDragOver={onDragOver}
						onDrop={onDrop(idx)}
					>
						<button
							type="button"
							className="usc-banner-card__image"
							onClick={async () => {
								try {
									const items = await pickImages({
										multiple: false,
										title: __('Replace banner', 'univer-smart-carousel'),
									});
									if (items[0]) {
										update(idx, { image_id: items[0].id, image: items[0] });
									}
								} catch (err) {
									console.error(err);
								}
							}}
							aria-label={__('Replace image', 'univer-smart-carousel')}
						>
							{b.image?.url ? (
								<img src={b.image.url} alt="" loading="lazy" />
							) : (
								<span className="usc-banner-card__placeholder">{__('No image', 'univer-smart-carousel')}</span>
							)}
							<span className="usc-banner-card__handle" aria-hidden="true">
								<svg viewBox="0 0 24 24" width="14" height="14">
									<path fill="currentColor" d="M9 4h2v2H9zm0 4h2v2H9zm0 4h2v2H9zm0 4h2v2H9zm4-12h2v2h-2zm0 4h2v2h-2zm0 4h2v2h-2zm0 4h2v2h-2z" />
								</svg>
							</span>
						</button>

						<div className="usc-banner-card__fields">
							<Input
								label={__('Destination URL', 'univer-smart-carousel')}
								type="url"
								placeholder="https://"
								value={b.link_url || ''}
								onChange={(e) => update(idx, { link_url: e.target.value })}
							/>
							<div className="usc-row-2">
								<Select
									label={__('Open link in', 'univer-smart-carousel')}
									options={TARGET_OPTIONS}
									value={b.link_target || '_self'}
									onChange={(v) => update(idx, { link_target: v })}
								/>
								<Input
									label={__('Alt text', 'univer-smart-carousel')}
									placeholder={__('Describe the banner…', 'univer-smart-carousel')}
									value={b.alt_text || ''}
									onChange={(e) => update(idx, { alt_text: e.target.value })}
								/>
							</div>
						</div>

						<div className="usc-banner-card__actions">
							<IconButton
								label={__('Remove banner', 'univer-smart-carousel')}
								onClick={() => remove(idx)}
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
	);
}
