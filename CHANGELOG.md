# Changelog

All notable changes to **TigerTinylink**. Format follows [Keep a Changelog](https://keepachangelog.com/);
this project uses [SemVer](https://semver.org/).

## [1.0.0] — 2026-11-17

### Added
- Short links at `/l/<slug>` with 301/302 redirects, optional expiry, enable/disable, notes, and a random or
  chosen slug (unique per org among live links).
- Query parameters: the destination's own are kept; the short link's are passed through (replacing same-named
  ones), switchable per link. Fragments are preserved.
- Local click log (referring host + device class) with per-link totals, a 30-day daily chart and top referrers.
  Link-preview bots are logged but not counted.
- Each human click is sent as a `short_link_click` event to the site's analytics provider(s) through
  `Tiger_Analytics::track()` (GA4 Measurement Protocol / Matomo), after the redirect is sent.
- Admin screen (Short links) and `/api` + MCP operations: datatable, get, save, setStatus, delete, stats.
- Guardrails: http(s)-only destinations re-checked on every redirect (no open redirect), per-IP rate limit on
  the public route (`Tiger_RateLimit`, `tiger.tinylink.rate_limit`).
- Six locales (en, es, pt, hi, de, fr).
