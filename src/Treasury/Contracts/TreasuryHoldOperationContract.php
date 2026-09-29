<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Treasury\Contracts;

use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationOperationData;
use LBHurtado\Wallet\Treasury\Data\TreasuryHoldConsumptionData;
use LBHurtado\Wallet\Treasury\Data\TreasuryHoldPlacementData;
use LBHurtado\Wallet\Treasury\Data\TreasuryHoldReleaseData;
use LBHurtado\Wallet\Treasury\Data\TreasuryHoldReplenishmentData;

interface TreasuryHoldOperationContract
{
    public function place(TreasuryHoldPlacementData $placement): TreasuryAllocationOperationData;

    public function replenish(TreasuryHoldReplenishmentData $replenishment): TreasuryAllocationOperationData;

    public function consume(TreasuryHoldConsumptionData $consumption): TreasuryAllocationOperationData;

    public function release(TreasuryHoldReleaseData $release): TreasuryAllocationOperationData;
}
