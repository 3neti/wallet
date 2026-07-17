<?php

use Bavix\Wallet\Models\Transaction;
use Bavix\Wallet\Models\Transfer;
use Bavix\Wallet\Models\Wallet;
use LBHurtado\Wallet\Tests\Models\User;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryAllocationReadModelContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationReadModelData;
use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationReadModelQueryData;
use LBHurtado\Wallet\Treasury\Data\TreasurySliceReadModelData;
use LBHurtado\Wallet\Treasury\Enums\TreasurySliceSemantics;
use LBHurtado\Wallet\Treasury\ReadModels\AbsentTreasuryAllocationReadModelService;

it('binds a package-neutral absent-facts Allocation read model as a singleton', function () {
    $first = app(TreasuryAllocationReadModelContract::class);
    $second = app(TreasuryAllocationReadModelContract::class);

    expect($first)->toBeInstanceOf(AbsentTreasuryAllocationReadModelService::class)
        ->and($second)->toBe($first);
});

it('keeps the Allocation read-model contract stable and package-owned', function () {
    $contract = new ReflectionClass(TreasuryAllocationReadModelContract::class);
    $methods = $contract->getMethods();
    $read = $contract->getMethod('read');

    expect($contract->isInterface())->toBeTrue()
        ->and($methods)->toHaveCount(1)
        ->and($read->getParameters())->toHaveCount(1)
        ->and($read->getParameters()[0]->getType()?->getName())->toBe(TreasuryAllocationReadModelQueryData::class)
        ->and($read->getReturnType()?->getName())->toBe(TreasuryAllocationReadModelData::class);
});

it('returns stable explicit zero fields when Treasury facts are absent', function () {
    $query = new TreasuryAllocationReadModelQueryData(
        allocationReference: 'allocation:missing',
        currency: 'PHP',
        inventoryReference: 'inventory:001',
        metadata: [
            'source' => 'test',
            'treasury_facts' => 'caller-override',
        ],
    );

    $readModel = app(TreasuryAllocationReadModelContract::class)->read($query);

    expect($query->toArray())->toBe([
        'allocationReference' => 'allocation:missing',
        'currency' => 'PHP',
        'inventoryReference' => 'inventory:001',
        'metadata' => [
            'source' => 'test',
            'treasury_facts' => 'caller-override',
        ],
    ])->and($readModel->toArray())->toBe([
        'allocationReference' => 'allocation:missing',
        'currency' => 'PHP',
        'allocatedAmountMinor' => 0,
        'drawnAmountMinor' => 0,
        'releasedAmountMinor' => 0,
        'outstandingAmountMinor' => 0,
        'usableAmountMinor' => 0,
        'sliceCount' => 0,
        'hasTreasuryFacts' => false,
        'inventoryReference' => 'inventory:001',
        'slices' => [],
        'metadata' => [
            'source' => 'test',
            'treasury_facts' => 'absent',
            'treasury_read_model' => 'allocation-slice-planning',
            'treasury_read_model_status' => 'read-only',
        ],
    ]);
});

it('defines stable open fixed and named Slice semantics for future real facts', function () {
    $open = new TreasurySliceReadModelData(
        sliceReference: 'slice:open',
        allocationReference: 'allocation:001',
        semantics: TreasurySliceSemantics::OPEN,
        currency: 'PHP',
        allocatedAmountMinor: 5000,
        drawnAmountMinor: 1000,
        releasedAmountMinor: 0,
        outstandingAmountMinor: 1000,
        usableAmountMinor: 4000,
        hasTreasuryFacts: true,
        metadata: ['treasury_facts' => 'present'],
    );
    $fixed = new TreasurySliceReadModelData(
        sliceReference: 'slice:fixed',
        allocationReference: 'allocation:001',
        semantics: TreasurySliceSemantics::FIXED,
        currency: 'PHP',
        allocatedAmountMinor: 3000,
        drawnAmountMinor: 3000,
        releasedAmountMinor: 0,
        outstandingAmountMinor: 3000,
        usableAmountMinor: 0,
        hasTreasuryFacts: true,
        metadata: ['treasury_facts' => 'present'],
    );
    $named = new TreasurySliceReadModelData(
        sliceReference: 'slice:named',
        allocationReference: 'allocation:001',
        semantics: TreasurySliceSemantics::NAMED,
        currency: 'PHP',
        allocatedAmountMinor: 2000,
        drawnAmountMinor: 0,
        releasedAmountMinor: 500,
        outstandingAmountMinor: 0,
        usableAmountMinor: 1500,
        hasTreasuryFacts: true,
        name: 'Provider reserve',
        metadata: ['treasury_facts' => 'present'],
    );

    expect(array_column(TreasurySliceSemantics::cases(), 'value'))->toBe(['open', 'fixed', 'named'])
        ->and($open->semantics)->toBe(TreasurySliceSemantics::OPEN)
        ->and($open->name)->toBeNull()
        ->and($fixed->semantics)->toBe(TreasurySliceSemantics::FIXED)
        ->and($fixed->usableAmountMinor)->toBe(0)
        ->and($named->semantics)->toBe(TreasurySliceSemantics::NAMED)
        ->and($named->name)->toBe('Provider reserve')
        ->and($named->toArray())->toBe([
            'sliceReference' => 'slice:named',
            'allocationReference' => 'allocation:001',
            'semantics' => TreasurySliceSemantics::NAMED,
            'currency' => 'PHP',
            'allocatedAmountMinor' => 2000,
            'drawnAmountMinor' => 0,
            'releasedAmountMinor' => 500,
            'outstandingAmountMinor' => 0,
            'usableAmountMinor' => 1500,
            'hasTreasuryFacts' => true,
            'name' => 'Provider reserve',
            'metadata' => ['treasury_facts' => 'present'],
        ]);

    $allocation = new TreasuryAllocationReadModelData(
        allocationReference: 'allocation:001',
        currency: 'PHP',
        allocatedAmountMinor: 10000,
        drawnAmountMinor: 4000,
        releasedAmountMinor: 500,
        outstandingAmountMinor: 4000,
        usableAmountMinor: 5500,
        sliceCount: 3,
        hasTreasuryFacts: true,
        inventoryReference: 'inventory:001',
        slices: [$open, $fixed, $named],
        metadata: ['treasury_facts' => 'present'],
    );

    expect($allocation->sliceCount)->toBe(3)
        ->and($allocation->slices)->toHaveCount(3)
        ->and($allocation->hasTreasuryFacts)->toBeTrue()
        ->and($allocation->allocatedAmountMinor)->toBe(10000)
        ->and($allocation->drawnAmountMinor)->toBe(4000)
        ->and($allocation->releasedAmountMinor)->toBe(500)
        ->and($allocation->outstandingAmountMinor)->toBe(4000)
        ->and($allocation->usableAmountMinor)->toBe(5500);
});

it('does not mutate wallet state or create Bavix financial records', function () {
    $holder = User::factory()->create();
    $holder->deposit(10000);
    $wallet = $holder->wallet;

    $balanceBefore = $wallet->balanceInt;
    $walletsBefore = Wallet::query()->count();
    $transactionsBefore = Transaction::query()->count();
    $transfersBefore = Transfer::query()->count();

    $readModel = app(TreasuryAllocationReadModelContract::class)->read(
        new TreasuryAllocationReadModelQueryData('allocation:001', 'PHP', 'inventory:001')
    );

    expect($readModel->hasTreasuryFacts)->toBeFalse()
        ->and($wallet->fresh()->balanceInt)->toBe($balanceBefore)
        ->and(Wallet::query()->count())->toBe($walletsBefore)
        ->and(Transaction::query()->count())->toBe($transactionsBefore)
        ->and(Transfer::query()->count())->toBe($transfersBefore);
});
