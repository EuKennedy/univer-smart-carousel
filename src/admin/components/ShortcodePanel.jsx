/**
 * Card showing the two generated shortcodes with one-click copy.
 */

import { __ } from '@wordpress/i18n';
import { Card, SectionHeader, IconButton } from './ui';

function Row({ label, code, onCopy }) {
	return (
		<div className="usc-shortcode-row">
			<span className="usc-shortcode-row__label">{label}</span>
			<div className="usc-shortcode-row__code">
				<code>{code}</code>
				<IconButton label={__('Copy', 'univer-smart-carousel')} onClick={() => onCopy(code)}>
					<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true">
						<path
							fill="currentColor"
							d="M16 1H4a2 2 0 0 0-2 2v14h2V3h12zm3 4H8a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2m0 16H8V7h11z"
						/>
					</svg>
				</IconButton>
			</div>
		</div>
	);
}

export default function ShortcodePanel({ desktop, mobile, onCopy }) {
	return (
		<Card className="usc-shortcode-card">
			<SectionHeader
				eyebrow={__('Embed', 'univer-smart-carousel')}
				title={__('Shortcodes', 'univer-smart-carousel')}
				description={__(
					'Paste these anywhere on your site. The carousel will only render while the campaign is Active.',
					'univer-smart-carousel'
				)}
			/>
			<div className="usc-stack">
				<Row
					label={__('Desktop', 'univer-smart-carousel')}
					code={desktop}
					onCopy={onCopy}
				/>
				<Row
					label={__('Mobile', 'univer-smart-carousel')}
					code={mobile}
					onCopy={onCopy}
				/>
			</div>
		</Card>
	);
}
