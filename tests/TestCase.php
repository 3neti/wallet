<?php

namespace LBHurtado\Wallet\Tests;

use Dotenv\Dotenv;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use LBHurtado\Wallet\Tests\Models\User;
use LBHurtado\Wallet\WalletServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Spatie\LaravelData\Normalizers\ArrayableNormalizer;
use Spatie\LaravelData\Normalizers\ArrayNormalizer;
use Spatie\LaravelData\Normalizers\JsonNormalizer;
use Spatie\LaravelData\Normalizers\ModelNormalizer;
use Spatie\LaravelData\Normalizers\ObjectNormalizer;

abstract class TestCase extends BaseTestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'LBHurtado\\Wallet\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );

        if (! defined('TESTING_PACKAGE_PATH')) {
            define('TESTING_PACKAGE_PATH', __DIR__.'/../resources/documents');
        }

        $this->loadEnvironment();
        $this->loadConfig();
        $this->loginTestUser();
    }

    protected function getPackageProviders($app): array
    {
        return [
            WalletServiceProvider::class,
            \Bavix\Wallet\WalletServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');

        $app['config']->set('data.validation_strategy', 'always');
        $app['config']->set('data.max_transformation_depth', 6);
        $app['config']->set('data.throw_when_max_transformation_depth_reached', 6);
        $app['config']->set('data.normalizers', [
            ModelNormalizer::class,
            ArrayableNormalizer::class,
            ObjectNormalizer::class,
            ArrayNormalizer::class,
            JsonNormalizer::class,
        ]);

        $app['config']->set('auth.defaults.guard', 'web');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom([
            __DIR__.'/database/migrations',
            $this->getBavixWalletMigrationPath(),
        ]);
    }

    protected function loginTestUser(): void
    {
        $user = User::query()->firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => 'password',
            ]
        );

        $this->actingAs($user, 'web');
    }

    protected function loadConfig(): void
    {
        $this->app['config']->set(
            'wallet',
            require __DIR__.'/../config/wallet.php'
        );

        $this->app['config']->set(
            'account',
            require __DIR__.'/../config/account.php'
        );

        $this->app['config']->set('account.system_user.model', User::class);
        $this->app['config']->set('account.system_user.identifier', 'test@example.com');
    }

    protected function loadEnvironment(): void
    {
        $path = __DIR__.'/../.env';

        if (file_exists($path)) {
            Dotenv::createImmutable(dirname($path), '.env')->load();
        }
    }

    protected function getBavixWalletMigrationPath(): string
    {
        $reflection = new \ReflectionClass(\Bavix\Wallet\WalletServiceProvider::class);

        $path = dirname($reflection->getFileName(), 2).'/database';

        if (! is_dir($path)) {
            throw new \RuntimeException('Unable to locate Bavix wallet migrations.');
        }

        return $path;
    }
}
