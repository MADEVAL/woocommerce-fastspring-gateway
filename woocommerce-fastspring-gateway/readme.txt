=== WooCommerce FastSpring Gateway ===
Contributors: globusstudio
Tags: woocommerce, payment gateway, fastspring, subscriptions, digital payments
Requires at least: 6.4
Tested up to: 6.7
Requires PHP: 8.1
WC requires at least: 8.0
WC tested up to: 9.6
Stable tag: 2.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept credit card, PayPal, Amazon Pay and other payments on your WooCommerce store via FastSpring.

== Description ==

WooCommerce FastSpring Gateway integrates your [FastSpring](https://fastspring.com) account with WooCommerce, enabling secure payments through the FastSpring Sessions API with a full-page checkout redirect.

**Features:**

* FastSpring Sessions API integration (no Store Builder Library popup)
* Full-page checkout redirect for a native FastSpring experience
* REST API: sessions, orders, accounts, products, subscriptions, refunds
* Mandatory HMAC SHA256 webhook signature verification
* Optional webhook IP address filtering
* WooCommerce product meta field for FastSpring product path mapping (SKU fallback)
* WooCommerce Subscriptions support with renewal and lifecycle events
* WooCommerce HPOS (High-Performance Order Storage) compatible
* Cart/Checkout Blocks compatibility declared
* PHP 8.1+ with strict type declarations throughout
* PSR-4 autoloading, clean and modern architecture
* 12 webhook event handlers (order, return, subscription lifecycle & charges)
* Configurable payment method icons
* Test mode with automatic storefront domain switching

**Supported Webhook Events:**

* order.completed, order.failed, order.canceled
* return.created
* subscription.activated, subscription.canceled, subscription.deactivated
* subscription.uncanceled, subscription.paused, subscription.resumed
* subscription.charge.completed, subscription.charge.failed

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **WooCommerce > Settings > Payments > FastSpring**.
4. Enter your Storefront URL (e.g., `yourstore.onfastspring.com`).
5. Enter your API Username and Password (from FastSpring Developer Tools > API Credentials).
6. Configure your Webhook URL in the FastSpring Dashboard > Developer Tools > Webhooks:
   `https://yoursite.com/?wc-api=wc_gateway_fastspring`
7. Set the same HMAC Webhook Secret in both FastSpring and the plugin settings.
8. Edit your WooCommerce products and enter the FastSpring Product Path for each.

== Frequently Asked Questions ==

= What PHP version is required? =

PHP 8.1 or higher.

= Does this plugin support WooCommerce HPOS? =

Yes. High-Performance Order Storage compatibility is declared.

= Does this plugin support subscriptions? =

Yes. When WooCommerce Subscriptions is active, subscription products are handled with proper recurring pricing, trials, and signup fees.

= Where do I find my FastSpring credentials? =

Log in to your FastSpring Dashboard > Developer Tools > API Credentials. Create a new username and password (the password is shown only once).

= Is webhook validation mandatory? =

Yes. Unlike previous versions, HMAC SHA256 signature validation is always enforced. Webhook requests without a valid signature are rejected.

== Changelog ==

= 2.1.0 =
* **Fix:** Resolved recursive data reference bug in ApiClient::update_subscription().
* Full WordPress Coding Standards (WPCS 3.3) compliance.
* PHPDoc comments for all classes, properties, and methods.
* Yoda conditions enforced throughout the codebase.
* Proper translators comments for all translatable strings with placeholders.
* phpcs:ignore annotations for legitimate base64_encode and raw $_SERVER usage (API auth, HMAC signatures).
* Inline code style cleanup (line endings, multi-line formatting, comment punctuation).
* Added @package tag to main plugin file header.
* Added phpcs.xml.dist configuration for WordPress standard.

= 2.0.0 =
* **Breaking:** Replaced Store Builder Library popup with FastSpring Sessions API redirect checkout.
* Removed Access Key, RSA Private Key, and Encryption (no longer needed).
* API Username and Password are now required (were optional).
* Added FastSpring Product Path meta field to WooCommerce product editor.
* Product SKU used as fallback when FastSpring path is not set.
* Storefront URL simplified to domain only (e.g., yourstore.onfastspring.com).
* Extended API client: create_session, create_account, update/pause/resume subscription, get_products, get_product.
* Removed billing address toggle (FastSpring handles all billing).
* Removed checkout.js, Encryption.php, PayloadBuilder.php, AjaxHandler.php.
* Cleaned admin settings sidebar with updated help cards.

= 1.3.0 =
* One-click RSA key pair generation from the settings page (no terminal needed).
* Public certificate download button for uploading to FastSpring.
* Updated sidebar help card with simplified key setup instructions.

= 1.2.0 =
* Settings page redesign: two-column layout with contextual help sidebar.
* Each setting now has a detailed guide with step-by-step instructions and links to FastSpring documentation.
* Required, Recommended and Optional badges on settings fields.
* Copyable webhook URL in the sidebar.
* Interactive field-to-card highlighting when editing settings.
* Added Requires Plugins header for WooCommerce dependency.

= 1.1.0 =
* Complete rewrite for modern PHP 8.1+, WordPress 6.4+, WooCommerce 8.0+.
* Namespace: GlobusStudio\WooCommerceFastSpring with PSR-4 autoloading.
* Updated Store Builder Library from v0.8.3 to v1.0.3 (new CDN).
* **Security:** Mandatory HMAC SHA256 webhook signature validation (was optional/bypassable).
* **Security:** All webhook and AJAX inputs sanitized via sanitize_text_field().
* **Security:** Timing-safe hash_equals() for signature comparison.
* **Security:** RSA private key validation (minimum 2048-bit) on save.
* REST API integration using WordPress HTTP API (wp_remote_request).
* HPOS and Cart/Checkout Blocks compatibility declared.
* Refund support via FastSpring Returns API.
* 12 webhook event handlers (was 5): added order.failed, order.canceled, subscription.uncanceled, subscription.paused, subscription.resumed, subscription.charge.completed, subscription.charge.failed.
* Payload built from WC_Order (not cart session) for reliability.
* Proper WC logger integration via wc_get_logger().
* Storefront path field validator with protocol stripping.
* Settings cache with flush on save.

== Upgrade Notice ==

= 2.0.0 =
Major update. The plugin now uses the FastSpring Sessions API instead of the Store Builder Library popup. You must enter API credentials and link your WooCommerce products to FastSpring product paths. Access Key and RSA Private Key settings are no longer used.

= 1.3.0 =
RSA key pair can now be generated directly from the settings page. No more terminal commands.

= 1.2.0 =
Settings page now includes an interactive help sidebar with setup instructions for each field.

= 1.1.0 =
Complete rewrite. Review your FastSpring credentials and webhook secret in WooCommerce > Settings > Payments > FastSpring after upgrading. HMAC webhook validation is now mandatory.
