<?php

namespace App\Providers;

use App\Domain\Value\DisabledProductionValueSource;
use App\Domain\Value\GoogleSheetsValueSource;
use App\Domain\Value\ValueSource;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ValueSource::class, fn (): ValueSource => config('value_source.enabled')
            ? new GoogleSheetsValueSource
            : new DisabledProductionValueSource);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
