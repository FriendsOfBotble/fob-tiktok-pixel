<?php

namespace FriendsOfBotble\TikTokPixel\Http\Controllers\Settings;

use Botble\Setting\Http\Controllers\SettingController;
use FriendsOfBotble\TikTokPixel\Forms\Settings\TikTokPixelSettingForm;
use FriendsOfBotble\TikTokPixel\Http\Requests\Settings\TikTokPixelSettingRequest;
use FriendsOfBotble\TikTokPixel\Services\TikTokPixelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class TikTokPixelSettingController extends SettingController
{
    public function edit()
    {
        $this->pageTitle(trans('plugins/fob-tiktok-pixel::tiktok-pixel.settings.title'));

        return TikTokPixelSettingForm::create()->renderForm();
    }

    public function update(TikTokPixelSettingRequest $request)
    {
        $validated = $request->validated();

        if (! empty($validated['tiktok_pixel_access_token_input'])) {
            $stored = TikTokPixelService::storeAccessToken($validated['tiktok_pixel_access_token_input']);

            if (! $stored) {
                return $this->httpResponse()
                    ->setError()
                    ->setMessage(trans('plugins/fob-tiktok-pixel::tiktok-pixel.settings.token_store_failed'));
            }
        }

        unset($validated['tiktok_pixel_access_token_input']);

        return $this->performUpdate($validated)
            ->withUpdatedSuccessMessage();
    }

    public function testConnection(TikTokPixelService $service): JsonResponse
    {
        try {
            if (! $service->isEventsApiEnabled()) {
                return response()->json([
                    'success' => false,
                    'message' => trans('plugins/fob-tiktok-pixel::tiktok-pixel.settings.events_api_not_enabled'),
                ], 400);
            }

            $result = $service->testConnection();

            return response()->json([
                'success' => $result['success'],
                'message' => $result['success']
                    ? trans('plugins/fob-tiktok-pixel::tiktok-pixel.settings.connection_success')
                    : ($result['message'] ?? trans('plugins/fob-tiktok-pixel::tiktok-pixel.settings.connection_failed')),
            ]);
        } catch (\Exception $e) {
            Log::error('TikTok Pixel: Test connection failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => trans('plugins/fob-tiktok-pixel::tiktok-pixel.settings.connection_failed'),
            ], 500);
        }
    }
}
