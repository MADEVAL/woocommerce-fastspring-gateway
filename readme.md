# WooCommerce FastSpring Gateway

Accept credit card, PayPal, Amazon Pay and other payments on your WooCommerce store through FastSpring.

## What it does

This plugin connects your WooCommerce store to [FastSpring](https://fastspring.com) as a payment gateway. When a customer checks out, the plugin creates a FastSpring session via the API and redirects the customer to the FastSpring checkout page. After payment, FastSpring sends a webhook to update the WooCommerce order.

Supports one-time purchases, subscriptions (via WooCommerce Subscriptions), and refunds through the FastSpring API.

## Requirements

- WordPress 6.4+
- WooCommerce 8.0+
- PHP 8.1+
- A FastSpring seller account with API credentials

## Features

- FastSpring Sessions API integration (full-page checkout redirect)
- REST API client: sessions, orders, accounts, products, subscriptions, refunds
- HMAC SHA256 webhook signature verification (mandatory)
- Optional webhook IP address filtering
- WooCommerce product meta field for FastSpring product path mapping (SKU fallback)
- WooCommerce Subscriptions support (renewals, trials, signup fees, lifecycle events)
- HPOS (High-Performance Order Storage) compatible
- Cart/Checkout Blocks compatibility declared
- 12 webhook event handlers for order, return, and subscription events
- Configurable payment method icons (Visa, Mastercard, PayPal, Amex, and more)
- Test mode with automatic storefront domain switching
- Interactive admin settings page with contextual help sidebar

## Installation

1. Download the latest release and upload the `woocommerce-fastspring-gateway` folder to `/wp-content/plugins/`.
2. Activate the plugin in WordPress.
3. Go to **WooCommerce > Settings > Payments > FastSpring**.
4. Enter your **Storefront URL** (e.g. `yourstore.onfastspring.com`).
5. Enter your **API Username** and **API Password** (from FastSpring Developer Tools > API Credentials).
6. In the FastSpring Dashboard > Developer Tools > Webhooks, add the webhook URL shown in the plugin settings.
7. Set the same **HMAC Webhook Secret** in both FastSpring and the plugin settings.
8. Edit your WooCommerce products and enter the **FastSpring Product Path** for each.

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
- All inputs are sanitized with WordPress sanitization functions.
- Timing-safe comparison (`hash_equals`) for signature verification.
- API credentials stored in WooCommerce settings (wp_options), never exposed client-side.

## Development

The plugin uses PSR-4 autoloading under the `GlobusStudio\WooCommerceFastSpring` namespace. No Composer required in production.

```
woocommerce-fastspring-gateway/
  woocommerce-fastspring-gateway.php   # Main plugin file, autoloader
  src/
    Plugin.php            # Bootstrap, environment checks, product meta
    Gateway.php           # WC_Payment_Gateway (Sessions API redirect)
    WebhookHandler.php    # Processes incoming FastSpring events
    ApiClient.php         # FastSpring REST API client
    Constants.php         # Plugin constants
    Admin/
      Settings.php        # Gateway settings field definitions + help sidebar
  assets/
    js/admin-settings.js  # Admin sidebar interactivity
    css/admin-settings.css # Admin settings page styles
    img/                  # Payment method SVG icons
```

## License

GPL-2.0-or-later. See [LICENSE](woocommerce-fastspring-gateway/LICENSE) for details.
