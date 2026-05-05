<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\CurrencyProviderInterface;
use App\Models\Report;
use App\Policies\ReportPolicy;
use App\Services\Currency\CurrencyLayerService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        /**
         * Bind the interface to the concrete implementation.
         * To swap providers (e.g. Open Exchange Rates), only change this binding.
         */
        $this->app->singleton(CurrencyProviderInterface::class, function () {
            return new CurrencyLayerService(
                apiKey:  config('services.currency_layer.key'),
                baseUrl: config('services.currency_layer.base_url', 'https://api.currencylayer.com'),
            );
        });
    }

    public function boot(): void
    {
        Gate::policy(Report::class, ReportPolicy::class);
    }
}
