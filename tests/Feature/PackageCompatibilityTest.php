<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use LBHurtado\Wallet\Actions\TopupWalletAction;
use LBHurtado\Wallet\Contracts\SystemUserResolverContract;
use LBHurtado\Wallet\Services\SystemUserResolverService;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryOperationContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryPositionReadModelContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryReadModelContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryMetadataSanitizerContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionOperationContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionProvisioningContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionReadModelContract;

it('loads every package-owned treasury migration', function () {
    expect(Schema::hasTable('treasury_settlement_resources'))->toBeTrue()
        ->and(Schema::hasTable('treasury_inventories'))->toBeTrue()
        ->and(Schema::hasTable('treasury_inventory_operations'))->toBeTrue()
        ->and(Schema::hasTable('treasury_positions'))->toBeTrue()
        ->and(Schema::hasTable('treasury_position_operations'))->toBeTrue();
});

it('resolves the supported public services through the container', function () {
    $systemUserResolver = app(SystemUserResolverContract::class);

    expect($systemUserResolver)
        ->toBeInstanceOf(SystemUserResolverService::class)
        ->toBe(app(SystemUserResolverContract::class))
        ->toBe(app(SystemUserResolverService::class))
        ->and(app(TopupWalletAction::class))
        ->toBeInstanceOf(TopupWalletAction::class);
});

it('binds durable treasury contracts on every supported laravel generation', function () {
    $contracts = [
        TreasuryInventoryOperationContract::class,
        TreasuryInventoryPositionReadModelContract::class,
        TreasuryInventoryReadModelContract::class,
        TreasuryMetadataSanitizerContract::class,
        TreasuryPositionOperationContract::class,
        TreasuryPositionProvisioningContract::class,
        TreasuryPositionReadModelContract::class,
    ];

    foreach ($contracts as $contract) {
        expect(app()->bound($contract))->toBeTrue()
            ->and(app($contract))->toBe(app($contract));
    }
});
