<?php

namespace Bnb\Laravel\Sequence;

use Bnb\Laravel\Sequence\Console\Commands\UpdateSequence;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class SequenceServiceProvider extends ServiceProvider
{

    /**
     * Bootstrap the application services.
     *
     * @param Router $router
     *
     * @return void
     */
    public function boot(Router $router)
    {

        $this->publishes([
            __DIR__ . '/../config/sequence.php' => config_path('sequence.php')
        ], 'config');

        $this->loadMigrationsFrom(__DIR__ . '/../migrations');

        $this->loadTranslationsFrom(__DIR__.'/../translations', 'sequence');

        if ($this->app->runningInConsole()) {
            $this->commands([
                UpdateSequence::class,
            ]);
        }
    }


    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/sequence.php', 'sequence');
    }


    private function routes($router)
    {

    }
}
