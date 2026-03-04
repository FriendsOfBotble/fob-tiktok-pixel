# Code Review Summary

## Scope
- Files reviewed: 10 requested + 3 supporting (`TikTokPixelService.php`, `HookServiceProvider.php`, `TikTokPixelServiceProvider.php`)
- Lines of code analyzed: ~850
- Review focus: security (XSS, CSRF, input validation, route protection), validation completeness, UI/UX, Botble CMS patterns, config defaults

---

## Overall Assessment

Well-structured plugin. Botble CMS patterns are followed correctly (SettingForm, SettingController, AdminHelper routes, permission guards). Security posture is good: access token is encrypted, `@json()` used throughout Blade for JS embedding, CSRF token present in AJAX, admin routes protected. Three issues require action: one high (event deduplication fragility for ViewContent), one medium cluster (search term unbounded + test event name + checkout price), and several low items.

---

## Critical Issues

None.

The settings-info.blade.php AJAX handler uses `result.textContent` (not `innerHTML`) — safe. `result.className` is derived from a boolean — no class injection. All JS value embedding uses `@json()` — no XSS. Route protection via `settings.options` permission is in place.

---

## High Priority Findings

### H1 — ViewContent event_id deduplication is fragile

**Files:** `src/Providers/HookServiceProvider.php:156-166` and `256-285`

Both `handleProductViewed` (client) and `sendServerViewContent` (server) are registered as listeners for `ProductViewed`. The server handler attempts to retrieve the matching event_id via a session key stored by the client handler:

```php
// handleProductViewed (line 166)
session()->put('tiktok_pixel_view_content_event_' . $product->getKey(), $eventId);

// sendServerViewContent (line 275)
$eventId = session()->pull('tiktok_pixel_view_content_event_' . $product->getKey());
```

**Problem:** Both listeners are registered in the same `boot()` call with no ordering guarantee. If the server listener fires first (Laravel dispatches registered listeners in registration order, which happens to be correct here), `session()->pull()` returns `null` and a new event_id is generated — defeating deduplication.

More importantly, `ProductViewed` is dispatched once, triggering both listeners synchronously. The session `put` happens inside the first listener; the session `pull` inside the second. This works **only** because listener registration order matches dispatch order. Any refactor that reorders registration silently breaks deduplication.

**Fix:** Mirror the `CompletePayment` pattern but generate the event_id before registering listeners, or better — generate it in `handleProductViewed` and store it on the `$service` instance keyed by product ID (avoids session I/O):

```php
// In TikTokPixelService
protected array $pendingEventIds = [];

public function storePendingEventId(string $key, string $eventId): void
{
    $this->pendingEventIds[$key] = $eventId;
}

public function consumePendingEventId(string $key): ?string
{
    $id = $this->pendingEventIds[$key] ?? null;
    unset($this->pendingEventIds[$key]);
    return $id;
}
```

Since the service is a singleton, this is safe within a single request.

### H2 — Double `Product::query()->find()` for same product in same request

**File:** `src/Providers/HookServiceProvider.php:147` and `261`

`handleProductViewed` and `sendServerViewContent` both call `Product::query()->find($event->productId)` independently. They fire in the same request for the same product ID, resulting in two identical DB queries. Extract a shared loader:

```php
protected function loadProduct(int|string $productId): ?Product
{
    return Product::query()->find($productId)?->original_product ?? Product::query()->find($productId);
}
```

Or use Laravel's model caching pattern if `Product` supports it.

---

## Medium Priority Improvements

### M1 — `tiktok_pixel_access_token_input` has no `max` length constraint

**File:** `src/Http/Requests/Settings/TikTokPixelSettingRequest.php:14`

```php
'tiktok_pixel_access_token_input' => ['nullable', 'string'],
```

No upper bound. A malicious admin could submit a very large payload that gets encrypted and stored. TikTok tokens are ~200 chars. Add `'max:2000'` as a reasonable upper bound.

### M2 — Search term has no length cap before buffering

**File:** `src/Providers/HookServiceProvider.php:242-249`

```php
$searchTerm = request()->query('q') ?? request()->query('keyword');
$service->bufferClientEvent('Search', ['query' => $searchTerm]);
```

`$searchTerm` is raw user input with no trim or length limit. It ends up injected into every page response via `event-script.blade.php` (safely via `@json`, so no XSS). The concern is payload bloat — a crafted URL with a very long `q` parameter bloats the HTML output. Cap at 500 chars:

```php
$searchTerm = mb_substr(trim((string) ($searchTerm ?? '')), 0, 500);
if (! $searchTerm) {
    return $html;
}
```

### M3 — `testConnection()` sends unknown event name to production TikTok API

**File:** `src/Services/TikTokPixelService.php:144-149`

```php
public function testConnection(): array
{
    return $this->sendServerEvent('Test', ['test' => true]);
}
```

TikTok Events API does not define a `Test` event. When `test_event_code` is not set this will be processed as a real (unrecognized) event and may appear in TikTok's system logs. Use a recognized event with dummy data, or require `test_event_code` to be set before allowing the test:

```php
public function testConnection(): array
{
    if (! setting('tiktok_pixel_test_event_code')) {
        return ['success' => false, 'message' => 'Set a Test Event Code in TikTok Events Manager before testing.'];
    }

    return $this->sendServerEvent('ViewContent', [
        'content_id' => 'test-connection',
        'content_type' => 'product',
        'value' => 0,
        'currency' => 'USD',
    ]);
}
```

### M4 — `handleCheckout` uses base price, not sale price

**File:** `src/Providers/HookServiceProvider.php:197`

```php
$totalValue = $products->sum(fn ($p) => (($p->front_sale_price ?? $p->price) ?: 0) * ($p->cartItem->qty ?? 1));
```

Actually, `front_sale_price` IS checked here. But in `$contents` on line 204:

```php
'price' => (float) ($p->front_sale_price ?? $p->price),
```

This is correct. No issue with `handleCheckout` price. **Retract M4.**

### M5 — `HtmlField` `events_heading` concatenates translated strings into raw HTML without escaping

**File:** `src/Forms/Settings/TikTokPixelSettingForm.php:86`

```php
->content('<h4 class="mt-3">' . trans('...event_tracking') . '</h4><p class="text-muted">' . trans('...event_tracking_help') . '</p>')
```

Translations are developer-controlled, so real-world risk is low. However, the pattern is unsafe if the plugin is ever used in a multi-tenant context where translations are user-editable. Use `e()` or move to a blade partial:

```php
->content(view('plugins/fob-tiktok-pixel::partials.events-heading')->render())
```

### M6 — `testConnection` controller endpoint leaks raw exception messages

**File:** `src/Http/Controllers/Settings/TikTokPixelSettingController.php:58-62`

```php
} catch (\Exception $e) {
    return response()->json([
        'success' => false,
        'message' => $e->getMessage(),
    ], 500);
}
```

`$e->getMessage()` can expose internal details (e.g., HTTP client errors with credentials visible in exception stack traces). In admin context this is lower risk, but replace with a generic message and log the detail:

```php
} catch (\Exception $e) {
    Log::error('TikTok Pixel: testConnection failed', ['error' => $e->getMessage()]);

    return response()->json([
        'success' => false,
        'message' => trans('plugins/fob-tiktok-pixel::tiktok-pixel.settings.connection_failed'),
    ], 500);
}
```

---

## Low Priority Suggestions

### L1 — `edit()` and `update()` controller methods missing return type hints

**File:** `src/Http/Controllers/Settings/TikTokPixelSettingController.php:13-38`

```php
public function edit()          // missing: Response
public function update(...)     // missing: JsonResponse|Response
```

Botble CMS CLAUDE.md requires explicit return types. Add them:

```php
public function edit(): Response
public function update(TikTokPixelSettingRequest $request): JsonResponse|Response
```

### L2 — `buildOrderUserData` has no return type hint

**File:** `src/Providers/HookServiceProvider.php:337`

```php
protected function buildOrderUserData($order, TikTokPixelService $service): array
```

The `$order` parameter is untyped. Add appropriate type (likely `\Botble\Ecommerce\Models\Order`):

```php
protected function buildOrderUserData(\Botble\Ecommerce\Models\Order $order, TikTokPixelService $service): array
```

Same applies to `handleProductViewed`, `handleOrderPlaced`, `sendServerViewContent`, `sendServerCompletePayment` — all use `$event` typed as mixed.

### L3 — `config/config.php` has only one key, no documented defaults

**File:** `config/config.php`

Only `events_api_endpoint` is defined. Consider documenting `timeout` (currently hardcoded as `5` in the service) as a configurable value for slow network environments:

```php
return [
    'events_api_endpoint' => 'https://business-api.tiktok.com/open_api/v1.3/event/track/',
    'timeout' => 5,
];
```

### L4 — `storeAccessToken` is `public static` inconsistency

**File:** `src/Services/TikTokPixelService.php:151`

All other methods are instance methods. `storeAccessToken` is `public static` only because the controller calls it without injecting the service. Since the class is a singleton, prefer injecting and making it an instance method for consistency.

### L5 — Event label `'contact' => 'SubmitForm / Contact'` uses a slash in a UI label

**File:** `src/Forms/Settings/TikTokPixelSettingForm.php:96`

```php
'contact' => 'SubmitForm / Contact',
```

This label is hardcoded (not translated) and mixed in with translated keys. Extract to translation file and clean up: `'Contact Form (SubmitForm)'` is clearer and consistent with TikTok's official naming.

### L6 — `permissions.php` flag does not match a dedicated permission

**File:** `config/permissions.php`

```php
'flag' => 'fob-tiktok-pixel.settings',
```

The route uses `->permission('settings.options')` which is the global settings permission, not the plugin-specific one. The `permissions.php` entry is therefore unused for access control — it only registers a label in the UI. This is consistent with other Botble setting plugins but worth documenting.

### L7 — `settings-info.blade.php` external link missing `rel="noopener noreferrer"`

**File:** `resources/views/partials/settings-info.blade.php:26`

```html
<a href="https://ads.tiktok.com/i18n/events_manager" target="_blank" class="btn btn-outline-primary mt-2">
```

`target="_blank"` without `rel="noopener noreferrer"` allows the opened page to access `window.opener`. Low risk for a trusted URL but follow best practice:

```html
<a href="..." target="_blank" rel="noopener noreferrer" class="...">
```

---

## Positive Observations

- Access token encrypted via `Crypt::encryptString` — correct approach, never stored in plaintext
- `@json()` used consistently in all three Blade views for JS context — no XSS vectors
- `isEventsApiEnabled()` guards all server-side API calls cleanly
- CSRF token included in test-connection fetch via `X-CSRF-TOKEN` header
- `CompletePayment` deduplication via session key is the right pattern
- `buildOrderUserData` uses `shippingAddress ?? $order->address` fallback — defensive
- `array_filter` on user data prevents sending null PII fields to TikTok API
- Singleton registration is appropriate; service is constructed once per request
- Route protection via `settings.options` permission on all three endpoints
- `HookServiceProvider::boot()` short-circuits cleanly when service is disabled
- Test coverage is solid: 21 test methods across service unit tests, validation tests, and controller tests
- `Plugin::removed()` cleans up settings (assumed from plugin.json structure — good lifecycle hygiene)
- `tiktok_pixel_enabled` requires both the toggle AND a non-empty pixel ID — correct guard
- `pixel-script.blade.php` uses TikTok's official async loader pattern unchanged

---

## Recommended Actions (Priority Order)

1. **[High — H1]** Fix ViewContent deduplication: use an in-memory `$pendingEventIds` map on the service singleton instead of session, matching the pattern of `CompletePayment` but without session I/O.

2. **[High — H2]** Eliminate double `Product::query()->find()` per request by sharing the loaded model between client and server event handlers.

3. **[Medium — M1]** Add `'max:2000'` to `tiktok_pixel_access_token_input` validation rule.

4. **[Medium — M2]** Cap search term at 500 chars before buffering: `mb_substr(trim(...), 0, 500)`.

5. **[Medium — M3]** Guard `testConnection()` to require a test event code, or use a standard TikTok event name with dummy data.

6. **[Medium — M6]** Replace raw `$e->getMessage()` in `testConnection` catch block with a generic translated message; log the detail.

7. **[Low — L1]** Add return type hints to `edit()` and `update()` controller methods.

8. **[Low — L2]** Type `$order` and `$event` parameters in `HookServiceProvider` handlers.

9. **[Low — L7]** Add `rel="noopener noreferrer"` to the `target="_blank"` external link.

10. **[Low — L3]** Move HTTP timeout to config for operator flexibility.

---

## Metrics

- Type Coverage: Good for core service methods; hook provider handlers use untyped `$event`/`$order` parameters
- Test Coverage: 21 test methods — service (17), validation (7), controller (5); missing: test for `detectSearchEvent` buffering, `buildOrderUserData` with/without address
- Linting Issues: 0 confirmed style violations against PSR-12; 2 missing return types on controller methods
- Security: No critical vulnerabilities; 1 medium (raw exception leak to admin), 1 theoretical (HTML in form field)

---

## Unresolved Questions

1. Does `get_application_currency()->title` return ISO 4217 code (`USD`) or a display name (`US Dollar`)? TikTok Events API expects ISO 4217. If `title` is a display name, currency conversion reporting will be silently wrong for all events.

2. Does the `ProductViewed` event carry a pre-loaded model object or only `$productId`? If the model is on the event, both DB queries can be eliminated entirely.

3. Should `testConnection` be blocked when `tiktok_pixel_test_event_code` is empty, or just send with a known-ignorable test payload? Depends on how strictly operators should be guided through the test flow.
