<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Treasury\Contracts;

use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationData;
use LBHurtado\Wallet\Treasury\Data\TreasuryDrawData;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryData;
use LBHurtado\Wallet\Treasury\Data\TreasuryReleaseData;
use LBHurtado\Wallet\Treasury\Data\TreasuryRepaymentData;
use LBHurtado\Wallet\Treasury\Data\TreasuryReversalData;
use LBHurtado\Wallet\Treasury\Data\TreasurySliceData;

/**
 * Planning boundary only. Implementations must not imply that a plan moved
 * money or persisted Treasury state.
 */
interface TreasuryPlanningContract
{
    public function planInventory(TreasuryInventoryData $inventory): TreasuryInventoryData;

    public function planAllocation(TreasuryAllocationData $allocation): TreasuryAllocationData;

    public function planSlice(TreasurySliceData $slice): TreasurySliceData;

    public function planDraw(TreasuryDrawData $draw): TreasuryDrawData;

    public function planRelease(TreasuryReleaseData $release): TreasuryReleaseData;

    public function planRepayment(TreasuryRepaymentData $repayment): TreasuryRepaymentData;

    public function planReversal(TreasuryReversalData $reversal): TreasuryReversalData;
}
