#!/usr/bin/env node
/**
 * Drop a "silence is golden" index.php into a directory that Vite just built,
 * because `emptyOutDir: true` blows away anything in there.
 *
 * Usage: node tools/silence.js dist/admin
 */
const fs = require('node:fs');
const path = require('node:path');

const target = process.argv[2];
if (!target) {
	console.error('silence.js: missing target directory');
	process.exit(1);
}

const file = path.join(process.cwd(), target, 'index.php');
fs.mkdirSync(path.dirname(file), { recursive: true });
fs.writeFileSync(file, '<?php // Silence is golden.\n', 'utf8');
console.log(`silence.js: wrote ${file}`);
