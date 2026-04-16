/**
 * Small set of UI primitives. Hand-rolled for the look we want — closer to
 * Linear/Stripe than the WP defaults.
 */

import { useEffect, useState, useCallback } from '@wordpress/element';
import { classNames } from '../lib/utils';

export function Button({
	variant = 'primary',
	size = 'md',
	leftIcon,
	rightIcon,
	loading = false,
	className,
	children,
	...rest
}) {
	return (
		<button
			type="button"
			className={classNames(
				'usc-btn',
				`usc-btn--${variant}`,
				`usc-btn--${size}`,
				loading && 'is-loading',
				className
			)}
			disabled={rest.disabled || loading}
			{...rest}
		>
			{loading ? <Spinner small /> : leftIcon}
			<span>{children}</span>
			{rightIcon}
		</button>
	);
}

export function IconButton({ label, children, className, ...rest }) {
	return (
		<button
			type="button"
			aria-label={label}
			title={label}
			className={classNames('usc-icon-btn', className)}
			{...rest}
		>
			{children}
		</button>
	);
}

export function Input({ label, hint, error, className, id, ...rest }) {
	const fieldId = id || `usc-input-${useId()}`;
	return (
		<div className={classNames('usc-field', error && 'has-error', className)}>
			{label && (
				<label htmlFor={fieldId} className="usc-field__label">
					{label}
				</label>
			)}
			<input id={fieldId} className="usc-input" {...rest} />
			{(hint || error) && (
				<span className={classNames('usc-field__hint', error && 'is-error')}>
					{error || hint}
				</span>
			)}
		</div>
	);
}

export function Select({ label, hint, options, value, onChange, className, id, ...rest }) {
	const fieldId = id || `usc-select-${useId()}`;
	return (
		<div className={classNames('usc-field', className)}>
			{label && (
				<label htmlFor={fieldId} className="usc-field__label">
					{label}
				</label>
			)}
			<select
				id={fieldId}
				className="usc-input"
				value={value}
				onChange={(e) => onChange(e.target.value)}
				{...rest}
			>
				{options.map((opt) => (
					<option key={opt.value} value={opt.value}>
						{opt.label}
					</option>
				))}
			</select>
			{hint && <span className="usc-field__hint">{hint}</span>}
		</div>
	);
}

export function Switch({ checked, onChange, label, hint }) {
	return (
		<label className="usc-switch">
			<input
				type="checkbox"
				checked={!!checked}
				onChange={(e) => onChange(e.target.checked)}
			/>
			<span className="usc-switch__track">
				<span className="usc-switch__thumb" />
			</span>
			<span className="usc-switch__copy">
				<span className="usc-switch__label">{label}</span>
				{hint && <span className="usc-switch__hint">{hint}</span>}
			</span>
		</label>
	);
}

export function Badge({ tone = 'neutral', children }) {
	return <span className={classNames('usc-badge', `usc-badge--${tone}`)}>{children}</span>;
}

export function Card({ children, className, padded = true }) {
	return (
		<div className={classNames('usc-card', padded && 'is-padded', className)}>{children}</div>
	);
}

export function SectionHeader({ eyebrow, title, description, actions }) {
	return (
		<header className="usc-section-header">
			<div>
				{eyebrow && <span className="usc-eyebrow">{eyebrow}</span>}
				<h2 className="usc-h2">{title}</h2>
				{description && <p className="usc-section-header__desc">{description}</p>}
			</div>
			{actions && <div className="usc-section-header__actions">{actions}</div>}
		</header>
	);
}

export function Spinner({ small = false }) {
	return <span className={classNames('usc-spinner', small && 'is-small')} aria-hidden="true" />;
}

export function EmptyState({ icon, title, description, action }) {
	return (
		<div className="usc-empty">
			{icon && <div className="usc-empty__icon">{icon}</div>}
			<h3 className="usc-empty__title">{title}</h3>
			{description && <p className="usc-empty__desc">{description}</p>}
			{action && <div className="usc-empty__action">{action}</div>}
		</div>
	);
}

/* ---------------- Toast ---------------- */

let toastNotify = null;

export function toast(message, tone = 'success') {
	if (toastNotify) toastNotify(message, tone);
}

export function ToastHost() {
	const [items, setItems] = useState([]);

	const push = useCallback((message, tone) => {
		const id = Math.random().toString(36).slice(2);
		setItems((prev) => [...prev, { id, message, tone }]);
		setTimeout(() => {
			setItems((prev) => prev.filter((i) => i.id !== id));
		}, 4000);
	}, []);

	useEffect(() => {
		toastNotify = push;
		return () => {
			toastNotify = null;
		};
	}, [push]);

	return (
		<div className="usc-toast-host" role="status" aria-live="polite">
			{items.map((item) => (
				<div key={item.id} className={`usc-toast usc-toast--${item.tone}`}>
					{item.message}
				</div>
			))}
		</div>
	);
}

/* ---------------- Modal ---------------- */

export function Modal({ open, onClose, title, children, footer }) {
	useEffect(() => {
		if (!open) return undefined;
		const onKey = (e) => {
			if (e.key === 'Escape') onClose();
		};
		document.addEventListener('keydown', onKey);
		document.body.style.overflow = 'hidden';
		return () => {
			document.removeEventListener('keydown', onKey);
			document.body.style.overflow = '';
		};
	}, [open, onClose]);

	if (!open) return null;
	return (
		<div className="usc-modal" role="dialog" aria-modal="true" aria-labelledby="usc-modal-title">
			<div className="usc-modal__backdrop" onClick={onClose} />
			<div className="usc-modal__panel">
				<header className="usc-modal__header">
					<h3 id="usc-modal-title" className="usc-modal__title">
						{title}
					</h3>
					<IconButton label="Close" onClick={onClose}>
						<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
							<path
								fill="currentColor"
								d="M19 6.41 17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"
							/>
						</svg>
					</IconButton>
				</header>
				<div className="usc-modal__body">{children}</div>
				{footer && <footer className="usc-modal__footer">{footer}</footer>}
			</div>
		</div>
	);
}

/* ---------------- useId polyfill ---------------- */

let _id = 0;
function useId() {
	const [id] = useState(() => `f${++_id}`);
	return id;
}
