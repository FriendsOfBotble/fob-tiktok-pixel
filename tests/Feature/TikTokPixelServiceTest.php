<?php

namespace FriendsOfBotble\TikTokPixel\Tests\Feature;

use Botble\Setting\Facades\Setting;
use Botble\Setting\Supports\SettingStore;
use FriendsOfBotble\TikTokPixel\Services\TikTokPixelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TikTokPixelServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(SettingStore::class)->forgetAll();
    }

    public function testIsDisabledByDefault(): void
    {
        $service = new TikTokPixelService();
        $this->assertFalse($service->isEnabled());
    }

    public function testIsEnabledWhenConfigured(): void
    {
        Setting::set('tiktok_pixel_enabled', true);
        Setting::set('tiktok_pixel_id', 'CTEST123');
        Setting::save();

        $service = new TikTokPixelService();
        $this->assertTrue($service->isEnabled());
    }

    public function testIsDisabledWithoutPixelId(): void
    {
        Setting::set('tiktok_pixel_enabled', true);
        Setting::save();

        $service = new TikTokPixelService();
        $this->assertFalse($service->isEnabled());
    }

    public function testGetPixelId(): void
    {
        Setting::set('tiktok_pixel_id', 'CTEST456');
        Setting::save();

        $service = new TikTokPixelService();
        $this->assertEquals('CTEST456', $service->getPixelId());
    }

    public function testGetPixelIdReturnsNullWhenNotSet(): void
    {
        $service = new TikTokPixelService();
        $this->assertNull($service->getPixelId());
    }

    public function testIsEventEnabledDefaultsToTrue(): void
    {
        $service = new TikTokPixelService();
        $this->assertTrue($service->isEventEnabled('page_view'));
        $this->assertTrue($service->isEventEnabled('view_content'));
        $this->assertTrue($service->isEventEnabled('add_to_cart'));
    }

    public function testIsEventEnabledRespectsSettings(): void
    {
        Setting::set('tiktok_pixel_track_page_view', false);
        Setting::save();

        $service = new TikTokPixelService();
        $this->assertFalse($service->isEventEnabled('page_view'));
    }

    public function testHashValueProducesSha256(): void
    {
        $service = new TikTokPixelService();
        $hash = $service->hashValue('test@example.com');
        $this->assertEquals(hash('sha256', 'test@example.com'), $hash);
    }

    public function testHashValueNormalizesInput(): void
    {
        $service = new TikTokPixelService();
        $hash1 = $service->hashValue('Test@Example.COM');
        $hash2 = $service->hashValue('test@example.com');
        $this->assertEquals($hash1, $hash2);
    }

    public function testHashValueTrimsWhitespace(): void
    {
        $service = new TikTokPixelService();
        $hash1 = $service->hashValue('  test@example.com  ');
        $hash2 = $service->hashValue('test@example.com');
        $this->assertEquals($hash1, $hash2);
    }

    public function testGenerateEventIdReturnsUuid(): void
    {
        $service = new TikTokPixelService();
        $id = $service->generateEventId();
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $id
        );
    }

    public function testGenerateEventIdReturnsUniqueValues(): void
    {
        $service = new TikTokPixelService();
        $id1 = $service->generateEventId();
        $id2 = $service->generateEventId();
        $this->assertNotEquals($id1, $id2);
    }

    public function testBufferClientEvent(): void
    {
        Setting::set('tiktok_pixel_enabled', true);
        Setting::set('tiktok_pixel_id', 'CTEST789');
        Setting::save();

        $service = new TikTokPixelService();
        $service->bufferClientEvent('ViewContent', ['content_id' => '123']);

        $events = $service->getClientEvents();
        $this->assertCount(1, $events);
        $this->assertEquals('ViewContent', $events[0]['event']);
        $this->assertEquals('123', $events[0]['properties']['content_id']);
        $this->assertNotEmpty($events[0]['event_id']);
    }

    public function testBufferClientEventWithCustomEventId(): void
    {
        Setting::set('tiktok_pixel_enabled', true);
        Setting::set('tiktok_pixel_id', 'CTEST789');
        Setting::save();

        $service = new TikTokPixelService();
        $service->bufferClientEvent('CompletePayment', ['value' => 99.99], 'custom-event-id');

        $events = $service->getClientEvents();
        $this->assertEquals('custom-event-id', $events[0]['event_id']);
    }

    public function testBufferClientEventSkipsWhenDisabled(): void
    {
        $service = new TikTokPixelService();
        $service->bufferClientEvent('ViewContent', ['content_id' => '123']);

        $this->assertEmpty($service->getClientEvents());
    }

    public function testBufferClientEventSkipsDisabledEventType(): void
    {
        Setting::set('tiktok_pixel_enabled', true);
        Setting::set('tiktok_pixel_id', 'CTEST789');
        Setting::set('tiktok_pixel_track_view_content', false);
        Setting::save();

        $service = new TikTokPixelService();
        $service->bufferClientEvent('ViewContent', ['content_id' => '123']);

        $this->assertEmpty($service->getClientEvents());
    }

    public function testStoreAccessTokenEncrypts(): void
    {
        $result = TikTokPixelService::storeAccessToken('test-token-123');
        $this->assertTrue($result);

        $encrypted = setting('tiktok_pixel_access_token');
        $this->assertNotEquals('test-token-123', $encrypted);
        $this->assertEquals('test-token-123', Crypt::decryptString($encrypted));
    }

    public function testHasAccessTokenReturnsFalseByDefault(): void
    {
        $service = new TikTokPixelService();
        $this->assertFalse($service->hasAccessToken());
    }

    public function testHasAccessTokenReturnsTrueWhenStored(): void
    {
        TikTokPixelService::storeAccessToken('test-token');

        $service = new TikTokPixelService();
        $this->assertTrue($service->hasAccessToken());
    }

    public function testIsEventsApiEnabledRequiresAllConditions(): void
    {
        $service = new TikTokPixelService();
        $this->assertFalse($service->isEventsApiEnabled());

        Setting::set('tiktok_pixel_enabled', true);
        Setting::set('tiktok_pixel_id', 'CTEST');
        Setting::set('tiktok_pixel_events_api_enabled', true);
        Setting::save();

        $service = new TikTokPixelService();
        $this->assertFalse($service->isEventsApiEnabled(), 'Should be false without access token');

        TikTokPixelService::storeAccessToken('test-token');

        $service = new TikTokPixelService();
        $this->assertTrue($service->isEventsApiEnabled());
    }

    public function testSendServerEventReturnsErrorWhenDisabled(): void
    {
        $service = new TikTokPixelService();
        $result = $service->sendServerEvent('ViewContent', ['test' => true]);

        $this->assertFalse($result['success']);
        $this->assertEquals('Events API not enabled', $result['message']);
    }

    public function testSendServerEventSuccess(): void
    {
        $this->enableEventsApi();

        Http::fake(['*' => Http::response(['code' => 0, 'message' => 'OK'], 200)]);

        $service = new TikTokPixelService();
        $result = $service->sendServerEvent('ViewContent', ['content_id' => '123']);

        $this->assertTrue($result['success']);
        $this->assertNotEmpty($result['event_id']);
        $this->assertEquals(0, $result['data']['code']);
    }

    public function testSendServerEventApiError(): void
    {
        $this->enableEventsApi();

        Http::fake(['*' => Http::response(['code' => 40002, 'message' => 'Invalid pixel code'], 200)]);

        $service = new TikTokPixelService();
        $result = $service->sendServerEvent('ViewContent', ['content_id' => '123']);

        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid pixel code', $result['message']);
        $this->assertEquals(40002, $result['code']);
    }

    public function testSendServerEventHttpError(): void
    {
        $this->enableEventsApi();

        Http::fake(['*' => Http::response('Server Error', 500)]);

        $service = new TikTokPixelService();
        $result = $service->sendServerEvent('ViewContent', ['content_id' => '123']);

        $this->assertFalse($result['success']);
        $this->assertStringContains('500', $result['message']);
    }

    public function testSendServerEventIncludesTestEventCode(): void
    {
        $this->enableEventsApi();
        Setting::set('tiktok_pixel_test_event_code', 'TEST123');
        Setting::save();

        Http::fake(['*' => Http::response(['code' => 0, 'message' => 'OK'], 200)]);

        $service = new TikTokPixelService();
        $service->sendServerEvent('ViewContent', ['content_id' => '123']);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return isset($data['data'][0]['test_event_code'])
                && $data['data'][0]['test_event_code'] === 'TEST123';
        });
    }

    public function testSendServerEventUsesCustomEventId(): void
    {
        $this->enableEventsApi();

        Http::fake(['*' => Http::response(['code' => 0, 'message' => 'OK'], 200)]);

        $service = new TikTokPixelService();
        $result = $service->sendServerEvent('ViewContent', ['content_id' => '123'], [], 'my-custom-event-id');

        $this->assertEquals('my-custom-event-id', $result['event_id']);
    }

    public function testTestConnectionSendsViewContentEvent(): void
    {
        $this->enableEventsApi();

        Http::fake(['*' => Http::response(['code' => 0, 'message' => 'OK'], 200)]);

        $service = new TikTokPixelService();
        $result = $service->testConnection();

        $this->assertTrue($result['success']);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $data['data'][0]['event'] === 'ViewContent'
                && $data['data'][0]['properties']['content_id'] === 'test';
        });
    }

    public function testGetDecryptedAccessTokenReturnsToken(): void
    {
        TikTokPixelService::storeAccessToken('my-secret-token');

        $service = new TikTokPixelService();
        $this->assertEquals('my-secret-token', $service->getDecryptedAccessToken());
    }

    public function testGetDecryptedAccessTokenReturnsNullWhenNotSet(): void
    {
        $service = new TikTokPixelService();
        $this->assertNull($service->getDecryptedAccessToken());
    }

    public function testEventNameToSettingKeyMapsKnownEvents(): void
    {
        $service = new TikTokPixelService();

        $this->assertEquals('page_view', $service->eventNameToSettingKey('PageView'));
        $this->assertEquals('view_content', $service->eventNameToSettingKey('ViewContent'));
        $this->assertEquals('add_to_cart', $service->eventNameToSettingKey('AddToCart'));
        $this->assertEquals('initiate_checkout', $service->eventNameToSettingKey('InitiateCheckout'));
        $this->assertEquals('complete_payment', $service->eventNameToSettingKey('CompletePayment'));
        $this->assertEquals('search', $service->eventNameToSettingKey('Search'));
        $this->assertEquals('contact', $service->eventNameToSettingKey('SubmitForm'));
        $this->assertEquals('contact', $service->eventNameToSettingKey('Contact'));
    }

    public function testEventNameToSettingKeyReturnsNullForUnknownEvent(): void
    {
        $service = new TikTokPixelService();
        $this->assertNull($service->eventNameToSettingKey('UnknownEvent'));
    }

    public function testBufferClientEventAllowsUnknownEventWhenEnabled(): void
    {
        Setting::set('tiktok_pixel_enabled', true);
        Setting::set('tiktok_pixel_id', 'CTEST789');
        Setting::save();

        $service = new TikTokPixelService();
        $service->bufferClientEvent('CustomEvent', ['data' => 'test']);

        $events = $service->getClientEvents();
        $this->assertCount(1, $events);
        $this->assertEquals('CustomEvent', $events[0]['event']);
    }

    protected function enableEventsApi(): void
    {
        Setting::set('tiktok_pixel_enabled', true);
        Setting::set('tiktok_pixel_id', 'CTEST');
        Setting::set('tiktok_pixel_events_api_enabled', true);
        Setting::save();
        TikTokPixelService::storeAccessToken('test-token');
    }

    protected function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertTrue(
            str_contains($haystack, $needle),
            "Failed asserting that '{$haystack}' contains '{$needle}'"
        );
    }
}
