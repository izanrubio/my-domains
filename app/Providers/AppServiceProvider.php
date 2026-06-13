<?php

namespace App\Providers;

use App\Services\Scanning\Checks\DnsCheck;
use App\Services\Scanning\Contracts\DohResolverInterface;
use App\Services\Scanning\DohResolver;
use App\Services\Scanning\DomainScanner;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DohResolverInterface::class, DohResolver::class);

        $this->app->singleton(DomainScanner::class, function ($app) {
            return new DomainScanner([
                $app->make(DnsCheck::class),
            ]);
        });
    }

    public function boot(): void {}
}
