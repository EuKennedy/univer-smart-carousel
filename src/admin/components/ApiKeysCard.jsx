/**
 * API Keys management card.
 *
 * Lives inside the Settings page. Lists existing keys, lets the admin
 * create new ones, and revoke / delete them. The plain key is shown
 * only on creation in a one-shot reveal — copy or lose.
 */

import { useEffect, useState, useCallback } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
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
	toast,
} from './ui';
import { ApiKeys as API } from '../lib/api';
import { copyToClipboard, formatDate } from '../lib/utils';

const SCOPE_OPTIONS = [
	{ value: 'read', label: __('Read only — GET requests', 'univer-smart-carousel') },
	{ value: 'write', label: __('Read + Write — full control', 'univer-smart-carousel') },
];

export default function ApiKeysCard() {
	const [keys, setKeys] = useState([]);
	const [loading, setLoading] = useState(true);
	const [creating, setCreating] = useState(false);

	// Modals
	const [createOpen, setCreateOpen] = useState(false);
	const [revealKey, setRevealKey] = useState(null); // { key, record } after create
	const [confirmDelete, setConfirmDelete] = useState(null); // record

	// Create form state
	const [draftName, setDraftName] = useState('');
	const [draftScope, setDraftScope] = useState('read');

	const refresh = useCallback(async () => {
		setLoading(true);
		try {
			const list = await API.list();
			setKeys(list);
		} catch (err) {
			toast(err?.message || __('Failed to load API keys.', 'univer-smart-carousel'), 'error');
		} finally {
			setLoading(false);
		}
	}, []);

	useEffect(() => {
		refresh();
	}, [refresh]);

	const onCreate = async () => {
		setCreating(true);
		try {
			const result = await API.create({ name: draftName, scope: draftScope });
			setRevealKey(result);
			setCreateOpen(false);
			setDraftName('');
			setDraftScope('read');
			await refresh();
		} catch (err) {
			toast(err?.message || __('Failed to create API key.', 'univer-smart-carousel'), 'error');
		} finally {
			setCreating(false);
		}
	};

	const onRevoke = async (record) => {
		try {
			await API.revoke(record.id);
			toast(__('Key revoked.', 'univer-smart-carousel'), 'success');
			await refresh();
		} catch (err) {
			toast(err?.message || __('Failed to revoke.', 'univer-smart-carousel'), 'error');
		}
	};

	const onDelete = async () => {
		if (!confirmDelete) return;
		try {
			await API.remove(confirmDelete.id);
			toast(__('Key deleted.', 'univer-smart-carousel'), 'success');
			setConfirmDelete(null);
			await refresh();
		} catch (err) {
			toast(err?.message || __('Failed to delete.', 'univer-smart-carousel'), 'error');
		}
	};

	const onCopy = (text) => {
		copyToClipboard(text).then(() => toast(__('Copied!', 'univer-smart-carousel'), 'success'));
	};

	return (
		<Card>
			<SectionHeader
				eyebrow={__('Integrations', 'univer-smart-carousel')}
				title={__('API Keys', 'univer-smart-carousel')}
				description={__(
					'Use these keys to control the plugin programmatically — from AI agents, Zapier, custom scripts, anywhere. Send the key as Authorization: Bearer …',
					'univer-smart-carousel'
				)}
				actions={
					<Button variant="primary" onClick={() => setCreateOpen(true)}>
						{__('New key', 'univer-smart-carousel')}
					</Button>
				}
			/>

			{loading && (
				<div className="usc-app__loading">
					<Spinner />
				</div>
			)}

			{!loading && keys.length === 0 && (
				<div className="usc-empty" style={{ margin: '24px 0' }}>
					<h3 className="usc-empty__title">{__('No API keys yet', 'univer-smart-carousel')}</h3>
					<p className="usc-empty__desc">
						{__(
							'Create your first key to give an AI or external system access to this plugin.',
							'univer-smart-carousel'
						)}
					</p>
				</div>
			)}

			{!loading && keys.length > 0 && (
				<table className="usc-keys-table">
					<thead>
						<tr>
							<th>{__('Name', 'univer-smart-carousel')}</th>
							<th>{__('Key', 'univer-smart-carousel')}</th>
							<th>{__('Scope', 'univer-smart-carousel')}</th>
							<th>{__('Last used', 'univer-smart-carousel')}</th>
							<th>{__('Status', 'univer-smart-carousel')}</th>
							<th aria-label={__('Actions', 'univer-smart-carousel')} />
						</tr>
					</thead>
					<tbody>
						{keys.map((k) => (
							<tr key={k.id}>
								<td>{k.name}</td>
								<td>
									<code className="usc-mono">{k.masked}</code>
								</td>
								<td>
									<Badge tone={k.scope === 'write' ? 'warning' : 'neutral'}>
										{k.scope}
									</Badge>
								</td>
								<td className="usc-muted">{k.last_used_at ? formatDate(k.last_used_at) : '—'}</td>
								<td>
									{k.is_active ? (
										<Badge tone="success">{__('Active', 'univer-smart-carousel')}</Badge>
									) : (
										<Badge tone="danger">{__('Revoked', 'univer-smart-carousel')}</Badge>
									)}
								</td>
								<td className="usc-keys-table__actions">
									{k.is_active && (
										<Button variant="ghost" size="sm" onClick={() => onRevoke(k)}>
											{__('Revoke', 'univer-smart-carousel')}
										</Button>
									)}
									<IconButton
										label={__('Delete key', 'univer-smart-carousel')}
										onClick={() => setConfirmDelete(k)}
									>
										<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true">
											<path
												fill="currentColor"
												d="M9 3v1H4v2h16V4h-5V3zm-3 5v12c0 1.1.9 2 2 2h8a2 2 0 0 0 2-2V8zm2 2h2v8H8zm4 0h2v8h-2z"
											/>
										</svg>
									</IconButton>
								</td>
							</tr>
						))}
					</tbody>
				</table>
			)}

			{/* Create modal */}
			<Modal
				open={createOpen}
				onClose={() => setCreateOpen(false)}
				title={__('New API key', 'univer-smart-carousel')}
				footer={
					<>
						<Button variant="ghost" onClick={() => setCreateOpen(false)}>
							{__('Cancel', 'univer-smart-carousel')}
						</Button>
						<Button variant="primary" onClick={onCreate} loading={creating}>
							{__('Generate key', 'univer-smart-carousel')}
						</Button>
					</>
				}
			>
				<div className="usc-stack">
					<Input
						label={__('Name', 'univer-smart-carousel')}
						hint={__('Where will this key be used? e.g. "ChatGPT — Black Friday rollout".', 'univer-smart-carousel')}
						value={draftName}
						onChange={(e) => setDraftName(e.target.value)}
						placeholder="ChatGPT — Black Friday"
					/>
					<Select
						label={__('Scope', 'univer-smart-carousel')}
						options={SCOPE_OPTIONS}
						value={draftScope}
						onChange={setDraftScope}
					/>
				</div>
			</Modal>

			{/* Reveal modal — shown ONCE after creation */}
			<Modal
				open={!!revealKey}
				onClose={() => setRevealKey(null)}
				title={__('Copy your new API key', 'univer-smart-carousel')}
				footer={
					<Button variant="primary" onClick={() => setRevealKey(null)}>
						{__('I copied it', 'univer-smart-carousel')}
					</Button>
				}
			>
				<p className="usc-muted">
					{__(
						'This is the only time you will see this key. Store it somewhere safe — if you lose it, you will need to generate a new one.',
						'univer-smart-carousel'
					)}
				</p>
				<div className="usc-key-reveal">
					<code className="usc-mono">{revealKey?.key}</code>
					<IconButton
						label={__('Copy', 'univer-smart-carousel')}
						onClick={() => onCopy(revealKey?.key || '')}
					>
						<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true">
							<path
								fill="currentColor"
								d="M16 1H4a2 2 0 0 0-2 2v14h2V3h12zm3 4H8a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2m0 16H8V7h11z"
							/>
						</svg>
					</IconButton>
				</div>
				<p className="usc-muted" style={{ marginTop: 12 }}>
					{__('Send it as a Bearer token:', 'univer-smart-carousel')}
				</p>
				<pre className="usc-codeblock">Authorization: Bearer {revealKey?.key}</pre>
			</Modal>

			{/* Delete confirm */}
			<Modal
				open={!!confirmDelete}
				onClose={() => setConfirmDelete(null)}
				title={sprintf(
					/* translators: %s: key name */
					__('Delete %s?', 'univer-smart-carousel'),
					confirmDelete?.name || ''
				)}
				footer={
					<>
						<Button variant="ghost" onClick={() => setConfirmDelete(null)}>
							{__('Cancel', 'univer-smart-carousel')}
						</Button>
						<Button variant="danger" onClick={onDelete}>
							{__('Delete permanently', 'univer-smart-carousel')}
						</Button>
					</>
				}
			>
				<p>
					{__(
						'Any integration using this key will stop working immediately. You can revoke instead if you want to keep the audit trail.',
						'univer-smart-carousel'
					)}
				</p>
			</Modal>
		</Card>
	);
}
