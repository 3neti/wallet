<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Treasury\ReadModels;

use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryReadModelContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryReadModelData;
use LBHurtado\Wallet\Treasury\Data\TreasuryWalletBalanceData;

final class WalletBalanceInventoryReadModelService implements TreasuryInventoryReadModelContract
{
    public function read(TreasuryWalletBalanceData $walletBalance): TreasuryInventoryReadModelData
    {
        $eligibleInventoryMinor = max(0, $walletBalance->walletBalanceMinor);

        return new TreasuryInventoryReadModelData(
            walletReference: $walletBalance->walletReference,
            currency: $walletBalance->currency,
            walletBalanceMinor: $walletBalance->walletBalanceMinor,
            eligibleInventoryMinor: $eligibleInventoryMinor,
            allocatedAmountMinor: 0,
            drawnAmountMinor: 0,
            releasedAmountMinor: 0,
            outstandingAmountMinor: 0,
            usableAmountMinor: $eligibleInventoryMinor,
            hasTreasuryFacts: false,
            inventoryReference: null,
            allocationReference: null,
            metadata: [
                ...$walletBalance->metadata,
                'treasury_read_model' => 'wallet-baseline',
                'treasury_read_model_status' => 'read-only',
                'treasury_facts' => 'absent',
            ],
        );
    }
}
