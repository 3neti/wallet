<?php

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use LBHurtado\Wallet\Events\DepositConfirmed;
use LBHurtado\Wallet\Events\DisbursementConfirmed;
use LBHurtado\Wallet\Events\DisbursementFailed;
use LBHurtado\Wallet\Tests\Models\User;

it('preserves confirmed disbursement event payloads and channels', function (
    string $eventClass,
    string $eventName,
    bool $isWithdrawal,
    float $expectedAmount,
) {
    $holder = User::factory()->create();

    if ($isWithdrawal) {
        $holder->deposit(20000);
        $transaction = $holder->withdraw(12345);
    } else {
        $transaction = $holder->deposit(12345);
    }

    $event = new $eventClass($transaction);
    $channels = $event->broadcastOn();

    expect($event)->toBeInstanceOf(ShouldBroadcast::class)
        ->and($event->transaction->is($transaction))->toBeTrue()
        ->and($event->broadcastAs())->toBe($eventName)
        ->and($event->broadcastWith())->toBe([
            'uuid' => $transaction->uuid,
            'amount' => $expectedAmount,
        ])
        ->and($channels)->toHaveCount(1)
        ->and($channels[0])->toBeInstanceOf(PrivateChannel::class)
        ->and($channels[0]->name)->toBe('private-App.Models.User.'.$holder->getKey());
})->with([
    'deposit confirmed' => [DepositConfirmed::class, 'deposit.confirmed', false, 123.45],
    'disbursement confirmed' => [DisbursementConfirmed::class, 'disbursement.confirmed', true, -123.45],
]);

it('preserves the legacy disbursement failed event signature as boundary debt', function () {
    $event = new ReflectionClass(DisbursementFailed::class);
    $constructor = $event->getConstructor();
    $parameters = $constructor?->getParameters() ?? [];
    $declaredPublicProperties = array_values(array_map(
        fn (ReflectionProperty $property) => $property->getName(),
        array_filter(
            $event->getProperties(ReflectionProperty::IS_PUBLIC),
            fn (ReflectionProperty $property) => $property->getDeclaringClass()->getName() === DisbursementFailed::class
        )
    ));

    expect($event->implementsInterface(ShouldBroadcast::class))->toBeFalse()
        ->and(array_keys(class_uses(DisbursementFailed::class)))->toBe([
            Dispatchable::class,
            InteractsWithSockets::class,
            SerializesModels::class,
        ])
        ->and($declaredPublicProperties)->toBe([
            'voucher',
            'exception',
            'mobile',
        ])
        ->and(array_map(fn (ReflectionParameter $parameter) => $parameter->getName(), $parameters))->toBe([
            'voucher',
            'exception',
            'mobile',
        ])
        ->and($parameters[0]->getType()?->getName())->toBe('LBHurtado\\Voucher\\Models\\Voucher')
        ->and($parameters[0]->allowsNull())->toBeFalse()
        ->and($parameters[1]->getType()?->getName())->toBe(Throwable::class)
        ->and($parameters[1]->allowsNull())->toBeFalse()
        ->and($parameters[2]->getType()?->getName())->toBe('string')
        ->and($parameters[2]->allowsNull())->toBeTrue()
        ->and($parameters[2]->isDefaultValueAvailable())->toBeTrue()
        ->and($parameters[2]->getDefaultValue())->toBeNull();
});
