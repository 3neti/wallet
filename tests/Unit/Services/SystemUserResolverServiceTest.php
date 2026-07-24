<?php

use Illuminate\Foundation\Auth\User as NonWalletUser;
use Illuminate\Support\Facades\Config;
use LBHurtado\Wallet\Contracts\SystemUserResolverContract;
use LBHurtado\Wallet\Exceptions\SystemUserNotFoundException;
use LBHurtado\Wallet\Services\SystemUserResolverService;
use LBHurtado\Wallet\Tests\Models\User;

beforeEach(function () {
    Config::set('account.system_user.candidates', []);
});

it('binds the resolver contract to the backwards-compatible service', function () {
    expect(app(SystemUserResolverContract::class))
        ->toBeInstanceOf(SystemUserResolverService::class)
        ->toBe(app(SystemUserResolverService::class));
});

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

it('resolves the same principal from federated candidates', function () {
    $user = User::factory()->create([
        'email' => 'treasury@example.com',
    ]);

    Config::set('account.system_user.candidates', [
        'wallet-default' => [
            'model' => User::class,
            'identifier_column' => 'id',
            'identifier' => $user->getKey(),
        ],
        'x-change' => [
            'model' => User::class,
            'identifier_column' => 'email',
            'identifier' => 'treasury@example.com',
        ],
    ]);

    expect(app(SystemUserResolverContract::class)->resolve()->is($user))->toBeTrue();
});

it('fails closed when federated candidates resolve to different principals', function () {
    $first = User::factory()->create();
    $second = User::factory()->create();

    Config::set('account.system_user.candidates', [
        'primary' => [
            'model' => User::class,
            'identifier_column' => 'id',
            'identifier' => $first->getKey(),
        ],
        'secondary' => [
            'model' => User::class,
            'identifier_column' => 'id',
            'identifier' => $second->getKey(),
        ],
    ]);

    expect(fn () => app(SystemUserResolverContract::class)->resolve())
        ->toThrow(
            SystemUserNotFoundException::class,
            'Configured system-user candidates resolved to different wallets.'
        );
});

it('rejects unsafe identifier columns without leaking identifiers', function () {
    Config::set('account.system_user.candidates', [
        'unsafe' => [
            'model' => User::class,
            'identifier_column' => 'email) or 1=1',
            'identifier' => 'do-not-leak@example.com',
        ],
    ]);

    try {
        app(SystemUserResolverContract::class)->resolve();
    } catch (SystemUserNotFoundException $exception) {
        expect($exception->getMessage())
            ->toContain('candidate [unsafe]')
            ->not->toContain('do-not-leak@example.com');

        return;
    }

    $this->fail('Expected system-user resolution to fail.');
});
