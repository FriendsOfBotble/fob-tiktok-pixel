# Code Review Summary

## Scope

- Files reviewed: 10
  - `tests/Feature/TikTokPixelServiceTest.php` (221 lines)
  - `tests/Feature/TikTokPixelSettingControllerTest.php` (97 lines)
  - `tests/Feature/TikTokPixelSettingValidationTest.php` (110 lines)
  - `README.md`, `CHANGELOG.md`, `CONTRIBUTING.md`, `LICENSE`, `docs/usage.md`
  - `plugin.json`, `composer.json`
- Source files cross-referenced: `TikTokPixelService.php`, `TikTokPixelSettingController.php`, `TikTokPixelSettingRequest.php`, `HookServiceProvider.php`
- Review focus: Test coverage gaps, test quality, documentation accuracy, plugin metadata

---

## Overall Assessment

Tests are well-structured and follow project conventions. The happy-path and basic failure cases are covered solidly for `TikTokPixelService`. However, the entire `HookServiceProvider` is untested, two public methods on `TikTokPixelService` have zero coverage, and several controller scenarios are missing. Documentation is accurate and thorough with one notable inaccuracy regarding the `Contact` event default state.

---

## Critical Issues

None.

---

## High Priority Findings

### H1 — `HookServiceProvider` is completely untested

Every event-handling method — `handleProductViewed`, `handleAddToCart`, `handleCheckout`, `handleOrderPlaced`, `sendServerViewContent`, `sendServerCompletePayment`, `detectSearchEvent`, `buildCustomerUserData`, `buildOrderUserData` — has zero test coverage. This is where the core business logic lives (event buffering, server-side dispatch, session-based deduplication). A bug in any of these methods would be silent.

Recommended additions:
- Test that `ProductViewed` fires both `bufferClientEvent('ViewContent')` and `sendServerEvent('ViewContent')` when Events API is enabled.
- Test that `OrderPlacedEvent` buffers `CompletePayment` client event and stores the `event_id` in session.
- Test that the same `event_id` is pulled from session on the server-side send (deduplication roundtrip).
- Test `detectSearchEvent` sets a Search event when `?q=` param is present on a matching route.
- Test `buildOrderUserData` correctly hashes email/phone from `shippingAddress` and falls back to `address`.
- Test `class_exists` guards: ecommerce hooks must not register when ecommerce classes are absent.

### H2 — `testConnection()` and `getDecryptedAccessToken()` untested

`testConnection()` is a public method that delegates to `sendServerEvent('Test', ...)`. The controller test covers the HTTP 400 path but never exercises a successful or failed API response, and `testConnection()` itself is never called in tests. `getDecryptedAccessToken()` has no test at all — it can be verified trivially by calling `TikTokPixelService::storeAccessToken()` and then asserting the decrypted value matches.

### H3 — `sendServerEvent` success/failure paths untested

`testSendServerEventReturnsErrorWhenDisabled` only tests the guard clause. The actual HTTP logic — successful API response (code 0), API error (code != 0), HTTP non-2xx response, and exception/timeout — is never exercised. Use `Http::fake()` to cover these branches.

```php
public function testSendServerEventSucceeds(): void
{
    Http::fake([
        '*' => Http::response(['code' => 0, 'message' => 'OK'], 200),
    ]);

    // configure service with enabled state + stored token
    $result = $service->sendServerEvent('ViewContent', []);
    $this->assertTrue($result['success']);
}

public function testSendServerEventHandlesApiError(): void
{
    Http::fake([
        '*' => Http::response(['code' => 40002, 'message' => 'Invalid pixel code'], 200),
    ]);
    $result = $service->sendServerEvent('ViewContent', []);
    $this->assertFalse($result['success']);
    $this->assertEquals('Invalid pixel code', $result['message']);
}
```

---

## Medium Priority Improvements

### M1 — Controller test: no assertion on persisted setting values for boolean toggles

`testSettingsCanBeUpdated` asserts `setting('tiktok_pixel_id')` but does not assert any of the boolean toggles (e.g., `tiktok_pixel_enabled`, `tiktok_pixel_track_page_view`). A regression in `performUpdate` that drops boolean fields would not be caught.

### M2 — Controller test: no unauthenticated test for PUT endpoint

`testUnauthenticatedCannotAccessSettings` only tests the GET route. The PUT update endpoint should also assert redirect/401 for unauthenticated requests.

```php
public function testUnauthenticatedCannotUpdateSettings(): void
{
    $this->putJson(route('fob-tiktok-pixel.settings.update'), [])
        ->assertUnauthorized();
}
```

### M3 — Controller test: `storeAccessToken` failure path untested

The controller has an explicit error branch when `TikTokPixelService::storeAccessToken()` returns `false` (line 28-31 of the controller). This path is never tested. It requires mocking `Crypt` or injecting a mock service.

### M4 — Validation test: `tiktok_pixel_test_event_code` missing

`TikTokPixelSettingRequest` defines a rule for `tiktok_pixel_test_event_code` (`nullable|string|max:120`). `TikTokPixelSettingValidationTest` never tests this field. The max-length boundary (121 chars) should be covered just like `tiktok_pixel_id`.

### M5 — Validation test: nullable fields not tested as absent

All validation tests supply a value. None verify that omitting the field entirely passes (nullable). This is trivial but ensures `nullable` rules are actually in place and haven't been accidentally changed to `required`.

### M6 — `eventNameToSettingKey` default branch maps unknown events to `page_view`

`bufferClientEvent` calls `eventNameToSettingKey($event)` and the `default` case returns `'page_view'`. This means calling `bufferClientEvent('UnknownEvent', [...])` will check `tiktok_pixel_track_page_view` rather than silently skipping or throwing. This is surprising behavior and is not tested. Add a test for an unknown event name and decide the intended contract.

### M7 — `composer.json` type is `package` — should be `library` or omitted

`"type": "package"` is not a standard Composer type for a PHP library. Botble plugins are discovered via `plugin.json`, so this is harmless, but the conventional Composer type for a reusable library is `"library"`. `"package"` is a Composer repository source type, not a package type, which could confuse tooling.

---

## Low Priority Suggestions

### L1 — Documentation: Contact event default is inconsistent

`docs/usage.md` (line 84) states the Contact/SubmitForm toggle defaults to **OFF**. `README.md` events table (line 47-55) does not specify defaults. `TikTokPixelService::isEventEnabled()` uses `setting('tiktok_pixel_track_' . $event, true)` — the hardcoded default is `true` (ON), not OFF. The `docs/usage.md` entry is inaccurate and should match the code default.

### L2 — CHANGELOG claims "33 tests, 62 assertions" — unverifiable without running

These numbers should be removed or replaced with a badge/CI link. Hardcoded counts go stale immediately when tests are added.

### L3 — CONTRIBUTING references PHP_CodeSniffer — project uses Laravel Pint

The CONTRIBUTING guide says "install PHP Code Sniffer" for coding standards. This project uses `./vendor/bin/pint` (Laravel Pint / PHP-CS-Fixer). The CONTRIBUTING guide should be updated to reference Pint.

### L4 — `plugin.json` missing `require` field for ecommerce soft dependency

`composer.json` has an empty `require: {}`. `plugin.json` has no `require` key. The plugin soft-depends on `botble/ecommerce` for most of its value. Documenting this (even as optional) in `plugin.json`'s `require` or a `suggest` equivalent helps operators understand what's needed for full functionality.

### L5 — `testStoreAccessTokenEncrypts` re-reads `setting()` after save without SettingStore reset

`storeAccessToken` calls `setting()->set()->save()` which updates the store. The test then calls `setting('tiktok_pixel_access_token')` to read back. This works because `setUp` calls `forgetAll()` before the test, not after, so the state is clean at start. This is correct but fragile: if `storeAccessToken` were to update an in-memory cache differently from `Setting::set()`, the test would silently pass with stale data. Minor — acceptable as-is.

---

## Positive Observations

- `setUp()` correctly calls `app(SettingStore::class)->forgetAll()` in all tests that interact with settings — no cross-test state leakage.
- `testIsEventsApiEnabledRequiresAllConditions` is a proper multi-step integration assertion that verifies all three guard conditions incrementally. Good pattern.
- `createUser()` follows the established project convention from `platform/plugins/real-estate/tests/`.
- Hash normalization tests (case + whitespace) directly validate the security contract that PII is normalized before hashing.
- `bufferClientEvent` tests cover both the custom `eventId` path and the disabled-event skip path.
- `docs/usage.md` architecture section is accurate and matches the actual source structure.
- `sendServerEvent` correctly wraps HTTP calls in try/catch and returns structured error arrays — the service contract is well-defined.

---

## Recommended Actions

1. **[High]** Add `HookServiceProvider` integration tests using `Http::fake()`, Laravel event dispatch, and session assertions to cover the core event pipeline.
2. **[High]** Add `Http::fake()` tests for `sendServerEvent` success, API error (code != 0), HTTP non-2xx, and exception paths.
3. **[High]** Add test for `testConnection()` and `getDecryptedAccessToken()` public methods.
4. **[Medium]** Add unauthenticated PUT test to `TikTokPixelSettingControllerTest`.
5. **[Medium]** Assert persisted boolean settings in `testSettingsCanBeUpdated`.
6. **[Medium]** Add validation test for `tiktok_pixel_test_event_code` max-length boundary.
7. **[Medium]** Clarify or fix `eventNameToSettingKey` default branch behavior and add a test for unknown event names.
8. **[Low]** Fix `docs/usage.md`: Contact event default is ON (true), not OFF.
9. **[Low]** Update `CONTRIBUTING.md` to reference Laravel Pint instead of PHP_CodeSniffer.
10. **[Low]** Fix `composer.json` type from `"package"` to `"library"`.
11. **[Low]** Remove hardcoded assertion counts from `CHANGELOG.md`.

---

## Metrics

- Type Coverage: N/A (PHP, not TypeScript)
- Test Coverage (estimated):
  - `TikTokPixelService` public methods: ~80% (missing `testConnection`, `getDecryptedAccessToken`, `sendServerEvent` HTTP paths)
  - `TikTokPixelSettingController`: ~50% (missing unauthenticated PUT, token-store failure path)
  - `TikTokPixelSettingRequest` rules: ~85% (missing `tiktok_pixel_test_event_code`)
  - `HookServiceProvider`: 0%
- Linting: Not run (no access to test environment) — project mandates `./vendor/bin/pint` before commit
- Documentation accuracy: 1 confirmed inaccuracy (Contact event default state)
