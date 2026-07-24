<?php

use Bavix\Wallet\Models\Transaction;
use Bavix\Wallet\Models\Transfer;
use LBHurtado\Wallet\Tests\Models\User;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryOperationContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryPositionReadModelContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryAdjustmentData;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryData;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryReclassificationData;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryRecognitionData;
use LBHurtado\Wallet\Treasury\Data\TreasuryOperationReversalData;
use LBHurtado\Wallet\Treasury\Exceptions\TreasuryImmutableOperation;
use LBHurtado\Wallet\Treasury\Exceptions\TreasuryInvariantViolation;
use LBHurtado\Wallet\Treasury\Exceptions\TreasuryOperationConflict;
use LBHurtado\Wallet\Treasury\Models\TreasuryInventory;
use LBHurtado\Wallet\Treasury\Models\TreasuryInventoryOperation;
use LBHurtado\Wallet\Treasury\Runtime\DatabaseTreasuryInventoryOperationRuntime;

function registerTreasuryInventory(
    TreasuryInventoryOperationContract $runtime,
    string $inventoryReference,
    string $resourceReference,
    string $resourceType = 'cash',
): TreasuryInventoryData {
    return $runtime->registerInventory(new TreasuryInventoryData(
        inventoryReference: $inventoryReference,
        resourceType: $resourceType,
        currency: 'PHP',
        capacityMinor: 0,
        status: 'requested',
        idempotencyKey: "register:{$inventoryReference}",
        externalReference: $resourceReference,
        metadata: ['legal_entity' => 'x-change'],
    ));
}

function recognizeTreasuryInventory(
    TreasuryInventoryOperationContract $runtime,
    string $inventoryReference,
    string $resourceReference,
    int $amountMinor,
    string $suffix,
): TreasuryInventoryRecognitionData {
    return $runtime->recognize(new TreasuryInventoryRecognitionData(
        operationReference: "recognition:{$suffix}",
        inventoryReference: $inventoryReference,
        settlementResourceReference: $resourceReference,
        amountMinor: $amountMinor,
        currency: 'PHP',
        status: 'requested',
        idempotencyKey: "recognition-key:{$suffix}",
        effectiveAt: '2026-07-23T12:00:00+08:00',
        externalReference: "provider-transaction:{$suffix}",
    ));
}

it('binds the durable inventory runtime and registers zero-balance inventory idempotently', function () {
    $runtime = app(TreasuryInventoryOperationContract::class);

    expect($runtime)->toBeInstanceOf(DatabaseTreasuryInventoryOperationRuntime::class)
        ->and(app(TreasuryInventoryOperationContract::class))->toBe($runtime);

    $first = registerTreasuryInventory($runtime, 'inventory:netbank:cash', 'resource:netbank:corporate');
    $second = registerTreasuryInventory($runtime, 'inventory:netbank:cash', 'resource:netbank:corporate');

    expect($first->status)->toBe('committed')
        ->and($first->capacityMinor)->toBe(0)
        ->and($second->toArray())->toBe($first->toArray())
        ->and(TreasuryInventory::query()->count())->toBe(1);
});

it('recognizes provider-backed capacity exactly once', function () {
    $runtime = app(TreasuryInventoryOperationContract::class);
    registerTreasuryInventory($runtime, 'inventory:netbank:cash', 'resource:netbank:corporate');

    $first = recognizeTreasuryInventory($runtime, 'inventory:netbank:cash', 'resource:netbank:corporate', 25000, '001');
    $second = recognizeTreasuryInventory($runtime, 'inventory:netbank:cash', 'resource:netbank:corporate', 25000, '001');
    $inventory = TreasuryInventory::query()->sole();

    expect($first->status)->toBe('committed')
        ->and($first->effectiveAt)->toBe('2026-07-23T04:00:00+00:00')
        ->and($second->toArray())->toBe($first->toArray())
        ->and($inventory->balance_minor)->toBe(25000)
        ->and($inventory->version)->toBe(1)
        ->and(TreasuryInventoryOperation::query()->count())->toBe(1);
});

it('keeps the Inventory registration response stable after later recognition', function () {
    $runtime = app(TreasuryInventoryOperationContract::class);
    $registration = registerTreasuryInventory($runtime, 'inventory:netbank:cash', 'resource:netbank:corporate');
    recognizeTreasuryInventory($runtime, 'inventory:netbank:cash', 'resource:netbank:corporate', 25000, '001');

    expect(registerTreasuryInventory(
        $runtime,
        'inventory:netbank:cash',
        'resource:netbank:corporate',
    )->toArray())->toBe($registration->toArray())
        ->and(TreasuryInventory::query()->sole()->balance_minor)->toBe(25000);
});

it('rejects conflicting idempotency input without changing inventory', function () {
    $runtime = app(TreasuryInventoryOperationContract::class);
    registerTreasuryInventory($runtime, 'inventory:netbank:cash', 'resource:netbank:corporate');
    recognizeTreasuryInventory($runtime, 'inventory:netbank:cash', 'resource:netbank:corporate', 25000, '001');

    expect(fn () => recognizeTreasuryInventory(
        $runtime,
        'inventory:netbank:cash',
        'resource:netbank:corporate',
        26000,
        '001',
    ))->toThrow(TreasuryOperationConflict::class);

    expect(TreasuryInventory::query()->sole()->balance_minor)->toBe(25000)
        ->and(TreasuryInventoryOperation::query()->count())->toBe(1);
});

it('requires verified evidence and rejects positive adjustment shortcuts', function () {
    $runtime = app(TreasuryInventoryOperationContract::class);
    registerTreasuryInventory($runtime, 'inventory:netbank:cash', 'resource:netbank:corporate');

    expect(fn () => $runtime->recognize(new TreasuryInventoryRecognitionData(
        'recognition:missing-evidence',
        'inventory:netbank:cash',
        'resource:netbank:corporate',
        1000,
        'PHP',
        'requested',
        'recognition-key:missing-evidence',
    )))->toThrow(TreasuryInvariantViolation::class);

    expect(fn () => $runtime->adjust(new TreasuryInventoryAdjustmentData(
        'adjustment:positive',
        'inventory:netbank:cash',
        1000,
        'PHP',
        'requested',
        'adjustment-key:positive',
    )))->toThrow(TreasuryInvariantViolation::class);

    expect(TreasuryInventoryOperation::query()->count())->toBe(0)
        ->and(TreasuryInventory::query()->sole()->balance_minor)->toBe(0);
});

it('reclassifies capacity without changing aggregate inventory', function () {
    $runtime = app(TreasuryInventoryOperationContract::class);
    registerTreasuryInventory($runtime, 'inventory:paynamics:float', 'resource:paynamics:wallet', 'emi_wallet_float');
    registerTreasuryInventory($runtime, 'inventory:bank:cash', 'resource:netbank:corporate');
    recognizeTreasuryInventory($runtime, 'inventory:paynamics:float', 'resource:paynamics:wallet', 24500, 'paynamics-001');

    $result = $runtime->reclassify(new TreasuryInventoryReclassificationData(
        operationReference: 'reclassification:001',
        sourceInventoryReference: 'inventory:paynamics:float',
        destinationInventoryReference: 'inventory:bank:cash',
        amountMinor: 20000,
        currency: 'PHP',
        status: 'requested',
        idempotencyKey: 'reclassification-key:001',
        externalReference: 'paynamics-settlement:001',
    ));

    $balances = TreasuryInventory::query()->pluck('balance_minor', 'inventory_reference');

    expect($result->status)->toBe('committed')
        ->and($balances['inventory:paynamics:float'])->toBe(4500)
        ->and($balances['inventory:bank:cash'])->toBe(20000)
        ->and($balances->sum())->toBe(24500);
});

it('records signed adjustments and operation-targeted reversals', function () {
    $runtime = app(TreasuryInventoryOperationContract::class);
    registerTreasuryInventory($runtime, 'inventory:netbank:cash', 'resource:netbank:corporate');
    recognizeTreasuryInventory($runtime, 'inventory:netbank:cash', 'resource:netbank:corporate', 25000, '001');

    $runtime->adjust(new TreasuryInventoryAdjustmentData(
        operationReference: 'adjustment:001',
        inventoryReference: 'inventory:netbank:cash',
        deltaAmountMinor: -500,
        currency: 'PHP',
        status: 'requested',
        idempotencyKey: 'adjustment-key:001',
        externalReference: 'reconciliation:001',
    ));

    $reversal = $runtime->reverse(new TreasuryOperationReversalData(
        operationReference: 'reversal:001',
        reversesOperationReference: 'recognition:001',
        amountMinor: 10000,
        currency: 'PHP',
        status: 'requested',
        idempotencyKey: 'reversal-key:001',
        externalReference: 'provider-reversal:001',
    ));

    expect($reversal->status)->toBe('committed')
        ->and($reversal->reversesOperationReference)->toBe('recognition:001')
        ->and(TreasuryInventory::query()->sole()->balance_minor)->toBe(14500)
        ->and(TreasuryInventoryOperation::query()->count())->toBe(3);
});

it('records an authoritative reversal as negative inventory after capacity moved elsewhere', function () {
    $runtime = app(TreasuryInventoryOperationContract::class);
    registerTreasuryInventory($runtime, 'inventory:provider:float', 'resource:provider:wallet', 'emi_wallet_float');
    registerTreasuryInventory($runtime, 'inventory:bank:cash', 'resource:bank:corporate');
    recognizeTreasuryInventory($runtime, 'inventory:provider:float', 'resource:provider:wallet', 1000, 'provider-001');

    $runtime->reclassify(new TreasuryInventoryReclassificationData(
        'reclassification:provider-001',
        'inventory:provider:float',
        'inventory:bank:cash',
        1000,
        'PHP',
        'requested',
        'reclassification-key:provider-001',
    ));

    $runtime->reverse(new TreasuryOperationReversalData(
        'reversal:provider-001',
        'recognition:provider-001',
        1000,
        'PHP',
        'requested',
        'reversal-key:provider-001',
    ));

    expect(TreasuryInventory::query()->where('inventory_reference', 'inventory:provider:float')->value('balance_minor'))->toBe(-1000)
        ->and(TreasuryInventory::query()->where('inventory_reference', 'inventory:bank:cash')->value('balance_minor'))->toBe(1000);
});

it('rolls back operations that would overdraw inventory or over-reverse a target', function () {
    $runtime = app(TreasuryInventoryOperationContract::class);
    registerTreasuryInventory($runtime, 'inventory:netbank:cash', 'resource:netbank:corporate');
    registerTreasuryInventory($runtime, 'inventory:other:cash', 'resource:other:corporate');
    recognizeTreasuryInventory($runtime, 'inventory:netbank:cash', 'resource:netbank:corporate', 1000, '001');

    expect(fn () => $runtime->reclassify(new TreasuryInventoryReclassificationData(
        'reclassification:overdraw',
        'inventory:netbank:cash',
        'inventory:other:cash',
        1001,
        'PHP',
        'requested',
        'reclassification-key:overdraw',
    )))->toThrow(TreasuryInvariantViolation::class);

    expect(fn () => $runtime->reverse(new TreasuryOperationReversalData(
        'reversal:over',
        'recognition:001',
        1001,
        'PHP',
        'requested',
        'reversal-key:over',
    )))->toThrow(TreasuryInvariantViolation::class);

    expect(TreasuryInventory::query()->where('inventory_reference', 'inventory:netbank:cash')->value('balance_minor'))->toBe(1000)
        ->and(TreasuryInventory::query()->where('inventory_reference', 'inventory:other:cash')->value('balance_minor'))->toBe(0)
        ->and(TreasuryInventoryOperation::query()->count())->toBe(1);
});

it('does not mutate Bavix wallets while recording Inventory operations', function () {
    $runtime = app(TreasuryInventoryOperationContract::class);
    $holder = User::factory()->create();
    $holder->deposit(10000);
    $wallet = $holder->wallet;
    $wallet->refreshBalance();
    $balanceBefore = $wallet->balanceInt;
    $transactionsBefore = Transaction::query()->count();
    $transfersBefore = Transfer::query()->count();

    registerTreasuryInventory($runtime, 'inventory:netbank:cash', 'resource:netbank:corporate');
    recognizeTreasuryInventory($runtime, 'inventory:netbank:cash', 'resource:netbank:corporate', 25000, '001');
    $wallet->refreshBalance();

    expect($wallet->balanceInt)->toBe($balanceBefore)
        ->and(Transaction::query()->count())->toBe($transactionsBefore)
        ->and(Transfer::query()->count())->toBe($transfersBefore);
});

it('keeps committed Inventory operations append only', function () {
    $runtime = app(TreasuryInventoryOperationContract::class);
    registerTreasuryInventory($runtime, 'inventory:netbank:cash', 'resource:netbank:corporate');
    recognizeTreasuryInventory($runtime, 'inventory:netbank:cash', 'resource:netbank:corporate', 25000, '001');
    $operation = TreasuryInventoryOperation::query()->sole();

    expect(function () use ($operation): void {
        $operation->forceFill(['status' => 'rewritten'])->save();
    })->toThrow(TreasuryImmutableOperation::class);

    expect(fn () => $operation->delete())->toThrow(TreasuryImmutableOperation::class)
        ->and($operation->fresh()?->status)->toBe('committed');
});

it('exposes a read-only Inventory position backed by the operation ledger', function () {
    $runtime = app(TreasuryInventoryOperationContract::class);
    registerTreasuryInventory($runtime, 'inventory:netbank:cash', 'resource:netbank:corporate');
    recognizeTreasuryInventory($runtime, 'inventory:netbank:cash', 'resource:netbank:corporate', 25000, '001');

    $position = app(TreasuryInventoryPositionReadModelContract::class)
        ->find('inventory:netbank:cash');

    expect($position)->not->toBeNull()
        ->and($position->balanceMinor)->toBe(25000)
        ->and($position->lastOperationReference)->toBe('recognition:001')
        ->and($position->hasTreasuryFacts)->toBeTrue()
        ->and(app(TreasuryInventoryPositionReadModelContract::class)
            ->operationExists('recognition:001'))->toBeTrue()
        ->and(app(TreasuryInventoryPositionReadModelContract::class)
            ->operationExists('recognition:missing'))->toBeFalse()
        ->and($position->metadata['treasury_facts'])->toBe('present')
        ->and(app(TreasuryInventoryPositionReadModelContract::class)->find('inventory:missing'))->toBeNull();
});

it('keeps each Inventory projection equal to committed destination less source operations', function () {
    $runtime = app(TreasuryInventoryOperationContract::class);
    registerTreasuryInventory($runtime, 'inventory:provider:float', 'resource:provider:wallet', 'emi_wallet_float');
    registerTreasuryInventory($runtime, 'inventory:bank:cash', 'resource:bank:corporate');
    recognizeTreasuryInventory($runtime, 'inventory:provider:float', 'resource:provider:wallet', 10000, 'projection-001');
    $runtime->reclassify(new TreasuryInventoryReclassificationData(
        'reclassification:projection-001',
        'inventory:provider:float',
        'inventory:bank:cash',
        7000,
        'PHP',
        'requested',
        'reclassification-key:projection-001',
    ));
    $runtime->adjust(new TreasuryInventoryAdjustmentData(
        'adjustment:projection-001',
        'inventory:bank:cash',
        -500,
        'PHP',
        'requested',
        'adjustment-key:projection-001',
    ));

    foreach (TreasuryInventory::query()->get() as $inventory) {
        $destinationMinor = (int) TreasuryInventoryOperation::query()
            ->where('destination_inventory_id', $inventory->getKey())
            ->sum('amount_minor');
        $sourceMinor = (int) TreasuryInventoryOperation::query()
            ->where('source_inventory_id', $inventory->getKey())
            ->sum('amount_minor');

        expect($inventory->balance_minor)->toBe($destinationMinor - $sourceMinor);
    }
});
