<?php

use Bavix\Wallet\Models\Transaction;
use Carbon\Carbon;
use LBHurtado\Wallet\Actions\WithdrawCash;
use LBHurtado\Wallet\Tests\Models\User;

beforeEach(function () {
    Carbon::setTestNow('2026-07-17 10:30:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

it('withdraws the full balance when no amount is supplied and returns the Bavix transaction', function () {
    $cash = User::factory()->create();
    $cash->deposit(12500);

    $transaction = (new WithdrawCash)->handle(
        $cash,
        'operation-full',
        'Full provider disbursement',
        ['provider' => 'sandbox']
    );

    $cash->wallet->refreshBalance();

    expect($transaction)->toBeInstanceOf(Transaction::class)
        ->and($transaction->type)->toBe(Transaction::TYPE_WITHDRAW)
        ->and($transaction->amountInt)->toBe(-12500)
        ->and($transaction->confirmed)->toBeTrue()
        ->and($transaction->meta)->toBe([
            'type' => 'disbursement',
            'operation_id' => 'operation-full',
            'notes' => 'Full provider disbursement',
            'withdrawn_at' => now()->toIso8601String(),
            'provider' => 'sandbox',
        ])
        ->and($cash->wallet->balanceInt)->toBe(0);
});

it('withdraws an explicit minor-unit amount and leaves the remainder', function () {
    $cash = User::factory()->create();
    $cash->deposit(10000);

    $transaction = (new WithdrawCash)->handle(
        cash: $cash,
        operationId: 'operation-partial',
        notes: 'Partial provider disbursement',
        amount: 2500,
    );

    $cash->wallet->refreshBalance();

    expect($transaction)->toBeInstanceOf(Transaction::class)
        ->and($transaction->amountInt)->toBe(-2500)
        ->and($transaction->meta)->toBe([
            'type' => 'disbursement',
            'operation_id' => 'operation-partial',
            'notes' => 'Partial provider disbursement',
            'withdrawn_at' => now()->toIso8601String(),
        ])
        ->and($cash->wallet->balanceInt)->toBe(7500);
});

it('rejects a withdrawal from a zero-balance wallet', function () {
    $cash = User::factory()->create();

    expect(fn () => (new WithdrawCash)->handle($cash))
        ->toThrow(
            InvalidArgumentException::class,
            "Cash wallet #{$cash->getKey()} has zero balance"
        );
});

it('rejects an explicit amount greater than the wallet balance', function () {
    $cash = User::factory()->create();
    $cash->deposit(1000);

    expect(fn () => (new WithdrawCash)->handle(cash: $cash, amount: 1001))
        ->toThrow(
            InvalidArgumentException::class,
            "Requested withdrawal 1001 exceeds balance 1000 on cash #{$cash->getKey()}"
        );
});

it('allows additional metadata to override the generated metadata', function () {
    $cash = User::factory()->create();
    $cash->deposit(5000);

    $transaction = (new WithdrawCash)->handle(
        cash: $cash,
        operationId: 'base-operation',
        notes: 'Base notes',
        additionalMeta: [
            'type' => 'custom-disbursement',
            'operation_id' => 'override-operation',
            'notes' => 'Override notes',
            'withdrawn_at' => 'external-timestamp',
            'provider' => 'sandbox',
        ],
        amount: 1000,
    );

    expect($transaction->meta)->toBe([
        'type' => 'custom-disbursement',
        'operation_id' => 'override-operation',
        'notes' => 'Override notes',
        'withdrawn_at' => 'external-timestamp',
        'provider' => 'sandbox',
    ]);
});
