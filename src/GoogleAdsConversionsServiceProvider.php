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
            ->hasMigrations([
                'create_leads_table',
                'add_gbraid_and_wbraid_to_leads_table',
            ])
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

            if (config('google-ads-conversions.routes.enabled', true)) {
                $prefix = (string) config('google-ads-conversions.routes.prefix', 'api/google-ads');
                $middleware = (array) config('google-ads-conversions.routes.middleware', ['api']);
                $path = (string) config('google-ads-conversions.routes.track_conversion_path', 'track-conversion');

                $router->group(['prefix' => $prefix, 'middleware' => $middleware], function (Router $router) use ($path) {
                    $router->post($path, Http\Controllers\TrackConversionController::class)
                        ->name('google-ads-conversions.track');
                });
            }
        }

        if ($this->app->runningInConsole()) {
            if ($migrations = static::pathsToPublish(null, 'google-ads-conversions-migrations')) {
                $this->publishes($migrations, 'laravel-google-ads-conversions-migrations');
            }
            if ($configs = static::pathsToPublish(null, 'google-ads-conversions-config')) {
                $this->publishes($configs, 'laravel-google-ads-conversions-config');
            }
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

        Blade::directive('googleAdsScript', function () {
            return '<script data-navigate-once>
(function() {
    function getClickData() {
        var cookies = document.cookie.split("; ");
        var gclid = (cookies.find(function(r) { return r.startsWith("google_ads_gclid="); }) || "").split("=")[1];
        var gbraid = (cookies.find(function(r) { return r.startsWith("google_ads_gbraid="); }) || "").split("=")[1];
        var wbraid = (cookies.find(function(r) { return r.startsWith("google_ads_wbraid="); }) || "").split("=")[1];
        return { gclid: gclid, gbraid: gbraid, wbraid: wbraid };
    }
    window.trackGoogleAdsConversion = function(eventName, value, currency, orderId) {
        var data = getClickData();
        var endpoint = <?php echo json_encode(
            \Illuminate\Support\Facades\Route::has("google-ads-conversions.track")
                ? route("google-ads-conversions.track")
                : url(config("google-ads-conversions.routes.prefix", "api/google-ads")."/".config("google-ads-conversions.routes.track_conversion_path", "track-conversion"))
        ); ?>;
        var payload = {
            event: eventName,
            gclid: data.gclid || undefined,
            gbraid: data.gbraid || undefined,
            wbraid: data.wbraid || undefined
        };
        if (value !== undefined && value !== null) {
            payload.value = value;
        }
        if (currency) {
            payload.currency = currency;
        }
        if (orderId) {
            payload.order_id = orderId;
        }
        var jsonPayload = JSON.stringify(payload);
        if (navigator.sendBeacon) {
            var blob = new Blob([jsonPayload], { type: "application/json" });
            navigator.sendBeacon(endpoint, blob);
        } else {
            fetch(endpoint, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: jsonPayload,
                keepalive: true
            }).catch(function() {});
        }
    };
})();
</script>';
        });

        Blade::directive('googleAdsNavigationTracking', function () {
            return '<?php if (\ElectricTomCat\GoogleAdsConversions\Facades\GoogleAdsConversions::hasAttribution()): ?>
<script data-navigate-once>
(function() {
    function getClickData() {
        var cookies = document.cookie.split("; ");
        var gclid = (cookies.find(function(r) { return r.startsWith("google_ads_gclid="); }) || "").split("=")[1];
        var gbraid = (cookies.find(function(r) { return r.startsWith("google_ads_gbraid="); }) || "").split("=")[1];
        var wbraid = (cookies.find(function(r) { return r.startsWith("google_ads_wbraid="); }) || "").split("=")[1];
        return { gclid: gclid, gbraid: gbraid, wbraid: wbraid };
    }
    function sendNavigationEvent() {
        var data = getClickData();
        if (!data.gclid && !data.gbraid && !data.wbraid) return;
        var endpoint = <?php echo json_encode(
            \Illuminate\Support\Facades\Route::has("google-ads-conversions.track")
                ? route("google-ads-conversions.track")
                : url(config("google-ads-conversions.routes.prefix", "api/google-ads")."/".config("google-ads-conversions.routes.track_conversion_path", "track-conversion"))
        ); ?>;
        var payload = JSON.stringify({
            event: "Page Navigation: " + window.location.pathname,
            value: 1,
            currency: "USD",
            gclid: data.gclid || undefined,
            gbraid: data.gbraid || undefined,
            wbraid: data.wbraid || undefined
        });
        if (navigator.sendBeacon) {
            var blob = new Blob([payload], { type: "application/json" });
            navigator.sendBeacon(endpoint, blob);
        } else {
            fetch(endpoint, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: payload,
                keepalive: true
            }).catch(function() {});
        }
    }
    // 1. Real Page Loads (Forward navigation only)
    function onInitialLoad() {
        var navEntry = performance.getEntriesByType("navigation")[0];
        var isBackForward = navEntry && navEntry.type === "back_forward";
        var isSameSite = document.referrer && new URL(document.referrer).origin === window.location.origin;
        if (isSameSite && !isBackForward) {
            sendNavigationEvent();
        }
    }
    if (document.readyState === "complete") {
        onInitialLoad();
    } else {
        window.addEventListener("load", onInitialLoad);
    }
    // 2. SPA Navigations (Livewire wire:navigate, Turbo, Inertia)
    var lastPath = window.location.pathname;
    var onSpaNavigate = function() {
        if (window.location.pathname !== lastPath) {
            lastPath = window.location.pathname;
            sendNavigationEvent();
        }
    };
    document.addEventListener("livewire:navigated", onSpaNavigate);
    document.addEventListener("turbo:load", onSpaNavigate);
    document.addEventListener("inertia:navigate", onSpaNavigate);
})();
</script>
<?php endif; ?>';
        });
    }
}
