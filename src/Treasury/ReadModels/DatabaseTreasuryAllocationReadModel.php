<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Treasury\ReadModels;

use LBHurtado\Wallet\Treasury\Contracts\TreasuryAllocationReadModelContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationReadModelData;
use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationReadModelQueryData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryAllocationOperationType;
use LBHurtado\Wallet\Treasury\Models\TreasuryAllocation;
use LBHurtado\Wallet\Treasury\Models\TreasuryAllocationOperation;

final class DatabaseTreasuryAllocationReadModel implements TreasuryAllocationReadModelContract
{
    public function read(TreasuryAllocationReadModelQueryData $query): TreasuryAllocationReadModelData
    {
        $allocation = TreasuryAllocation::query()
            ->select([
                'id',
                'allocation_reference',
                'currency',
                'initial_amount_minor',
                'maximum_amount_minor',
                'balance_minor',
                'replenishable',
                'version',
                'status',
                'external_reference',
                'metadata',
            ])
            ->where('allocation_reference', $query->allocationReference)
            ->where('currency', strtoupper($query->currency))
            ->first();

        if ($allocation === null) {
            return $this->absent($query);
        }

        $operationCount = TreasuryAllocationOperation::query()
            ->whereBelongsTo($allocation, 'allocation')
            ->where('status', 'committed')
            ->count();
        $operationTotals = TreasuryAllocationOperation::query()
            ->from('treasury_allocation_operations as operations')
            ->leftJoin(
                'treasury_allocation_operations as reversals',
                'reversals.reverses_operation_id',
                '=',
                'operations.id',
            )
            ->where('operations.allocation_id', $allocation->getKey())
            ->where('operations.status', 'committed')
            ->whereNull('reversals.id')
            ->selectRaw('SUM(CASE WHEN operations.operation_type = ? THEN operations.amount_minor ELSE 0 END) as drawn_minor', [TreasuryAllocationOperationType::Draw->value])
            ->selectRaw('SUM(CASE WHEN operations.operation_type = ? THEN operations.amount_minor ELSE 0 END) as replenished_minor', [TreasuryAllocationOperationType::Replenishment->value])
            ->selectRaw('SUM(CASE WHEN operations.operation_type = ? THEN operations.amount_minor ELSE 0 END) as released_minor', [TreasuryAllocationOperationType::Release->value])
            ->first();
        $drawn = (int) ($operationTotals?->drawn_minor ?? 0);
        $replenished = (int) ($operationTotals?->replenished_minor ?? 0);
        $released = (int) ($operationTotals?->released_minor ?? 0);
        $allocated = $allocation->initial_amount_minor + $replenished;

        return new TreasuryAllocationReadModelData(
            allocationReference: $allocation->allocation_reference,
            currency: $allocation->currency,
            allocatedAmountMinor: $allocated,
            drawnAmountMinor: $drawn,
            releasedAmountMinor: $released,
            outstandingAmountMinor: $drawn,
            usableAmountMinor: $allocation->balance_minor,
            sliceCount: 0,
            hasTreasuryFacts: true,
            inventoryReference: $query->inventoryReference,
            slices: [],
            metadata: [
                ...($allocation->metadata ?? []),
                'treasury_facts' => 'present',
                'treasury_read_model' => 'durable-allocation',
                'allocation_status' => $allocation->status,
                'allocation_version' => $allocation->version,
                'maximum_amount_minor' => $allocation->maximum_amount_minor,
                'replenishable' => $allocation->replenishable,
                'operation_count' => $operationCount,
            ],
        );
    }

    private function absent(TreasuryAllocationReadModelQueryData $query): TreasuryAllocationReadModelData
    {
        return new TreasuryAllocationReadModelData(
            allocationReference: $query->allocationReference,
            currency: $query->currency,
            allocatedAmountMinor: 0,
            drawnAmountMinor: 0,
            releasedAmountMinor: 0,
            outstandingAmountMinor: 0,
            usableAmountMinor: 0,
            sliceCount: 0,
            hasTreasuryFacts: false,
            inventoryReference: $query->inventoryReference,
            slices: [],
            metadata: [
                ...$query->metadata,
                'treasury_facts' => 'absent',
                'treasury_read_model' => 'allocation-slice-planning',
                'treasury_read_model_status' => 'read-only',
            ],
        );
    }
}
