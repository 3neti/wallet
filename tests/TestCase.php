<?php

namespace LBHurtado\Wallet\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use LBHurtado\Wallet\Tests\Models\User;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->loginTestUser(); // Log in a test user for all tests
        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'LBHurtado\\Wallet\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
        // Set the base path for the package
        if (! defined('TESTING_PACKAGE_PATH')) {
            define('TESTING_PACKAGE_PATH', __DIR__.'/../resources/documents');
        }
        $this->loadEnvironment();

        // Load configuration files
        $this->loadConfig();
    }

    protected function getPackageProviders($app)
    {
        return [
            \LBHurtado\Wallet\WalletServiceProvider::class,
            \Bavix\Wallet\WalletServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        config()->set('data.validation_strategy', 'always');
        config()->set('data.max_transformation_depth', 6);
        config()->set('data.throw_when_max_transformation_depth_reached', 6);
        config()->set('data.normalizers', [
            \Spatie\LaravelData\Normalizers\ModelNormalizer::class,
            // Spatie\LaravelData\Normalizers\FormRequestNormalizer::class,
            \Spatie\LaravelData\Normalizers\ArrayableNormalizer::class,
            \Spatie\LaravelData\Normalizers\ObjectNormalizer::class,
            \Spatie\LaravelData\Normalizers\ArrayNormalizer::class,
            \Spatie\LaravelData\Normalizers\JsonNormalizer::class,
        ]);

        // Optional: Set web guard as the default
        $app['config']->set('auth.defaults.guard', 'web');

        // Run the migration from the local package
        $userMigration = include __DIR__.'/../database/migrations/0001_01_01_000000_create_users_table.php';
        $userMigration->up();

        // Dynamically include and run all migrations from vendor/bavix/laravel-wallet/database
        //        $migrationPath = base_path('vendor/bavix/laravel-wallet/database/migrations');
        $migrationPath = __DIR__.'/../vendor/bavix/laravel-wallet/database';

        foreach (scandir($migrationPath) as $migrationFile) {
            if (pathinfo($migrationFile, PATHINFO_EXTENSION) === 'php') {
                $migration = include $migrationPath.'/'.$migrationFile;
                $migration->up();
            }
        }

    }

    // Define a reusable method for logging in a user
    protected function loginTestUser()
    {
        $user = new User([
            'id' => 1, // Unique ID for the user
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
        ]);
        $user->save();

        $this->actingAs($user); // Simulate authentication as this user
    }

    /**
     * Load the package configuration files.
     */
    protected function loadConfig()
    {
        $this->app['config']->set(
            'wallet',
            require __DIR__.'/../config/wallet.php'
        );
    }

    /**
     * Load the `.env.wallet` file, if it exists.
     *
     * @return void
     */
    protected function loadEnvironment()
    {
        $path = __DIR__.'/../.env';

        if (file_exists($path)) {
            \Dotenv\Dotenv::createImmutable(dirname($path), '.env')->load();
        }
    }
}
