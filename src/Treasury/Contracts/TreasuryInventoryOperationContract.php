<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Treasury\Contracts;

use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryAdjustmentData;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryData;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryReclassificationData;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryRecognitionData;
use LBHurtado\Wallet\Treasury\Data\TreasuryOperationReversalData;

interface TreasuryInventoryOperationContract
{
    public function registerInventory(TreasuryInventoryData $inventory): TreasuryInventoryData;

    public function recognize(TreasuryInventoryRecognitionData $recognition): TreasuryInventoryRecognitionData;

    public function reclassify(TreasuryInventoryReclassificationData $reclassification): TreasuryInventoryReclassificationData;

    public function adjust(TreasuryInventoryAdjustmentData $adjustment): TreasuryInventoryAdjustmentData;

    public function reverse(TreasuryOperationReversalData $reversal): TreasuryOperationReversalData;
}
