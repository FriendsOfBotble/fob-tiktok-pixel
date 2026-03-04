# FOB TikTok Pixel — Usage Guide

## Table of Contents

- [Installation](#installation)
- [Getting Your TikTok Pixel Credentials](#getting-your-tiktok-pixel-credentials)
- [Plugin Configuration](#plugin-configuration)
- [Event Tracking](#event-tracking)
- [Server-Side Events API](#server-side-events-api)
- [Enhanced Matching](#enhanced-matching)
- [Test Mode](#test-mode)
- [Verifying Events](#verifying-events)
- [Troubleshooting](#troubleshooting)
- [Architecture](#architecture)
- [FAQ](#faq)

---

## Installation

1. Copy or symlink the plugin to `platform/plugins/fob-tiktok-pixel/`
2. Activate:
   ```bash
   php artisan cms:plugin:activate fob-tiktok-pixel
   ```
3. No `composer update` needed — this plugin has zero external dependencies.

---

## Getting Your TikTok Pixel Credentials

### Pixel ID

1. Log in to [TikTok Ads Manager](https://ads.tiktok.com)
2. Go to **Assets → Events → Web Events**
3. Click **Create Pixel** (or select an existing one)
4. Choose **Manually Install Pixel Code**
5. Your Pixel ID starts with `C` (e.g., `CXXXXXXXXXXXXXXXXX`)

### Access Token (for Events API)

1. In TikTok Ads Manager, go to **Assets → Events → Web Events**
2. Select your pixel → **Settings**
3. Scroll to **Events API** section
4. Click **Generate Access Token**
5. Copy the token — you'll paste it in the plugin settings

> **Note:** Access tokens do not expire unless manually revoked. Store it securely.

---

## Plugin Configuration

Navigate to **Admin → Settings → Others → TikTok Pixel**.

### Basic Setup (Client-Side Only)

1. Toggle **Enable TikTok Pixel** → ON
2. Enter your **Pixel ID**
3. Click **Save**

This injects the TikTok Pixel JavaScript into your storefront `<head>` tag. It will immediately start tracking PageView events on every page load.

### Full Setup (Client + Server-Side)

1. Complete basic setup above
2. Paste your **Access Token** in the textarea
3. Toggle **Enable Server-Side Events API** → ON
4. Click **Save**
5. Click **Test Connection** to verify

### Event Toggles

Each event can be individually enabled/disabled:

| Toggle | Event | Default |
|--------|-------|---------|
| PageView | Every page load | ON |
| ViewContent | Product page viewed | ON |
| AddToCart | Product added to cart | ON |
| InitiateCheckout | Checkout page loaded | ON |
| CompletePayment | Order placed successfully | ON |
| Search | Search results page | ON |
| Contact / SubmitForm | Contact form submission | OFF |

Disabling an event stops it from firing on **both** client-side and server-side.

---

## Event Tracking

### How Events Are Triggered

The plugin hooks into Botble CMS and ecommerce events automatically:

| Event | Trigger Source | Hook/Event Used |
|-------|---------------|-----------------|
| **PageView** | Every frontend page | `ttq.page()` in pixel base code |
| **ViewContent** | Product detail page | `ProductViewed` Laravel event |
| **AddToCart** | Add-to-cart button | `ecommerce_before_add_to_cart` action |
| **InitiateCheckout** | Checkout page load | `ecommerce_post_checkout` action |
| **CompletePayment** | Order confirmation | `OrderPlacedEvent` Laravel event |
| **Search** | Search results page | URL query parameter detection (`?q=` or `?keyword=`) |
| **Contact** | Contact form submit | Contact plugin hooks (if available) |

### Event Parameters

Each e-commerce event includes relevant parameters:

**ViewContent / AddToCart:**
```json
{
  "content_id": "123",
  "content_type": "product",
  "content_name": "Product Name",
  "value": 29.99,
  "currency": "USD"
}
```

**InitiateCheckout / CompletePayment:**
```json
{
  "contents": [
    {
      "content_id": "123",
      "content_type": "product",
      "content_name": "Product Name",
      "quantity": 2,
      "price": 29.99
    }
  ],
  "value": 59.98,
  "currency": "USD"
}
```

**Search:**
```json
{
  "query": "red shoes"
}
```

### Pricing

The plugin uses the **sale price** (`front_sale_price`) when available, falling back to the regular price. This ensures conversion values match what the customer actually paid.

---

## Server-Side Events API

Server-side tracking sends events directly from your server to TikTok's API, bypassing ad blockers and browser limitations.

### How It Works

```
Customer Action
    ├── Client-Side: ttq.track() in browser (JavaScript)
    └── Server-Side: HTTP POST to TikTok API (PHP)
         └── Same event_id for deduplication
```

### Supported Server-Side Events

| Event | When Sent |
|-------|-----------|
| **ViewContent** | Product page viewed |
| **CompletePayment** | Order placed |

### Event Deduplication

Both client and server events share the same `event_id` (UUID). TikTok automatically deduplicates events with matching `event_id` within a 48-hour window.

**How the plugin shares event IDs:**
- A UUID is generated when the event occurs
- Stored in the PHP session (e.g., `tiktok_pixel_order_event_{order_id}`)
- Client-side: passed to `ttq.track()` as `{ event_id: "uuid" }`
- Server-side: included in the API payload
- TikTok matches and deduplicates

### API Endpoint

Events are sent to:
```
POST https://business-api.tiktok.com/open_api/v1.3/event/track/
```

With headers:
```
Access-Token: <your-token>
Content-Type: application/json
```

### Server-Side User Data

Server events include user data for better matching:

| Field | Source | Hashing |
|-------|--------|---------|
| `ip` | Request IP | Plain |
| `user_agent` | Request User-Agent | Plain |
| `email` | Customer/order email | SHA256 |
| `phone` | Customer/order phone | SHA256 |
| `external_id` | Customer ID | SHA256 |

---

## Enhanced Matching

When a customer is logged in, the plugin automatically calls `ttq.identify()` with hashed user data:

```javascript
ttq.identify({
  email: "sha256_hash_of_email",
  phone_number: "sha256_hash_of_phone",
  external_id: "sha256_hash_of_customer_id"
});
```

This improves ad attribution by matching website visitors to TikTok users. All values are:
- Trimmed of whitespace
- Lowercased
- SHA256 hashed

No plaintext PII is ever sent to TikTok.

---

## Test Mode

### Using Test Event Code

1. In TikTok Ads Manager, go to **Events → Test Events**
2. Copy the **Test Event Code**
3. Paste it in plugin settings → **Test Event Code** field
4. Save settings
5. Browse your site to trigger events
6. Events appear in TikTok's **Test Events** tab without affecting production data

> **Important:** Remove the test event code after testing. Events sent with a test code are NOT counted as real conversions.

### Test Connection Button

The **Test Connection** button sends a test event to the Events API to verify:
- Access token is valid
- Pixel ID is correct
- Server can reach TikTok's API

---

## Verifying Events

### In Browser (Client-Side)

1. Open browser DevTools → **Console**
2. Look for `ttq` calls (no errors = pixel loaded)
3. Check **Network** tab → filter for `analytics.tiktok.com`
4. You should see requests for each event

### TikTok Pixel Helper (Chrome Extension)

1. Install [TikTok Pixel Helper](https://chrome.google.com/webstore/detail/tiktok-pixel-helper) from Chrome Web Store
2. Visit your site
3. The extension icon shows a green badge with event count
4. Click to see event details

### TikTok Events Manager

1. Go to **Assets → Events → Web Events** in TikTok Ads Manager
2. Select your pixel
3. Check **Overview** tab for recent events
4. Use **Test Events** tab during development
5. Allow 15-20 minutes for events to appear

### Server-Side Logs

Server-side events are logged in Laravel's log file:

```
# Successful events
TikTok Pixel: Event sent {"event":"ViewContent","event_id":"uuid"}

# API errors
TikTok Pixel: API returned error {"event":"CompletePayment","code":40002,"message":"..."}

# Connection errors
TikTok Pixel: Exception sending event {"event":"ViewContent","error":"..."}
```

Check logs at: `storage/logs/laravel.log`

---

## Troubleshooting

### Pixel Not Loading

**Symptoms:** No TikTok requests in browser Network tab, TikTok Pixel Helper shows nothing.

**Checklist:**
1. Is the plugin enabled? (Settings → Others → TikTok Pixel → Enable toggle ON)
2. Is the Pixel ID entered? (Must not be empty)
3. Are you on the frontend? (Pixel does NOT load in admin panel)
4. Check browser console for JavaScript errors
5. Check if an ad blocker is blocking `analytics.tiktok.com`

### Events Not Firing

**Symptoms:** Pixel loads but specific events don't appear.

**Checklist:**
1. Is the event toggle enabled? (Settings → TikTok Pixel → Event Tracking section)
2. Is the ecommerce plugin installed and activated?
3. For ViewContent: Are you on a product detail page?
4. For Search: Does the URL contain `?q=` or `?keyword=` parameter?
5. Check browser console for `ttq.track()` calls

### Events API Connection Failed

**Symptoms:** Test Connection returns error.

**Causes & Fixes:**
| Error | Cause | Fix |
|-------|-------|-----|
| "Events API not enabled" | Missing prerequisites | Enable plugin + set Pixel ID + paste token + enable Events API toggle, then save |
| "Unauthorized" / 401 | Invalid or expired token | Generate a new access token in TikTok Ads Manager |
| "Invalid pixel code" | Pixel ID mismatch | Ensure Pixel ID matches the pixel associated with your token |
| HTTP timeout | Network issue | Check server can reach `business-api.tiktok.com` (not blocked by firewall) |
| "Invalid parameter" | Malformed data | Check Laravel logs for full error details |

### Duplicate Events in TikTok

**Symptoms:** Same event counted twice.

**Causes & Fixes:**
- If using Events API: The plugin automatically deduplicates using `event_id`. If you still see duplicates, check that JavaScript isn't loading the pixel twice (e.g., from another plugin or manual code)
- If NOT using Events API: Only client-side events fire, so duplicates shouldn't occur. Check for manual TikTok pixel code in your theme

### Wrong Conversion Values

**Symptoms:** Revenue numbers in TikTok don't match actual orders.

**Checklist:**
1. The plugin uses `front_sale_price` (sale price) when available, falling back to regular `price`
2. Currency is taken from `get_application_currency()->title` — ensure your CMS currency code is correct (e.g., "USD", "VND")
3. For CompletePayment, the `value` is the order's `amount` field (total order value)

### Access Token Decryption Error

**Symptoms:** Log shows "Failed to decrypt access token".

**Cause:** The `APP_KEY` in `.env` changed after the token was stored.

**Fix:** Re-enter the access token in plugin settings and save again.

---

## Architecture

### File Structure

```
fob-tiktok-pixel/
├── config/
│   ├── config.php              # API endpoint URL
│   └── permissions.php         # ACL permission flag
├── resources/
│   ├── lang/en/tiktok-pixel.php  # English translations
│   └── views/partials/
│       ├── pixel-script.blade.php   # Base pixel <script> tag
│       ├── event-script.blade.php   # Buffered event ttq.track() calls
│       └── settings-info.blade.php  # Admin panel info/test button
├── routes/
│   └── web.php                 # Admin routes (settings + test connection)
├── src/
│   ├── Forms/Settings/
│   │   └── TikTokPixelSettingForm.php
│   ├── Http/
│   │   ├── Controllers/Settings/
│   │   │   └── TikTokPixelSettingController.php
│   │   └── Requests/Settings/
│   │       └── TikTokPixelSettingRequest.php
│   ├── Providers/
│   │   ├── TikTokPixelServiceProvider.php  # Main provider
│   │   └── HookServiceProvider.php         # Frontend + ecommerce hooks
│   ├── Services/
│   │   └── TikTokPixelService.php          # Core service (singleton)
│   └── Plugin.php                          # Lifecycle (activate/deactivate/remove)
└── tests/
    └── Feature/
        ├── TikTokPixelServiceTest.php
        ├── TikTokPixelSettingControllerTest.php
        └── TikTokPixelSettingValidationTest.php
```

### Data Flow

```
┌─────────────────────────────────────────────────────┐
│                    Frontend Page Load                │
│                                                     │
│  THEME_FRONT_HEADER (priority 15)                   │
│  └── Injects: pixel-script.blade.php                │
│      └── ttq.load(), ttq.identify(), ttq.page()     │
│                                                     │
│  E-commerce Event (e.g., ProductViewed)             │
│  ├── Client handler: bufferClientEvent('ViewContent')│
│  │   └── Stores event_id in session                 │
│  └── Server handler: sendServerEvent('ViewContent') │
│      └── Reads event_id from session (dedup)        │
│                                                     │
│  THEME_FRONT_FOOTER (priority 998)                  │
│  └── Renders: event-script.blade.php                │
│      └── ttq.track() for each buffered event        │
└─────────────────────────────────────────────────────┘
```

### Settings Storage

All settings are stored in the Botble `settings` table with `tiktok_pixel_` prefix:

| Setting Key | Type | Description |
|------------|------|-------------|
| `tiktok_pixel_enabled` | boolean | Master enable/disable |
| `tiktok_pixel_id` | string | TikTok Pixel ID |
| `tiktok_pixel_access_token` | string | Encrypted access token |
| `tiktok_pixel_events_api_enabled` | boolean | Server-side API toggle |
| `tiktok_pixel_test_event_code` | string | Test mode code |
| `tiktok_pixel_track_page_view` | boolean | PageView event toggle |
| `tiktok_pixel_track_view_content` | boolean | ViewContent event toggle |
| `tiktok_pixel_track_add_to_cart` | boolean | AddToCart event toggle |
| `tiktok_pixel_track_initiate_checkout` | boolean | InitiateCheckout event toggle |
| `tiktok_pixel_track_complete_payment` | boolean | CompletePayment event toggle |
| `tiktok_pixel_track_search` | boolean | Search event toggle |
| `tiktok_pixel_track_contact` | boolean | Contact event toggle |

### Security

- **Access token**: Encrypted at rest using `Crypt::encryptString()` (AES-256-CBC via Laravel)
- **User data**: Email, phone, external_id are SHA256 hashed before sending to TikTok
- **Admin only**: Settings page requires `fob-tiktok-pixel.settings` permission
- **Frontend only**: Pixel script skips injection on admin pages (`is_in_admin()` check)
- **XSS safe**: All Blade outputs use `@json()` directive for JavaScript context

---

## FAQ

**Q: Does this plugin work without the ecommerce plugin?**
A: Yes. The plugin checks for ecommerce classes with `class_exists()`. Without ecommerce, only PageView events fire.

**Q: Does the pixel slow down my site?**
A: No. The pixel script loads asynchronously (`async=!0`) and doesn't block page rendering.

**Q: Can I use this with other tracking plugins (Facebook Pixel, Google Analytics)?**
A: Yes. Each tracking plugin operates independently. No conflicts.

**Q: What happens if the Events API call fails?**
A: The error is logged to `storage/logs/laravel.log`. Client-side events still fire normally. The 5-second timeout prevents slow API responses from affecting page load.

**Q: How do I remove all plugin data?**
A: Deactivate and remove the plugin. The `Plugin::removed()` method automatically deletes all `tiktok_pixel_*` settings from the database.

**Q: Which TikTok API version does this use?**
A: Events API v1.3 (`/open_api/v1.3/event/track/`).
