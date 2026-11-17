# TigerTinylink

*Short links on your own domain. `yoursite.com/l/spring` sends visitors to any long URL — UTM tags, referral
codes and every other parameter intact — and counts every click.*

> **`TIGER.md` is the vendor description** — the pitch the Module Installer shows before you install. The
> machine-readable manifest is [`module.json`](module.json).

## What it does

- **Short links under `/l/`** on your own domain — no third-party shortener, no shared domain, no ads.
- **Parameters intact.** The destination keeps its own query string, and parameters added to the short link
  pass through (`/l/spring?utm_source=instagram`), so one link can be tagged per channel. Switch it off per link.
- **Counts every click** in your own database: per-link totals, a 30-day daily chart, top referrers, mobile vs
  desktop. Link-preview bots (Slack, X, Facebook…) are logged but not counted.
- **Works with your analytics, whichever it is.** With Google Analytics 4 (Measurement Protocol secret) or
  Matomo set up in Tiger's Analytics settings, each click is also sent as a `short_link_click` event. No
  analytics? Redirects and local counts work anyway.
- **Links pages (link-in-bio).** `yoursite.com/links/you` — a photo, a bio, social icons and a stack of buttons,
  in your site's own theme. Headings, dividers, featured cards, an email signup and product cards; drag to
  reorder; schedule a block to appear or disappear. Every button is a short link, so you see views, taps per
  button and where visitors came from — on your domain, not someone else's.
- **Not just for marketing.** Any long URL can sit behind a short one: a booking page, a file, a form.
- **301 or 302, expiry dates, enable/disable** per link. A random slug, or one you choose.
- **Agents can drive it.** Every action is an `/api` operation, typed for MCP, so an AI client can create and
  manage links for you.
- **Safe by construction:** destinations must be real `http(s)` URLs, the redirect only ever goes to a URL an
  admin saved (no open redirect), and the public route is rate-limited.

Free and open source (BSD-3-Clause).
