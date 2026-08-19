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
            ->with(['operations' => fn ($operations) => $operations
                ->select([
                    'id',
                    'allocation_id',
                    'operation_type',
                    'reverses_operation_id',
                    'amount_minor',
                ])
                ->orderBy('id')])
            ->where('allocation_reference', $query->allocationReference)
            ->where('currency', $query->currency)
            ->first();

        if ($allocation === null) {
            return $this->absent($query);
        }

        $reversed = $allocation->operations
            ->where('operation_type', TreasuryAllocationOperationType::Reversal)
            ->pluck('reverses_operation_id')
            ->filter()
            ->all();
        $effective = $allocation->operations
            ->reject(fn (TreasuryAllocationOperation $operation): bool => in_array(
                $operation->getKey(),
                $reversed,
                true,
            ));
        $drawn = $effective
            ->where('operation_type', TreasuryAllocationOperationType::Draw)
            ->sum('amount_minor');
        $replenished = $effective
            ->where('operation_type', TreasuryAllocationOperationType::Replenishment)
            ->sum('amount_minor');
        $released = $effective
            ->where('operation_type', TreasuryAllocationOperationType::Release)
            ->sum('amount_minor');
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
                'operation_count' => $allocation->operations->count(),
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
