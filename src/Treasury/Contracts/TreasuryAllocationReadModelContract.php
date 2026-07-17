<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Treasury\Contracts;

use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationReadModelData;
use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationReadModelQueryData;

/**
 * Read-only boundary for future Allocation and Slice projections.
 */
interface TreasuryAllocationReadModelContract
{
    public function read(TreasuryAllocationReadModelQueryData $query): TreasuryAllocationReadModelData;
}
