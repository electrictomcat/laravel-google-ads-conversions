<?php

namespace ElectricTomCat\GoogleAdsConversions;

use ElectricTomCat\GoogleAdsConversions\Http\Middleware\CaptureGclid;
use ElectricTomCat\GoogleAdsConversions\Support\EventResolver;
use Illuminate\Routing\Router;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class GoogleAdsConversionsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-google-ads-conversions')
            ->hasConfigFile()
            ->hasMigration('create_leads_table');
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(EventResolver::class);

        $this->app->singleton(GoogleAdsConversions::class, function ($app) {
            return new GoogleAdsConversions($app->make(EventResolver::class));
        });

        $this->app->singleton(ConversionUploader::class, function ($app) {
            return new ConversionUploader($app->make(EventResolver::class));
        });
    }

    public function packageBooted(): void
    {
        if (! $this->app->bound(Router::class)) {
            return;
        }

        /** @var Router $router */
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('capture-gclid', CaptureGclid::class);
    }
}
