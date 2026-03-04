<?php

namespace FriendsOfBotble\TikTokPixel\Tests\Feature;

use FriendsOfBotble\TikTokPixel\Http\Requests\Settings\TikTokPixelSettingRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class TikTokPixelSettingValidationTest extends TestCase
{
    protected function getValidationRules(): array
    {
        return (new TikTokPixelSettingRequest())->rules();
    }

    public function testPixelEnabledAcceptsValidValues(): void
    {
        $rules = $this->getValidationRules();

        foreach (['0', '1'] as $value) {
            $validator = Validator::make(
                ['tiktok_pixel_enabled' => $value],
                ['tiktok_pixel_enabled' => $rules['tiktok_pixel_enabled']]
            );
            $this->assertTrue($validator->passes(), "tiktok_pixel_enabled should accept '{$value}'");
        }
    }

    public function testPixelEnabledRejectsInvalidValues(): void
    {
        $rules = $this->getValidationRules();

        $validator = Validator::make(
            ['tiktok_pixel_enabled' => 'yes'],
            ['tiktok_pixel_enabled' => $rules['tiktok_pixel_enabled']]
        );
        $this->assertFalse($validator->passes());
    }

    public function testPixelIdAcceptsValidString(): void
    {
        $rules = $this->getValidationRules();

        $validator = Validator::make(
            ['tiktok_pixel_id' => 'CXXXXXXXXXXXXXXXXX'],
            ['tiktok_pixel_id' => $rules['tiktok_pixel_id']]
        );
        $this->assertTrue($validator->passes());
    }

    public function testPixelIdRejectsTooLongString(): void
    {
        $rules = $this->getValidationRules();

        $validator = Validator::make(
            ['tiktok_pixel_id' => str_repeat('X', 121)],
            ['tiktok_pixel_id' => $rules['tiktok_pixel_id']]
        );
        $this->assertFalse($validator->passes());
    }

    public function testAccessTokenAcceptsString(): void
    {
        $rules = $this->getValidationRules();

        $validator = Validator::make(
            ['tiktok_pixel_access_token_input' => 'some-long-token-value'],
            ['tiktok_pixel_access_token_input' => $rules['tiktok_pixel_access_token_input']]
        );
        $this->assertTrue($validator->passes());
    }

    public function testEventToggleAcceptsValidValues(): void
    {
        $rules = $this->getValidationRules();

        $fields = [
            'tiktok_pixel_track_page_view',
            'tiktok_pixel_track_view_content',
            'tiktok_pixel_track_add_to_cart',
            'tiktok_pixel_track_initiate_checkout',
            'tiktok_pixel_track_complete_payment',
            'tiktok_pixel_track_search',
            'tiktok_pixel_track_contact',
        ];

        foreach ($fields as $field) {
            foreach (['0', '1'] as $value) {
                $validator = Validator::make(
                    [$field => $value],
                    [$field => $rules[$field]]
                );
                $this->assertTrue($validator->passes(), "{$field} should accept '{$value}'");
            }
        }
    }

    public function testEventsApiEnabledAcceptsValidValues(): void
    {
        $rules = $this->getValidationRules();

        foreach (['0', '1'] as $value) {
            $validator = Validator::make(
                ['tiktok_pixel_events_api_enabled' => $value],
                ['tiktok_pixel_events_api_enabled' => $rules['tiktok_pixel_events_api_enabled']]
            );
            $this->assertTrue($validator->passes());
        }
    }

    public function testTestEventCodeAcceptsValidString(): void
    {
        $rules = $this->getValidationRules();

        $validator = Validator::make(
            ['tiktok_pixel_test_event_code' => 'TEST123'],
            ['tiktok_pixel_test_event_code' => $rules['tiktok_pixel_test_event_code']]
        );
        $this->assertTrue($validator->passes());
    }

    public function testTestEventCodeRejectsTooLongString(): void
    {
        $rules = $this->getValidationRules();

        $validator = Validator::make(
            ['tiktok_pixel_test_event_code' => str_repeat('X', 121)],
            ['tiktok_pixel_test_event_code' => $rules['tiktok_pixel_test_event_code']]
        );
        $this->assertFalse($validator->passes());
    }

    public function testAccessTokenRejectsTooLongString(): void
    {
        $rules = $this->getValidationRules();

        $validator = Validator::make(
            ['tiktok_pixel_access_token_input' => str_repeat('X', 2001)],
            ['tiktok_pixel_access_token_input' => $rules['tiktok_pixel_access_token_input']]
        );
        $this->assertFalse($validator->passes());
    }
}
