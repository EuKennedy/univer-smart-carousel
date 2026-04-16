import { defineConfig } from 'vite';
import path from 'node:path';

/**
 * Public-facing carousel runtime build.
 *
 * - Self-contained IIFE (no globals required from the page).
 * - Tree-shaken Embla + Autoplay (~10kb gzipped together).
 * - CSS extracted into dist/frontend/index.css.
 */
export default defineConfig({
	build: {
		outDir: 'dist/frontend',
		emptyOutDir: true,
		sourcemap: false,
		minify: 'esbuild',
		cssCodeSplit: false,
		target: 'es2018',
		lib: {
			entry: path.resolve(__dirname, 'src/frontend/index.js'),
			name: 'UniverSmartCarousel',
			formats: ['iife'],
			fileName: () => 'index.js',
		},
		rollupOptions: {
			output: {
				assetFileNames: (info) => {
					if (info.name && info.name.endsWith('.css')) return 'index.css';
					return '[name][extname]';
				},
			},
		},
	},
});
