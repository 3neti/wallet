<?php

namespace LBHurtado\Wallet;

use Illuminate\Support\ServiceProvider;
use LBHurtado\Wallet\Contracts\SystemUserResolverContract;
use LBHurtado\Wallet\Providers\EventServiceProvider;
use LBHurtado\Wallet\Services\SystemUserResolverService;
use LBHurtado\Wallet\Treasury\Adapters\Bavix\BavixTreasuryPositionOperationRuntime;
use LBHurtado\Wallet\Treasury\Adapters\Bavix\BavixTreasuryPositionReadModel;
use LBHurtado\Wallet\Treasury\Adapters\Bavix\BavixTreasuryPositionRuntime;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryAllocationReadModelContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryOperationContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryOperationPlanningContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryPositionReadModelContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryReadModelContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryMetadataSanitizerContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPlanningContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionOperationContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionProvisioningContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionReadModelContract;
use LBHurtado\Wallet\Treasury\ReadModels\AbsentTreasuryAllocationReadModelService;
use LBHurtado\Wallet\Treasury\ReadModels\DatabaseTreasuryInventoryPositionReadModel;
use LBHurtado\Wallet\Treasury\ReadModels\WalletBalanceInventoryReadModelService;
use LBHurtado\Wallet\Treasury\Runtime\DatabaseTreasuryInventoryOperationRuntime;
use LBHurtado\Wallet\Treasury\Runtime\NullTreasuryInventoryOperationPlanningRuntime;
use LBHurtado\Wallet\Treasury\Runtime\NullTreasuryPlanningRuntime;
use LBHurtado\Wallet\Treasury\Services\ConfigTreasuryMetadataSanitizer;

class WalletServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/wallet.php',
            'wallet'
        );
        $this->mergeConfigFrom(
            __DIR__.'/../config/account.php',
            'account'
        );

        // Register event service provider
        $this->app->register(EventServiceProvider::class);

        $this->app->singleton(
            SystemUserResolverService::class
        );

        $this->app->alias(
            SystemUserResolverService::class,
            SystemUserResolverContract::class
        );

        $this->app->singleton(
            TreasuryPlanningContract::class,
            NullTreasuryPlanningRuntime::class
        );

        $this->app->singleton(
            TreasuryInventoryOperationPlanningContract::class,
            NullTreasuryInventoryOperationPlanningRuntime::class
        );

        $this->app->singleton(
            TreasuryMetadataSanitizerContract::class,
            ConfigTreasuryMetadataSanitizer::class,
        );

        $this->app->singleton(
            TreasuryInventoryOperationContract::class,
            DatabaseTreasuryInventoryOperationRuntime::class
        );

        $this->app->singleton(
            TreasuryInventoryPositionReadModelContract::class,
            DatabaseTreasuryInventoryPositionReadModel::class
        );

        $this->app->singleton(
            TreasuryInventoryReadModelContract::class,
            WalletBalanceInventoryReadModelService::class
        );

        $this->app->singleton(
            TreasuryAllocationReadModelContract::class,
            AbsentTreasuryAllocationReadModelService::class
        );

        $this->app->singleton(
            TreasuryPositionProvisioningContract::class,
            BavixTreasuryPositionRuntime::class
        );

        $this->app->singleton(
            TreasuryPositionReadModelContract::class,
            BavixTreasuryPositionReadModel::class
        );

        $this->app->singleton(
            TreasuryPositionOperationContract::class,
            BavixTreasuryPositionOperationRuntime::class
        );
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Allow publishing the configuration files
        $this->publishes([
            __DIR__.'/../config/account.php' => config_path('account.php'),
            __DIR__.'/../config/wallet.php' => config_path('wallet.php'),
        ], 'config');
    }
}
