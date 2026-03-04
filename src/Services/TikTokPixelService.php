<?php

namespace FriendsOfBotble\TikTokPixel\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TikTokPixelService
{
    protected ?string $accessToken = null;

    protected array $clientEvents = [];

    public function __construct()
    {
        $this->loadAccessToken();
    }

    public function isEnabled(): bool
    {
        return (bool) setting('tiktok_pixel_enabled', false)
            && ! empty($this->getPixelId());
    }

    public function getPixelId(): ?string
    {
        return setting('tiktok_pixel_id') ?: null;
    }

    public function isEventEnabled(string $event): bool
    {
        return (bool) setting('tiktok_pixel_track_' . $event, true);
    }

    public function isEventsApiEnabled(): bool
    {
        return $this->isEnabled()
            && (bool) setting('tiktok_pixel_events_api_enabled', false)
            && $this->accessToken !== null;
    }

    public function hashValue(string $value): string
    {
        return hash('sha256', strtolower(trim($value)));
    }

    public function generateEventId(): string
    {
        return (string) Str::uuid();
    }

    public function bufferClientEvent(string $event, array $properties = [], ?string $eventId = null): void
    {
        $settingKey = $this->eventNameToSettingKey($event);

        if (! $this->isEnabled() || ($settingKey !== null && ! $this->isEventEnabled($settingKey))) {
            return;
        }

        $this->clientEvents[] = [
            'event' => $event,
            'properties' => $properties,
            'event_id' => $eventId ?? $this->generateEventId(),
        ];
    }

    public function getClientEvents(): array
    {
        return $this->clientEvents;
    }

    public function sendServerEvent(
        string $event,
        array $properties = [],
        array $userData = [],
        ?string $eventId = null
    ): array {
        if (! $this->isEventsApiEnabled()) {
            return ['success' => false, 'message' => 'Events API not enabled'];
        }

        $eventId = $eventId ?? $this->generateEventId();

        $payload = [
            'pixel_code' => $this->getPixelId(),
            'event' => $event,
            'event_id' => $eventId,
            'timestamp' => now()->toIso8601String(),
            'user' => $this->buildUserData($userData),
            'properties' => $properties,
        ];

        $testEventCode = setting('tiktok_pixel_test_event_code');

        if ($testEventCode) {
            $payload['test_event_code'] = $testEventCode;
        }

        try {
            $response = Http::timeout((int) config('plugins.fob-tiktok-pixel.api_timeout', 5))
                ->withHeaders([
                    'Access-Token' => $this->accessToken,
                    'Content-Type' => 'application/json',
                ])
                ->post(config('plugins.fob-tiktok-pixel.events_api_endpoint'), [
                    'data' => [$payload],
                ]);

            if ($response->successful()) {
                $body = $response->json();
                $code = $body['code'] ?? -1;

                if ($code === 0) {
                    Log::info('TikTok Pixel: Event sent', ['event' => $event, 'event_id' => $eventId]);

                    return ['success' => true, 'event_id' => $eventId, 'data' => $body];
                }

                Log::warning('TikTok Pixel: API returned error', [
                    'event' => $event,
                    'code' => $code,
                    'message' => $body['message'] ?? 'Unknown',
                ]);

                return ['success' => false, 'message' => $body['message'] ?? 'API error', 'code' => $code];
            }

            Log::warning('TikTok Pixel: HTTP error', [
                'event' => $event,
                'status' => $response->status(),
            ]);

            return ['success' => false, 'message' => "HTTP {$response->status()}", 'status' => $response->status()];
        } catch (\Exception $e) {
            Log::error('TikTok Pixel: Exception sending event', [
                'event' => $event,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function testConnection(): array
    {
        return $this->sendServerEvent('ViewContent', [
            'content_type' => 'product',
            'content_id' => 'test',
            'content_name' => 'Connection Test',
        ]);
    }

    public static function storeAccessToken(string $token): bool
    {
        try {
            $encrypted = Crypt::encryptString(trim($token));
            setting()->set('tiktok_pixel_access_token', $encrypted)->save();

            return true;
        } catch (\Exception $e) {
            Log::error('TikTok Pixel: Failed to store access token', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function getDecryptedAccessToken(): ?string
    {
        return $this->accessToken;
    }

    public function hasAccessToken(): bool
    {
        return $this->accessToken !== null;
    }

    protected function loadAccessToken(): void
    {
        $encrypted = setting('tiktok_pixel_access_token');

        if (! $encrypted) {
            return;
        }

        try {
            $this->accessToken = Crypt::decryptString($encrypted);
        } catch (\Exception $e) {
            Log::error('TikTok Pixel: Failed to decrypt access token', ['error' => $e->getMessage()]);
        }
    }

    protected function buildUserData(array $overrides = []): array
    {
        $data = [
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ];

        return array_filter(array_merge($data, $overrides));
    }

    public function eventNameToSettingKey(string $event): ?string
    {
        return match ($event) {
            'PageView' => 'page_view',
            'ViewContent' => 'view_content',
            'AddToCart' => 'add_to_cart',
            'InitiateCheckout' => 'initiate_checkout',
            'CompletePayment' => 'complete_payment',
            'Search' => 'search',
            'SubmitForm', 'Contact' => 'contact',
            default => null,
        };
    }
}
