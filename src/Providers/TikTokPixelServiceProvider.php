<?php

namespace FriendsOfBotble\TikTokPixel\Providers;

use Botble\Base\Facades\PanelSectionManager;
use Botble\Base\PanelSections\PanelSectionItem;
use Botble\Base\Traits\LoadAndPublishDataTrait;
use Botble\Setting\PanelSections\SettingOthersPanelSection;
use FriendsOfBotble\TikTokPixel\Services\TikTokPixelService;
use Illuminate\Support\ServiceProvider;

class TikTokPixelServiceProvider extends ServiceProvider
{
    use LoadAndPublishDataTrait;

    public function register(): void
    {
        $this->app->singleton(TikTokPixelService::class);
    }

    public function boot(): void
    {
        $this->setNamespace('plugins/fob-tiktok-pixel')
            ->loadAndPublishConfigurations(['permissions'])
            ->loadAndPublishTranslations()
            ->loadAndPublishViews()
            ->loadRoutes();

        $this->mergeConfigFrom(__DIR__ . '/../../config/config.php', 'plugins.fob-tiktok-pixel');

        PanelSectionManager::default()->beforeRendering(function (): void {
            PanelSectionManager::registerItem(
                SettingOthersPanelSection::class,
                fn () => PanelSectionItem::make('tiktok-pixel')
                    ->setTitle(trans('plugins/fob-tiktok-pixel::tiktok-pixel.settings.title'))
                    ->withIcon('ti ti-brand-tiktok')
                    ->withDescription(trans('plugins/fob-tiktok-pixel::tiktok-pixel.settings.description'))
                    ->withPriority(210)
                    ->withRoute('fob-tiktok-pixel.settings')
            );
        });

        $this->app->booted(function (): void {
            $this->app->register(HookServiceProvider::class);
        });
    }
}
