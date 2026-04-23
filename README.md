# Univer Smart Carousel

A WordPress + WooCommerce banner carousel that stays out of your way. Built at Univerbeauty for marketing teams that rotate hero banners weekly and care about Core Web Vitals.

Other carousel plugins drag 60 KB of jQuery onto every page on your site, fight your theme's CSS, and hand your designers an admin that's painful enough to avoid. This one does the opposite: ~10 KB of vanilla JS, loaded only on pages that actually render a carousel, driven from a React admin that looks closer to Linear than to wp-admin.

## Why two shortcodes (desktop + mobile)

This is the most overlooked vantage of the plugin, and it's the one marketing loves once they get used to it.

Every carousel you create gives you **two fixed shortcodes**:

```
[carouseldesktop_my-carousel]
[carouselmobile_my-carousel]
```

The slug after the underscore never changes — it's locked to the carousel you created in the admin. That means:

- **Swap a banner once, it flips everywhere.** Your designer uploads the new hero in the admin; every page that uses that shortcode updates on the next render. No page builder rework, no copy-paste across templates, no "wait, which page was that banner on?".
- **Desktop and mobile stay independent.** They're rendered by separate shortcodes with their own image sets, aspect ratios, and layouts — so you don't have to force-fit a 1920×650 desktop hero into a mobile viewport with CSS tricks.
- **Marketing doesn't need a developer on call.** Give them admin access to one WP screen and they can rotate campaigns forever.

The design team exports the banners, marketing rotates them in the Smart Carousel admin, and the two shortcodes sitting inside your pages Just Keep Working.

## Using the shortcodes on Elementor (important)

Because the desktop and mobile shortcodes render independently, you need to tell Elementor which one to show on which breakpoint. Otherwise the same visitor will see both stacked on top of each other.

The setup is two widgets, each with a visibility rule:

**Step 1 — Insert both shortcodes**, usually as two Shortcode widgets stacked in the same section:

```
[carouseldesktop_my-carousel]
[carouselmobile_my-carousel]
```

**Step 2 — Configure visibility on each widget.** Click the widget → **Advanced** → **Responsive**:

| Shortcode | Hide on desktop | Hide on tablet | Hide on mobile |
| --- | :---: | :---: | :---: |
| `[carouseldesktop_*]` | ❌ off | ✅ **on** | ✅ **on** |
| `[carouselmobile_*]` | ✅ **on** | ❌ off | ❌ off |

So:
- **Desktop shortcode** → hide on tablet + mobile (only desktop shows it).
- **Mobile shortcode** → hide on desktop (tablet + mobile show it, since tablet viewports are closer to mobile in practice).

If you're not on Elementor, the same principle applies: wrap each shortcode in a container with your theme's responsive visibility utility (Astra, Bricks, Blocksy, Kadence all have equivalents), or add your own media queries around them.

## Features at a glance

- **Three-level content model**: Carousel → Groups → Banners. Groups let you split a single carousel into sub-campaigns (Black Friday, Mother's Day) that you can pause/resume independently without losing banners.
- **Drag-to-reorder** groups and banners. Group-level and banner-level toggles to hide sub-campaigns or individual banners temporarily.
- **Click the thumbnail to replace an image** — no delete-and-re-add cycle.
- **Duplicate banners** in one click, handy for A/B-ish variants with different destination links.
- **Optional per-banner internal name** ("Black Friday hero") — separate from alt text.
- **Per-campaign image optimization**: resize + recompress + WebP on demand, variants cached in `uploads/`. Default settings cut a typical 2.5 MB hero banner to 280–400 KB with no visible quality loss.
- **Slides per view** from 1 to 5 (half steps included: 1.5, 2.5, 3.5, 4.5).
- **Aspect ratio** accepts presets (`16/9`, `21/9`, `1/1`) **and free-form** (`1560x1080`, `16:9`, `auto`).
- **Navigation**: none / dots / arrows. Autoplay, loop, pause-on-hover, slide/fade transition.
- **Scheduling**: start and end dates. The carousel renders an admin-only warning when out of window so you know why it's invisible.
- **Accessibility by default**: semantic markup, keyboard nav, `prefers-reduced-motion` respected, ARIA labels on every control.
- **i18n**: bundled English + Brazilian Portuguese, switchable in Settings independent of the site language.

## Performance

The public bundle is **~10 KB gzipped** — Embla Carousel and its autoplay plugin, no jQuery, no React, no polyfills. It only loads on pages that have one of the shortcodes; everywhere else pays nothing.

LCP-friendly by default:

- The first slide gets a `<link rel="preload" as="image">` hint (with the WebP variant when available), and the `<img>` renders with `loading="eager" fetchpriority="high"`.
- Subsequent slides are `loading="lazy" decoding="async"`.
- Autoplay pauses when the carousel scrolls offscreen (via `IntersectionObserver`).
- Autoplay is disabled entirely when the user has `prefers-reduced-motion` set.

On a 1920×650 hero banner with default optimization settings:

| Variant | Size | vs original |
| --- | --: | --: |
| Original (Q90 JPEG) | ~420 KB | baseline |
| Optimized JPEG (Q82) | ~180 KB | −57% |
| WebP at same resolution | ~110 KB | −74% |

## Public API (AI-friendly)

Everything the admin can do, an AI agent or external integration can do. The plugin ships with a full REST API under `/wp-json/usc/v1`:

- Bearer-token auth, revocable + scoped (`read` / `write`).
- Single `/discover` endpoint returns the full schema + examples in one JSON document.
- Verb-style endpoints (`/activate`, `/deactivate`, `/by-slug/{slug}`, per-banner CRUD) on top of the classic REST.
- Partial updates: `PUT` preserves any field you don't send.
- Cookie + nonce auth also works for in-browser callers (the bundled React admin uses this).

Quick start:

```bash
# 1. WP admin → Smart Carousel → Settings → API Keys → New key
# 2. See everything the API exposes:
curl https://your-site.com/wp-json/usc/v1/discover

# 3. List your carousels:
curl -H "Authorization: Bearer usc_live_xxx" \
  https://your-site.com/wp-json/usc/v1/campaigns
```

Full reference in [API.md](API.md).

## Installation

**From a release ZIP (easiest):**

1. Grab the latest ZIP from the [Releases](https://github.com/EuKennedy/univer-smart-carousel/releases) page.
2. In WP admin: Plugins → Add New → Upload Plugin.
3. Activate. "Smart Carousel" shows up in the sidebar.

**From source, if you want to hack on it:**

```bash
git clone https://github.com/EuKennedy/univer-smart-carousel.git
cd univer-smart-carousel
npm install && npm run build
```

Then drop the folder into `wp-content/plugins/` and activate.

## Requirements

- WordPress 6.0+
- PHP 7.4+
- WooCommerce optional (nothing breaks without it — the plugin just doesn't assume you have it)
- For WebP image optimization: GD with WebP support (default in PHP 7.1+) **or** Imagick with WebP enabled. Without either, the plugin silently serves JPEG-only and still works fine.

## Database

Four tables, created via `dbDelta` on activation. They survive deactivation — turn the plugin off and on, your carousels are still there.

- `wp_usc_campaigns` — carousel metadata + JSON settings + scheduling window.
- `wp_usc_banner_groups` — named groups inside a carousel, per device, with pause toggle.
- `wp_usc_banners` — image, name, link, target, alt, order, active toggle.
- `wp_usc_api_keys` — hashed Bearer tokens (plain key never persisted).

## Working on it locally

```bash
npm install
npm run build              # both bundles
npm run dev:admin          # watch the React admin
npm run dev:frontend       # watch the public carousel
```

Build output lives in `dist/` and is committed on purpose — that way installing from a release ZIP is plug-and-play, no `npm install` needed for end users.

## What's next

- Click + impression tracking with a small in-admin dashboard.
- Native A/B testing at the campaign level.
- Targeting by device / user role / page.
- A Gutenberg block for editors that don't love shortcodes.
- WP-CLI commands for scripted rollouts.

If any of these would actually help you, open an issue and say which — that's how priorities get set.

## Contributing

PRs and bug reports are both welcome. For PRs, open an issue first so we can talk through the approach — saves both of us a round trip. For bugs, please include the WordPress version, PHP version, and active theme. Nine times out of ten, a weird carousel bug in the wild is a theme leaking CSS onto our elements.

Security-relevant findings go to the [security policy](SECURITY.md), not public issues.

## License

**MIT with the Commons Clause.**

In plain English: download it, install it, modify it, run it on your site (commercial or not), use it for as many clients as you want — go wild. What you **cannot** do is sell the plugin itself, repackage it under a different name and sell that, include it in a paid bundle, or host it as a paid SaaS product.

If you're using it on your own site or your client's site, you're fine. If you're trying to make money reselling the plugin itself, you're not — and it will be enforced.

Copyright (c) 2026 **Kennedy Rodrigues Gomes Teixeira**. All rights reserved. See [LICENSE](LICENSE) for the full text.

— Kennedy / [Univerbeauty](https://github.com/EuKennedy)
