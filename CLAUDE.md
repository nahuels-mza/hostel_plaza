# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

Website and management system for **Hostel Plaza** (Mendoza, Argentina): public landing pages, a booking flow, an admin/staff panel, deposit payment, confirmation emails, and an AI-powered WhatsApp agent for availability/price inquiries.

Plain **PHP with no framework** (no Composer except for the vendored PHPMailer). Every `.php` file in the repo root is a page or endpoint served directly by Apache/PHP. There is no database — data is persisted as flat **JSON files** in the repo root (`rooms.json`, `bookings.json`, `users.json`, `staff.json`, `events.json`, `discounts.json`, `expenses.json`, `tasks.json`, `messages.json`, `schedule.json`, `terms.json`, etc.).

There is no build/lint/test tooling for the PHP code (no Composer scripts, no PHPUnit, no linter config). The `assets/` folder holds a pre-built frontend bundle (`index-*.js`/`.css`) that is not built from source in this repo — treat it as a static asset.

## Commands

- **Run locally**: serve the repo root with PHP's built-in server, e.g. `php -S localhost:8000`. `dev_env.php`'s `hp_is_localhost()` detects `localhost`/`127.0.0.1` and switches secrets/behavior accordingly (see Secrets below).
- **No build step, no test suite, no linter** — there's nothing to run beyond starting a PHP server. Verify changes by loading the relevant page/endpoint directly.
- **Test the WhatsApp availability logic without Meta**, from the repo root:
  ```bash
  php -r '
    require "whatsapp/availability.php";
    $cfg = require "whatsapp/config.php";
    $res = hp_bananadesk_fetch($cfg["bananadesk"], "2026-06-05", "2026-06-08", $cfg["paths"]["cache"]);
    print_r($res);
  '
  ```

## Architecture

### Public site
`index.php`, `rooms.php`, `room.php`, `about.php`, `faq.php`, `history.php`, `virtual-tour.php`, `terms.php`, `privacy.php`, `tourist-events.php` are public pages sharing `header.php` / `footer.php` and partials prefixed with `_` (`_seo.php`, `_ga.php`, `_search_fields.php`, `_about_gallery.php`).

Language handling: the guest picks a language via a selector in `header.php`, stored in the `hp_lang` cookie. Only `es` and `en` have dedicated copy (see `hp_detect_lang()` in `mail_booking.php`); other languages fall back to English and are translated client-side via Google Translate.

### Booking flow
1. `book.php` is a multi-step wizard (dates → guest details → payment) that checks live availability/pricing via `room_availability.php` / `rooms_for_dates.php`, both of which query **BananaDesk** — an external booking engine — in real time.
2. `bananadesk_reserve.php` (`hp_bananadesk_reserve()`) creates the reservation in BananaDesk; a local record is also saved to `bookings.json`.
3. Payment is **PayPal** (`pay.php`, `paypal_lib.php`, `paypal_create_order.php`, `paypal_capture_order.php`) — the guest can also just pay at check-in, so this step is optional. There is a dormant **Payway/Decidir** integration (`payway_lib.php`, `payway_charge.php`) left unused because its antifraud flow (CyberSource device fingerprint) was never fully wired up; don't assume it's live. (Note: `README.md` still describes Stripe — that's stale; PayPal is what's actually integrated.)
4. Confirmation email is built by `mail_booking.php` (`hp_mail_booking()`), a single template for all guests (no per-country variants anymore), and sent via the vendored `PHPMailer-master/`.

### Admin / staff panel
`login.php` authenticates against **hardcoded credentials in the file itself** (not `users.json`/`staff.json` — those exist but aren't what `login.php` checks) and sets `$_SESSION['admin_logged_in']` or `$_SESSION['staff_logged_in']`. Session-gated pages: `admin.php` (full management), `staff.php` / `guest.php` / `checklist.php` (scoped views for staff), `extend.php`, `logout.php`.

### WhatsApp agent (`whatsapp/`)
Self-contained subsystem, documented in depth in `whatsapp/README.md`. Answers guest questions over WhatsApp using **Claude (Anthropic)** as the language engine and **BananaDesk** as the live source of availability/pricing.

- `webhook.php` — public endpoint Meta calls (verification handshake + incoming messages).
- `agent.php` — main loop: builds context, calls Claude with tool definitions (`hp_tools_definition()`), keeps per-phone-number conversation memory in `conversations.json`.
- `availability.php` — HTTP client to BananaDesk's public `room-type-availability` endpoint, normalizes bilingual (`///`-separated) descriptions, caches responses in `whatsapp/cache/` for `cache_ttl` seconds (default 300) to avoid hammering the endpoint.
- `whatsapp_client.php` — WhatsApp Cloud API (Graph) client.
- `claude_client.php` — Anthropic API client.
- `config.php` (gitignored; template in `config.example.php`) — all credentials and tunables (WhatsApp token/phone ID, Claude model/key, admin forwarding number, BananaDesk base URL/TTL, paths, `agent.history_turns`, `agent.office_hours_only`).

To add a new capability to the bot (e.g. creating bookings, surfacing events), add a tool in `hp_tools_definition()` in `agent.php` — Claude decides when to call it.

### Cross-cutting: logging (`logger.php`)
One log file per day per channel: `logs/<channel>/YYYY-MM-DD.log` (channels: `mail`, `whatsapp`, `payway`, `paypal`, `bananadesk`). `logs/.htaccess` denies all web access recursively.

- `hp_write_log(string $channel, string $message)` — append a timestamped line/block.
- `hp_client_info()` — formats IP/user-agent/referer for a log line.
- `hp_dedupe_check(string $channel, string $fingerprint, array $meta, int $windowSeconds)` — non-blocking duplicate-submit detector backed by `logs/<channel>/.recent.json` (flock-guarded); the caller decides what to do with a duplicate, it never blocks anything itself.

Note: `whatsapp/agent.php` has its own single-channel `hp_log($msg)` — deliberately a different name from `hp_write_log` to avoid a collision if both files are ever loaded in the same request.

### Secrets and environment (`dev_env.php`)
`hp_is_localhost()` is true only for `localhost`/`127.0.0.1`/`::1` and is the *only* place environment behavior should branch. `hp_secrets_path(string $repoRoot)` resolves the secrets file location:
- Localhost → `do_not_upload/secrets.php` (inside the repo, gitignored).
- Production → `<one level above repoRoot>/storagedir/secrets.php` (outside the webroot, never deployed via git).

Root-level secrets cover SMTP, Claude API key, WhatsApp Cloud API creds, Payway (test/prod), and PayPal (sandbox/live) — see `secrets.example.php` for the full key list and where each value comes from. The WhatsApp bot has its own, separate secrets template at `whatsapp/secrets.example.php` (a smaller set: WhatsApp + Claude only) alongside `whatsapp/config.example.php` for its non-secret tunables.

For provider env switches, there's a single flag to flip between test/live rather than swapping code paths: `payway_env` (`test`|`prod`) and `paypal_env` (`sandbox`|`live`) in the secrets file.

### URL rewriting (`.htaccess`)
Clean URLs strip `.php` (e.g. `/rooms` → `rooms.php`) except for `whatsapp/*` and POST-body endpoints (`payway_charge.php`, `paypal_create_order.php`, `paypal_capture_order.php`) — a 301 redirect would drop the POST body on those. `/room/<slug>` internally maps to `room.php?name=<slug>`.

## Gitignored / generated data
`bookings.json`, `conversations.json`, `whatsapp/cache/`, `logs/*/*.log`, `logs/*/.recent.json`, `secrets.php`, and everything in `do_not_upload/` are runtime/production state or secrets — never expect them to be present or committed in a fresh checkout, and never commit real values into their `*.example.*` counterparts.
