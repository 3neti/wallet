<?php

use Bavix\Wallet\Models\Transaction;
use Bavix\Wallet\Models\Transfer;
use Bavix\Wallet\Models\Wallet;
use LBHurtado\Wallet\Tests\Models\User;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryOperationPlanningContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryAdjustmentData;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryReclassificationData;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryRecognitionData;
use LBHurtado\Wallet\Treasury\Data\TreasuryOperationReversalData;
use LBHurtado\Wallet\Treasury\Runtime\NullTreasuryInventoryOperationPlanningRuntime;
use LBHurtado\Wallet\Treasury\Runtime\NullTreasuryPlanningRuntime;

function nullTreasuryInventoryOperationPlans(): array
{
    $metadata = [
        'caller_marker' => 'inventory-operation-test',
        'treasury_runtime' => 'caller-value-must-not-win',
    ];

    return [
        'planRecognition' => [
            new TreasuryInventoryRecognitionData('recognition:001', 'inventory:001', 'resource:001', 10000, 'PHP', 'requested', 'recognition-key:001', metadata: $metadata),
            'inventory-recognition',
        ],
        'planReclassification' => [
            new TreasuryInventoryReclassificationData('reclassification:001', 'inventory:001', 'inventory:002', 9000, 'PHP', 'requested', 'reclassification-key:001', metadata: $metadata),
            'inventory-reclassification',
        ],
        'planAdjustment' => [
            new TreasuryInventoryAdjustmentData('adjustment:001', 'inventory:002', -1000, 'PHP', 'requested', 'adjustment-key:001', metadata: $metadata),
            'inventory-adjustment',
        ],
        'planReversal' => [
            new TreasuryOperationReversalData('reversal:001', 'recognition:001', 10000, 'PHP', 'requested', 'reversal-key:001', metadata: $metadata),
            'inventory-operation-reversal',
        ],
    ];
}

it('returns deterministic non-mutating plans for inventory operations', function () {
    $runtime = new NullTreasuryInventoryOperationPlanningRuntime;

    foreach (nullTreasuryInventoryOperationPlans() as $method => [$input, $operation]) {
        $expected = $input->toArray();
        $expected['status'] = NullTreasuryPlanningRuntime::STATUS;
        $expected['metadata'] = array_merge($input->metadata, [
            'treasury_runtime' => NullTreasuryPlanningRuntime::RUNTIME,
            'treasury_runtime_status' => NullTreasuryPlanningRuntime::RUNTIME_STATUS,
            'treasury_operation' => $operation,
        ]);

        $first = $runtime->{$method}($input);
        $second = $runtime->{$method}($input);

        expect($first)->toBeInstanceOf($input::class)
            ->and($first)->not->toBe($input)
            ->and($first->toArray())->toBe($expected)
            ->and($second->toArray())->toBe($expected)
            ->and($input->status)->toBe('requested');
    }
});

it('binds the inventory operation planning contract as a singleton', function () {
    $first = app(TreasuryInventoryOperationPlanningContract::class);
    $second = app(TreasuryInventoryOperationPlanningContract::class);

    expect($first)->toBeInstanceOf(NullTreasuryInventoryOperationPlanningRuntime::class)
        ->and($second)->toBe($first);
});

it('does not mutate wallet state or create rows', function () {
    $runtime = app(TreasuryInventoryOperationPlanningContract::class);
    $holder = User::factory()->create();
    $holder->deposit(10000);
    $wallet = $holder->wallet;
    $wallet->refreshBalance();

    $balanceBefore = $wallet->balanceInt;
    $rowCountsBefore = [
        'users' => User::query()->count(),
        'wallets' => Wallet::query()->count(),
        'transactions' => Transaction::query()->count(),
        'transfers' => Transfer::query()->count(),
    ];

    foreach (nullTreasuryInventoryOperationPlans() as $method => [$input]) {
        $runtime->{$method}($input);
    }

    $wallet->refreshBalance();

    expect($wallet->balanceInt)->toBe($balanceBefore)
        ->and([
            'users' => User::query()->count(),
            'wallets' => Wallet::query()->count(),
            'transactions' => Transaction::query()->count(),
            'transfers' => Transfer::query()->count(),
        ])->toBe($rowCountsBefore);
});
