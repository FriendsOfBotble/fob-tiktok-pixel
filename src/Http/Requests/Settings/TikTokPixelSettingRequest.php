<?php

namespace FriendsOfBotble\TikTokPixel\Http\Requests\Settings;

use Botble\Support\Http\Requests\Request;

class TikTokPixelSettingRequest extends Request
{
    public function rules(): array
    {
        return [
            'tiktok_pixel_enabled' => ['nullable', 'in:0,1'],
            'tiktok_pixel_id' => ['nullable', 'string', 'max:120'],
            'tiktok_pixel_access_token_input' => ['nullable', 'string', 'max:2000'],
            'tiktok_pixel_events_api_enabled' => ['nullable', 'in:0,1'],
            'tiktok_pixel_test_event_code' => ['nullable', 'string', 'max:120'],
            'tiktok_pixel_track_page_view' => ['nullable', 'in:0,1'],
            'tiktok_pixel_track_view_content' => ['nullable', 'in:0,1'],
            'tiktok_pixel_track_add_to_cart' => ['nullable', 'in:0,1'],
            'tiktok_pixel_track_initiate_checkout' => ['nullable', 'in:0,1'],
            'tiktok_pixel_track_complete_payment' => ['nullable', 'in:0,1'],
            'tiktok_pixel_track_search' => ['nullable', 'in:0,1'],
            'tiktok_pixel_track_contact' => ['nullable', 'in:0,1'],
        ];
    }
}
