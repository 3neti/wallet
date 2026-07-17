<?php

use Bavix\Wallet\Models\Transaction;
use Bavix\Wallet\Models\Transfer;
use Bavix\Wallet\Models\Wallet;
use LBHurtado\Wallet\Tests\Models\User;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPlanningContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationData;
use LBHurtado\Wallet\Treasury\Data\TreasuryDrawData;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryData;
use LBHurtado\Wallet\Treasury\Data\TreasuryReleaseData;
use LBHurtado\Wallet\Treasury\Data\TreasuryRepaymentData;
use LBHurtado\Wallet\Treasury\Data\TreasuryReversalData;
use LBHurtado\Wallet\Treasury\Data\TreasurySliceData;
use LBHurtado\Wallet\Treasury\Runtime\NullTreasuryPlanningRuntime;

function nullTreasuryRuntimePlans(): array
{
    $metadata = [
        'caller_marker' => 'phase-3-test',
        'treasury_runtime' => 'caller-value-must-not-win',
    ];

    return [
        'planInventory' => [
            new TreasuryInventoryData('inventory:001', 'cash', 'PHP', 10000, 'requested', 'inventory-key:001', metadata: $metadata),
            'inventory',
        ],
        'planAllocation' => [
            new TreasuryAllocationData('allocation:001', 'inventory:001', 8000, 'PHP', 'requested', 'allocation-key:001', metadata: $metadata),
            'allocation',
        ],
        'planSlice' => [
            new TreasurySliceData('slice:001', 'allocation:001', 4000, 'PHP', 'requested', 'slice-key:001', metadata: $metadata),
            'slice',
        ],
        'planDraw' => [
            new TreasuryDrawData('draw:001', 'allocation:001', 2000, 'PHP', 'requested', 'draw-key:001', 'slice:001', $metadata),
            'draw',
        ],
        'planRelease' => [
            new TreasuryReleaseData('release:001', 'allocation:001', 2000, 'PHP', 'requested', 'release-key:001', 'slice:001', $metadata),
            'release',
        ],
        'planRepayment' => [
            new TreasuryRepaymentData('repayment:001', 'allocation:001', 1000, 'PHP', 'requested', 'repayment-key:001', 'slice:001', 'draw:001', $metadata),
            'repayment',
        ],
        'planReversal' => [
            new TreasuryReversalData('reversal:001', 'draw:001', 'allocation:001', 2000, 'PHP', 'requested', 'reversal-key:001', 'slice:001', $metadata),
            'reversal',
        ],
    ];
}

it('returns deterministic observable null-runtime plans for all seven methods', function () {
    $runtime = new NullTreasuryPlanningRuntime;

    foreach (nullTreasuryRuntimePlans() as $method => [$input, $operation]) {
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
            ->and($input->status)->toBe('requested')
            ->and($input->metadata['treasury_runtime'])->toBe('caller-value-must-not-win');
    }
});

it('binds the package planning contract to one null-runtime singleton', function () {
    $first = app(TreasuryPlanningContract::class);
    $second = app(TreasuryPlanningContract::class);

    expect($first)->toBeInstanceOf(NullTreasuryPlanningRuntime::class)
        ->and($second)->toBe($first);
});

it('does not mutate balances or create database rows', function () {
    $runtime = app(TreasuryPlanningContract::class);
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

    foreach (nullTreasuryRuntimePlans() as $method => [$input]) {
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
