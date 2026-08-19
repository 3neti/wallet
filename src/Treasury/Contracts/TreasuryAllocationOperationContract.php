<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Treasury\Contracts;

use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationActivationData;
use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationMovementData;
use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationOperationData;
use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationReleaseRequestData;
use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationReversalRequestData;

interface TreasuryAllocationOperationContract
{
    public function activate(TreasuryAllocationActivationData $activation): TreasuryAllocationOperationData;

    public function draw(TreasuryAllocationMovementData $draw): TreasuryAllocationOperationData;

    public function replenish(TreasuryAllocationMovementData $replenishment): TreasuryAllocationOperationData;

    public function release(TreasuryAllocationReleaseRequestData $release): TreasuryAllocationOperationData;

    public function reverse(TreasuryAllocationReversalRequestData $reversal): TreasuryAllocationOperationData;
}
