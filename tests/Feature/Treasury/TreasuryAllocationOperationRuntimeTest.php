<?php

declare(strict_types=1);

use Bavix\Wallet\Models\Transfer;
use Bavix\Wallet\Models\Wallet;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\Wallet\Tests\Models\User;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryAllocationActivityReadModelContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryAllocationOperationContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryAllocationReadModelContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionOperationContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionProvisioningContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationActivationData;
use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationActivityReadModelQueryData;
use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationMovementData;
use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationReadModelQueryData;
use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationReleaseRequestData;
use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationReversalRequestData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionDefinitionData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionReservationData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryAllocationOperationType;
use LBHurtado\Wallet\Treasury\Enums\TreasuryCustodyMode;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\Wallet\Treasury\Exceptions\TreasuryAllocationConflict;
use LBHurtado\Wallet\Treasury\Exceptions\TreasuryImmutableOperation;
use LBHurtado\Wallet\Treasury\Exceptions\TreasuryInvariantViolation;
use LBHurtado\Wallet\Treasury\Models\TreasuryAllocation;
use LBHurtado\Wallet\Treasury\Models\TreasuryAllocationOperation;
use LBHurtado\Wallet\Treasury\Models\TreasuryPosition;
use LBHurtado\Wallet\Treasury\Models\TreasuryPositionOperation;

it('activates one Allocation from one exact principal Reservation and replays it', function () {
    $fixture = durableAllocationFixture();
    $runtime = app(TreasuryAllocationOperationContract::class);

    $first = $runtime->activate($fixture['activation']);
    $replay = $runtime->activate($fixture['activation']);

    expect($replay->toArray())->toBe($first->toArray())
        ->and($first->operationType)->toBe(TreasuryAllocationOperationType::Activation)
        ->and($first->balanceBeforeMinor)->toBe(0)
        ->and($first->balanceAfterMinor)->toBe(10_000)
        ->and(TreasuryAllocation::query()->count())->toBe(1)
        ->and(TreasuryAllocationOperation::query()->count())->toBe(1)
        ->and(Transfer::query()->count())->toBe($fixture['transfers_after_reservation']);

    $conflicting = new TreasuryAllocationActivationData(
        operationReference: $fixture['activation']->operationReference,
        allocationReference: $fixture['activation']->allocationReference,
        backingReservationOperationReference: $fixture['activation']->backingReservationOperationReference,
        initialAmountMinor: $fixture['activation']->initialAmountMinor,
        maximumAmountMinor: 30_000,
        currency: $fixture['activation']->currency,
        replenishable: $fixture['activation']->replenishable,
        idempotencyKey: $fixture['activation']->idempotencyKey,
        externalReference: $fixture['activation']->externalReference,
        metadata: $fixture['activation']->metadata,
    );

    expect(fn () => $runtime->activate($conflicting))
        ->toThrow(TreasuryAllocationConflict::class, 'different input');
});

it('rejects duplicate or mismatched Reservation backing without creating an Allocation', function () {
    $fixture = durableAllocationFixture();
    $runtime = app(TreasuryAllocationOperationContract::class);
    $runtime->activate($fixture['activation']);

    $second = new TreasuryAllocationActivationData(
        operationReference: 'allocation:transit:002:activation',
        allocationReference: 'allocation:transit:002',
        backingReservationOperationReference: $fixture['activation']->backingReservationOperationReference,
        initialAmountMinor: 10_000,
        maximumAmountMinor: 20_000,
        currency: 'PHP',
        replenishable: true,
        idempotencyKey: 'allocation:transit:002:activation:key',
        externalReference: 'facility:transit:002',
    );

    expect(fn () => $runtime->activate($second))
        ->toThrow(TreasuryAllocationConflict::class, 'already claimed')
        ->and(fn () => $runtime->activate(new TreasuryAllocationActivationData(
            operationReference: 'allocation:transit:003:activation',
            allocationReference: 'allocation:transit:003',
            backingReservationOperationReference: $fixture['activation']->backingReservationOperationReference,
            initialAmountMinor: 9_999,
            maximumAmountMinor: 20_000,
            currency: 'PHP',
            replenishable: true,
            idempotencyKey: 'allocation:transit:003:activation:key',
            externalReference: 'facility:transit:003',
        )))->toThrow(TreasuryInvariantViolation::class, 'exactly match')
        ->and(TreasuryAllocation::query()->count())->toBe(1);
});

it('draws atomically to an eligible Client Funds Position and exposes conserved facts', function () {
    $fixture = durableAllocationFixture();
    $runtime = app(TreasuryAllocationOperationContract::class);
    $runtime->activate($fixture['activation']);
    $draw = durableAllocationMovement(
        operationReference: 'allocation:transit:001:draw:001',
        idempotencyKey: 'allocation:transit:001:draw:001:key',
        counterparty: $fixture['counterparty'],
        amountMinor: 2_500,
    );
    $transfersBefore = Transfer::query()->count();

    $first = $runtime->draw($draw);
    $replay = $runtime->draw($draw);
    $read = app(TreasuryAllocationReadModelContract::class)->read(
        new TreasuryAllocationReadModelQueryData('allocation:transit:001', 'PHP'),
    );

    expect($replay->toArray())->toBe($first->toArray())
        ->and($first->balanceAfterMinor)->toBe(7_500)
        ->and(Transfer::query()->count())->toBe($transfersBefore + 1)
        ->and(positionBalance($fixture['reserve']))->toBe(7_500)
        ->and(positionBalance($fixture['counterparty']))->toBe(2_500)
        ->and($read->hasTreasuryFacts)->toBeTrue()
        ->and($read->allocatedAmountMinor)->toBe(10_000)
        ->and($read->drawnAmountMinor)->toBe(2_500)
        ->and($read->releasedAmountMinor)->toBe(0)
        ->and($read->usableAmountMinor)->toBe(7_500)
        ->and($read->allocatedAmountMinor)->toBe(
            $read->drawnAmountMinor + $read->releasedAmountMinor + $read->usableAmountMinor,
        )
        ->and($read->toArray())->not->toHaveKeys([
            'reserve_position_id',
            'release_position_id',
            'internal_ledger_id',
        ]);
});

it('rolls back an overdraw without any partial transfer or operation', function () {
    $fixture = durableAllocationFixture();
    $runtime = app(TreasuryAllocationOperationContract::class);
    $runtime->activate($fixture['activation']);
    $transfersBefore = Transfer::query()->count();
    $positionOperationsBefore = TreasuryPositionOperation::query()->count();

    expect(fn () => $runtime->draw(durableAllocationMovement(
        operationReference: 'allocation:transit:001:draw:over',
        idempotencyKey: 'allocation:transit:001:draw:over:key',
        counterparty: $fixture['counterparty'],
        amountMinor: 10_001,
    )))->toThrow(TreasuryInvariantViolation::class, 'balance limits')
        ->and(TreasuryAllocation::query()->sole()->balance_minor)->toBe(10_000)
        ->and(TreasuryAllocationOperation::query()->count())->toBe(1)
        ->and(TreasuryPositionOperation::query()->count())->toBe($positionOperationsBefore)
        ->and(Transfer::query()->count())->toBe($transfersBefore)
        ->and(positionBalance($fixture['counterparty']))->toBe(0);
});

it('replenishes within policy and releases the exact remainder to the Reservation source', function () {
    $fixture = durableAllocationFixture();
    $runtime = app(TreasuryAllocationOperationContract::class);
    $runtime->activate($fixture['activation']);
    $runtime->draw(durableAllocationMovement(
        operationReference: 'allocation:transit:001:draw:001',
        idempotencyKey: 'allocation:transit:001:draw:001:key',
        counterparty: $fixture['counterparty'],
        amountMinor: 2_500,
    ));

    Wallet::query()->findOrFail($fixture['counterparty']->internal_ledger_id)->deposit(5_000);
    $replenishment = durableAllocationMovement(
        operationReference: 'allocation:transit:001:replenish:001',
        idempotencyKey: 'allocation:transit:001:replenish:001:key',
        counterparty: $fixture['counterparty'],
        amountMinor: 5_000,
    );
    $runtime->replenish($replenishment);
    $release = $runtime->release(new TreasuryAllocationReleaseRequestData(
        operationReference: 'allocation:transit:001:release',
        allocationReference: 'allocation:transit:001',
        currency: 'PHP',
        idempotencyKey: 'allocation:transit:001:release:key',
        externalReference: 'facility:transit:001:closed',
    ));
    $read = app(TreasuryAllocationReadModelContract::class)->read(
        new TreasuryAllocationReadModelQueryData('allocation:transit:001', 'PHP'),
    );

    expect($release->amountMinor)->toBe(12_500)
        ->and($release->balanceAfterMinor)->toBe(0)
        ->and(TreasuryAllocation::query()->sole()->status)->toBe('released')
        ->and(positionBalance($fixture['reserve']))->toBe(0)
        ->and(positionBalance($fixture['release']))->toBe(32_500)
        ->and($read->allocatedAmountMinor)->toBe(15_000)
        ->and($read->drawnAmountMinor)->toBe(2_500)
        ->and($read->releasedAmountMinor)->toBe(12_500)
        ->and($read->usableAmountMinor)->toBe(0);
});

it('presents paginated Allocation activity without ledger authority references', function () {
    $fixture = durableAllocationFixture();
    $runtime = app(TreasuryAllocationOperationContract::class);
    $runtime->activate($fixture['activation']);
    $runtime->draw(durableAllocationMovement(
        operationReference: 'allocation:transit:001:draw:001',
        idempotencyKey: 'allocation:transit:001:draw:001:key',
        counterparty: $fixture['counterparty'],
        amountMinor: 2_500,
    ));
    Wallet::query()->findOrFail($fixture['counterparty']->internal_ledger_id)->deposit(1_000);
    $runtime->replenish(durableAllocationMovement(
        operationReference: 'allocation:transit:001:replenish:001',
        idempotencyKey: 'allocation:transit:001:replenish:001:key',
        counterparty: $fixture['counterparty'],
        amountMinor: 1_000,
    ));
    $runtime->reverse(new TreasuryAllocationReversalRequestData(
        operationReference: 'allocation:transit:001:reversal:001',
        allocationReference: 'allocation:transit:001',
        reversesOperationReference: 'allocation:transit:001:draw:001',
        currency: 'PHP',
        idempotencyKey: 'allocation:transit:001:reversal:001:key',
        externalReference: 'fare:001:reversed',
    ));
    $runtime->release(new TreasuryAllocationReleaseRequestData(
        operationReference: 'allocation:transit:001:release',
        allocationReference: 'allocation:transit:001',
        currency: 'PHP',
        idempotencyKey: 'allocation:transit:001:release:key',
        externalReference: 'facility:transit:001:closed',
    ));

    $firstPage = app(TreasuryAllocationActivityReadModelContract::class)->read(
        new TreasuryAllocationActivityReadModelQueryData(
            allocationReference: 'allocation:transit:001',
            currency: 'PHP',
            page: 1,
            perPage: 2,
        ),
    );
    $secondPage = app(TreasuryAllocationActivityReadModelContract::class)->read(
        new TreasuryAllocationActivityReadModelQueryData(
            allocationReference: 'allocation:transit:001',
            currency: 'PHP',
            page: 2,
            perPage: 2,
        ),
    );

    expect($firstPage->hasTreasuryFacts)->toBeTrue()
        ->and($firstPage->total)->toBe(5)
        ->and($firstPage->lastPage)->toBe(3)
        ->and(array_column($firstPage->movements, 'type'))->toBe(['release', 'reversal'])
        ->and(array_column($secondPage->movements, 'type'))->toBe(['replenishment', 'draw'])
        ->and($firstPage->toArray())->not->toHaveKeys([
            'allocationReference',
            'positionReference',
            'operationReference',
            'idempotencyKey',
            'metadata',
        ])
        ->and($firstPage->movements[0]->toArray())->toBe([
            'type' => 'release',
            'amountMinor' => 11_000,
            'currency' => 'PHP',
            'balanceBeforeMinor' => 11_000,
            'balanceAfterMinor' => 0,
            'effectiveAt' => $firstPage->movements[0]->effectiveAt,
        ]);
});

it('reverses one draw exactly once and preserves immutable operation evidence', function () {
    $fixture = durableAllocationFixture();
    $runtime = app(TreasuryAllocationOperationContract::class);
    $runtime->activate($fixture['activation']);
    $runtime->draw(durableAllocationMovement(
        operationReference: 'allocation:transit:001:draw:001',
        idempotencyKey: 'allocation:transit:001:draw:001:key',
        counterparty: $fixture['counterparty'],
        amountMinor: 2_500,
    ));
    $reversal = new TreasuryAllocationReversalRequestData(
        operationReference: 'allocation:transit:001:reversal:001',
        allocationReference: 'allocation:transit:001',
        reversesOperationReference: 'allocation:transit:001:draw:001',
        currency: 'PHP',
        idempotencyKey: 'allocation:transit:001:reversal:001:key',
        externalReference: 'fare:001:reversed',
    );

    $first = $runtime->reverse($reversal);
    $replay = $runtime->reverse($reversal);

    expect($replay->toArray())->toBe($first->toArray())
        ->and($first->balanceAfterMinor)->toBe(10_000)
        ->and(positionBalance($fixture['reserve']))->toBe(10_000)
        ->and(positionBalance($fixture['counterparty']))->toBe(0)
        ->and(fn () => $runtime->reverse(new TreasuryAllocationReversalRequestData(
            operationReference: 'allocation:transit:001:reversal:002',
            allocationReference: $reversal->allocationReference,
            reversesOperationReference: $reversal->reversesOperationReference,
            currency: $reversal->currency,
            idempotencyKey: 'allocation:transit:001:reversal:002:key',
            externalReference: $reversal->externalReference,
            metadata: $reversal->metadata,
        )))->toThrow(TreasuryAllocationConflict::class, 'already been reversed');

    $operation = TreasuryAllocationOperation::query()
        ->where('operation_reference', 'allocation:transit:001:draw:001')
        ->sole();

    expect(fn () => $operation->update(['status' => 'changed']))
        ->toThrow(TreasuryImmutableOperation::class)
        ->and(fn () => $operation->delete())
        ->toThrow(TreasuryImmutableOperation::class);
});

it('reverses replenishment only while the restored balance remains valid', function () {
    $fixture = durableAllocationFixture();
    $runtime = app(TreasuryAllocationOperationContract::class);
    $runtime->activate($fixture['activation']);
    Wallet::query()->findOrFail($fixture['counterparty']->internal_ledger_id)->deposit(5_000);
    $runtime->replenish(durableAllocationMovement(
        operationReference: 'allocation:transit:001:replenish:001',
        idempotencyKey: 'allocation:transit:001:replenish:001:key',
        counterparty: $fixture['counterparty'],
        amountMinor: 5_000,
    ));

    $reversal = $runtime->reverse(new TreasuryAllocationReversalRequestData(
        operationReference: 'allocation:transit:001:reversal:replenish',
        allocationReference: 'allocation:transit:001',
        reversesOperationReference: 'allocation:transit:001:replenish:001',
        currency: 'PHP',
        idempotencyKey: 'allocation:transit:001:reversal:replenish:key',
        externalReference: 'top-up:001:reversed',
    ));
    $read = app(TreasuryAllocationReadModelContract::class)->read(
        new TreasuryAllocationReadModelQueryData('allocation:transit:001', 'PHP'),
    );

    expect($reversal->balanceBeforeMinor)->toBe(15_000)
        ->and($reversal->balanceAfterMinor)->toBe(10_000)
        ->and(positionBalance($fixture['reserve']))->toBe(10_000)
        ->and(positionBalance($fixture['counterparty']))->toBe(5_000)
        ->and($read->allocatedAmountMinor)->toBe(10_000)
        ->and($read->usableAmountMinor)->toBe(10_000);
});

it('sanitizes metadata and rejects incompatible destinations', function () {
    $fixture = durableAllocationFixture();
    $runtime = app(TreasuryAllocationOperationContract::class);
    $runtime->activate($fixture['activation']);
    $other = User::factory()->create();
    $incompatible = provisionAllocationPosition(
        principal: $other,
        reference: 'position:other:client',
        principalReference: 'principal:other',
        purpose: TreasuryPositionPurpose::ClientFunds,
        provider: 'other-provider',
        resourceReference: 'resource:other:primary:php',
    );

    expect(fn () => $runtime->draw(durableAllocationMovement(
        operationReference: 'allocation:transit:001:draw:other',
        idempotencyKey: 'allocation:transit:001:draw:other:key',
        counterparty: $incompatible,
        amountMinor: 100,
    )))->toThrow(TreasuryInvariantViolation::class, 'share one provider')
        ->and(TreasuryAllocationOperation::query()->count())->toBe(1);

    $draw = durableAllocationMovement(
        operationReference: 'allocation:transit:001:draw:safe',
        idempotencyKey: 'allocation:transit:001:draw:safe:key',
        counterparty: $fixture['counterparty'],
        amountMinor: 100,
        metadata: ['pay_code' => 'SECRET', 'safe_reference' => 'fare:100'],
    );
    $runtime->draw($draw);
    $metadata = TreasuryAllocationOperation::query()
        ->where('operation_reference', $draw->operationReference)
        ->value('metadata');

    expect($metadata)->not->toHaveKey('pay_code')
        ->and($metadata)->toMatchArray(['safe_reference' => 'fare:100']);
});

it('keeps non-replenishable and zero-balance release policies fail closed', function () {
    $fixture = durableAllocationFixture(replenishable: false, maximumAmountMinor: 10_000);
    $runtime = app(TreasuryAllocationOperationContract::class);
    $runtime->activate($fixture['activation']);
    Wallet::query()->findOrFail($fixture['counterparty']->internal_ledger_id)->deposit(1_000);
    $transfersBefore = Transfer::query()->count();

    expect(fn () => $runtime->replenish(durableAllocationMovement(
        operationReference: 'allocation:transit:001:replenish:blocked',
        idempotencyKey: 'allocation:transit:001:replenish:blocked:key',
        counterparty: $fixture['counterparty'],
        amountMinor: 1_000,
    )))->toThrow(TreasuryInvariantViolation::class, 'not replenishable')
        ->and(Transfer::query()->count())->toBe($transfersBefore);

    $runtime->draw(durableAllocationMovement(
        operationReference: 'allocation:transit:001:draw:all',
        idempotencyKey: 'allocation:transit:001:draw:all:key',
        counterparty: $fixture['counterparty'],
        amountMinor: 10_000,
    ));
    $transfersBeforeRelease = Transfer::query()->count();
    $release = $runtime->release(new TreasuryAllocationReleaseRequestData(
        operationReference: 'allocation:transit:001:release:empty',
        allocationReference: 'allocation:transit:001',
        currency: 'PHP',
        idempotencyKey: 'allocation:transit:001:release:empty:key',
        externalReference: 'facility:transit:001:depleted',
    ));

    expect($release->amountMinor)->toBe(0)
        ->and($release->balanceAfterMinor)->toBe(0)
        ->and(Transfer::query()->count())->toBe($transfersBeforeRelease)
        ->and(TreasuryAllocation::query()->sole()->status)->toBe('released');
});

/**
 * @return array{
 *   activation: TreasuryAllocationActivationData,
 *   release: TreasuryPosition,
 *   reserve: TreasuryPosition,
 *   counterparty: TreasuryPosition,
 *   transfers_after_reservation: int
 * }
 */
function durableAllocationFixture(
    bool $replenishable = true,
    int $maximumAmountMinor = 20_000,
): array {
    $issuer = User::factory()->create();
    $merchant = User::factory()->create();
    $release = provisionAllocationPosition(
        principal: $issuer,
        reference: 'position:issuer:client',
        principalReference: 'principal:issuer',
        purpose: TreasuryPositionPurpose::ClientFunds,
    );
    $reserve = provisionAllocationPosition(
        principal: $issuer,
        reference: 'position:issuer:reserve',
        principalReference: 'principal:issuer',
        purpose: TreasuryPositionPurpose::PayCodeReserve,
    );
    $counterparty = provisionAllocationPosition(
        principal: $merchant,
        reference: 'position:merchant:client',
        principalReference: 'principal:merchant',
        purpose: TreasuryPositionPurpose::ClientFunds,
    );
    Wallet::query()->findOrFail($release->internal_ledger_id)->deposit(30_000);
    app(TreasuryPositionOperationContract::class)->reserve(new TreasuryPositionReservationData(
        operationReference: 'reservation:transit:001',
        sourcePositionReference: $release->position_reference,
        destinationPositionReference: $reserve->position_reference,
        amountMinor: 10_000,
        currency: 'PHP',
        idempotencyKey: 'reservation:transit:001:key',
        externalReference: 'facility:transit:001',
    ));

    return [
        'activation' => new TreasuryAllocationActivationData(
            operationReference: 'allocation:transit:001:activation',
            allocationReference: 'allocation:transit:001',
            backingReservationOperationReference: 'reservation:transit:001',
            initialAmountMinor: 10_000,
            maximumAmountMinor: $maximumAmountMinor,
            currency: 'PHP',
            replenishable: $replenishable,
            idempotencyKey: 'allocation:transit:001:activation:key',
            externalReference: 'facility:transit:001',
            metadata: ['purpose' => 'reusable_balance'],
        ),
        'release' => $release,
        'reserve' => $reserve,
        'counterparty' => $counterparty,
        'transfers_after_reservation' => Transfer::query()->count(),
    ];
}

/**
 * @param  array<string, mixed>  $metadata
 */
function durableAllocationMovement(
    string $operationReference,
    string $idempotencyKey,
    TreasuryPosition $counterparty,
    int $amountMinor,
    array $metadata = [],
): TreasuryAllocationMovementData {
    return new TreasuryAllocationMovementData(
        operationReference: $operationReference,
        allocationReference: 'allocation:transit:001',
        counterpartyPositionReference: $counterparty->position_reference,
        amountMinor: $amountMinor,
        currency: 'PHP',
        idempotencyKey: $idempotencyKey,
        externalReference: str_replace(':key', '', $idempotencyKey),
        metadata: $metadata,
    );
}

function provisionAllocationPosition(
    Model $principal,
    string $reference,
    string $principalReference,
    TreasuryPositionPurpose $purpose,
    string $provider = 'netbank',
    string $resourceReference = 'resource:netbank:primary:php',
): TreasuryPosition {
    $position = app(TreasuryPositionProvisioningContract::class)->provision(
        $principal,
        new TreasuryPositionDefinitionData(
            positionReference: $reference,
            principalReference: $principalReference,
            mandateReference: 'mandate:'.$principalReference,
            settlementResourceReference: $resourceReference,
            settlementResourceType: 'provider_deposit_account',
            provider: $provider,
            connectionReference: 'primary',
            currency: 'PHP',
            decimalPlaces: 2,
            purpose: $purpose,
            custodyMode: TreasuryCustodyMode::PooledInternal,
            legalProfile: 'treasury-settlement-ph-v1',
            legalProfileVersion: '2026-07-24.1',
            idempotencyKey: 'registration:'.$reference,
            reconciliationReference: 'reconciliation:'.$provider.':primary',
        ),
    );

    return TreasuryPosition::query()
        ->where('position_reference', $position->positionReference)
        ->sole();
}

function positionBalance(TreasuryPosition $position): int
{
    return Wallet::query()
        ->findOrFail($position->internal_ledger_id)
        ->getBalanceIntAttribute();
}
