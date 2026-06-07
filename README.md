# Orilyt Security Hardening

> 🇫🇷 [Version française](README.fr.md)

A zero-config WordPress hardening plugin that stops attackers from discovering your login credentials — the reconnaissance step behind most brute-force campaigns.

Born from a real targeted attack campaign (April 2026) against a fleet of production WordPress sites. Deployed since on 30+ sites across three hosting providers.

## What it blocks

| Protection | WordPress weakness | Behavior |
|---|---|---|
| **Author enumeration** | `/?author=N` redirects to `/author/username/`, leaking every login by iterating N | Hard 404 |
| **REST API user listing** | `/wp-json/wp/v2/users` publicly lists accounts (login, slug) | 401 for anonymous visitors |
| **Lost-password abuse** | The reset form confirms whether an account exists, and can be spammed (it sends real emails) | Max 5 attempts / 15 min / IP, and the response is identical whether the account exists or not |
| **Login error leaks** | "Unknown username" vs "incorrect password" confirms which logins exist | Single generic "Invalid credentials" message |

The common thread: a brute-force attack needs a **login** and a password. This plugin makes the login undiscoverable, and slows down everything else.

## Installation

**Option A — from the WordPress admin (recommended)**
1. Download the ZIP from the [latest release](../../releases/latest) (or *Code → Download ZIP*).
2. *Plugins → Add New → Upload Plugin* → activate.
3. On activation, the protection installs itself as a **must-use plugin** (`wp-content/mu-plugins/`) — it cannot be disabled from the admin, even by a compromised account. The visible plugin acts as the on/off switch and shows a status indicator.

**Option B — manual (mu-plugin only)**
Copy `mu/0-orilyt-hardening.php` into `wp-content/mu-plugins/` (create the folder if needed). No activation required.

## Requirements

WordPress 5.x+, PHP 7.4+. Single site and multisite. No settings screen, no database tables (only 15-minute transients for rate limiting), no measurable performance impact.

## Caveats

- **Behind a proxy/CDN** (Cloudflare…): `REMOTE_ADDR` is the proxy IP, so the rate limit becomes global instead of per-visitor. Restore the real client IP server-side first.
- **Headless setups** or plugins requiring anonymous access to the users REST endpoint will be blocked by protection #2.
- Login error messages are preserved in French and English; on other locales the "lost password" link inside error messages is also genericized (cosmetic).
- This plugin does **not** rate-limit `wp-login.php` itself — high-volume brute force belongs at the server level (fail2ban, WAF).

## License

[MIT](LICENSE) — © 2026 Jean-Benoît Kauffmann ([orilyt.com](https://orilyt.com))
