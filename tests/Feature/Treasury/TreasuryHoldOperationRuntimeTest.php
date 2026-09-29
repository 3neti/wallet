<?php

declare(strict_types=1);

use Bavix\Wallet\Models\Wallet;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\Wallet\Tests\Models\User;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryHoldOperationContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionProvisioningContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryHoldConsumptionData;
use LBHurtado\Wallet\Treasury\Data\TreasuryHoldPlacementData;
use LBHurtado\Wallet\Treasury\Data\TreasuryHoldReleaseData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionDefinitionData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryAllocationOperationType;
use LBHurtado\Wallet\Treasury\Enums\TreasuryCustodyMode;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\Wallet\Treasury\Models\TreasuryAllocation;
use LBHurtado\Wallet\Treasury\Models\TreasuryPosition;

it('places consumes and releases an order-bound hold without another wallet', function (): void {
    $owner = User::factory()->create();
    $clientFunds = holdPosition($owner, 'hold:client', TreasuryPositionPurpose::ClientFunds);
    $reserve = holdPosition($owner, 'hold:reserve', TreasuryPositionPurpose::PayCodeReserve);
    Wallet::query()->findOrFail($clientFunds->internal_ledger_id)->deposit(10_000);
    $walletCount = Wallet::query()->count();
    $runtime = app(TreasuryHoldOperationContract::class);

    $placed = $runtime->place(new TreasuryHoldPlacementData(
        operationReference: 'hold:order-1:place',
        holdReference: 'hold:order-1',
        sourcePositionReference: $clientFunds->position_reference,
        heldPositionReference: $reserve->position_reference,
        amountMinor: 5_000,
        currency: 'PHP',
        idempotencyKey: 'hold:order-1:place:key',
        externalReference: 'order:1',
    ));
    $replayed = $runtime->place(new TreasuryHoldPlacementData(
        operationReference: 'hold:order-1:place',
        holdReference: 'hold:order-1',
        sourcePositionReference: $clientFunds->position_reference,
        heldPositionReference: $reserve->position_reference,
        amountMinor: 5_000,
        currency: 'PHP',
        idempotencyKey: 'hold:order-1:place:key',
        externalReference: 'order:1',
    ));

    expect($placed->operationType)->toBe(TreasuryAllocationOperationType::Activation)
        ->and($replayed->operationReference)->toBe($placed->operationReference)
        ->and(Wallet::query()->count())->toBe($walletCount)
        ->and(Wallet::query()->findOrFail($clientFunds->internal_ledger_id)->balanceInt)->toBe(5_000)
        ->and(Wallet::query()->findOrFail($reserve->internal_ledger_id)->balanceInt)->toBe(5_000);

    $consumed = $runtime->consume(new TreasuryHoldConsumptionData(
        operationReference: 'hold:order-1:consume',
        holdReference: 'hold:order-1',
        destinationPositionReference: $clientFunds->position_reference,
        amountMinor: 3_000,
        currency: 'PHP',
        idempotencyKey: 'hold:order-1:consume:key',
        externalReference: 'order:1',
    ));

    expect($consumed->operationType)->toBe(TreasuryAllocationOperationType::Draw)
        ->and($consumed->balanceAfterMinor)->toBe(2_000);

    $released = $runtime->release(new TreasuryHoldReleaseData(
        operationReference: 'hold:order-1:release',
        holdReference: 'hold:order-1',
        currency: 'PHP',
        idempotencyKey: 'hold:order-1:release:key',
        externalReference: 'order:1',
    ));

    expect($released->operationType)->toBe(TreasuryAllocationOperationType::Release)
        ->and($released->amountMinor)->toBe(2_000)
        ->and(TreasuryAllocation::query()->where('allocation_reference', 'hold:order-1')->value('status'))
        ->toBe('released')
        ->and(Wallet::query()->findOrFail($clientFunds->internal_ledger_id)->balanceInt)->toBe(10_000)
        ->and(Wallet::query()->findOrFail($reserve->internal_ledger_id)->balanceInt)->toBe(0);
});

function holdPosition(
    Model $owner,
    string $reference,
    TreasuryPositionPurpose $purpose,
): TreasuryPosition {
    $position = app(TreasuryPositionProvisioningContract::class)->provision(
        $owner,
        new TreasuryPositionDefinitionData(
            positionReference: $reference,
            principalReference: 'principal:hold-owner',
            mandateReference: 'mandate:hold-owner',
            settlementResourceReference: 'resource:hold-primary',
            settlementResourceType: 'provider_deposit_account',
            provider: 'netbank',
            connectionReference: 'netbank-primary',
            currency: 'PHP',
            decimalPlaces: 2,
            purpose: $purpose,
            custodyMode: TreasuryCustodyMode::PooledInternal,
            legalProfile: 'treasury-settlement-ph-v1',
            legalProfileVersion: '2026-07-24.1',
            idempotencyKey: 'registration:'.$reference,
            reconciliationReference: 'reconciliation:netbank:primary',
            metadata: [],
        ),
    );

    return TreasuryPosition::query()
        ->where('position_reference', $position->positionReference)
        ->sole();
}
