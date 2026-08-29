<?php

namespace ElectricTomCat\GoogleAdsConversions\Tests;

use ElectricTomCat\GoogleAdsConversions\GoogleAdsConversionsServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'ElectricTomCat\\GoogleAdsConversions\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            GoogleAdsConversionsServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('app.key', 'base64:6Cu/9HwZffm4f5g4q9iV8z+c9E8yK1pL6M2mN7jO3Q4=');

        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        config()->set('cache.default', 'array');

        $migration = include __DIR__.'/../database/migrations/create_leads_table.php.stub';
        $migration->up();
    }
}
