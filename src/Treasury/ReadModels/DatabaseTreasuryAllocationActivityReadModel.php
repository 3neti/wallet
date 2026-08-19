<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Treasury\ReadModels;

use LBHurtado\Wallet\Treasury\Contracts\TreasuryAllocationActivityReadModelContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationActivityItemData;
use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationActivityReadModelData;
use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationActivityReadModelQueryData;
use LBHurtado\Wallet\Treasury\Models\TreasuryAllocation;
use LBHurtado\Wallet\Treasury\Models\TreasuryAllocationOperation;

final class DatabaseTreasuryAllocationActivityReadModel implements TreasuryAllocationActivityReadModelContract
{
    public function read(
        TreasuryAllocationActivityReadModelQueryData $query,
    ): TreasuryAllocationActivityReadModelData {
        $allocation = TreasuryAllocation::query()
            ->select(['id'])
            ->where('allocation_reference', $query->allocationReference)
            ->where('currency', strtoupper($query->currency))
            ->first();
        $page = max(1, $query->page);
        $perPage = min(100, max(1, $query->perPage));

        if (! $allocation instanceof TreasuryAllocation) {
            return new TreasuryAllocationActivityReadModelData(
                hasTreasuryFacts: false,
                movements: [],
                currentPage: $page,
                perPage: $perPage,
                total: 0,
                lastPage: 1,
            );
        }

        $operations = TreasuryAllocationOperation::query()
            ->select([
                'id',
                'operation_type',
                'amount_minor',
                'currency',
                'balance_before_minor',
                'balance_after_minor',
                'effective_at',
            ])
            ->whereBelongsTo($allocation, 'allocation')
            ->where('status', 'committed')
            ->orderByDesc('effective_at')
            ->orderByDesc('id')
            ->paginate(perPage: $perPage, page: $page);

        return new TreasuryAllocationActivityReadModelData(
            hasTreasuryFacts: true,
            movements: collect($operations->items())
                ->map(static fn (TreasuryAllocationOperation $operation): TreasuryAllocationActivityItemData => new TreasuryAllocationActivityItemData(
                    type: $operation->operation_type->value,
                    amountMinor: $operation->amount_minor,
                    currency: $operation->currency,
                    balanceBeforeMinor: $operation->balance_before_minor,
                    balanceAfterMinor: $operation->balance_after_minor,
                    effectiveAt: $operation->effective_at->utc()->toIso8601String(),
                ))
                ->values()
                ->all(),
            currentPage: $operations->currentPage(),
            perPage: $operations->perPage(),
            total: $operations->total(),
            lastPage: $operations->lastPage(),
        );
    }
}
