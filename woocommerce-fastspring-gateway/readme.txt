=== WooCommerce FastSpring Gateway ===
Contributors: globusstudio
Tags: woocommerce, payment gateway, fastspring, subscriptions, digital payments
Requires at least: 6.4
Tested up to: 6.7
Requires PHP: 8.1
WC requires at least: 8.0
WC tested up to: 9.6
Stable tag: 1.2.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept credit card, PayPal, Amazon Pay and other payments on your WooCommerce store via FastSpring.

== Description ==

WooCommerce FastSpring Gateway integrates your [FastSpring](https://fastspring.com) account with WooCommerce, enabling secure payments through the FastSpring popup checkout.

**Features:**

* FastSpring Store Builder Library v1.0.3 (latest)
* Secure encrypted payloads (AES-128-ECB + RSA 2048-bit)
* Mandatory HMAC SHA256 webhook signature verification
* Optional webhook IP address filtering
* REST API order verification and refund support
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
4. Enter your Access Key and RSA Private Key (from FastSpring Developer Tools > Store Builder Library).
5. Enter your Storefront Path (e.g., `yourstore.onfastspring.com/popup-checkout`).
6. Configure your Webhook URL in the FastSpring Dashboard > Developer Tools > Webhooks:
   `https://yoursite.com/?wc-api=wc_gateway_fastspring`
7. Set the same HMAC Webhook Secret in both FastSpring and the plugin settings.
8. (Optional) Enter API Username and Password for order verification and refund support.

== Frequently Asked Questions ==

= What PHP version is required? =

PHP 8.1 or higher.

= Does this plugin support WooCommerce HPOS? =

Yes. High-Performance Order Storage compatibility is declared.

= Does this plugin support subscriptions? =

Yes. When WooCommerce Subscriptions is active, subscription products are handled with proper recurring pricing, trials, and signup fees.

= Where do I find my FastSpring credentials? =

Log in to your FastSpring Dashboard > Developer Tools. Access Key and Private Key are under Store Builder Library. API credentials are under API Credentials.

= Is webhook validation mandatory? =

Yes. Unlike previous versions, HMAC SHA256 signature validation is always enforced. Webhook requests without a valid signature are rejected.

== Changelog ==

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

= 1.2.0 =
Settings page now includes an interactive help sidebar with setup instructions for each field.

= 1.1.0 =
Complete rewrite. Review your FastSpring credentials and webhook secret in WooCommerce > Settings > Payments > FastSpring after upgrading. HMAC webhook validation is now mandatory.
