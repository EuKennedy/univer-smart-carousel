# Univer Smart Carousel

> Lightweight, premium banner carousel for WordPress and WooCommerce.
> Built for marketing teams that ship campaigns weekly and care about Web Vitals.

[![License: MIT](https://img.shields.io/badge/License-MIT-black.svg)](LICENSE)
![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777BB4.svg)
![WordPress 6.0+](https://img.shields.io/badge/WordPress-6.0%2B-21759B.svg)

---

## Why this exists

Most WordPress carousels ship 60+ KB of jQuery, hijack your CSS, and tank your LCP score.
**Univer Smart Carousel** is the opposite:

- **~10 KB gzipped** of vanilla JS on the public side (Embla + Autoplay)
- **Zero asset cost** on pages without a carousel — assets are conditionally enqueued
- **Two shortcodes per campaign** — one for desktop, one for mobile
- **Premium admin UI** — React, Linear/Stripe-grade design, drag-to-reorder banners
- **A11Y by default** — semantic markup, keyboard nav, `prefers-reduced-motion` respected
- **Built for LCP** — first slide is preloaded, eager + `fetchpriority="high"`
- **Per-banner click links**, target, and alt text
- **Slides per view: 1, 1.5, 2, 2.5, 3, 3.5, 4, 4.5, 5** (independent for desktop / mobile)
- **Scheduling** — start/end dates per campaign

---

## Installation

### From release ZIP

1. Download the latest release ZIP from the [Releases page](https://github.com/EuKennedy/univer-smart-carousel/releases).
2. In WordPress admin, go to **Plugins → Add New → Upload Plugin** and select the ZIP.
3. Click **Activate**.
4. A new **Smart Carousel** entry appears in the WordPress sidebar (just below WooCommerce).

### From source

```bash
git clone https://github.com/EuKennedy/univer-smart-carousel.git
cd univer-smart-carousel
npm install
npm run build
```

Then drop the entire `univer-smart-carousel/` folder into `wp-content/plugins/` and activate.

---

## Quick start

1. Open **Smart Carousel** in the WordPress sidebar.
2. Click **New** to create a campaign — give it a name (e.g. *Christmas 2026*).
3. Pick **desktop banners** and **mobile banners** from your media library.
   Each banner can have its own destination URL, link target, and alt text.
4. Adjust **Layout** (slides per view, gap, aspect ratio) and **Behavior** (autoplay, loop, dots, arrows).
5. Set **Status** to **Active** and click **Save**.
6. Copy the generated shortcodes from the right-hand panel:

```text
[carouseldesktop_christmas-2026]
[carouselmobile_christmas-2026]
```

7. Paste them anywhere on your site — page, post, Elementor, hooks, theme template.
   Wrap them in your own responsive container if you want to hide one on the other breakpoint:

```html
<div class="hidden md:block">[carouseldesktop_christmas-2026]</div>
<div class="block md:hidden">[carouselmobile_christmas-2026]</div>
```

---

## Admin

The admin page is a single-page React app mounted on `/wp-admin/admin.php?page=univer-smart-carousel`.
It lets you:

- Create, edit, duplicate, schedule, and delete campaigns
- Pick images via the WordPress Media Library (multi-select supported)
- Drag-reorder banners within each device tab
- Preview the generated shortcodes with one-click copy
- Configure per-campaign:
  - Slides per view (1 → 5, including half-steps)
  - Aspect ratio (21/9, 16/9, 4/3, 1/1, 4/5, 9/16)
  - Autoplay + delay
  - Loop, arrows, dots, progress bar, pause-on-hover
  - Border radius, gap, transition (slide / fade)

---

## How it stays light on the frontend

Most carousel plugins enqueue their assets on every page. We don't.

1. The shortcode handler sets a flag the first time it actually runs.
2. The frontend loader hooks into `wp_footer` and **only enqueues** the JS/CSS if that flag is set.
3. The bundle is a single ~10 KB gzipped IIFE — no jQuery, no React, no polyfills.
4. The first slide is rendered with `<link rel="preload" as="image">` for a clean LCP.
5. Subsequent slides are `loading="lazy" decoding="async"`.
6. `IntersectionObserver` pauses autoplay when the carousel scrolls offscreen.
7. `prefers-reduced-motion: reduce` disables autoplay entirely.

---

## Architecture

```
univer-smart-carousel/
├── univer-smart-carousel.php      # Main plugin file (constants, hooks, boot)
├── includes/
│   ├── autoload.php
│   ├── class-plugin.php           # Singleton orchestrator
│   ├── admin/
│   │   └── class-admin-loader.php
│   ├── database/
│   │   ├── class-database-installer.php
│   │   └── class-campaign-repository.php
│   ├── frontend/
│   │   ├── class-frontend-loader.php
│   │   └── class-carousel-renderer.php
│   ├── rest-api/
│   │   ├── class-rest-api-module.php
│   │   └── v1/
│   │       └── class-campaigns-controller.php
│   └── shortcode/
│       └── class-shortcode-handler.php
├── src/
│   ├── admin/                     # React admin app (built with Vite)
│   └── frontend/                  # Embla wiring (built with Vite)
└── dist/
    ├── admin/index.{js,css}       # Built admin assets
    └── frontend/index.{js,css}    # Built frontend assets
```

### REST API

All admin operations go through `usc/v1`:

| Method | Path                  | Description                        |
| :----- | :-------------------- | :--------------------------------- |
| GET    | `/campaigns`          | List campaigns (search, status)    |
| POST   | `/campaigns`          | Create a campaign with banners     |
| GET    | `/campaigns/{id}`     | Get a single campaign with banners |
| PUT    | `/campaigns/{id}`     | Update a campaign                  |
| DELETE | `/campaigns/{id}`     | Delete a campaign                  |

All endpoints require the `manage_options` capability and a valid REST nonce
(handled automatically by `@wordpress/api-fetch`).

### Database

Two tables, created via `dbDelta` on activation:

- `wp_usc_campaigns` — campaign metadata + JSON settings + scheduling window
- `wp_usc_banners`   — per-campaign banners (one row per banner per device), with link/target/alt/order

Tables are **not** dropped on deactivation — uninstalling and reinstalling preserves data.

---

## Development

```bash
# Install dependencies
npm install

# Build everything
npm run build

# Watch mode (separate terminals)
npm run dev:admin
npm run dev:frontend
```

Vite outputs `dist/admin/index.{js,css}` and `dist/frontend/index.{js,css}`, both committed
to the repository so end users can install from a release ZIP without running a build.

### Coding standards

- PHP 7.4+, namespaced `\Univer\SmartCarousel\…`
- WordPress class file naming (`class-foo-bar.php`)
- All input sanitized at the boundary (`sanitize_text_field`, `esc_url_raw`, `absint`, …)
- All output escaped (`esc_html`, `esc_attr`, `esc_url`)
- All REST endpoints have a `permission_callback`
- All DB queries go through `Campaign_Repository`

### Pull requests

PRs are welcome. Please:

1. Open an issue describing the change first.
2. Match the existing code style.
3. Include a short note in the PR body about Web Vitals impact.

---

## Roadmap

The first release covers everything marketing needs to ship banners by themselves.
Phase 2 will add:

- **Click + impression tracking** with a built-in dashboard
- **Native A/B testing** at the campaign level
- **Targeting** by user role, device, page, geo
- **Block editor (Gutenberg) embed**
- **WP-CLI commands** for scripted campaign rollouts

---

## License

[MIT](LICENSE) © Kennedy / Univerbeauty.
