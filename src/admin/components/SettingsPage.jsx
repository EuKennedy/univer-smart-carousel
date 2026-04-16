/**
 * Plugin-wide Settings page.
 *
 * Currently a single section (Language). Built for growth — add new
 * cards under <main> and wire them through the same `update` flow.
 */

import { useEffect, useState, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, Card, SectionHeader, Select, Spinner, toast } from './ui';
import { Settings as API } from '../lib/api';

const LANGUAGE_OPTIONS = [
	{ value: 'en', label: __('English', 'univer-smart-carousel') },
	{ value: 'pt-br', label: __('Português (Brasil)', 'univer-smart-carousel') },
];

export default function SettingsPage({ onSaved }) {
	const [settings, setSettings] = useState(null);
	const [loading, setLoading] = useState(true);
	const [saving, setSaving] = useState(false);

	const load = useCallback(async () => {
		setLoading(true);
		try {
			const data = await API.get();
			setSettings(data);
		} catch (err) {
			toast(err?.message || __('Failed to load settings.', 'univer-smart-carousel'), 'error');
		} finally {
			setLoading(false);
		}
	}, []);

	useEffect(() => {
		load();
	}, [load]);

	const setField = (patch) => setSettings((prev) => ({ ...prev, ...patch }));

	const handleSave = async () => {
		setSaving(true);
		try {
			const saved = await API.update(settings);
			setSettings(saved);
			toast(__('Settings saved.', 'univer-smart-carousel'), 'success');
			if (typeof onSaved === 'function') {
				onSaved(saved);
			}
		} catch (err) {
			toast(err?.message || __('Save failed.', 'univer-smart-carousel'), 'error');
		} finally {
			setSaving(false);
		}
	};

	if (loading || !settings) {
		return (
			<div className="usc-app__loading">
				<Spinner />
				<span>{__('Loading…', 'univer-smart-carousel')}</span>
			</div>
		);
	}

	return (
		<div className="usc-settings">
			<div className="usc-settings__inner">
				<header className="usc-settings__head">
					<div>
						<h1 className="usc-h1">{__('Settings', 'univer-smart-carousel')}</h1>
						<p className="usc-muted">
							{__(
								'Plugin-wide configuration. These apply to every campaign.',
								'univer-smart-carousel'
							)}
						</p>
					</div>
					<Button variant="primary" onClick={handleSave} loading={saving}>
						{__('Save settings', 'univer-smart-carousel')}
					</Button>
				</header>

				<Card>
					<SectionHeader
						eyebrow={__('General', 'univer-smart-carousel')}
						title={__('Language', 'univer-smart-carousel')}
						description={__(
							'Used in the admin and on the carousel rendered on your site. Independent of your WordPress site language.',
							'univer-smart-carousel'
						)}
					/>
					<div className="usc-stack">
						<Select
							label={__('Plugin language', 'univer-smart-carousel')}
							options={LANGUAGE_OPTIONS}
							value={settings.language || 'en'}
							onChange={(v) => setField({ language: v })}
						/>
						<p className="usc-muted">
							{__(
								'Reload the admin page after saving to see the new language take effect.',
								'univer-smart-carousel'
							)}
						</p>
					</div>
				</Card>
			</div>
		</div>
	);
}
