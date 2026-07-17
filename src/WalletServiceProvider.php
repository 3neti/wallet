<?php

namespace LBHurtado\Wallet;

use Illuminate\Support\ServiceProvider;
use LBHurtado\Wallet\Providers\EventServiceProvider;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryAllocationReadModelContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryReadModelContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPlanningContract;
use LBHurtado\Wallet\Treasury\ReadModels\AbsentTreasuryAllocationReadModelService;
use LBHurtado\Wallet\Treasury\ReadModels\WalletBalanceInventoryReadModelService;
use LBHurtado\Wallet\Treasury\Runtime\NullTreasuryPlanningRuntime;

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
            TreasuryPlanningContract::class,
            NullTreasuryPlanningRuntime::class
        );

        $this->app->singleton(
            TreasuryInventoryReadModelContract::class,
            WalletBalanceInventoryReadModelService::class
        );

        $this->app->singleton(
            TreasuryAllocationReadModelContract::class,
            AbsentTreasuryAllocationReadModelService::class
        );
    }

    public function boot(): void
    {
        // Allow publishing the configuration files
        $this->publishes([
            __DIR__.'/../config/account.php' => config_path('account.php'),
            __DIR__.'/../config/wallet.php' => config_path('wallet.php'),
        ], 'config');
    }
}
