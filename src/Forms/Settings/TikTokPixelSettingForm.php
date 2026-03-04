<?php

namespace FriendsOfBotble\TikTokPixel\Forms\Settings;

use Botble\Base\Forms\FieldOptions\AlertFieldOption;
use Botble\Base\Forms\FieldOptions\HtmlFieldOption;
use Botble\Base\Forms\FieldOptions\OnOffFieldOption;
use Botble\Base\Forms\FieldOptions\TextareaFieldOption;
use Botble\Base\Forms\FieldOptions\TextFieldOption;
use Botble\Base\Forms\Fields\AlertField;
use Botble\Base\Forms\Fields\HtmlField;
use Botble\Base\Forms\Fields\OnOffCheckboxField;
use Botble\Base\Forms\Fields\TextareaField;
use Botble\Base\Forms\Fields\TextField;
use Botble\Setting\Forms\SettingForm;
use FriendsOfBotble\TikTokPixel\Http\Requests\Settings\TikTokPixelSettingRequest;
use FriendsOfBotble\TikTokPixel\Services\TikTokPixelService;

class TikTokPixelSettingForm extends SettingForm
{
    public function setup(): void
    {
        parent::setup();

        $service = app(TikTokPixelService::class);

        $this
            ->setSectionTitle(trans('plugins/fob-tiktok-pixel::tiktok-pixel.settings.title'))
            ->setSectionDescription(trans('plugins/fob-tiktok-pixel::tiktok-pixel.settings.description'))
            ->setValidatorClass(TikTokPixelSettingRequest::class)
            ->add(
                'tiktok_pixel_enabled',
                OnOffCheckboxField::class,
                OnOffFieldOption::make()
                    ->label(trans('plugins/fob-tiktok-pixel::tiktok-pixel.settings.enable'))
                    ->helperText(trans('plugins/fob-tiktok-pixel::tiktok-pixel.settings.enable_help'))
                    ->value(setting('tiktok_pixel_enabled', false))
            )
            ->add(
                'tiktok_pixel_id',
                TextField::class,
                TextFieldOption::make()
                    ->label(trans('plugins/fob-tiktok-pixel::tiktok-pixel.settings.pixel_id'))
                    ->helperText(trans('plugins/fob-tiktok-pixel::tiktok-pixel.settings.pixel_id_help'))
                    ->placeholder('CXXXXXXXXXXXXXXXXX')
                    ->value(setting('tiktok_pixel_id'))
            )
            ->add(
                'token_status',
                AlertField::class,
                AlertFieldOption::make()
                    ->type($service->hasAccessToken() ? 'success' : 'info')
                    ->content($service->hasAccessToken()
                        ? trans('plugins/fob-tiktok-pixel::tiktok-pixel.settings.token_configured')
                        : trans('plugins/fob-tiktok-pixel::tiktok-pixel.settings.token_not_configured'))
            )
            ->add(
                'tiktok_pixel_access_token_input',
                TextareaField::class,
                TextareaFieldOption::make()
                    ->label(trans('plugins/fob-tiktok-pixel::tiktok-pixel.settings.access_token'))
                    ->helperText(trans('plugins/fob-tiktok-pixel::tiktok-pixel.settings.access_token_help'))
                    ->placeholder(trans('plugins/fob-tiktok-pixel::tiktok-pixel.settings.access_token_placeholder'))
                    ->rows(3)
            )
            ->add(
                'tiktok_pixel_events_api_enabled',
                OnOffCheckboxField::class,
                OnOffFieldOption::make()
                    ->label(trans('plugins/fob-tiktok-pixel::tiktok-pixel.settings.events_api_enable'))
                    ->helperText(trans('plugins/fob-tiktok-pixel::tiktok-pixel.settings.events_api_enable_help'))
                    ->value(setting('tiktok_pixel_events_api_enabled', false))
            )
            ->add(
                'tiktok_pixel_test_event_code',
                TextField::class,
                TextFieldOption::make()
                    ->label(trans('plugins/fob-tiktok-pixel::tiktok-pixel.settings.test_event_code'))
                    ->helperText(trans('plugins/fob-tiktok-pixel::tiktok-pixel.settings.test_event_code_help'))
                    ->value(setting('tiktok_pixel_test_event_code'))
            )
            ->add(
                'events_heading',
                HtmlField::class,
                HtmlFieldOption::make()
                    ->content('<h4 class="mt-3">' . e(trans('plugins/fob-tiktok-pixel::tiktok-pixel.settings.event_tracking')) . '</h4><p class="text-muted">' . e(trans('plugins/fob-tiktok-pixel::tiktok-pixel.settings.event_tracking_help')) . '</p>')
            );

        $events = [
            'page_view' => 'PageView',
            'view_content' => 'ViewContent',
            'add_to_cart' => 'AddToCart',
            'initiate_checkout' => 'InitiateCheckout',
            'complete_payment' => 'CompletePayment',
            'search' => 'Search',
            'contact' => 'SubmitForm / Contact',
        ];

        foreach ($events as $key => $label) {
            $this->add(
                'tiktok_pixel_track_' . $key,
                OnOffCheckboxField::class,
                OnOffFieldOption::make()
                    ->label($label)
                    ->value(setting('tiktok_pixel_track_' . $key, $key !== 'contact'))
            );
        }

        $this->add(
            'settings_info',
            HtmlField::class,
            HtmlFieldOption::make()
                ->content(view('plugins/fob-tiktok-pixel::partials.settings-info', [
                    'service' => $service,
                ])->render())
        );
    }
}
