<!-- tiger:doc title="Short links — Getting started" slug="tinylink/getting-started" visibility="admin" icon="fa-link" -->

# Short links

TigerTinylink gives your site short links under `/l/`: `yoursite.com/l/spring` sends visitors to any long
address you choose, with its parameters intact, and counts every click.

## Create a link

1. Open **Short links** in the admin menu and click **New link**.
2. Paste the **destination URL** — the full address, including any parameters (`utm_campaign=…`, referral
   codes…). It must start with `http://` or `https://`.
3. Optionally choose the **short path** (letters, numbers, `-` and `_`). Leave it blank for a random one.
4. Save. Use the copy button in the grid to copy the full short link.

## Parameters

Parameters on the short link are added to the destination. With a destination of
`https://shop.example/sale?utm_campaign=spring`, a visit to `/l/spring?utm_source=instagram` lands on
`https://shop.example/sale?utm_campaign=spring&utm_source=instagram`. A parameter on the short link replaces the
destination's parameter of the same name. To always send the destination exactly as saved, turn off
**Pass the short link's parameters through** for that link.

## Redirect type and expiry

- **302** (the default) is a temporary redirect: you can change the destination later and visitors follow the
  new one.
- **301** is permanent: browsers may cache it, so changing the destination later may not reach everyone.
- An **expiry** (UTC) stops the link redirecting after that time; it then answers *not found*, like a disabled
  link.

## Clicks and analytics

The chart button on each row shows the last 30 days: clicks per day, mobile vs desktop, and the top referring
sites. Link-preview fetchers (Slack, X, Facebook, messaging apps) are recorded but not counted as clicks.

If the site has analytics set up under **Analytics** — Google Analytics 4 with a *Measurement Protocol API
secret*, or Matomo — each click is also sent there as a `short_link_click` event with the parameters `slug` and
`device`. Without analytics, redirects and the local counts still work.

## Limits

Each visitor IP may follow up to 120 short links a minute (per web server); past that the site answers
*429 Too Many Requests*. An administrator can change this with the `tiger.tinylink.rate_limit` setting.
