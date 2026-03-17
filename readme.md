# WooCommerce FastSpring Gateway

Accept credit card, PayPal, Amazon Pay and other payments on your WooCommerce store through FastSpring.

## What it does

This plugin connects your WooCommerce store to [FastSpring](https://fastspring.com) as a payment gateway. Customers pay through the FastSpring popup checkout right on your site, and orders are tracked and managed in WooCommerce as usual.

Supports one-time purchases, subscriptions (via WooCommerce Subscriptions), and refunds through the FastSpring API.

## Requirements

- WordPress 6.4+
- WooCommerce 8.0+
- PHP 8.1+
- OpenSSL extension
- A FastSpring seller account

## Features

- FastSpring Store Builder Library v1.0.3
- Secure encrypted payloads (AES-128-ECB + RSA 2048-bit)
- HMAC SHA256 webhook signature verification (mandatory)
- Optional webhook IP address filtering
- REST API order verification and refund processing
- WooCommerce Subscriptions support (renewals, trials, signup fees, lifecycle events)
- HPOS (High-Performance Order Storage) compatible
- Cart/Checkout Blocks compatibility declared
- 12 webhook event handlers for order, return, and subscription events
- Configurable payment method icons (Visa, Mastercard, PayPal, Amex, and more)
- Test mode with automatic storefront domain switching

## Installation

1. Download the latest release and upload the `woocommerce-fastspring-gateway` folder to `/wp-content/plugins/`.
2. Activate the plugin in WordPress.
3. Go to **WooCommerce > Settings > Payments > FastSpring**.
4. Enter your **Access Key** and **RSA Private Key** (from FastSpring Developer Tools > Store Builder Library).
5. Enter your **Storefront Path** (e.g. `yourstore.onfastspring.com/popup-checkout`).
6. In the FastSpring Dashboard > Developer Tools > Webhooks, add the webhook URL shown in the plugin settings.
7. Set the same **HMAC Webhook Secret** in both FastSpring and the plugin settings.
8. Optionally enter API Username and Password for order verification and refund support.

## Webhook events

The plugin handles the following FastSpring events:

| Event | Action |
|-------|--------|
| `order.completed` | Marks WC order as paid |
| `order.failed` | Sets order status to failed |
| `order.canceled` | Sets order status to cancelled |
| `return.created` | Sets order status to refunded |
| `subscription.activated` | Stores subscription ID, adds order note |
| `subscription.canceled` | Cancels the order |
| `subscription.deactivated` | Puts order on hold |
| `subscription.uncanceled` | Restores order to processing |
| `subscription.paused` | Puts order on hold |
| `subscription.resumed` | Restores order to processing |
| `subscription.charge.completed` | Adds order note with charge reference |
| `subscription.charge.failed` | Adds order note about failed charge |

## Security

- All webhook requests are validated with HMAC SHA256 signatures. Requests without valid signatures are rejected.
- Checkout payloads are encrypted with AES-128-ECB and the key is signed with RSA 2048-bit.
- RSA private key is validated on save (minimum 2048-bit).
- All inputs are sanitized with WordPress sanitization functions.
- Timing-safe comparison (`hash_equals`) for signature verification.

## Development

The plugin uses PSR-4 autoloading under the `GlobusStudio\WooCommerceFastSpring` namespace. No Composer required in production.

```
woocommerce-fastspring-gateway/
  woocommerce-fastspring-gateway.php   # Main plugin file, autoloader
  src/
    Plugin.php            # Bootstrap, environment checks, settings
    Gateway.php           # WC_Payment_Gateway implementation
    PayloadBuilder.php    # Builds encrypted session payloads
    WebhookHandler.php    # Processes incoming FastSpring events
    AjaxHandler.php       # Receipt AJAX after popup payment
    ApiClient.php         # FastSpring REST API client
    Encryption.php        # AES + RSA encryption
    Constants.php         # Plugin constants
    Admin/
      Settings.php        # Gateway settings field definitions
  assets/
    js/checkout.js        # Frontend checkout logic
    img/                  # Payment method SVG icons
```

## License

GPL-2.0-or-later. See [LICENSE](woocommerce-fastspring-gateway/LICENSE) for details.
