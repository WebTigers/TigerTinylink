# TigerTinylink

Short links for a [Tiger](https://github.com/WebTigers/TigerCore) site: `yoursite.com/l/<slug>` redirects to any
URL, query parameters intact, and counts the click. Free, BSD-3-Clause.

## Install

Admin → Modules → Add New → search **TigerTinylink** → Install → Activate. Requires Tiger 1.65.0 or later.
Activation runs the two migrations (`tinylink_link`, `tinylink_click`) and adds **Short links** to the admin menu.

## Use

- **Admin → Short links → New link.** Paste the destination, optionally choose the short path, save. Copy the
  short link from the grid.
- **Parameters:** `/l/spring?utm_source=instagram` adds `utm_source=instagram` to the destination; a parameter
  on the short link replaces the destination's own parameter of the same name. Turn "Pass the short link's
  parameters through" off to always send the destination exactly as saved.
- **Clicks:** the chart button shows the last 30 days. Bots and link-preview fetchers are logged, not counted.
- **Analytics:** set up GA4 (with a Measurement Protocol API secret) or Matomo under Admin → Analytics and each
  human click is also sent there as a `short_link_click` event (parameters `slug`, `device`).

## Settings

| Config key | Default | |
|---|---|---|
| `tiger.tinylink.rate_limit` | `120` | Redirects allowed per visitor IP per minute (per web server). `0` turns the limit off. |

The `/l/` prefix is a route in `configs/routes.ini`; change it there if your site already uses `/l/`.

## API / MCP

`tinylink/link/{datatable, get, save, setStatus, delete, stats}` — admin role, scoped to the caller's org. A
scoped MCP token reaches them when "TigerTinylink — short links" is ticked in its scope.

## Development

```bash
../tiger-core/vendor/bin/phpunit --testsuite unit
TIGER_TEST_DB_NAME=tinylink_test TIGER_TEST_DB_SOCKET=/tmp/mysql.sock ../tiger-core/vendor/bin/phpunit
```

Tests need a sibling `tiger-core` checkout with `composer install` run. See [AGENTS.md](AGENTS.md).
