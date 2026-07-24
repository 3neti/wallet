<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Treasury\Contracts;

use LBHurtado\Wallet\Treasury\Data\TreasuryPositionAllocationData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionRecognitionData;

interface TreasuryPositionOperationContract
{
    public function recognize(
        TreasuryPositionRecognitionData $recognition,
    ): TreasuryPositionRecognitionData;

    public function allocate(
        TreasuryPositionAllocationData $allocation,
    ): TreasuryPositionAllocationData;
}
