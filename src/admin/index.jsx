/*!
 * Univer Smart Carousel — admin entry point.
 *
 * Copyright (c) 2026 Kennedy Rodrigues Gomes Teixeira. All rights reserved.
 * Licensed under MIT with the Commons Clause. Commercial resale prohibited.
 * See LICENSE in the repository root for full terms.
 *
 * Mounts the React app onto the WP admin page shell.
 */

import { createRoot, render } from '@wordpress/element';
import { setLocaleData } from '@wordpress/i18n';
import App from './App';
import './styles/index.css';

const ROOT_ID = 'usc-admin-root';

// If the user picked a non-English language in Settings, the PHP side ships
// the dictionary inline on USC_CFG.translations. Apply it before any
// component calls __() for the first time.
const cfg = window.USC_CFG || {};
if (cfg.translations && cfg.translations.locale_data) {
	const data = cfg.translations.locale_data['univer-smart-carousel'];
	if (data) {
		setLocaleData(data, 'univer-smart-carousel');
	}
}

function mount() {
	const el = document.getElementById(ROOT_ID);
	if (!el) return;

	if (typeof createRoot === 'function') {
		createRoot(el).render(<App />);
	} else {
		// Older wp-element fallback.
		render(<App />, el);
	}
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', mount, { once: true });
} else {
	mount();
}
