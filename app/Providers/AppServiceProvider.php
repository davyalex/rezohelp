<?php

namespace App\Providers;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $horsProduction = ! $this->app->isProduction();

        Model::preventLazyLoading($horsProduction);
        Model::preventSilentlyDiscardingAttributes($horsProduction);

        Carbon::setLocale($this->app->getLocale());
        CarbonImmutable::setLocale($this->app->getLocale());
    }
}
