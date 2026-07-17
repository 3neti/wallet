<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Treasury\Adapters\Bavix;

use Bavix\Wallet\Models\Wallet;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryReadModelContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryReadModelData;
use LBHurtado\Wallet\Treasury\Data\TreasuryWalletBalanceData;

final class BavixInventoryReadModelService
{
    public function __construct(
        private readonly TreasuryInventoryReadModelContract $readModel,
    ) {}

    public function read(Wallet $wallet): TreasuryInventoryReadModelData
    {
        return $this->readModel->read(new TreasuryWalletBalanceData(
            walletReference: $wallet->uuid,
            currency: $wallet->currency,
            walletBalanceMinor: $wallet->balanceInt,
        ));
    }
}
