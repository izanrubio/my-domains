<?php

namespace App\Providers;

use App\Services\Scanning\Checks\BlacklistCheck;
use App\Services\Scanning\Checks\DnsCheck;
use App\Services\Scanning\Checks\EmailSecurityCheck;
use App\Services\Scanning\Checks\HttpHeadersCheck;
use App\Services\Scanning\Checks\ReachabilityCheck;
use App\Services\Scanning\Checks\SslCheck;
use App\Services\Scanning\Contracts\CertFetcherInterface;
use App\Services\Scanning\Contracts\DohResolverInterface;
use App\Services\Scanning\DohResolver;
use App\Services\Scanning\DomainScanner;
use App\Services\Scanning\StreamCertFetcher;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DohResolverInterface::class, DohResolver::class);
        $this->app->bind(CertFetcherInterface::class, StreamCertFetcher::class);

        $this->app->singleton(DomainScanner::class, function ($app) {
            return new DomainScanner([
                $app->make(DnsCheck::class),
                $app->make(SslCheck::class),
                $app->make(EmailSecurityCheck::class),
                $app->make(HttpHeadersCheck::class),
                $app->make(BlacklistCheck::class),
                $app->make(ReachabilityCheck::class),
            ]);
        });
    }

    public function boot(): void {}
}
