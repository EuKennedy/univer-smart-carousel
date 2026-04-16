/**
 * Aspect ratio picker.
 *
 * A free-form input ("21/9", "1560x1080", "auto", "16:9", whatever) plus
 * a row of preset chips below for the common ratios. Letting the user
 * type beats forcing them through a select when their banner is some
 * weird size like 1560×1080.
 */

import { __ } from '@wordpress/i18n';
import { classNames } from '../lib/utils';

const PRESETS = [
	{ value: 'auto', label: 'Auto' },
	{ value: '21/9', label: '21:9' },
	{ value: '16/9', label: '16:9' },
	{ value: '4/3', label: '4:3' },
	{ value: '1/1', label: '1:1' },
	{ value: '4/5', label: '4:5' },
	{ value: '9/16', label: '9:16' },
];

export default function AspectRatioField({ label, hint, value, onChange }) {
	const current = String(value ?? '').trim();

	return (
		<div className="usc-field usc-aspect-field">
			{label && <label className="usc-field__label">{label}</label>}
			<input
				type="text"
				className="usc-input"
				value={current}
				onChange={(e) => onChange(e.target.value)}
				placeholder={__('e.g. auto, 16/9, 1560x1080', 'univer-smart-carousel')}
				spellCheck="false"
				autoComplete="off"
			/>
			<div className="usc-aspect-presets" role="group" aria-label={__('Preset ratios', 'univer-smart-carousel')}>
				{PRESETS.map((p) => (
					<button
						key={p.value}
						type="button"
						className={classNames('usc-chip', current === p.value && 'is-active')}
						onClick={() => onChange(p.value)}
					>
						{p.label}
					</button>
				))}
			</div>
			{hint && <span className="usc-field__hint">{hint}</span>}
		</div>
	);
}
