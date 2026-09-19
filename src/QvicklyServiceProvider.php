<?php

namespace Andreaskviby\Qvickly;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;

class QvicklyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/qvickly.php', 'qvickly');

        $this->app->singleton(Qvickly::class, fn ($app) => new Qvickly(
            $app['config']->get('qvickly', []),
            $app->make(HttpFactory::class),
        ));

        $this->app->alias(Qvickly::class, 'qvickly');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/qvickly.php' => config_path('qvickly.php'),
            ], 'qvickly-config');
        }
    }

    /** @return array<int, string> */
    public function provides(): array
    {
        return [Qvickly::class, 'qvickly'];
    }
}
