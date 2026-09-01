<?php

namespace ElectricTomCat\GoogleAdsConversions;

use ElectricTomCat\GoogleAdsConversions\Commands\DiagnoseCommand;
use ElectricTomCat\GoogleAdsConversions\Commands\InstallCommand;
use ElectricTomCat\GoogleAdsConversions\Commands\SyncConversionsCommand;
use ElectricTomCat\GoogleAdsConversions\Commands\TestConnectionCommand;
use ElectricTomCat\GoogleAdsConversions\Commands\UploadConversionsCommand;
use ElectricTomCat\GoogleAdsConversions\Http\Middleware\CaptureGclid;
use ElectricTomCat\GoogleAdsConversions\Support\ConsentManager;
use ElectricTomCat\GoogleAdsConversions\Support\EventResolver;
use ElectricTomCat\GoogleAdsConversions\Support\UserDataHasher;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class GoogleAdsConversionsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-google-ads-conversions')
            ->hasConfigFile()
            ->hasMigration('create_leads_table')
            ->hasCommands([
                InstallCommand::class,
                UploadConversionsCommand::class,
                SyncConversionsCommand::class,
                TestConnectionCommand::class,
                DiagnoseCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(EventResolver::class);
        $this->app->singleton(ConsentManager::class);
        $this->app->singleton(UserDataHasher::class);

        $this->app->singleton(GoogleAdsConversions::class, function ($app) {
            return new GoogleAdsConversions(
                $app->make(EventResolver::class),
                $app->make(UserDataHasher::class),
            );
        });

        $this->app->singleton(ConversionUploader::class, function ($app) {
            return new ConversionUploader(
                $app->make(EventResolver::class),
                $app->make(ConsentManager::class),
                $app->make(UserDataHasher::class),
            );
        });
    }

    public function packageBooted(): void
    {
        if ($this->app->bound(Router::class)) {
            /** @var Router $router */
            $router = $this->app->make(Router::class);
            $router->aliasMiddleware('capture-gclid', CaptureGclid::class);
        }

        // Register Blade Directives for Form Inputs
        Blade::directive('googleAdsClickInputs', function () {
            return '<?php
                if ($gclid = \ElectricTomCat\GoogleAdsConversions\Facades\GoogleAdsConversions::gclid()) {
                    echo \'<input type="hidden" name="gclid" value="\'.e($gclid).\'">\';
                }
                if ($gbraid = \ElectricTomCat\GoogleAdsConversions\Facades\GoogleAdsConversions::gbraid()) {
                    echo \'<input type="hidden" name="gbraid" value="\'.e($gbraid).\'">\';
                }
                if ($wbraid = \ElectricTomCat\GoogleAdsConversions\Facades\GoogleAdsConversions::wbraid()) {
                    echo \'<input type="hidden" name="wbraid" value="\'.e($wbraid).\'">\';
                }
            ?>';
        });

        Blade::directive('googleAdsGclid', function () {
            return '<?php
                if ($gclid = \ElectricTomCat\GoogleAdsConversions\Facades\GoogleAdsConversions::gclid()) {
                    echo \'<input type="hidden" name="gclid" value="\'.e($gclid).\'">\';
                }
            ?>';
        });
    }
}
