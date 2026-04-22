# Univer Smart Carousel — Public API

A REST API designed to be driven by AI agents and external integrations as easily as by humans. The whole admin UI is just a client of the same endpoints documented here.

## Quick start

1. In WP admin, go to **Smart Carousel → Settings → API Keys → New key**.
2. Pick a scope: **Read** (GET only) or **Read + Write** (full control).
3. Copy the key — you will only see it once. Format: `usc_live_xxxxxxxxxxxxxxxx…`
4. Send it as a Bearer token on every request:

```http
Authorization: Bearer usc_live_xxxxxxxxxxxxxxxx
```

5. Hit `GET /wp-json/usc/v1/discover` to see everything the API exposes.

## The `/discover` endpoint

This is the single most important endpoint. It returns a JSON document describing every model, endpoint, enum, and a handful of `curl` examples — enough for an LLM to drive the API end-to-end without reading anything else.

```bash
curl https://your-site.com/wp-json/usc/v1/discover
```

It's a public endpoint (no auth needed) because the schema reveals structure, not data.

## Authentication

| Auth method | When to use |
| --- | --- |
| `Authorization: Bearer usc_live_…` | External integrations, AI agents, automations. |
| WP cookie + nonce | The bundled React admin app. Anything in-browser, signed in. |

Both methods coexist. The same scope check (`read` vs `write`) applies to both. A leaked Bearer token can be revoked in the admin without affecting any user account.

### Scopes

- **`read`** — `GET` and `HEAD` only.
- **`write`** — read plus `POST`, `PUT`, `PATCH`, `DELETE`.

A `write` key implicitly has `read`.

API keys themselves can **only** be managed by a logged-in admin via cookie auth. A Bearer token can't mint another Bearer token — that boundary keeps key escalation contained if a token leaks.

## Endpoints

Base URL: `https://your-site.com/wp-json/usc/v1`

### Discovery

| Method | Path | Auth | Description |
| --- | --- | --- | --- |
| GET | `/discover` | public | Full API schema in one document. |

### Campaigns — primary CRUD

| Method | Path | Scope | Description |
| --- | --- | --- | --- |
| GET    | `/campaigns` | read | List campaigns. Query: `?search=…&status=draft\|active\|paused`. |
| POST   | `/campaigns` | write | Create. Body: campaign fields. |
| GET    | `/campaigns/{id}` | read | One campaign with banners. |
| PUT    | `/campaigns/{id}` | write | **Partial update** — omitted fields preserved. |
| DELETE | `/campaigns/{id}` | write | Delete + cascade banners. |

### Campaigns — verbose / AI-friendly

| Method | Path | Scope | Description |
| --- | --- | --- | --- |
| GET    | `/campaigns/by-slug/{slug}` | read | Look up by slug. |
| PUT    | `/campaigns/by-slug/{slug}` | write | Partial update by slug. |
| DELETE | `/campaigns/by-slug/{slug}` | write | Delete by slug. |
| POST   | `/campaigns/{id}/activate` | write | Set `status = active`. |
| POST   | `/campaigns/{id}/deactivate` | write | Set `status = paused`. |

### Banners (legacy nested routes)

| Method | Path | Scope | Description |
| --- | --- | --- | --- |
| GET    | `/campaigns/{id}/banners` | read | List banners. Query: `?device=desktop\|mobile`. |
| POST   | `/campaigns/{id}/banners` | write | Append a banner. Body: `{ device, image_id, link_url?, alt_text? }`. |
| DELETE | `/campaigns/{id}/banners/{bid}` | write | Remove one banner. |

### Groups

| Method | Path | Scope | Description |
| --- | --- | --- | --- |
| GET    | `/campaigns/{id}/groups` | read | List groups inside a carousel. Query: `?device=…`. |
| POST   | `/campaigns/{id}/groups` | write | Create a group. Body: `{ device, name }`. |
| GET    | `/groups/{gid}` | read | Get a single group. |
| PUT    | `/groups/{gid}` | write | Partial update — `name`, `is_active` (pause/resume), `sort_order`. |
| DELETE | `/groups/{gid}` | write | Delete + cascade banners. |
| POST   | `/groups/{gid}/banners` | write | Append a banner to the group. Body: `{ image_id, link_url?, alt_text?, link_target? }`. |
| POST   | `/campaigns/{id}/groups/reorder` | write | Rewrite the sort order of groups inside a carousel+device in one round-trip. Body: `{ device, order: [gid, gid, ...] }`. |

### Per-banner ops

| Method | Path | Scope | Description |
| --- | --- | --- | --- |
| PUT    | `/banners/{bid}` | write | Partial — toggle `is_active`, change `name`, `image_id` (swap image in place), `link_url`, `link_target`, `alt_text`, `sort_order`, `group_id`. |
| DELETE | `/banners/{bid}` | write | Remove a single banner. |
| POST   | `/banners/{bid}/duplicate` | write | Clone into the same group. `" (copy)"` is appended to the name if set. Returns `{ duplicated, id, source_id }`. |
| POST   | `/groups/{gid}/banners/reorder` | write | Rewrite sort_order for every banner in the group. Body: `{ order: [bid, bid, ...] }`. |

### Aliases

| Method | Path | Description |
| --- | --- | --- |
| `*` | `/carousels/*` | Alias for `/campaigns/*` — same controller, same payloads. The product calls these "carousels"; the data layer kept the original "campaign" name for backwards compatibility. |

### Settings

| Method | Path | Scope | Description |
| --- | --- | --- | --- |
| GET | `/settings` | read | Plugin-wide settings (e.g. language). |
| PUT | `/settings` | write | Partial update. |

### API keys (cookie-only)

| Method | Path | Auth | Description |
| --- | --- | --- | --- |
| GET    | `/api-keys` | cookie | List keys (no secrets). |
| POST   | `/api-keys` | cookie | Create. Body: `{ name, scope }`. **Plain key returned once.** |
| POST   | `/api-keys/{id}/revoke` | cookie | Soft revoke. |
| DELETE | `/api-keys/{id}` | cookie | Hard delete. |

## Examples

### List active campaigns

```bash
curl -H "Authorization: Bearer usc_live_xxx" \
  "https://your-site.com/wp-json/usc/v1/campaigns?status=active"
```

### Create a campaign

```bash
curl -X POST \
  -H "Authorization: Bearer usc_live_xxx" \
  -H "Content-Type: application/json" \
  -d '{"name": "Black Friday 2026", "status": "draft"}' \
  https://your-site.com/wp-json/usc/v1/campaigns
```

Returns the persisted campaign with `id`, `slug`, the two `shortcode_*` strings, and default settings.

### Look up by slug, then activate

```bash
# Step 1 — get the id (and confirm the campaign exists)
curl -H "Authorization: Bearer usc_live_xxx" \
  https://your-site.com/wp-json/usc/v1/campaigns/by-slug/black-friday-2026

# Step 2 — flip it live
curl -X POST -H "Authorization: Bearer usc_live_xxx" \
  https://your-site.com/wp-json/usc/v1/campaigns/123/activate
```

### Add a banner

You'll need a WP attachment ID first. Upload via the standard `/wp/v2/media` endpoint (or any plugin that returns an attachment id).

```bash
curl -X POST \
  -H "Authorization: Bearer usc_live_xxx" \
  -H "Content-Type: application/json" \
  -d '{
    "device": "desktop",
    "image_id": 1234,
    "link_url": "https://example.com/promo",
    "link_target": "_blank",
    "alt_text": "Black Friday — up to 70% off"
  }' \
  https://your-site.com/wp-json/usc/v1/campaigns/123/banners
```

### Tweak a single setting (partial update)

```bash
curl -X PUT \
  -H "Authorization: Bearer usc_live_xxx" \
  -H "Content-Type: application/json" \
  -d '{"settings": {"navigation": "dots"}}' \
  https://your-site.com/wp-json/usc/v1/campaigns/123
```

### Create a group, then add banners to it

```bash
# 1. Create a "Black Friday" group on the desktop tab
curl -X POST \
  -H "Authorization: Bearer usc_live_xxx" \
  -H "Content-Type: application/json" \
  -d '{"device":"desktop","name":"Black Friday"}' \
  https://your-site.com/wp-json/usc/v1/campaigns/123/groups
# → { id: 7, ... }

# 2. Append a banner
curl -X POST \
  -H "Authorization: Bearer usc_live_xxx" \
  -H "Content-Type: application/json" \
  -d '{"image_id":1234,"link_url":"https://example.com/promo","alt_text":"BF up to 70% off"}' \
  https://your-site.com/wp-json/usc/v1/groups/7/banners
```

### Pause a whole group during a swap

```bash
# Pause
curl -X PUT \
  -H "Authorization: Bearer usc_live_xxx" \
  -H "Content-Type: application/json" \
  -d '{"is_active": false}' \
  https://your-site.com/wp-json/usc/v1/groups/7

# …swap banners…

# Resume
curl -X PUT \
  -H "Authorization: Bearer usc_live_xxx" \
  -H "Content-Type: application/json" \
  -d '{"is_active": true}' \
  https://your-site.com/wp-json/usc/v1/groups/7
```

### Toggle one banner without affecting the rest

```bash
curl -X PUT \
  -H "Authorization: Bearer usc_live_xxx" \
  -H "Content-Type: application/json" \
  -d '{"is_active": false}' \
  https://your-site.com/wp-json/usc/v1/banners/45
```

`PUT` is partial. Sending `{"settings": {"navigation": "dots"}}` flips just the navigation style — name, slug, dates, banners, and every other setting are left alone.

## Error shape

Errors follow the standard WP REST shape:

```json
{
  "code": "usc_not_found",
  "message": "Campaign not found.",
  "data": { "status": 404 }
}
```

| HTTP | Code prefix | Meaning |
| --- | --- | --- |
| 401 | `usc_unauthenticated` | No valid auth was provided. |
| 403 | `usc_insufficient_scope` | Token is `read`, request needs `write`. |
| 404 | `usc_not_found` | The campaign / banner / key doesn't exist. |
| 500 | `usc_*_failed` | Server-side write failed. Check WP debug log. |

## Versioning

The API namespace is `/usc/v1`. Breaking changes will get a new namespace (`/usc/v2`); additions stay in v1.

## License

Same as the plugin: MIT with the Commons Clause. You may use the API for any purpose, commercial or not. You may not resell the plugin itself.
