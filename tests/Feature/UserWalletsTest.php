<?php

use LBHurtado\Wallet\Enums\WalletType;
use LBHurtado\Wallet\Services\WalletProvisioningService;
use LBHurtado\Wallet\Tests\Models\User;

it('creates wallets for all WalletType enums upon user creation', function () {
    $user = User::factory()->create();

    foreach (WalletType::cases() as $type) {
        $wallet = $user->getWallet($type->value);
        expect($wallet)->not->toBeNull()
            ->and($wallet->slug)->toBe($type->value)
            ->and($wallet->name)->toBe($type->label())
            ->and($wallet->meta)->toBe($type->defaultMeta())
            ->and($wallet->holder_id)->toBe($user->getKey());
    }
});

it('sets wallet balances to zero by default', function () {
    $user = User::factory()->create();

    foreach (WalletType::cases() as $type) {
        $wallet = $user->getWallet($type->value);

        expect((float) $wallet->balanceFloat)->toBe(0.0)
            ->and($wallet->meta)->toBeArray();
    }
});

it('keeps wallet slugs labels and default metadata stable', function () {
    $definitions = collect(WalletType::cases())
        ->mapWithKeys(fn (WalletType $type) => [
            $type->value => [
                'label' => $type->label(),
                'meta' => $type->defaultMeta(),
            ],
        ])
        ->all();

    expect($definitions)->toBe([
        'platform' => [
            'label' => 'Platform Wallet',
            'meta' => ['description' => 'Main wallet for platform transactions.'],
        ],
        'rewards' => [
            'label' => 'Rewards Wallet',
            'meta' => ['description' => 'Wallet for loyalty points and rewards.'],
        ],
        'escrow' => [
            'label' => 'Escrow Wallet',
            'meta' => ['description' => 'Wallet for held funds.'],
        ],
        'commission' => [
            'label' => 'Commission Wallet',
            'meta' => ['description' => 'Platform earnings.'],
        ],
    ]);
});

it('does not duplicate wallets on repeated provisioning', function () {
    $user = User::factory()->create();
    $walletService = app(WalletProvisioningService::class);

    $walletService->createDefaultWalletsForUser($user);
    $walletService->createDefaultWalletsForUser($user);

    foreach (WalletType::cases() as $type) {
        $wallets = $user->wallets()->where('slug', $type->value)->get();
        expect($wallets)->toHaveCount(1);
    }
});
