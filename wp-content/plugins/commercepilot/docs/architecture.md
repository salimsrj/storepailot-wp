# CommercePilot WordPress Plugin

Version 1.1.0. WordPress owns WooCommerce and the storefront widget. Laravel owns AI, usage, and subscriptions.

## Environment

- WordPress 6.x+ (this site runs 7.1)
- WooCommerce 8.x+ (this site runs 11.1)
- PHP 8.1+
- Composer optional; a built-in autoloader loads `class-*.php` files

## Architecture

```
Customer → Chatbot JS → WordPress REST → Laravel /api/v1/chat → AI Agent
                                                      ↓
                                         HMAC → WordPress REST → WooCommerce
```

The browser never receives `site_token`, `site_secret`, or an OpenAI key.

## Laravel API used by this plugin

| Method | Path | Auth |
|--------|------|------|
| POST | `/api/v1/chat` | Bearer `cp_live_*` |
| GET | `/api/v1/site` | Bearer |
| PATCH | `/api/v1/site` | Bearer |
| PATCH | `/api/v1/site/settings` | Bearer |
| GET | `/api/v1/usage` | Bearer |
| POST | `/api/v1/site/token/rotate` | Bearer + HMAC |
| GET | `/api/v1/conversations` | Bearer + HMAC |
| GET | `/api/v1/conversations/{uuid}` | Bearer + HMAC |
| GET | `/api/v1/conversations/{uuid}/messages` | Bearer + HMAC |
| POST | `/api/v1/conversations/{uuid}/takeover` | Bearer + HMAC |
| POST | `/api/v1/conversations/{uuid}/release` | Bearer + HMAC |
| POST | `/api/v1/conversations/{uuid}/messages` | Bearer + HMAC |
| GET | `/up` | none (reachability) |

Conversation routes use a separate Laravel rate-limit profile (`site.rate:poll`)
so inbox and widget polling cannot exhaust the daily chat allowance.

Laravel has no OAuth connect callback. Store owners create a site in the SaaS admin and paste `site_id`, `token`, and `secret` into WordPress. `POST /api/v1/sites` currently returns `token` but not `secret`; keep the secret from the admin/onboarding flow.

## WordPress REST (Laravel HMAC inbound)

HMAC: `X-CommercePilot-Timestamp` + `X-CommercePilot-Signature`  
Signature = `HMAC-SHA256(timestamp + "." + raw_body, site_secret)`  
GET requests sign an empty body. Tolerance ±300s with replay cache.

Outbound requests to Laravel use a request-bound variant, because every GET has
an empty body and would otherwise produce an identical digest for a given
second, colliding in replay protection once polling was introduced:

`HMAC-SHA256(timestamp + "." + METHOD + "." + path_with_query + "." + raw_body, site_secret)`

`Security::sign_request()` in the plugin and `HmacSigner::signRequest()` in
Laravel must stay in step; the plugin and backend upgrade together.

| Method | Path |
|--------|------|
| POST | `/wp-json/commercepilot/v1/products/search` |
| GET | `/wp-json/commercepilot/v1/products/{id}` |
| GET | `/wp-json/commercepilot/v1/products/{id}/variations` |
| POST | `/wp-json/commercepilot/v1/stock` |
| GET | `/wp-json/commercepilot/v1/cart` |
| POST | `/wp-json/commercepilot/v1/cart/items` |
| DELETE | `/wp-json/commercepilot/v1/cart/items` |
| PATCH | `/wp-json/commercepilot/v1/cart/items` |
| GET | `/wp-json/commercepilot/v1/checkout` |

## WordPress REST (browser)

Nonce header `X-WP-Nonce` (`wp_rest`). No Laravel credentials.

| Method | Path |
|--------|------|
| POST | `/wp-json/commercepilot/v1/chat` |
| GET | `/wp-json/commercepilot/v1/messages` (poll for agent replies) |
| GET | `/wp-json/commercepilot/v1/site` |
| GET | `/wp-json/commercepilot/v1/cart` |
| POST | `/wp-json/commercepilot/v1/cart/add` |
| POST | `/wp-json/commercepilot/v1/cart/remove` |
| POST | `/wp-json/commercepilot/v1/cart/update` |
| GET | `/wp-json/commercepilot/v1/checkout` |
| GET | `/wp-json/commercepilot/v1/health` |

## Human takeover

Each Laravel conversation carries a `mode` of `ai` or `human`. The Conversations
page in wp-admin lists threads, shows full history, and toggles that flag.

While a conversation is in `human` mode, `ChatService::reply()` records the
visitor's message and returns before reserving usage or calling the AI provider,
so a taken-over chat costs nothing and consumes no plan quota. The reply comes
from the store owner instead:

```
Visitor → WP /chat → Laravel: stores message, returns { mode: human, message: null }
Admin   → WP /admin/conversations/{uuid}/reply → Laravel: assistant message, metadata.author = human
Visitor ← WP /messages?after_id=N ← Laravel: the agent's reply
```

Agent replies are stored with `role = assistant` (not a new role) so the thread
stays coherent for the AI if the conversation is later released back to it;
`messages.metadata.author` distinguishes them. Delivery is by polling: the
widget every 5s while handed over, the inbox every 5s per thread and 15s per
list, both paused when the tab is hidden. There is no websocket layer in the
backend.

## Options

Option key: `commercepilot_settings`

Stored locally: connection credentials (encrypted token/secret), API URL, assistant settings, feature flags, appearance, uninstall flag.

Not stored: conversations, subscriptions, OpenAI keys, payment data, product
replicas. The admin inbox reads history from Laravel on demand; the plugin still
creates no database tables.

## Cart session

Visitor UUID (frontend `localStorage.commercepilot_visitor_id`) maps to WooCommerce guest session:

`t_` + first 30 hex chars of `HMAC-SHA256(lowercase visitor_id, wp_salt('auth'))`

That key is a valid WooCommerce 11 guest id (`t_` prefix). Browser REST and Laravel HMAC load the same `woocommerce_sessions` row. Browser responses may set the WC session cookie so checkout uses the same cart.

## Security

- Admin pages and admin REST require `manage_options`
- Admin forms use WordPress nonces
- Secrets encrypted with AES-256-CBC using WordPress salts
- AI text is rendered with `textContent`
- Product cards are built from structured JSON
- Logger redacts tokens, secrets, and payment fields
- Laravel remains the source of truth for usage and plans
