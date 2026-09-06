=== CommercePilot ===
Contributors: commercepilot
Tags: woocommerce, chatbot, ai, sales, assistant
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI Sales Assistant for WooCommerce. Connects your store to the CommercePilot Laravel SaaS backend.

== Description ==

CommercePilot is a lightweight WordPress plugin that adds an AI sales assistant to your WooCommerce store.

The plugin does **not** run its own AI. All AI, usage, subscriptions, and conversations are owned by the CommercePilot Laravel backend. WordPress owns WooCommerce, the chatbot UI, and secure API communication.

= Features =

* Floating chatbot for shoppers
* Product search and recommendations via WooCommerce
* Add to cart, update cart, and checkout URL
* Admin dashboard, conversations, settings, connection, usage, and account pages
* Chat history and human takeover: reply to any shopper yourself from wp-admin
* Encrypted site token and site secret storage
* HMAC-signed WooCommerce operations for Laravel tool calls

= Requirements =

* WordPress 6.0+
* WooCommerce 8.0+
* PHP 8.1+
* A CommercePilot account

== Installation ==

1. Upload the `commercepilot` folder to `/wp-content/plugins/`.
2. Activate WooCommerce, then activate CommercePilot.
3. Open **CommercePilot → Connection**.
4. Enter your Laravel API URL, site ID, site token, and site secret.
5. Test the connection, then enable the chatbot in Settings.

== Setup ==

1. Create a site in the CommercePilot admin.
2. Copy the one-time site token and site secret.
3. Paste them into WordPress. Secrets are encrypted with WordPress salts and never sent to the browser.
4. Configure assistant name, welcome message, features, and appearance.
5. The chatbot appears on the storefront when connected and enabled.

== Privacy ==

The plugin sends chat messages, a visitor UUID, and a conversation UUID to the CommercePilot API. Product and cart actions stay on your store. Payment details are never collected by the chatbot. SaaS account data is not deleted when you uninstall WordPress unless you opt in to delete plugin options.

== Frequently Asked Questions ==

= Does this plugin call OpenAI? =

No. All AI requests go through the CommercePilot Laravel API.

= Where is the site token stored? =

In the `commercepilot_settings` WordPress option, encrypted. It is never localized to JavaScript.

= Why do I need a site secret? =

Laravel signs WooCommerce tool requests with HMAC-SHA256. WordPress verifies those signatures with the site secret.

= Can I answer a shopper myself? =

Yes. Open **CommercePilot → Conversations**, pick a chat, and click **Take over**.
The AI stops answering that conversation immediately — no AI request is made and
no plan quota is used — and your replies appear in the shopper's chat window.
Click **Give back to AI** to hand it back.

= How does the cart stay in sync? =

Each visitor UUID maps to a deterministic WooCommerce guest session key (`t_` + HMAC). Browser chat actions and Laravel tool calls load the same WooCommerce session.

== Troubleshooting ==

* **Not Connected** — add credentials on the Connection page.
* **Connection expired** — rotate or reconnect the site token.
* **Assistant unavailable** — confirm the Laravel API URL is reachable from the WordPress server.
* **Chatbot missing** — enable it in Settings and confirm WooCommerce is active.

== Changelog ==

= 1.1.0 =
* Conversations page: browse chat history from wp-admin.
* Human takeover per conversation; the AI is bypassed entirely while a person is handling the chat.
* Chatbot restores its thread on reload and receives agent replies live.
* Outbound API signatures are now bound to their request method and path. Requires the matching backend release.

= 1.0.0 =
* Initial release.
