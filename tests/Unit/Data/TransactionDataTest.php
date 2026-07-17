<?php

use LBHurtado\Wallet\Data\TransactionData;
use LBHurtado\Wallet\Tests\Models\User;

it('has transaction data', function () {
    $user = User::factory()->create();
    $transaction = $user->deposit(
        12345,
        [
            'payload' => ['reference' => 'deposit-123', 'channel' => 'test'],
            'ignored' => 'outside-payload',
        ],
        false
    );

    $data = TransactionData::fromModel($transaction);

    expect($data)->toBeInstanceOf(TransactionData::class)
        ->and($data->amount->getCurrency()->getCurrencyCode())->toBe('PHP')
        ->and((string) $data->amount->getMinorAmount())->toBe('12345')
        ->and($data->confirmed)->toBeFalse()
        ->and($data->payload)->toBe([
            'reference' => 'deposit-123',
            'channel' => 'test',
        ])
        ->and(array_keys(get_object_vars($data)))->toBe([
            'amount',
            'confirmed',
            'payload',
        ]);
});

it('uses an empty payload when transaction metadata has no payload key', function () {
    $user = User::factory()->create();
    $transaction = $user->deposit(100, ['reference' => 'outside-payload']);

    expect(TransactionData::fromModel($transaction)->payload)->toBe([]);
});
