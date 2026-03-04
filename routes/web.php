<?php

use Botble\Base\Facades\AdminHelper;
use FriendsOfBotble\TikTokPixel\Http\Controllers\Settings\TikTokPixelSettingController;
use Illuminate\Support\Facades\Route;

AdminHelper::registerRoutes(function (): void {
    Route::group(['prefix' => 'settings'], function (): void {
        Route::get('tiktok-pixel', [TikTokPixelSettingController::class, 'edit'])
            ->name('fob-tiktok-pixel.settings')
            ->permission('settings.options');

        Route::put('tiktok-pixel', [TikTokPixelSettingController::class, 'update'])
            ->name('fob-tiktok-pixel.settings.update')
            ->permission('settings.options');
    });

    Route::group(['prefix' => 'settings/tiktok-pixel', 'as' => 'fob-tiktok-pixel.settings.'], function (): void {
        Route::post('test-connection', [TikTokPixelSettingController::class, 'testConnection'])
            ->name('test-connection')
            ->permission('settings.options');
    });
});
