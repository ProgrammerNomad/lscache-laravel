<?php

namespace Litespeed\LSCache;

use Illuminate\Support\ServiceProvider;
use Illuminate\Routing\Router;
use Illuminate\Contracts\Http\Kernel;

class LSCacheServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        // Make the config defaults available even when the user has not run
        // vendor:publish yet.
        $this->mergeConfigFrom(__DIR__ . '/../config/lscache.php', 'lscache');

        // Bind as singleton so that the static $pendingPurges queue in
        // LiteSpeedCache is always backed by the same resolved instance and
        // state is not silently discarded between facade calls.
        $this->app->singleton(LiteSpeedCache::class, function () {
            return new LiteSpeedCache();
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(Router $router, Kernel $kernel)
    {
        $router->aliasMiddleware('lscache', LSCacheMiddleware::class);
        $router->aliasMiddleware('lstags', LSTagsMiddleware::class);

        // pushMiddleware is still available in Laravel 11 / 12 / 13 and is the
        // correct way to register a global middleware from a service provider.
        // In console / queue contexts the HTTP kernel is still bound in the
        // container but middleware is never executed, so this is safe.
        $kernel->pushMiddleware(LSCacheMiddleware::class);

        $this->publishes([
            __DIR__ . '/../config/lscache.php' => config_path('lscache.php'),
        ], 'config');
    }
}
