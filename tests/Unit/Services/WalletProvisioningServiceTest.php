<?php

use Illuminate\Support\Facades\App;
use LBHurtado\Wallet\Services\WalletProvisioningService;
use LBHurtado\Wallet\Tests\Models\User;

it('provisions default wallets on user creation', function () {
    // Fake the WalletProvisioningService and bind it to the container
    $mock = Mockery::mock(WalletProvisioningService::class);
    $mock->shouldReceive('createDefaultWalletsForUser')->once();
    App::instance(WalletProvisioningService::class, $mock);

    // Create a user (this should trigger the booted logic)
    User::factory()->create();
});
