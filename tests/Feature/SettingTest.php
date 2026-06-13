<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_returns_all_setting_keys(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/settings')
            ->assertOk()
            ->assertJsonStructure(['cloudflare_api_token', 'expiry_alert_days', 'alert_email']);
    }

    public function test_cloudflare_token_is_masked_when_set(): void
    {
        $user = User::factory()->create();
        $user->setSetting('cloudflare_api_token', 'abcdefgh1234');

        $response = $this->actingAs($user)->getJson('/api/settings');
        $response->assertOk();

        $token = $response->json('cloudflare_api_token');
        $this->assertStringEndsWith('1234', $token);
        $this->assertStringContainsString('****', $token);
        $this->assertStringNotContainsString('abcdefgh', $token);
    }

    public function test_can_update_settings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->putJson('/api/settings', [
            'alert_email' => 'alerts@example.com',
            'expiry_alert_days' => 45,
        ])->assertOk();

        $this->assertSame('alerts@example.com', $user->fresh()->getSetting('alert_email'));
        $this->assertSame('45', $user->fresh()->getSetting('expiry_alert_days'));
    }

    public function test_can_update_cloudflare_token(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->putJson('/api/settings', [
            'cloudflare_api_token' => 'my-secret-token',
        ])->assertOk();

        $this->assertSame('my-secret-token', $user->fresh()->getSetting('cloudflare_api_token'));
    }

    public function test_update_validates_email(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->putJson('/api/settings', ['alert_email' => 'not-an-email'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('alert_email');
    }

    public function test_update_validates_expiry_days_range(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->putJson('/api/settings', ['expiry_alert_days' => 0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('expiry_alert_days');
    }
}
