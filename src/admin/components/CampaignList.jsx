/**
 * Sidebar listing of campaigns with search + create.
 */

import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, Badge, EmptyState, Input } from './ui';
import { classNames, formatDate } from '../lib/utils';

const STATUS_TONES = {
	active: 'success',
	draft: 'neutral',
	paused: 'warning',
};

export default function CampaignList({
	campaigns,
	loading,
	activeId,
	onSelect,
	onCreate,
	onSearch,
	search,
}) {
	const [query, setQuery] = useState(search || '');

	return (
		<aside className="usc-list">
			<div className="usc-list__head">
				<div className="usc-list__head-row">
					<h2 className="usc-h3">{__('Campaigns', 'univer-smart-carousel')}</h2>
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
						placeholder={__('Search campaigns…', 'univer-smart-carousel')}
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

				{!loading && campaigns.length === 0 && (
					<EmptyState
						title={__('No campaigns yet', 'univer-smart-carousel')}
						description={__(
							'Create your first carousel campaign to get a shareable shortcode.',
							'univer-smart-carousel'
						)}
						action={
							<Button onClick={onCreate}>
								{__('Create campaign', 'univer-smart-carousel')}
							</Button>
						}
					/>
				)}

				{!loading &&
					campaigns.map((c) => (
						<button
							type="button"
							key={c.id}
							className={classNames('usc-list-item', activeId === c.id && 'is-active')}
							onClick={() => onSelect(c.id)}
						>
							<div className="usc-list-item__top">
								<span className="usc-list-item__name">{c.name}</span>
								<Badge tone={STATUS_TONES[c.status] || 'neutral'}>{c.status}</Badge>
							</div>
							<div className="usc-list-item__meta">
								<span className="usc-list-item__slug">{c.slug}</span>
								<span className="usc-list-item__date">{formatDate(c.updated_at)}</span>
							</div>
						</button>
					))}
			</div>
		</aside>
	);
}
