# Changelog

All notable changes to **TigerTinylink**. Format follows [Keep a Changelog](https://keepachangelog.com/);
this project uses [SemVer](https://semver.org/).

## [1.1.0] — 2026-11-17

### Added — links pages (link-in-bio)
- **`/links/<handle>`** — one shareable page with all your links, rendered in the active theme (it reskins with
  the site). `/links` serves the org's default page. Several pages per org, each with its own handle.
- **Profile:** photo (media library), name, bio, and a row of social links (Instagram, TikTok, YouTube, X,
  Threads, Facebook, LinkedIn, Pinterest, Twitch, Discord, Spotify, GitHub, website, email).
- **Blocks:** link buttons (each a real short link, so every tap is counted and redirected through `/l/`; an
  icon or image; "featured" as a larger card), section headings, dividers, an email signup (the newsletter
  module, when active) and product cards (TigerShop, when active). Per-block show/hide and a show-from/until
  schedule. Drag to reorder in the admin.
- **Look:** three button styles (rounded, pill, outlined) and three backgrounds (site, soft, brand colour) —
  all from the site's Bootstrap skin tokens, no inline styles; 48px+ targets and visible focus rings.
- **Stats:** page views per day, taps per button, tap rate and top referrers. A tap is attributed to the page
  it came from (`_tlp`, stripped before the redirect; only a live page of the same org counts).
- **SEO / sharing:** title, description and share image through the head registry (TigerSEO when present).
- **`/api` + MCP:** `tinylink/page/{list,get,save,delete,stats}` and `tinylink/block/{save,setStatus,delete,reorder}`
  — an agent can build and arrange a page.
- The admin menu is now a **Short links** group: Links, Links pages.

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
