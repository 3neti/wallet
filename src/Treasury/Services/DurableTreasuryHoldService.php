<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Treasury\Services;

use Illuminate\Support\Facades\DB;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryAllocationOperationContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryHoldOperationContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionOperationContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationActivationData;
use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationMovementData;
use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationOperationData;
use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationReleaseRequestData;
use LBHurtado\Wallet\Treasury\Data\TreasuryHoldConsumptionData;
use LBHurtado\Wallet\Treasury\Data\TreasuryHoldPlacementData;
use LBHurtado\Wallet\Treasury\Data\TreasuryHoldReleaseData;
use LBHurtado\Wallet\Treasury\Data\TreasuryHoldReplenishmentData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionReservationData;

final readonly class DurableTreasuryHoldService implements TreasuryHoldOperationContract
{
    public function __construct(
        private TreasuryPositionOperationContract $positions,
        private TreasuryAllocationOperationContract $allocations,
    ) {}

    public function place(TreasuryHoldPlacementData $placement): TreasuryAllocationOperationData
    {
        return DB::transaction(function () use ($placement): TreasuryAllocationOperationData {
            $reservationReference = $placement->operationReference.':reservation';
            $metadata = [
                ...$placement->metadata,
                'treasury_hold_reference' => $placement->holdReference,
                'treasury_hold_kind' => 'instruction_funding',
            ];

            $this->positions->reserve(new TreasuryPositionReservationData(
                operationReference: $reservationReference,
                sourcePositionReference: $placement->sourcePositionReference,
                destinationPositionReference: $placement->heldPositionReference,
                amountMinor: $placement->amountMinor,
                currency: $placement->currency,
                idempotencyKey: $placement->idempotencyKey.':reservation',
                externalReference: $placement->externalReference,
                metadata: $metadata,
            ));

            return $this->allocations->activate(new TreasuryAllocationActivationData(
                operationReference: $placement->operationReference,
                allocationReference: $placement->holdReference,
                backingReservationOperationReference: $reservationReference,
                initialAmountMinor: $placement->amountMinor,
                maximumAmountMinor: $placement->maximumAmountMinor ?? $placement->amountMinor,
                currency: $placement->currency,
                replenishable: $placement->replenishable,
                idempotencyKey: $placement->idempotencyKey,
                externalReference: $placement->externalReference,
                metadata: $metadata,
            ));
        }, attempts: 5);
    }

    public function replenish(TreasuryHoldReplenishmentData $replenishment): TreasuryAllocationOperationData
    {
        return $this->allocations->replenish(new TreasuryAllocationMovementData(
            operationReference: $replenishment->operationReference,
            allocationReference: $replenishment->holdReference,
            counterpartyPositionReference: $replenishment->sourcePositionReference,
            amountMinor: $replenishment->amountMinor,
            currency: $replenishment->currency,
            idempotencyKey: $replenishment->idempotencyKey,
            externalReference: $replenishment->externalReference,
            metadata: [
                ...$replenishment->metadata,
                'treasury_hold_reference' => $replenishment->holdReference,
                'treasury_hold_action' => 'replenish',
            ],
        ));
    }

    public function consume(TreasuryHoldConsumptionData $consumption): TreasuryAllocationOperationData
    {
        return $this->allocations->draw(new TreasuryAllocationMovementData(
            operationReference: $consumption->operationReference,
            allocationReference: $consumption->holdReference,
            counterpartyPositionReference: $consumption->destinationPositionReference,
            amountMinor: $consumption->amountMinor,
            currency: $consumption->currency,
            idempotencyKey: $consumption->idempotencyKey,
            externalReference: $consumption->externalReference,
            metadata: [
                ...$consumption->metadata,
                'treasury_hold_reference' => $consumption->holdReference,
                'treasury_hold_action' => 'consume',
            ],
        ));
    }

    public function release(TreasuryHoldReleaseData $release): TreasuryAllocationOperationData
    {
        return $this->allocations->release(new TreasuryAllocationReleaseRequestData(
            operationReference: $release->operationReference,
            allocationReference: $release->holdReference,
            currency: $release->currency,
            idempotencyKey: $release->idempotencyKey,
            externalReference: $release->externalReference,
            metadata: [
                ...$release->metadata,
                'treasury_hold_reference' => $release->holdReference,
                'treasury_hold_action' => 'release',
            ],
        ));
    }
}
