# Univer Smart Carousel

A WordPress plugin I built at Univerbeauty because none of the carousel plugins out there did what we actually needed.

Our marketing team ships a new banner campaign almost every week — Black Friday, Mother's Day, Mês do Profissional, you name it. Every plugin I tried either dragged 60kb of jQuery onto every page on the site, fought my theme's CSS, or had an admin so painful that designers refused to touch it. So I wrote this one over a few weekends.

It does one thing: lets marketing pick a few images (one set for desktop, one for mobile), drop two shortcodes anywhere on the site, and walk away.

## The two shortcodes

Every campaign you create gives you exactly two:

```
[carouseldesktop_christmas-2026]
[carouselmobile_christmas-2026]
```

The slug after the underscore matches the campaign name you typed in the admin. Paste them wherever — page, post, Elementor, theme template. If you need to show one on desktop and the other on mobile, wrap them in your own responsive container; I deliberately didn't bake media queries into the shortcode because every site handles responsive differently and I didn't want to fight anyone's theme.

## What's in the box

- A campaign editor under **Smart Carousel** in the WordPress sidebar
- Two device tabs — pick images from the media library, drag to reorder, set link/target/alt per banner
- Per-campaign knobs: slides per view (1 through 5, including 1.5/2.5/3.5/4.5), aspect ratio, gap, border radius, autoplay + delay, loop, navigation style (none/dots/arrows), progress bar, pause on hover, slide/fade transition
- Start/end dates so a campaign turns itself on and off (handy for set-and-forget Black Friday rollouts)
- Draft / Active / Paused status
- Plugin-wide **Settings tab** with a language switcher (English / Português Brasil) — flips the entire admin and the on-page accessibility text in one click

What's **not** in there yet: click tracking, A/B testing, role/page/device targeting. See the bottom — those are coming.

## Installing it

**The easy way:**

1. Grab the latest ZIP from [Releases](https://github.com/EuKennedy/univer-smart-carousel/releases)
2. In WP admin: Plugins → Add New → Upload Plugin
3. Activate. "Smart Carousel" shows up in the sidebar, just below WooCommerce.

**From source, if you want to hack on it:**

```bash
git clone https://github.com/EuKennedy/univer-smart-carousel.git
cd univer-smart-carousel
npm install && npm run build
```

Then drop the folder into `wp-content/plugins/` and activate.

## About performance

This is the part I actually cared about. Performance was the reason I wrote the thing.

The public-side bundle is ~10 KB gzipped (Embla Carousel + its autoplay plugin — no jQuery, no React, no polyfills). It only loads on pages that have one of the shortcodes in them; everywhere else pays nothing. The first slide gets a `<link rel="preload" as="image">` and renders with `loading="eager" fetchpriority="high"` so it doesn't murder your LCP. The rest are lazy. Autoplay pauses when the carousel scrolls offscreen (via `IntersectionObserver`) and is disabled entirely if the user has `prefers-reduced-motion` set.

If something in the bundle looks bloated to you, open an issue and I'll take a look.

## Public API (AI-friendly)

The plugin ships with a full REST API designed to be driven by AI agents and external integrations as easily as the bundled admin UI. Everything the admin can do, the API can do.

- Bearer-token auth with revocable, scoped (`read` / `write`) keys
- Single `/discover` endpoint returns the whole schema + examples in one document
- Verb-style endpoints (`/activate`, `/deactivate`, `/by-slug/{slug}`, per-banner CRUD) on top of the classic REST
- Partial updates — `PUT` preserves anything you don't send
- Cookie + nonce auth still works for in-browser callers (the bundled React admin uses this)

Quick start:

```bash
# 1. WP admin → Smart Carousel → Settings → API Keys → New key
# 2. See everything the API exposes:
curl https://your-site.com/wp-json/usc/v1/discover

# 3. List campaigns:
curl -H "Authorization: Bearer usc_live_xxx" \
  https://your-site.com/wp-json/usc/v1/campaigns
```

Full reference in [API.md](API.md).

## Database

Two tables, created with `dbDelta` on activation. They survive deactivation — turn the plugin off and on, your campaigns are still there.

- `wp_usc_campaigns` — name, slug, status, settings (JSON), scheduling window
- `wp_usc_banners` — image, link, target, alt, order, indexed on `(campaign_id, device)`

## Working on it locally

```bash
npm install
npm run build              # both bundles
npm run dev:admin          # watch the React admin
npm run dev:frontend       # watch the public carousel
```

Build output lives in `dist/` and is committed to the repo on purpose — that way installing from a release ZIP is plug-and-play, no `npm install` for end users.

## What's next

A handful of things I wanted but refused to block the first release on:

- Click + impression tracking with a small in-admin dashboard
- Native A/B testing at the campaign level
- Targeting by device, user role, or page
- A Gutenberg block (for editors that don't love shortcodes)
- WP-CLI commands for scripted rollouts

If any of those would actually help you, open an issue and tell me which one — that's how I'll prioritize.

## PRs and bugs

Both welcome. For PRs, open an issue first so we can talk through the approach — saves both of us a round trip. For bugs, please include the WordPress version, PHP version, and active theme; nine times out of ten the weird carousel bug in the wild is a theme leaking CSS.

## License

**MIT with the Commons Clause.**

In plain English: download it, install it, modify it, run it on your site (commercial site or not), use it for as many clients as you want — go wild. What you **cannot** do is sell the plugin itself, repackage it under a different name and sell that, include it in a paid bundle, or host it as a paid SaaS product.

If you're using it on your own site or your client's site, you're fine. If you're trying to make money by reselling the plugin itself, you're not — and I'll enforce that.

Copyright (c) 2026 **Kennedy Rodrigues Gomes Teixeira**. All rights reserved. See [LICENSE](LICENSE) for the full text.

— Kennedy / [Univerbeauty](https://github.com/EuKennedy)
