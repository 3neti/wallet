<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Treasury\Contracts;

use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationActivityReadModelData;
use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationActivityReadModelQueryData;

interface TreasuryAllocationActivityReadModelContract
{
    public function read(
        TreasuryAllocationActivityReadModelQueryData $query,
    ): TreasuryAllocationActivityReadModelData;
}
