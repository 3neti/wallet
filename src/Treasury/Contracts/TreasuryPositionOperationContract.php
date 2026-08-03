<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Treasury\Contracts;

use LBHurtado\Wallet\Treasury\Data\TreasuryPositionAllocationData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionCommercialChargeData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionCommercialReversalData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionDerecognitionData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionPayableSettlementData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionRecognitionData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionReleaseData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionReservationData;

interface TreasuryPositionOperationContract
{
    public function recognize(
        TreasuryPositionRecognitionData $recognition,
    ): TreasuryPositionRecognitionData;

    public function allocate(
        TreasuryPositionAllocationData $allocation,
    ): TreasuryPositionAllocationData;

    public function charge(
        TreasuryPositionCommercialChargeData $charge,
    ): TreasuryPositionCommercialChargeData;

    public function reverseCommercialMovement(
        TreasuryPositionCommercialReversalData $reversal,
    ): TreasuryPositionCommercialReversalData;

    public function reserve(
        TreasuryPositionReservationData $reservation,
    ): TreasuryPositionReservationData;

    public function reserveAccountFunding(
        TreasuryPositionReservationData $reservation,
    ): TreasuryPositionReservationData;

    public function release(
        TreasuryPositionReleaseData $release,
    ): TreasuryPositionReleaseData;

    public function derecognize(
        TreasuryPositionDerecognitionData $derecognition,
    ): TreasuryPositionDerecognitionData;

    public function settlePayable(
        TreasuryPositionPayableSettlementData $settlement,
    ): TreasuryPositionPayableSettlementData;
}
