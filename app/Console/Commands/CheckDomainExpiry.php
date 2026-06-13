<?php

namespace App\Console\Commands;

use App\Mail\DomainExpiryAlert;
use App\Models\Domain;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class CheckDomainExpiry extends Command
{
    protected $signature = 'domains:check-expiry';
    protected $description = 'Send expiry alerts for domains expiring soon without auto-renewal';

    public function handle(): int
    {
        $users = User::all();

        foreach ($users as $user) {
            $alertEmail = $user->getSetting('alert_email');

            if (! $alertEmail) {
                continue;
            }

            $alertDays = (int) ($user->getSetting('expiry_alert_days') ?? 30);

            $expiring = Domain::query()
                ->whereNotNull('expires_at')
                ->where('auto_renew', false)
                ->whereDate('expires_at', '>=', now()->toDateString())
                ->whereDate('expires_at', '<=', now()->addDays($alertDays)->toDateString())
                ->orderBy('expires_at')
                ->get();

            if ($expiring->isEmpty()) {
                $this->info("No domains expiring within {$alertDays} days for {$alertEmail}.");
                continue;
            }

            Mail::to($alertEmail)->send(new DomainExpiryAlert($expiring, $alertDays));
            $this->info("Sent expiry alert to {$alertEmail} for {$expiring->count()} domain(s).");
        }

        return self::SUCCESS;
    }
}
