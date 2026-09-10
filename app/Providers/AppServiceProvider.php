<?php

namespace App\Providers;

use App\Application\Security\Contracts\InboundCredentialResolverInterface;
use App\Infrastructure\Security\ConfigInboundCredentialResolver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(InboundCredentialResolverInterface::class, ConfigInboundCredentialResolver::class);
    }

    public function boot(): void
    {
        RateLimiter::for('inbound', function (Request $request) {
            $key = (string) $request->attributes->get('inbound_rate_key', 'anonymous');

            return Limit::perMinute((int) config('inbound.rate_limit_per_minute', 120))->by($key);
        });
    }
}
