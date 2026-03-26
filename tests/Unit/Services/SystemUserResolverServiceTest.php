<?php

use Illuminate\Support\Facades\Config;
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

it('throws SystemUserNotFoundException if resolved user is not a Wallet', function () {
    // Arrange: Insert an invalid user into the DB
    $user = User::factory()->create([
        'email' => 'fake@user.com',
    ]);

    // Configure to point to InvalidUser model
    Config::set('account.system_user.identifier', 'apple@hurtado.ph');
    Config::set('account.system_user.identifier_column', 'email');
    Config::set('account.system_user.model', User::class);

    // Act & Assert
    $service = new SystemUserResolverService;
    $this->expectException(SystemUserNotFoundException::class);
    $service->resolve();
});
