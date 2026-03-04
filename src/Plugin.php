<?php

namespace FriendsOfBotble\TikTokPixel;

use Botble\PluginManagement\Abstracts\PluginOperationAbstract;

class Plugin extends PluginOperationAbstract
{
    public static function activated(): void
    {
    }

    public static function deactivated(): void
    {
    }

    public static function removed(): void
    {
        $keys = [];

        foreach ([
            'enabled',
            'id',
            'access_token',
            'events_api_enabled',
            'test_event_code',
            'track_page_view',
            'track_view_content',
            'track_add_to_cart',
            'track_initiate_checkout',
            'track_complete_payment',
            'track_search',
            'track_contact',
        ] as $suffix) {
            $keys[] = 'tiktok_pixel_' . $suffix;
        }

        setting()->delete($keys);
    }
}
