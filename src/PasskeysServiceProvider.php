<?php

namespace NishangSystems\Passkeys;

use Illuminate\Support\ServiceProvider;

class PasskeysServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/passkeys.php',
            'passkeys'
        );
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/passkeys.php' => config_path('passkeys.php'),
            ], 'passkeys-config');

            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'passkeys-migrations');
        }

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'passkeys');

        if (config('passkeys.routes.enabled', true)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/passkeys.php');
        }
    }
}
