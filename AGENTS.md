# AGENTS.md — TigerTinylink

> **Step 0 — check core before you build.** Before writing any mechanism, grep
> [`../tiger-core/CAPABILITIES.md`](../tiger-core/CAPABILITIES.md) — a generated, CI-checked index of every
> `Tiger_*` class and module. Core probably already has it. **Assume a capability exists until you have grepped
> the index and confirmed it doesn't.**

This module follows tiger-core's conventions (`AGENTS.md`, `ADMIN.md`, `WEBSERVICES.md` in the tiger-core repo).
If something here contradicts core's docs, core wins.

## What this is

Short links: `/l/<slug>` → a stored destination. Slug/namespace `tinylink` / `Tinylink`.

| Piece | File |
|---|---|
| Public redirect (guest) | `controllers/GoController.php`, route in `configs/routes.ini` |
| Admin screen | `controllers/AdminController.php`, `views/scripts/admin/index.phtml`, `assets/js/tinylink.admin-index.js` |
| `/api` (admin, per-method ACL) | `services/Link.php` — datatable, get, save, setStatus, delete, stats |
| Store | `models/Link.php` (`tinylink_link`), `models/Click.php` (`tinylink_click`) |
| Forms (one CSRF salt) | `forms/Write.php` base; `Link`, `Status`, `Id`, `Page`, `PageItem`, `Reorder`, `PageId` |
| Links pages — public (guest) | `controllers/PageController.php` (`/links/:handle`), `views/scripts/page/view.phtml`, `assets/css/tinylink.page.css` |
| Links pages — admin | `controllers/PagesController.php`, `views/scripts/pages/*`, `assets/js/tinylink.pages-*.js` |
| Links pages — `/api` | `services/Page.php` (list, get, save, delete, stats), `services/Block.php` (save, setStatus, delete, reorder) |
| Links pages — store | `models/Page.php`, `models/PageItem.php`, `models/PageView.php`; `tinylink_click.page_id` |

## Invariants — do not break these

- **No open redirect.** The redirect sends a visitor only to a destination an admin saved, re-checked with
  `Tinylink_Service_Link::isSafeDestination()` on every hit (absolute http/https with a host, no credentials,
  no whitespace/control characters). Never accept a destination from the request.
- **The redirect resolves in the SITE's org** (`Tiger_Model_Org::siteOrgId()`), never the visitor's.
- **Org lane.** Every finder in `models/Link.php` takes the org; the service always passes `_orgId()`. A bare
  `findById` on a write is a defect.
- **Analytics is sent after the response.** `Tiger_Analytics::track()` runs in a shutdown function after
  `fastcgi_finish_request()`; a slow provider must never delay the visitor. Bots are not sent.
- **Click data stays coarse:** referring host + device class only. No IP, no raw user agent.
- **Slugs are unique per org among live links** (`slug_active` generated column — the core TIGER-274 norm), so
  deleting a link frees its slug. Write rows with `update()`/`insert()` and explicit columns — never
  `$row->save()`, which would try to write the generated column.
- **A links-page button is always a short link** (`/l/<slug>?_tlp=<page_id>`). `_tlp` attributes the tap and is
  stripped before the redirect; GoController credits it only to a live page of the same org.
- **Service names are one word** (`link`, `page`, `block`): the /api factory `ucfirst`s the name and the MCP
  catalog lowercases it, so a two-word class (`PageItem`) would not round-trip to its ACL resource.
- **The public page is markup + one stylesheet** driven by `--bs-*` tokens — no inline style, no JS needed.
- **Every write validates through a `Tinylink_Form_*`**; a partial update seeds `_csrf` before
  `isValidPartial()` (core AGENTS.md). Every `/api` method has its own ACL rule and declares its input
  (`ModuleContractTest` enforces both).

## Tests

```bash
../tiger-core/vendor/bin/phpunit --testsuite unit
TIGER_TEST_DB_NAME=tinylink_test TIGER_TEST_DB_SOCKET=/tmp/mysql.sock TIGER_TEST_DB_USER=root TIGER_TEST_DB_PASS='' \
  ../tiger-core/vendor/bin/phpunit
```
