<?php

use Bavix\Wallet\Internal\Events\BalanceUpdatedEventInterface;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use LBHurtado\Wallet\Events\BalanceUpdated;
use LBHurtado\Wallet\Tests\Models\User;

it('preserves the balance updated event contract and broadcast payload', function () {
    $holder = User::factory()->create();
    $holder->deposit(12345);
    $wallet = $holder->wallet;
    $wallet->refreshBalance();
    $updatedAt = new DateTimeImmutable('2026-07-17 12:34:56');

    $event = new BalanceUpdated($wallet, $updatedAt);
    $channels = $event->broadcastOn();

    expect($event)->toBeInstanceOf(BalanceUpdatedEventInterface::class)
        ->and($event)->toBeInstanceOf(ShouldBroadcast::class)
        ->and($event->getWalletId())->toBe($wallet->getKey())
        ->and($event->getWalletUuid())->toBe($wallet->uuid)
        ->and($event->getBalance())->toBe('12345')
        ->and($event->getBalanceFloat())->toBe(123.45)
        ->and($event->getUpdatedAt())->toBe($updatedAt)
        ->and($event->broadcastAs())->toBe('balance.updated')
        ->and($channels)->toHaveCount(1)
        ->and($channels[0])->toBeInstanceOf(PrivateChannel::class)
        ->and($channels[0]->name)->toBe('private-App.Models.User.'.$holder->getKey())
        ->and($event->broadcastWith())->toBe([
            'walletId' => $wallet->getKey(),
            'balanceFloat' => 123.45,
            'updatedAt' => '2026-07-17 12:34:56',
            'message' => 'Balance updated.',
        ]);
});
