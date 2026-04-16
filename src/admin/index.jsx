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
import App from './App';
import './styles/index.css';

const ROOT_ID = 'usc-admin-root';

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
