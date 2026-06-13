<?php

namespace Tests\Feature\Commands;

use App\Mail\DomainExpiryAlert;
use App\Models\Domain;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CheckDomainExpiryTest extends TestCase
{
    use RefreshDatabase;

    private function makeUserWithAlert(int $days = 30): User
    {
        $user = User::factory()->create();
        $user->setSetting('alert_email', 'alert@example.com');
        $user->setSetting('expiry_alert_days', (string) $days);
        return $user;
    }

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_sends_alert_for_expiring_domain_without_auto_renew(): void
    {
        $this->makeUserWithAlert();
        Domain::factory()->expiringSoon(15)->create(['name' => 'expiring.com']);

        $this->artisan('domains:check-expiry')->assertSuccessful();

        Mail::assertSent(DomainExpiryAlert::class, function (DomainExpiryAlert $mail) {
            return $mail->domains->contains('name', 'expiring.com');
        });
    }

    public function test_does_not_alert_for_auto_renew_domains(): void
    {
        $this->makeUserWithAlert();
        Domain::factory()->expiringSoon(15)->autoRenew()->create(['name' => 'autorenew.com']);

        $this->artisan('domains:check-expiry')->assertSuccessful();

        Mail::assertNotSent(DomainExpiryAlert::class);
    }

    public function test_does_not_alert_when_outside_threshold(): void
    {
        $this->makeUserWithAlert(30);
        Domain::factory()->expiringSoon(60)->create(['name' => 'faraway.com']);

        $this->artisan('domains:check-expiry')->assertSuccessful();

        Mail::assertNotSent(DomainExpiryAlert::class);
    }

    public function test_does_not_alert_when_no_alert_email_configured(): void
    {
        User::factory()->create(); // no alert settings
        Domain::factory()->expiringSoon(5)->create();

        $this->artisan('domains:check-expiry')->assertSuccessful();

        Mail::assertNotSent(DomainExpiryAlert::class);
    }

    public function test_does_not_alert_for_already_expired_domains(): void
    {
        $this->makeUserWithAlert();
        Domain::factory()->expired()->create(['name' => 'expired.com']);

        $this->artisan('domains:check-expiry')->assertSuccessful();

        Mail::assertNotSent(DomainExpiryAlert::class);
    }

    public function test_alert_includes_multiple_expiring_domains(): void
    {
        $this->makeUserWithAlert();
        Domain::factory()->expiringSoon(5)->create(['name' => 'first.com']);
        Domain::factory()->expiringSoon(20)->create(['name' => 'second.com']);

        $this->artisan('domains:check-expiry')->assertSuccessful();

        Mail::assertSent(DomainExpiryAlert::class, fn (DomainExpiryAlert $m) => $m->domains->count() === 2);
    }

    public function test_does_not_send_when_no_domains_expiring(): void
    {
        $this->makeUserWithAlert();
        Domain::factory()->create(['expires_at' => null]);

        $this->artisan('domains:check-expiry')->assertSuccessful();

        Mail::assertNotSent(DomainExpiryAlert::class);
    }
}
