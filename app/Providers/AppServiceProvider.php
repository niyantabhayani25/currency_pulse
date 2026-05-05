<?php

namespace App\Providers;

use App\Services\CurrencyLayerService;
use App\Services\FrankfurterService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CurrencyLayerService::class, fn () => new CurrencyLayerService(
            apiKey:  config('services.currency_layer.key') ?? '',
            baseUrl: config('services.currency_layer.base_url') ?? 'http://api.currencylayer.com',
        ));

        $this->app->bind(FrankfurterService::class, fn () => new FrankfurterService(
            baseUrl: config('services.frankfurter.base_url', 'https://api.frankfurter.app'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        Event::listen(Registered::class, function () {
            session()->flash('success', 'Welcome to CurrencyPulse! Your account has been created.');
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
