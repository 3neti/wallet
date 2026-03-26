<?php

use Bavix\Wallet\Interfaces\Wallet;
use Bavix\Wallet\Models\Transfer;
use Illuminate\Support\Facades\Config;
use LBHurtado\Wallet\Actions\TopupWalletAction;
use LBHurtado\Wallet\Services\SystemUserResolverService;
use LBHurtado\Wallet\Tests\Models\User;

beforeEach(function () {
    Config::set('account.system_user.identifier', 'system@dev-asiana.io');
    Config::set('account.system_user.identifier_column', 'email');
    Config::set('account.system_user.model', User::class);

    // Seed or ensure a system user exists
    $system = User::factory()->create([
        'name' => 'System',
        'email' => 'system@dev-asiana.io',
    ]);
    $system->wallet; // Explicitly create the wallet
    $system->depositFloat(10000.00); // Start with a balance of 10,000
});

it('handles wallet top-ups via system user transfer', function () {
    // Mock dependencies
    $systemUserResolverMock = Mockery::mock(SystemUserResolverService::class);
    $systemUserMock = Mockery::mock(Wallet::class);
    $walletMock = Mockery::mock(Wallet::class);
    $transferMock = Mockery::mock(Transfer::class);

    // Bind the mock system resolver into the IoC container
    $this->app->instance(SystemUserResolverService::class, $systemUserResolverMock);

    // Define expectations
    $systemUserResolverMock->shouldReceive('resolve')
        ->once()
        ->andReturn($systemUserMock);

    $systemUserMock->shouldReceive('transferFloat')
        ->once()
        ->with($walletMock, 1000.0) // Example amount: 1000.0
        ->andReturn($transferMock);

    // Call the action
    $action = new TopupWalletAction;
    $result = $action->handle($walletMock, 1000.0);

    // Assert the result is what we expect (the mocked transfer)
    expect($result)->toBe($transferMock);
});

it('updates balances for system and user after top-up', function () {
    // Set up the system user and recipient user
    $systemUser = app(SystemUserResolverService::class)->resolve();
    $recipientUser = auth()->user();

    // Verify initial balances
    expect((float) $systemUser->balanceFloat)->toBe(10000.00);
    expect((float) $recipientUser->balanceFloat)->toBe(0.00);

    // Call the action

    if ($recipientUser instanceof User) {
        $transfer = TopupWalletAction::run($recipientUser, 1000.0); // Transfer 5,000 to recipient

        // Verify balances after the transfer
        $systemUser->wallet->refreshBalance();
        expect((float) $systemUser->balanceFloat)->toBe(9000.00);
        $recipientUser->wallet->refreshBalance();
        expect((float) $recipientUser->balanceFloat)->toBe(1000.00);

        // Verify the transfer details
        expect($transfer)->toBeInstanceOf(Transfer::class);
        expect($transfer->from->holder->is($systemUser))->toBeTrue();
        expect($transfer->to->holder->is($recipientUser))->toBeTrue();
        expect((float) $transfer->withdraw->amountFloat)->toBe(-1000.00);
        expect((float) $transfer->deposit->amountFloat)->toBe(1000.00);
    }
});
