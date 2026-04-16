/**
 * jsx-runtime shim — aliased in vite.admin.config.js to satisfy the
 * automatic JSX transform without bundling React. We map every jsx*
 * call to `wp.element.createElement`, which is what classic JSX does.
 */

const E = (typeof window !== 'undefined' && window.wp && window.wp.element) || {};
const { createElement, Fragment } = E;

function jsx(type, props, key) {
	const { children, ...rest } = props || {};
	if (key !== undefined) rest.key = key;
	return createElement(type, rest, children);
}

export { jsx, jsx as jsxs, jsx as jsxDEV, Fragment };
