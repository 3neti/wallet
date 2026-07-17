<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Treasury\Contracts;

use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryReadModelData;
use LBHurtado\Wallet\Treasury\Data\TreasuryWalletBalanceData;

/**
 * Read-only boundary for describing current wallet-backed Treasury inventory.
 */
interface TreasuryInventoryReadModelContract
{
    public function read(TreasuryWalletBalanceData $walletBalance): TreasuryInventoryReadModelData;
}
