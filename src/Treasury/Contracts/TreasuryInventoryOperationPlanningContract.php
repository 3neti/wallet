<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Treasury\Contracts;

use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryAdjustmentData;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryReclassificationData;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryRecognitionData;
use LBHurtado\Wallet\Treasury\Data\TreasuryOperationReversalData;

/**
 * Planning boundary only. Implementations must not imply that a plan changed
 * Inventory, moved money, or persisted Treasury state.
 */
interface TreasuryInventoryOperationPlanningContract
{
    public function planRecognition(TreasuryInventoryRecognitionData $recognition): TreasuryInventoryRecognitionData;

    public function planReclassification(TreasuryInventoryReclassificationData $reclassification): TreasuryInventoryReclassificationData;

    public function planAdjustment(TreasuryInventoryAdjustmentData $adjustment): TreasuryInventoryAdjustmentData;

    public function planReversal(TreasuryOperationReversalData $reversal): TreasuryOperationReversalData;
}
