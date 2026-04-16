# Security

## How API keys are stored

When you generate an API key in **Settings → API Keys**, the plain key (e.g. `usc_live_aBcD…`) is shown to you **once** and never written to disk in plain text. What gets persisted in `wp_usc_api_keys` is:

| Column | What it holds | Why |
| --- | --- | --- |
| `key_prefix` | First 12 characters of the key (e.g. `usc_live_aBcD`). | Lets the lookup index narrow to ~1 row before doing the hash check. Not a secret on its own — exposing it doesn't grant access. |
| `key_hash` | `wp_hash_password($plain)` — the WordPress wrapper around bcrypt. | One-way. Can't be reversed back into the plain key. A database leak is bad, but not "everyone can call the API as you" bad. |

So if someone steals a copy of the database, they get prefixes (which alone do nothing) and bcrypt hashes (which can't be replayed against the API). They do **not** get usable API keys.

## What can a leaked Bearer token do?

If a plain API key (`usc_live_…`) leaks, the holder gains exactly what the key's scope allows:

- `read` scope → list / fetch carousels, groups, banners, settings.
- `write` scope → create / update / delete carousels, groups, banners, settings.

That holder **cannot**:

- Create more API keys (mint new tokens).
- Read or modify other plugins' data.
- Touch the WordPress core, users, options outside of `usc_global_settings`.
- Escalate to `manage_options` outside our namespace.

Mint operations (`POST /api-keys`, `DELETE /api-keys/{id}`) are deliberately locked to **cookie-authenticated administrators** in `wp-admin`. A Bearer token can't extend itself.

## What to do when a key leaks

1. WP admin → **Smart Carousel → Settings → API Keys**.
2. Find the key by its prefix or name.
3. Click **Revoke** (soft — keeps the row for audit) or **Delete** (hard).

The change takes effect on the next request. There's no token cache to wait on.

## Reporting a vulnerability

If you find a security issue, please **do not** open a public GitHub issue. Email the maintainer directly:

- Kennedy Rodrigues Gomes Teixeira — open an issue marked `security` and request a private contact, or reach out via the org channels.

A reasonable disclosure window is requested before any public writeup.

## What this repository never contains

This is a **public** repository. The following are protected by `.gitignore` and should never appear in a commit:

- `.env`, `.env.*` — environment files
- `*.pem`, `*.key`, `*.crt`, `*.p12`, `*.pfx` — TLS material and private keys
- `*.gpg`, `*.asc` — PGP keys
- `id_rsa*`, `id_ed25519*`, `*_rsa`, `*_ed25519` — SSH keys
- `secrets.json`, `secrets.yml`, `credentials.*`, `auth.json`, `.netrc`, `.pgpass` — credential files
- `wp-config.php`, `wp-config-*.php` — WordPress configuration with DB credentials and salts
- `*.sql`, `*.sql.gz`, `*.dump`, `*.bak`, `*.backup`, `backup-*`, `dump-*` — database dumps (production data)
- `wp-content/uploads/`, `wp-content/cache/` — runtime data
- `node_modules/`, `vendor/` — dependency trees
- `.claude/` — local AI tooling state
- `*.zip` — packaged distributions (built on demand, not source)

If you ever accidentally commit something sensitive: rotate the secret immediately and consider the value compromised regardless of what GitHub history says. Force-pushing is **not** a fix — assume anything that touched the remote was scraped.

## Audit checklist

Before publishing or before each release:

```bash
# Look for real-looking tokens (not just mentions) in tracked files
git ls-files | xargs grep -hEo \
  "(ghp_[A-Za-z0-9]{20,}|usc_live_[A-Za-z0-9]{16,}|sk-[A-Za-z0-9]{20,}|AKIA[0-9A-Z]{16}|xox[baprs]-[A-Za-z0-9-]{10,})" \
  2>/dev/null | sort -u

# Look for accidentally-committed credential files
git ls-files | grep -iE "\.(env|pem|key|crt|p12|sql|dump)$|secrets|credentials|password|wp-config\.php"
```

Both should return empty. If anything appears, address before tagging the release.
