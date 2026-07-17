<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Foundation\Auth\User as NonWalletUser;
use LBHurtado\Wallet\Exceptions\SystemUserNotFoundException;
use LBHurtado\Wallet\Services\SystemUserResolverService;
use LBHurtado\Wallet\Tests\Models\User;

it('resolves the system user based on config/account.php', function () {
    // Arrange: set config values
    Config::set('account.system_user.identifier', 'apple@hurtado.ph');
    Config::set('account.system_user.identifier_column', 'email');
    Config::set('account.system_user.model', User::class);

    // Seed or ensure a user exists
    $user = User::factory()->create([
        'email' => 'apple@hurtado.ph',
    ]);

    // Act
    $resolvedUser = app(SystemUserResolverService::class)->resolve();

    // Assert
    expect($resolvedUser->is($user))->toBeTrue();
});

it('rejects an invalid configured model', function () {
    Config::set('account.system_user.model', 'Missing\\Configured\\SystemUser');

    expect(fn () => app(SystemUserResolverService::class)->resolve())
        ->toThrow(SystemUserNotFoundException::class);
});

it('rejects a resolved model that does not implement the Bavix Wallet interface', function () {
    User::factory()->create([
        'email' => 'not-a-wallet@example.com',
    ]);

    Config::set('account.system_user.identifier', 'not-a-wallet@example.com');
    Config::set('account.system_user.identifier_column', 'email');
    Config::set('account.system_user.model', NonWalletUser::class);

    expect(fn () => app(SystemUserResolverService::class)->resolve())
        ->toThrow(SystemUserNotFoundException::class);
});

it('rejects a missing configured system user', function () {
    Config::set('account.system_user.identifier', 'missing@example.com');
    Config::set('account.system_user.identifier_column', 'email');
    Config::set('account.system_user.model', User::class);

    expect(fn () => app(SystemUserResolverService::class)->resolve())
        ->toThrow(SystemUserNotFoundException::class);
});
