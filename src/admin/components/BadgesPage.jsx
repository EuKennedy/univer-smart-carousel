/**
 * Offer badges manager.
 *
 * Lists every term of a WooCommerce attribute taxonomy (default:
 * pa_badge-ofertas) with its current swatch image, and lets the admin
 * rename the term + swap the image without leaving this page.
 *
 * Why this lives inside the Smart Carousel admin: marketing already
 * lives here to swap hero banners, and offer badges rotate on the
 * same cadence (Black Friday, Mother's Day, etc.). Consolidating the
 * two workflows saves the "which tab was I in?" context switch.
 */

import { useEffect, useState, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, Card, IconButton, Input, Modal, SectionHeader, Spinner, toast } from './ui';
import { Badges as API } from '../lib/api';
import { pickImages } from '../lib/media';

export default function BadgesPage() {
	const [badges, setBadges] = useState([]);
	const [loading, setLoading] = useState(true);
	const [taxonomy] = useState('pa_badge-ofertas'); // Fixed for now; surfaceable later if needed.

	const [editing, setEditing] = useState(null); // badge being edited
	const [draftName, setDraftName] = useState('');
	const [draftImage, setDraftImage] = useState(null); // { id, url } or null
	const [saving, setSaving] = useState(false);

	const load = useCallback(async () => {
		setLoading(true);
		try {
			const data = await API.list(taxonomy);
			setBadges(Array.isArray(data) ? data : []);
		} catch (err) {
			toast(err?.message || __('Failed to load badges.', 'univer-smart-carousel'), 'error');
		} finally {
			setLoading(false);
		}
	}, [taxonomy]);

	useEffect(() => {
		load();
	}, [load]);

	const openEditor = (badge) => {
		setEditing(badge);
		setDraftName(badge.name);
		setDraftImage(
			badge.swatch?.image
				? { id: badge.swatch.image.id, url: badge.swatch.image.url }
				: null
		);
	};

	const closeEditor = () => {
		setEditing(null);
		setDraftName('');
		setDraftImage(null);
	};

	const pickSwatch = async () => {
		try {
			const items = await pickImages({
				multiple: false,
				title: __('Choose the new swatch image', 'univer-smart-carousel'),
			});
			if (items[0]) {
				setDraftImage({ id: items[0].id, url: items[0].url });
			}
		} catch (err) {
			console.error(err);
		}
	};

	const save = async () => {
		if (!editing) return;
		setSaving(true);
		try {
			const payload = { taxonomy };
			const name = draftName.trim();
			if (name !== editing.name) {
				payload.name = name;
			}
			const currentId = editing.swatch?.image?.id || 0;
			const nextId = draftImage?.id || 0;
			if (nextId !== currentId) {
				payload.image_id = nextId;
				// Preserve whatever meta key the discovery picked, so we don't
				// accidentally write to a different key than the site's swatch
				// plugin reads from.
				if (editing.swatch?.meta_key) {
					payload.meta_key = editing.swatch.meta_key;
				}
			}

			// Nothing changed? Short-circuit.
			if (!payload.name && !('image_id' in payload)) {
				toast(__('Nothing changed.', 'univer-smart-carousel'), 'success');
				closeEditor();
				setSaving(false);
				return;
			}

			await API.update(editing.id, payload);
			toast(__('Badge updated.', 'univer-smart-carousel'), 'success');
			closeEditor();
			await load();
		} catch (err) {
			toast(err?.message || __('Failed to update badge.', 'univer-smart-carousel'), 'error');
		} finally {
			setSaving(false);
		}
	};

	return (
		<div className="usc-settings">
			<div className="usc-settings__inner">
				<header className="usc-settings__head">
					<div>
						<h1 className="usc-h1">{__('Offer badges', 'univer-smart-carousel')}</h1>
						<p className="usc-muted">
							{__(
								'Rename and re-swatch the terms of the pa_badge-ofertas attribute without leaving this page.',
								'univer-smart-carousel'
							)}
						</p>
					</div>
					<Button variant="secondary" onClick={load} disabled={loading}>
						{__('Refresh', 'univer-smart-carousel')}
					</Button>
				</header>

				<Card>
					<SectionHeader
						eyebrow={__('WooCommerce attribute', 'univer-smart-carousel')}
						title={`pa_badge-ofertas`}
						description={__(
							'Every term in this attribute is listed below. Click a card to edit its name and swatch image.',
							'univer-smart-carousel'
						)}
					/>

					{loading && (
						<div className="usc-app__loading">
							<Spinner />
							<span>{__('Loading…', 'univer-smart-carousel')}</span>
						</div>
					)}

					{!loading && badges.length === 0 && (
						<div className="usc-empty" style={{ margin: '24px 0' }}>
							<h3 className="usc-empty__title">{__('No badges found', 'univer-smart-carousel')}</h3>
							<p className="usc-empty__desc">
								{__(
									'The taxonomy pa_badge-ofertas exists but has no terms yet.',
									'univer-smart-carousel'
								)}
							</p>
						</div>
					)}

					{!loading && badges.length > 0 && (
						<ul className="usc-badges-grid">
							{badges.map((b) => (
								<li key={b.id} className="usc-badge-card">
									<button
										type="button"
										className="usc-badge-card__inner"
										onClick={() => openEditor(b)}
										aria-label={__('Edit badge', 'univer-smart-carousel')}
									>
										<div className="usc-badge-card__swatch">
											{b.swatch?.image?.url ? (
												<img src={b.swatch.image.url} alt="" loading="lazy" />
											) : (
												<span className="usc-badge-card__placeholder">
													{__('No image', 'univer-smart-carousel')}
												</span>
											)}
										</div>
										<div className="usc-badge-card__body">
											<span className="usc-badge-card__name">{b.name}</span>
											<span className="usc-badge-card__meta">
												{b.count} {__('products', 'univer-smart-carousel')}
											</span>
										</div>
									</button>
								</li>
							))}
						</ul>
					)}
				</Card>
			</div>

			<Modal
				open={!!editing}
				onClose={closeEditor}
				title={editing ? __('Edit badge', 'univer-smart-carousel') : ''}
				footer={
					<>
						<Button variant="ghost" onClick={closeEditor}>
							{__('Cancel', 'univer-smart-carousel')}
						</Button>
						<Button variant="primary" onClick={save} loading={saving}>
							{__('Save', 'univer-smart-carousel')}
						</Button>
					</>
				}
			>
				{editing && (
					<div className="usc-stack">
						<Input
							label={__('Badge name', 'univer-smart-carousel')}
							hint={__(
								'This is what appears under the product card and in the URL slug stays the same.',
								'univer-smart-carousel'
							)}
							value={draftName}
							onChange={(e) => setDraftName(e.target.value)}
							autoFocus
						/>

						<div className="usc-field">
							<label className="usc-field__label">
								{__('Swatch image', 'univer-smart-carousel')}
							</label>
							<div className="usc-badge-editor__swatch">
								<div className="usc-badge-editor__preview">
									{draftImage?.url ? (
										<img src={draftImage.url} alt="" />
									) : (
										<span className="usc-badge-card__placeholder">
											{__('No image', 'univer-smart-carousel')}
										</span>
									)}
								</div>
								<div className="usc-badge-editor__actions">
									<Button variant="secondary" onClick={pickSwatch}>
										{draftImage
											? __('Replace image', 'univer-smart-carousel')
											: __('Pick from media library', 'univer-smart-carousel')}
									</Button>
									{draftImage && (
										<Button
											variant="ghost"
											onClick={() => setDraftImage(null)}
										>
											{__('Remove', 'univer-smart-carousel')}
										</Button>
									)}
								</div>
							</div>
						</div>

						<p className="usc-muted" style={{ marginTop: 4 }}>
							{__('Prefer the native WordPress screen?', 'univer-smart-carousel')}{' '}
							<a href={editing.edit_url} target="_blank" rel="noopener noreferrer">
								{__('Open term editor', 'univer-smart-carousel')} ↗
							</a>
						</p>
					</div>
				)}
			</Modal>
		</div>
	);
}
