/*!
 * Univer Smart Carousel — frontend runtime.
 *
 * Copyright (c) 2026 Kennedy Rodrigues Gomes Teixeira. All rights reserved.
 * Licensed under MIT with the Commons Clause. Commercial resale prohibited.
 * See LICENSE in the repository root for full terms.
 *
 * - Picks up every [data-usc-carousel] container on the page.
 * - Reads its config from data-usc-config (JSON).
 * - Wires Embla Carousel + (optional) Autoplay plugin.
 * - Renders dots, progress bar, and arrow handlers.
 * - Respects prefers-reduced-motion (disables autoplay).
 *
 * Built into a single IIFE bundle by Vite. ~10kb gzipped.
 */

import EmblaCarousel from 'embla-carousel';
import Autoplay from 'embla-carousel-autoplay';

import './index.css';

const PARSE_CONFIG = (el) => {
	try {
		return JSON.parse(el.dataset.uscConfig || '{}');
	} catch (e) {
		return {};
	}
};

const PREFERS_REDUCED_MOTION =
	typeof window !== 'undefined' &&
	window.matchMedia &&
	window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function init(el) {
	if (el.__uscInit) return;
	el.__uscInit = true;

	const config = PARSE_CONFIG(el);
	const viewport = el.querySelector('[data-usc-viewport]');
	const track = el.querySelector('[data-usc-track]');
	if (!viewport || !track) return;

	const slides = Array.from(track.children);
	if (slides.length === 0) return;

	// Single-slide carousel: no need for Embla. Just render and bail.
	if (slides.length === 1) {
		track.style.transform = 'none';
		const arrows = el.querySelectorAll('[data-usc-prev], [data-usc-next]');
		arrows.forEach((a) => a.remove());
		const dots = el.querySelector('[data-usc-dots]');
		if (dots) dots.remove();
		return;
	}

	const wantsAutoplay = !!config.autoplay && !PREFERS_REDUCED_MOTION;

	const plugins = [];
	if (wantsAutoplay) {
		plugins.push(
			Autoplay({
				delay: Math.max(1000, Number(config.autoplayDelay) || 5000),
				stopOnInteraction: false,
				stopOnMouseEnter: !!config.pauseOnHover,
				stopOnFocusIn: true,
			})
		);
	}

	const embla = EmblaCarousel(
		viewport,
		{
			loop: !!config.loop,
			align: 'start',
			containScroll: 'trimSnaps',
			dragFree: false,
			skipSnaps: false,
			duration: 28,
		},
		plugins
	);

	// Arrows
	const prevBtn = el.querySelector('[data-usc-prev]');
	const nextBtn = el.querySelector('[data-usc-next]');
	prevBtn?.addEventListener('click', () => embla.scrollPrev());
	nextBtn?.addEventListener('click', () => embla.scrollNext());

	const updateArrows = () => {
		if (!prevBtn || !nextBtn) return;
		if (embla.canScrollPrev()) {
			prevBtn.removeAttribute('disabled');
			prevBtn.removeAttribute('aria-disabled');
		} else {
			prevBtn.setAttribute('disabled', '');
			prevBtn.setAttribute('aria-disabled', 'true');
		}
		if (embla.canScrollNext()) {
			nextBtn.removeAttribute('disabled');
			nextBtn.removeAttribute('aria-disabled');
		} else {
			nextBtn.setAttribute('disabled', '');
			nextBtn.setAttribute('aria-disabled', 'true');
		}
	};

	// Dots
	const dotsContainer = el.querySelector('[data-usc-dots]');
	let dotNodes = [];
	if (dotsContainer) {
		const snaps = embla.scrollSnapList();
		snaps.forEach((_, i) => {
			const btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'usc-dot';
			btn.setAttribute('role', 'tab');
			btn.setAttribute(
				'aria-label',
				(window.wp?.i18n?.__ || ((s) => s))(`Go to slide ${i + 1}`, 'univer-smart-carousel')
			);
			btn.addEventListener('click', () => embla.scrollTo(i));
			dotsContainer.appendChild(btn);
			dotNodes.push(btn);
		});
	}

	const updateDots = () => {
		const selected = embla.selectedScrollSnap();
		dotNodes.forEach((node, i) => {
			node.classList.toggle('is-active', i === selected);
			node.setAttribute('aria-selected', i === selected ? 'true' : 'false');
		});
	};

	// Progress bar
	const progressBar = el.querySelector('[data-usc-progress] > span');
	const updateProgress = () => {
		if (!progressBar) return;
		const progress = Math.max(0, Math.min(1, embla.scrollProgress()));
		progressBar.style.transform = `translateX(${progress * 100 - 100}%)`;
	};

	// Pause autoplay when carousel is offscreen.
	if (wantsAutoplay && 'IntersectionObserver' in window) {
		const ap = embla.plugins().autoplay;
		const io = new IntersectionObserver(
			(entries) => {
				entries.forEach((entry) => {
					if (!ap) return;
					if (entry.isIntersecting) {
						ap.play();
					} else {
						ap.stop();
					}
				});
			},
			{ threshold: 0.25 }
		);
		io.observe(el);
	}

	embla.on('select', () => {
		updateArrows();
		updateDots();
	});
	embla.on('reInit', () => {
		updateArrows();
		updateDots();
	});
	embla.on('scroll', updateProgress);

	updateArrows();
	updateDots();
	updateProgress();
}

function initHeaderTop(el) {
	if (el.__uscHtInit) return;
	el.__uscHtInit = true;

	const config = PARSE_CONFIG(el);
	const viewport = el.querySelector('[data-usc-ht-viewport]');
	const container = el.querySelector('[data-usc-ht-container]');
	if (!viewport || !container) return;

	const slides = Array.from(container.children);
	if (slides.length === 0) return;

	// Single slide: render static, no Embla — saves a few KB of work.
	if (slides.length === 1) {
		container.style.transform = 'none';
		return;
	}

	// Reduced-motion users get a static first slide. Auto-rotation with
	// no interaction trigger is exactly what prefers-reduced-motion is
	// designed to suppress.
	if (PREFERS_REDUCED_MOTION) {
		container.style.transform = 'none';
		return;
	}

	const autoplay = Autoplay({
		delay: Math.max(1000, Number(config.autoplayDelay) || 4000),
		stopOnInteraction: false,
		stopOnMouseEnter: false,
		stopOnFocusIn: false,
	});

	EmblaCarousel(
		viewport,
		{
			loop: true,
			axis: 'y',
			align: 'start',
			containScroll: false,
			dragFree: false,
			skipSnaps: false,
			duration: Math.max(8, Math.min(60, Math.round((Number(config.transitionMs) || 600) / 20))),
		},
		[autoplay]
	);
}

function bootAll() {
	document.querySelectorAll('[data-usc-carousel]').forEach(init);
	document.querySelectorAll('[data-usc-header-top]').forEach(initHeaderTop);
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', bootAll, { once: true });
} else {
	bootAll();
}

// Re-scan for new carousels mounted dynamically (e.g., by AJAX or SPAs).
window.UniverSmartCarousel = { init: bootAll };
