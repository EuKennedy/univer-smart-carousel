/**
 * Aliased to "react" / "react-dom" in vite.admin.config.js.
 *
 * `@wordpress/element` is a thin React-compatible facade shipped by core,
 * so we route both `react` and `react-dom` to the same global. This way:
 *   - JSX (classic transform) compiles to `React.createElement(...)` and finds it.
 *   - Components calling `import { useState } from 'react'` keep working.
 *   - We don't ship our own React copy in the bundle.
 */

// `wp.element` is exposed globally by WordPress when @wordpress/element is enqueued.
const E = (typeof window !== 'undefined' && window.wp && window.wp.element) || {};

export const createElement = E.createElement;
export const Fragment = E.Fragment;
export const Component = E.Component;
export const PureComponent = E.PureComponent;
export const useState = E.useState;
export const useEffect = E.useEffect;
export const useRef = E.useRef;
export const useMemo = E.useMemo;
export const useCallback = E.useCallback;
export const useContext = E.useContext;
export const useLayoutEffect = E.useLayoutEffect;
export const useReducer = E.useReducer;
export const useId = E.useId;
export const createContext = E.createContext;
export const forwardRef = E.forwardRef;
export const memo = E.memo;
export const Children = E.Children;
export const cloneElement = E.cloneElement;
export const isValidElement = E.isValidElement;
export const createRef = E.createRef;
export const Suspense = E.Suspense;
export const lazy = E.lazy;

// react-dom-equivalents
export const render = E.render;
export const createRoot = E.createRoot;
export const unmountComponentAtNode = E.unmountComponentAtNode;

const React = {
	createElement,
	Fragment,
	Component,
	PureComponent,
	useState,
	useEffect,
	useRef,
	useMemo,
	useCallback,
	useContext,
	useLayoutEffect,
	useReducer,
	useId,
	createContext,
	forwardRef,
	memo,
	Children,
	cloneElement,
	isValidElement,
	createRef,
	Suspense,
	lazy,
	render,
	createRoot,
};

export default React;
