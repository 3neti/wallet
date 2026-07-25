<?php

declare(strict_types=1);

use Bavix\Wallet\Models\Transaction;
use Bavix\Wallet\Models\Transfer;
use Bavix\Wallet\Models\Wallet;
use LBHurtado\Wallet\Tests\Models\User;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionOperationContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionProvisioningContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionReadModelContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionAllocationData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionDefinitionData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionDerecognitionData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionRecognitionData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionReleaseData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionReservationData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryCustodyMode;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\Wallet\Treasury\Exceptions\TreasuryInvariantViolation;
use LBHurtado\Wallet\Treasury\Exceptions\TreasuryOperationConflict;
use LBHurtado\Wallet\Treasury\Models\TreasuryPosition;
use LBHurtado\Wallet\Treasury\Models\TreasuryPositionOperation;

it('recognizes provider funds into clearing and allocates them once to client funds', function () {
    [$clearing, $client] = treasuryOperationPositions();
    $runtime = app(TreasuryPositionOperationContract::class);
    $transactionsBefore = Transaction::query()->count();
    $transfersBefore = Transfer::query()->count();
    $recognition = new TreasuryPositionRecognitionData(
        operationReference: 'position-recognition:provider:txn-123',
        destinationPositionReference: $clearing->position_reference,
        amountMinor: 2_000_000_00,
        currency: 'PHP',
        idempotencyKey: 'position-recognition-key:provider:txn-123',
        externalReference: 'netbank:txn-123',
        metadata: ['evidence_reference' => 'provider-observation:123'],
    );
    $allocation = new TreasuryPositionAllocationData(
        operationReference: 'position-allocation:provider:txn-123',
        sourcePositionReference: $clearing->position_reference,
        destinationPositionReference: $client->position_reference,
        amountMinor: 2_000_000_00,
        currency: 'PHP',
        idempotencyKey: 'position-allocation-key:provider:txn-123',
        externalReference: $recognition->operationReference,
        metadata: ['allocation_reason' => 'verified_account_funding'],
    );

    $firstRecognition = $runtime->recognize($recognition);
    $firstAllocation = $runtime->allocate($allocation);
    $secondRecognition = $runtime->recognize($recognition);
    $secondAllocation = $runtime->allocate($allocation);
    $clearing->refresh();
    $client->refresh();
    $clearingLedger = Wallet::query()
        ->findOrFail($clearing->internal_ledger_id);
    $clientLedger = Wallet::query()
        ->findOrFail($client->internal_ledger_id);

    expect($firstRecognition->destinationTransactionId)->not->toBeNull()
        ->and($secondRecognition->toArray())->toBe($firstRecognition->toArray())
        ->and($firstAllocation->destinationTransactionId)->not->toBeNull()
        ->and($secondAllocation->toArray())->toBe($firstAllocation->toArray())
        ->and($clearingLedger->getBalanceIntAttribute())->toBe(0)
        ->and($clientLedger->getBalanceIntAttribute())->toBe(2_000_000_00)
        ->and(TreasuryPositionOperation::query()->count())->toBe(2)
        ->and(app(TreasuryPositionReadModelContract::class)
            ->operationExists($firstRecognition->operationReference))->toBeTrue()
        ->and(app(TreasuryPositionReadModelContract::class)
            ->operationExists('position-operation:missing'))->toBeFalse()
        ->and(Transaction::query()->count())->toBe($transactionsBefore + 3)
        ->and(Transfer::query()->count())->toBe($transfersBefore + 1);
});

it('recognizes an opening balance into unattributed funds and allocates it to client funds', function () {
    [$unattributed, $client] = treasuryOperationPositions(
        TreasuryPositionPurpose::LegacyUnattributed,
    );
    $runtime = app(TreasuryPositionOperationContract::class);
    $recognition = $runtime->recognize(new TreasuryPositionRecognitionData(
        operationReference: 'opening-position-recognition:provider:snapshot-1',
        destinationPositionReference: $unattributed->position_reference,
        amountMinor: 1_000_000_00,
        currency: 'PHP',
        idempotencyKey: 'opening-position-recognition-key:provider:snapshot-1',
        externalReference: 'provider-balance:snapshot-1',
    ));
    $allocation = $runtime->allocate(new TreasuryPositionAllocationData(
        operationReference: 'legacy-position-allocation:provider:account-1',
        sourcePositionReference: $unattributed->position_reference,
        destinationPositionReference: $client->position_reference,
        amountMinor: 250_000_00,
        currency: 'PHP',
        idempotencyKey: 'legacy-position-allocation-key:provider:account-1',
        externalReference: $recognition->operationReference,
    ));
    $unattributedLedger = Wallet::query()->findOrFail($unattributed->internal_ledger_id);
    $clientLedger = Wallet::query()->findOrFail($client->internal_ledger_id);

    expect($allocation->transferUuid)->not->toBeNull()
        ->and($unattributedLedger->getBalanceIntAttribute())->toBe(750_000_00)
        ->and($clientLedger->getBalanceIntAttribute())->toBe(250_000_00);
});

it('reserves releases and derecognizes Pay Code funds exactly once', function () {
    [$clearing, $client] = treasuryOperationPositions();
    $runtime = app(TreasuryPositionOperationContract::class);
    $clientOwner = $client->principal;
    $reserveData = app(TreasuryPositionProvisioningContract::class)->provision(
        $clientOwner,
        treasuryOperationPositionDefinition(
            principal: $clientOwner,
            purpose: TreasuryPositionPurpose::PayCodeReserve,
        ),
    );
    $reserve = TreasuryPosition::query()
        ->where('position_reference', $reserveData->positionReference)
        ->sole();
    $runtime->recognize(new TreasuryPositionRecognitionData(
        operationReference: 'position-recognition:provider:pay-code-funds',
        destinationPositionReference: $clearing->position_reference,
        amountMinor: 50_00,
        currency: 'PHP',
        idempotencyKey: 'position-recognition-key:provider:pay-code-funds',
        externalReference: 'netbank:pay-code-funds',
    ));
    $runtime->allocate(new TreasuryPositionAllocationData(
        operationReference: 'position-allocation:provider:pay-code-funds',
        sourcePositionReference: $clearing->position_reference,
        destinationPositionReference: $client->position_reference,
        amountMinor: 50_00,
        currency: 'PHP',
        idempotencyKey: 'position-allocation-key:provider:pay-code-funds',
        externalReference: 'position-recognition:provider:pay-code-funds',
    ));
    $reservation = new TreasuryPositionReservationData(
        operationReference: 'position-reservation:pay-code:TEST-0001',
        sourcePositionReference: $client->position_reference,
        destinationPositionReference: $reserve->position_reference,
        amountMinor: 12_50,
        currency: 'PHP',
        idempotencyKey: 'position-reservation-key:pay-code:TEST-0001',
        externalReference: 'pay-code:TEST-0001',
    );
    $release = new TreasuryPositionReleaseData(
        operationReference: 'position-release:pay-code:TEST-0001:partial',
        sourcePositionReference: $reserve->position_reference,
        destinationPositionReference: $client->position_reference,
        amountMinor: 2_50,
        currency: 'PHP',
        idempotencyKey: 'position-release-key:pay-code:TEST-0001:partial',
        externalReference: $reservation->operationReference,
    );
    $derecognition = new TreasuryPositionDerecognitionData(
        operationReference: 'position-derecognition:pay-code:TEST-0001',
        sourcePositionReference: $reserve->position_reference,
        amountMinor: 10_00,
        currency: 'PHP',
        idempotencyKey: 'position-derecognition-key:pay-code:TEST-0001',
        externalReference: 'netbank:payout:123',
    );

    $firstReservation = $runtime->reserve($reservation);
    $secondReservation = $runtime->reserve($reservation);
    $firstRelease = $runtime->release($release);
    $secondRelease = $runtime->release($release);
    $firstDerecognition = $runtime->derecognize($derecognition);
    $secondDerecognition = $runtime->derecognize($derecognition);
    $clientLedger = Wallet::query()->findOrFail($client->internal_ledger_id);
    $reserveLedger = Wallet::query()->findOrFail($reserve->internal_ledger_id);

    expect($secondReservation->toArray())->toBe($firstReservation->toArray())
        ->and($secondRelease->toArray())->toBe($firstRelease->toArray())
        ->and($secondDerecognition->toArray())->toBe($firstDerecognition->toArray())
        ->and($clientLedger->getBalanceIntAttribute())->toBe(40_00)
        ->and($reserveLedger->getBalanceIntAttribute())->toBe(0)
        ->and(TreasuryPositionOperation::query()->count())->toBe(5);
});

it('derecognizes legacy unattributed funds without permitting client funds write-downs', function () {
    [$unattributed, $client] = treasuryOperationPositions(
        TreasuryPositionPurpose::LegacyUnattributed,
    );
    $runtime = app(TreasuryPositionOperationContract::class);
    $runtime->recognize(new TreasuryPositionRecognitionData(
        operationReference: 'opening-position-recognition:provider:snapshot-write-down',
        destinationPositionReference: $unattributed->position_reference,
        amountMinor: 100_00,
        currency: 'PHP',
        idempotencyKey: 'opening-position-recognition-key:provider:snapshot-write-down',
        externalReference: 'provider-balance:snapshot-write-down',
    ));
    $derecognition = new TreasuryPositionDerecognitionData(
        operationReference: 'position-derecognition:legacy-unattributed:provider-outflow',
        sourcePositionReference: $unattributed->position_reference,
        amountMinor: 45_00,
        currency: 'PHP',
        idempotencyKey: 'position-derecognition-key:legacy-unattributed:provider-outflow',
        externalReference: 'netbank:provider-outflow',
    );

    $first = $runtime->derecognize($derecognition);
    $second = $runtime->derecognize($derecognition);
    $unattributedLedger = Wallet::query()
        ->findOrFail($unattributed->internal_ledger_id);

    expect($second->toArray())->toBe($first->toArray())
        ->and($unattributedLedger->getBalanceIntAttribute())->toBe(55_00)
        ->and(fn () => $runtime->derecognize(
            new TreasuryPositionDerecognitionData(
                operationReference: 'position-derecognition:client-funds:forbidden',
                sourcePositionReference: $client->position_reference,
                amountMinor: 1_00,
                currency: 'PHP',
                idempotencyKey: 'position-derecognition-key:client-funds:forbidden',
                externalReference: 'operator:forbidden',
            ),
        ))->toThrow(
            TreasuryInvariantViolation::class,
            'not eligible for this operation',
        );
});

it('rejects allocations across provider connections', function () {
    [$clearing] = treasuryOperationPositions();
    $clientOwner = User::factory()->create();
    $client = app(TreasuryPositionProvisioningContract::class)->provision(
        $clientOwner,
        treasuryOperationPositionDefinition(
            principal: $clientOwner,
            purpose: TreasuryPositionPurpose::ClientFunds,
            provider: 'future_emi',
            connection: 'future-primary',
            resource: 'resource:future:primary:php',
        ),
    );
    $runtime = app(TreasuryPositionOperationContract::class);
    $runtime->recognize(new TreasuryPositionRecognitionData(
        operationReference: 'position-recognition:provider:txn-456',
        destinationPositionReference: $clearing->position_reference,
        amountMinor: 100_00,
        currency: 'PHP',
        idempotencyKey: 'position-recognition-key:provider:txn-456',
        externalReference: 'netbank:txn-456',
    ));

    expect(fn () => $runtime->allocate(new TreasuryPositionAllocationData(
        operationReference: 'position-allocation:provider:txn-456',
        sourcePositionReference: $clearing->position_reference,
        destinationPositionReference: $client->positionReference,
        amountMinor: 100_00,
        currency: 'PHP',
        idempotencyKey: 'position-allocation-key:provider:txn-456',
        externalReference: 'position-recognition:provider:txn-456',
    )))->toThrow(
        TreasuryInvariantViolation::class,
        'must share one provider connection and Settlement Resource',
    );
});

it('rejects idempotency reuse with changed monetary input', function () {
    [$clearing] = treasuryOperationPositions();
    $runtime = app(TreasuryPositionOperationContract::class);
    $runtime->recognize(new TreasuryPositionRecognitionData(
        operationReference: 'position-recognition:provider:txn-789',
        destinationPositionReference: $clearing->position_reference,
        amountMinor: 100_00,
        currency: 'PHP',
        idempotencyKey: 'position-recognition-key:provider:txn-789',
        externalReference: 'netbank:txn-789',
    ));

    expect(fn () => $runtime->recognize(new TreasuryPositionRecognitionData(
        operationReference: 'position-recognition:provider:txn-789',
        destinationPositionReference: $clearing->position_reference,
        amountMinor: 101_00,
        currency: 'PHP',
        idempotencyKey: 'position-recognition-key:provider:txn-789',
        externalReference: 'netbank:txn-789',
    )))->toThrow(TreasuryOperationConflict::class, 'different input');
});

/**
 * @return array{TreasuryPosition, TreasuryPosition}
 */
function treasuryOperationPositions(
    TreasuryPositionPurpose $sourcePurpose = TreasuryPositionPurpose::TreasuryClearing,
): array {
    $system = User::factory()->create();
    $client = User::factory()->create();
    $runtime = app(TreasuryPositionProvisioningContract::class);
    $clearingData = $runtime->provision(
        $system,
        treasuryOperationPositionDefinition(
            principal: $system,
            purpose: $sourcePurpose,
        ),
    );
    $clientData = $runtime->provision(
        $client,
        treasuryOperationPositionDefinition(
            principal: $client,
            purpose: TreasuryPositionPurpose::ClientFunds,
        ),
    );

    return [
        TreasuryPosition::query()
            ->where('position_reference', $clearingData->positionReference)
            ->sole(),
        TreasuryPosition::query()
            ->where('position_reference', $clientData->positionReference)
            ->sole(),
    ];
}

function treasuryOperationPositionDefinition(
    User $principal,
    TreasuryPositionPurpose $purpose,
    string $provider = 'netbank',
    string $connection = 'primary',
    string $resource = 'resource:netbank:primary:php',
): TreasuryPositionDefinitionData {
    $scope = hash('sha256', implode('|', [
        $principal->getKey(),
        $provider,
        $connection,
        $purpose->value,
    ]));
    $positionReference = 'position:operation-test:'.substr($scope, 0, 32);

    return new TreasuryPositionDefinitionData(
        positionReference: $positionReference,
        principalReference: 'principal:user:'.$principal->getKey(),
        mandateReference: 'mandate:operation-test:'.substr($scope, 0, 32),
        settlementResourceReference: $resource,
        settlementResourceType: 'provider_deposit_account',
        provider: $provider,
        connectionReference: $connection,
        currency: 'PHP',
        decimalPlaces: 2,
        purpose: $purpose,
        custodyMode: TreasuryCustodyMode::ProviderProjection,
        legalProfile: 'treasury-settlement-ph-v1',
        legalProfileVersion: '2026-07-24.1',
        idempotencyKey: 'position-registration:operation-test:'.substr($scope, 0, 32),
        reconciliationReference: "reconciliation:{$provider}:{$connection}",
    );
}
