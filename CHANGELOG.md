# Changelog

All notable changes to this project will be documented in this file.

## [1.0.0] - 2026-03-04

### Added
- Initial release of FOB TikTok Pixel plugin
- Client-side TikTok Pixel JavaScript injection via THEME_FRONT_HEADER
- Server-Side Events API integration (v1.3)
- E-commerce event tracking: PageView, ViewContent, AddToCart, InitiateCheckout, CompletePayment, Search, Contact
- Event deduplication between client and server using shared event_id
- Enhanced Matching with SHA256 hashed user data (email, phone, external_id)
- Encrypted access token storage with Laravel Crypt
- Admin settings panel in Settings > Others
- Per-event enable/disable toggles
- Test event code support for development
- Test connection button for Events API verification
- Comprehensive test suite (33 tests, 62 assertions)

### Features
- **Async Pixel Loading**: Non-blocking script injection for zero performance impact
- **Soft E-commerce Dependency**: Works without ecommerce plugin (event hooks guarded with class_exists)
- **Sale Price Aware**: Tracks front_sale_price for accurate conversion values
- **Session-Based Dedup**: Reliable event_id sharing between client/server via session storage
- **Customer Identification**: Automatic ttq.identify() for logged-in customers

### Technical
- Built on Botble CMS 7.5.0+
- Laravel HTTP facade for API calls (no external dependencies)
- Event-driven architecture with Laravel events and action hooks
- PSR-12 code standards (Laravel Pint)
- PHPUnit test coverage

### Settings
- Enable/disable toggle
- Pixel ID field
- Access Token (encrypted storage)
- Events API enable/disable
- Test Event Code for development
- Individual event tracking toggles

### Requirements
- PHP 8.2+
- Botble CMS 7.5.0+
- TikTok Ads Manager account with Pixel
- Access Token (for Events API only)
