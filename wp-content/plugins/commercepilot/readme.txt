=== CommercePilot – AI Sales Assistant for WooCommerce ===
Contributors: commercepilot
Tags: woocommerce, chatbot, ai, live chat, sales assistant
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Requires Plugins: woocommerce
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

An AI shopping assistant for WooCommerce that answers questions, recommends products, adds them to the cart, and hands chats over to your team.

== Description ==

CommercePilot adds a chat assistant to your WooCommerce storefront. Shoppers ask questions in plain language, and the assistant searches your catalog, recommends products, handles variations, and adds items to the cart without the shopper leaving the page.

When a chat needs a person, you can take it over from wp-admin and reply yourself. While you are handling a chat the AI is switched off for it entirely.

= Features =

* Floating chat widget with your colors, position, light or dark theme, and profile picture
* Product search and recommendations from your live WooCommerce catalog
* Add to cart, variation picker, and a checkout shortcut, all inside the chat
* Conversations inbox in wp-admin with chat history
* Human takeover: answer any shopper yourself, share product cards, then hand the chat back to the AI
* Unread badge and message sounds for shoppers, with a mute button
* Online, away, and offline status
* Works with full-page caching
* Site credentials are stored encrypted and never sent to the browser
* WooCommerce High-Performance Order Storage (HPOS) and Cart/Checkout Blocks compatible

= Requirements =

* WordPress 6.5 or later
* WooCommerce 8.0 or later
* PHP 8.1 or later
* A CommercePilot account

== External services ==

This plugin connects to the CommercePilot service (https://api.commercepilot.com), which runs the AI assistant, stores conversations, and tracks plan usage. The plugin cannot work without it.

What is sent and when:

* **When a shopper sends a chat message:** the message text, a random visitor ID generated in the shopper's browser, and the conversation ID. No name, email address, or payment details are sent.
* **When the assistant looks up products or changes a cart:** the CommercePilot service calls back into your store's REST API. These requests are signed and verified with your site secret. Product data is read from your store on demand and is not copied in bulk.
* **When you connect or save settings:** your store name, site URL, WordPress, WooCommerce, and plugin versions, and the assistant settings you choose.
* **When you open CommercePilot pages in wp-admin:** requests for your conversation list, unread count, and usage.

The service is provided by CommercePilot. Terms of Service: https://commercepilot.com/terms. Privacy Policy: https://commercepilot.com/privacy.

== Installation ==

1. Install and activate WooCommerce.
2. Upload the `commercepilot` folder to `/wp-content/plugins/`, or install the zip from **Plugins → Add New → Upload Plugin**.
3. Activate CommercePilot.
4. Go to **CommercePilot → Connection** and click **Sign Up** to create an account.
5. Paste your Site ID, site token, and site secret, then click **Connect CommercePilot**.
6. Go to **CommercePilot → Settings** to set the assistant's name, welcome message, and appearance.

== Frequently Asked Questions ==

= Do I need a CommercePilot account? =

Yes. The AI runs on the CommercePilot service. You can sign up from **CommercePilot → Connection**.

= Where are my credentials stored? =

In the `commercepilot_settings` WordPress option. The site token and site secret are encrypted with your WordPress salts and are never sent to the browser.

= Can I answer a shopper myself? =

Yes. Open **CommercePilot → Conversations**, pick a chat, and click **Take over**. The AI stops answering that conversation, and no plan quota is used while you handle it. Your replies appear in the shopper's chat window. Click **Give back to AI** to hand it back.

= Does it work with page caching plugins? =

Yes. If a cached page carries an expired security token, the widget fetches a fresh one automatically.

= Does the chatbot collect payment details? =

No. Checkout always happens on your normal WooCommerce checkout page.

= What happens to my data if I uninstall? =

By default, settings are kept so you can reinstall without reconnecting. Enable **Delete CommercePilot data on uninstall** in Settings to remove them. Conversations are stored in your CommercePilot account and can be managed there.

= Can I point the plugin at a staging backend? =

Yes. Define `COMMERCEPILOT_API_URL`, `COMMERCEPILOT_SIGNUP_URL`, and `COMMERCEPILOT_PLANS_URL` in `wp-config.php`.

== Troubleshooting ==

* **Not Connected:** enter your credentials on the Connection page.
* **Connection expired:** reconnect, or rotate your site token.
* **Assistant unavailable:** check that your server can reach https://api.commercepilot.com (some hosts block outbound requests).
* **Chatbot not showing:** make sure it is enabled in Settings and that the store is connected.

== Screenshots ==

1. The chat widget on a product page.
2. Product recommendations with Add to cart.
3. The Conversations inbox with human takeover.
4. Appearance settings.

== Changelog ==

= 1.2.0 =
* Production service endpoints by default. They can be overridden from wp-config.php.
* The widget now recovers on its own when a cached page carries an expired security token.
* The widget keeps working when browser storage is blocked.
* Shopper messages keep their line breaks.
* The checkout link stays visible after the chat redraws, and the theme mini-cart refreshes after Add to cart.
* The widget no longer polls from background tabs.
* Inbox: a reply you are typing is no longer cleared when a new message arrives, and failed replies are put back in the box.
* wp-admin pages stay fast when the service is slow or unreachable.
* Declared compatibility with WooCommerce HPOS and Cart/Checkout Blocks.
* Settings and Connect links on the Plugins screen.
* The Usage page shows the correct billing period.

= 1.1.0 =
* Conversations page: browse chat history from wp-admin.
* Human takeover per conversation. The AI is bypassed entirely while a person is handling the chat.
* The chatbot restores its thread on reload and receives agent replies live.
* Outbound API signatures are now bound to their request method and path.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.2.0 =
Reliability fixes for cached storefronts and the admin inbox. If you run a local or staging backend, define COMMERCEPILOT_API_URL in wp-config.php before updating.
