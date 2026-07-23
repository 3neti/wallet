<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Treasury\Contracts;

use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryPositionData;

interface TreasuryInventoryPositionReadModelContract
{
    public function find(string $inventoryReference): ?TreasuryInventoryPositionData;
}
