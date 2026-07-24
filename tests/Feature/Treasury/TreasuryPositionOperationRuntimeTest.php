<?php

declare(strict_types=1);

use Bavix\Wallet\Models\Transaction;
use Bavix\Wallet\Models\Transfer;
use Bavix\Wallet\Models\Wallet;
use LBHurtado\Wallet\Tests\Models\User;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionOperationContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionProvisioningContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionAllocationData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionDefinitionData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionRecognitionData;
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
        ->and(Transaction::query()->count())->toBe($transactionsBefore + 3)
        ->and(Transfer::query()->count())->toBe($transfersBefore + 1);
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
function treasuryOperationPositions(): array
{
    $system = User::factory()->create();
    $client = User::factory()->create();
    $runtime = app(TreasuryPositionProvisioningContract::class);
    $clearingData = $runtime->provision(
        $system,
        treasuryOperationPositionDefinition(
            principal: $system,
            purpose: TreasuryPositionPurpose::TreasuryClearing,
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
