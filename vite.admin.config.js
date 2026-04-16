import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import path from 'node:path';

/**
 * Admin React app build.
 *
 * - JSX transformed via React, but React itself is provided by WordPress
 *   through `@wordpress/element` at runtime — we map both `react` and
 *   `react-dom` to the global `window.wp.element`.
 * - Output is an IIFE so it just runs when enqueued.
 */
export default defineConfig({
	plugins: [
		react({
			jsxRuntime: 'classic',
		}),
	],
	resolve: {
		alias: {
			react: path.resolve(__dirname, 'src/admin/lib/react-shim.js'),
			'react-dom': path.resolve(__dirname, 'src/admin/lib/react-shim.js'),
			'react/jsx-runtime': path.resolve(__dirname, 'src/admin/lib/jsx-shim.js'),
		},
	},
	build: {
		outDir: 'dist/admin',
		emptyOutDir: true,
		sourcemap: false,
		minify: 'esbuild',
		cssCodeSplit: false,
		lib: {
			entry: path.resolve(__dirname, 'src/admin/index.jsx'),
			name: 'UniverSmartCarouselAdmin',
			formats: ['iife'],
			fileName: () => 'index.js',
		},
		rollupOptions: {
			external: ['@wordpress/element', '@wordpress/i18n', '@wordpress/api-fetch'],
			output: {
				globals: {
					'@wordpress/element': 'wp.element',
					'@wordpress/i18n': 'wp.i18n',
					'@wordpress/api-fetch': 'wp.apiFetch',
				},
				assetFileNames: (info) => {
					if (info.name && info.name.endsWith('.css')) return 'index.css';
					return '[name][extname]';
				},
			},
		},
	},
});
