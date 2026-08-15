<?php

namespace NishangSystems\Passkeys\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NishangSystems\Passkeys\PasskeysServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'NishangSystems\\Passkeys\\Tests\\Factories\\' . class_basename($modelName) . 'Factory'
        );

        $this->createUsersTable();
        $this->artisan('migrate', ['--database' => 'testing'])->run();
    }

    protected function getPackageProviders($app): array
    {
        return [
            PasskeysServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('cache.default', 'array');
        $app['config']->set('passkeys.user_model', TestUser::class);
        $app['config']->set('passkeys.guard', 'web');
        $app['config']->set('passkeys.allowed_origins', [
            'http://localhost',
            'http://localhost:8000',
        ]);
    }

    private function createUsersTable(): void
    {
        Schema::create('test_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamps();
        });
    }
}
