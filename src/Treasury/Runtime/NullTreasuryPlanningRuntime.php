<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Treasury\Runtime;

use LBHurtado\Wallet\Treasury\Contracts\TreasuryPlanningContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationData;
use LBHurtado\Wallet\Treasury\Data\TreasuryDrawData;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryData;
use LBHurtado\Wallet\Treasury\Data\TreasuryReleaseData;
use LBHurtado\Wallet\Treasury\Data\TreasuryRepaymentData;
use LBHurtado\Wallet\Treasury\Data\TreasuryReversalData;
use LBHurtado\Wallet\Treasury\Data\TreasurySliceData;

final class NullTreasuryPlanningRuntime implements TreasuryPlanningContract
{
    public const STATUS = 'null-runtime-planned';

    public const RUNTIME = 'null';

    public const RUNTIME_STATUS = 'planned';

    public function planInventory(TreasuryInventoryData $inventory): TreasuryInventoryData
    {
        return new TreasuryInventoryData(
            inventoryReference: $inventory->inventoryReference,
            resourceType: $inventory->resourceType,
            currency: $inventory->currency,
            capacityMinor: $inventory->capacityMinor,
            status: self::STATUS,
            idempotencyKey: $inventory->idempotencyKey,
            externalReference: $inventory->externalReference,
            metadata: $this->metadata($inventory->metadata, 'inventory'),
        );
    }

    public function planAllocation(TreasuryAllocationData $allocation): TreasuryAllocationData
    {
        return new TreasuryAllocationData(
            allocationReference: $allocation->allocationReference,
            inventoryReference: $allocation->inventoryReference,
            amountMinor: $allocation->amountMinor,
            currency: $allocation->currency,
            status: self::STATUS,
            idempotencyKey: $allocation->idempotencyKey,
            externalReference: $allocation->externalReference,
            metadata: $this->metadata($allocation->metadata, 'allocation'),
        );
    }

    public function planSlice(TreasurySliceData $slice): TreasurySliceData
    {
        return new TreasurySliceData(
            sliceReference: $slice->sliceReference,
            allocationReference: $slice->allocationReference,
            amountMinor: $slice->amountMinor,
            currency: $slice->currency,
            status: self::STATUS,
            idempotencyKey: $slice->idempotencyKey,
            externalReference: $slice->externalReference,
            metadata: $this->metadata($slice->metadata, 'slice'),
        );
    }

    public function planDraw(TreasuryDrawData $draw): TreasuryDrawData
    {
        return new TreasuryDrawData(
            operationReference: $draw->operationReference,
            allocationReference: $draw->allocationReference,
            amountMinor: $draw->amountMinor,
            currency: $draw->currency,
            status: self::STATUS,
            idempotencyKey: $draw->idempotencyKey,
            sliceReference: $draw->sliceReference,
            metadata: $this->metadata($draw->metadata, 'draw'),
        );
    }

    public function planRelease(TreasuryReleaseData $release): TreasuryReleaseData
    {
        return new TreasuryReleaseData(
            operationReference: $release->operationReference,
            allocationReference: $release->allocationReference,
            amountMinor: $release->amountMinor,
            currency: $release->currency,
            status: self::STATUS,
            idempotencyKey: $release->idempotencyKey,
            sliceReference: $release->sliceReference,
            metadata: $this->metadata($release->metadata, 'release'),
        );
    }

    public function planRepayment(TreasuryRepaymentData $repayment): TreasuryRepaymentData
    {
        return new TreasuryRepaymentData(
            operationReference: $repayment->operationReference,
            allocationReference: $repayment->allocationReference,
            amountMinor: $repayment->amountMinor,
            currency: $repayment->currency,
            status: self::STATUS,
            idempotencyKey: $repayment->idempotencyKey,
            sliceReference: $repayment->sliceReference,
            drawReference: $repayment->drawReference,
            metadata: $this->metadata($repayment->metadata, 'repayment'),
        );
    }

    public function planReversal(TreasuryReversalData $reversal): TreasuryReversalData
    {
        return new TreasuryReversalData(
            operationReference: $reversal->operationReference,
            reversesOperationReference: $reversal->reversesOperationReference,
            allocationReference: $reversal->allocationReference,
            amountMinor: $reversal->amountMinor,
            currency: $reversal->currency,
            status: self::STATUS,
            idempotencyKey: $reversal->idempotencyKey,
            sliceReference: $reversal->sliceReference,
            metadata: $this->metadata($reversal->metadata, 'reversal'),
        );
    }

    private function metadata(array $metadata, string $operation): array
    {
        return array_merge($metadata, [
            'treasury_runtime' => self::RUNTIME,
            'treasury_runtime_status' => self::RUNTIME_STATUS,
            'treasury_operation' => $operation,
        ]);
    }
}
