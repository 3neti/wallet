<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Treasury\ReadModels;

use LBHurtado\Wallet\Treasury\Contracts\TreasuryAllocationReadModelContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationReadModelData;
use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationReadModelQueryData;

final class AbsentTreasuryAllocationReadModelService implements TreasuryAllocationReadModelContract
{
    public function read(TreasuryAllocationReadModelQueryData $query): TreasuryAllocationReadModelData
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
                'treasury_read_model' => 'allocation-slice-planning',
                'treasury_read_model_status' => 'read-only',
                'treasury_facts' => 'absent',
            ],
        );
    }
}
