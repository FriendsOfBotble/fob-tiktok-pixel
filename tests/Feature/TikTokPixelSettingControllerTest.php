<?php

namespace FriendsOfBotble\TikTokPixel\Tests\Feature;

use Botble\ACL\Models\User;
use Botble\ACL\Services\ActivateUserService;
use Botble\Setting\Supports\SettingStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TikTokPixelSettingControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        app(SettingStore::class)->forgetAll();
        $this->user = $this->createUser();
    }

    protected function createUser(): User
    {
        Schema::disableForeignKeyConstraints();
        User::query()->truncate();

        $user = new User();
        $user->forceFill([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'admin@test.com',
            'username' => 'admin',
            'password' => bcrypt('password'),
            'super_user' => 1,
            'manage_supers' => 1,
        ]);
        $user->save();

        app(ActivateUserService::class)->activate($user);

        return $user;
    }

    public function testSettingsPageIsAccessible(): void
    {
        $this->actingAs($this->user);
        $this->get(route('fob-tiktok-pixel.settings'))->assertStatus(200);
    }

    public function testSettingsCanBeUpdated(): void
    {
        $this->actingAs($this->user);
        $this->putJson(route('fob-tiktok-pixel.settings.update'), [
            'tiktok_pixel_enabled' => '1',
            'tiktok_pixel_id' => 'CTEST123',
            'tiktok_pixel_events_api_enabled' => '1',
            'tiktok_pixel_track_page_view' => '1',
            'tiktok_pixel_track_view_content' => '1',
            'tiktok_pixel_track_add_to_cart' => '1',
            'tiktok_pixel_track_initiate_checkout' => '1',
            'tiktok_pixel_track_complete_payment' => '1',
            'tiktok_pixel_track_search' => '1',
        ])->assertSuccessful();

        $this->assertEquals('CTEST123', setting('tiktok_pixel_id'));
    }

    public function testSettingsCanStoreAccessToken(): void
    {
        $this->actingAs($this->user);
        $this->putJson(route('fob-tiktok-pixel.settings.update'), [
            'tiktok_pixel_enabled' => '1',
            'tiktok_pixel_id' => 'CTEST123',
            'tiktok_pixel_access_token_input' => 'my-secret-token',
        ])->assertSuccessful();

        $encrypted = setting('tiktok_pixel_access_token');
        $this->assertNotNull($encrypted);
        $this->assertNotEquals('my-secret-token', $encrypted);
    }

    public function testUnauthenticatedCannotAccessSettings(): void
    {
        $this->get(route('fob-tiktok-pixel.settings'))->assertRedirect();
    }

    public function testSettingsCanPersistBooleanToggles(): void
    {
        $this->actingAs($this->user);
        $this->putJson(route('fob-tiktok-pixel.settings.update'), [
            'tiktok_pixel_enabled' => '1',
            'tiktok_pixel_id' => 'CTEST123',
            'tiktok_pixel_track_page_view' => '0',
            'tiktok_pixel_track_view_content' => '1',
            'tiktok_pixel_track_add_to_cart' => '0',
        ])->assertSuccessful();

        $this->assertEquals('0', setting('tiktok_pixel_track_page_view'));
        $this->assertEquals('1', setting('tiktok_pixel_track_view_content'));
        $this->assertEquals('0', setting('tiktok_pixel_track_add_to_cart'));
    }

    public function testUnauthenticatedCannotUpdateSettings(): void
    {
        $this->putJson(route('fob-tiktok-pixel.settings.update'), [
            'tiktok_pixel_enabled' => '1',
            'tiktok_pixel_id' => 'CTEST123',
        ])->assertUnauthorized();
    }

    public function testUnauthenticatedCannotTestConnection(): void
    {
        $this->postJson(route('fob-tiktok-pixel.settings.test-connection'))
            ->assertUnauthorized();
    }

    public function testTestConnectionRequiresEventsApi(): void
    {
        $this->actingAs($this->user);
        $this->postJson(route('fob-tiktok-pixel.settings.test-connection'))
            ->assertStatus(400)
            ->assertJson(['success' => false]);
    }
}
