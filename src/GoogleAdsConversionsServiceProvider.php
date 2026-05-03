<?php

namespace ElectricTomCat\GoogleAdsConversions;

use ElectricTomCat\GoogleAdsConversions\Commands\GoogleAdsConversionsCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class GoogleAdsConversionsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-google-ads-conversions')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_google_ads_conversions_table')
            ->hasCommand(GoogleAdsConversionsCommand::class);
    }
}
