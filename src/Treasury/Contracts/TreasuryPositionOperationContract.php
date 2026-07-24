<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Treasury\Contracts;

use LBHurtado\Wallet\Treasury\Data\TreasuryPositionAllocationData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionDerecognitionData;
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

    public function reserve(
        TreasuryPositionReservationData $reservation,
    ): TreasuryPositionReservationData;

    public function release(
        TreasuryPositionReleaseData $release,
    ): TreasuryPositionReleaseData;

    public function derecognize(
        TreasuryPositionDerecognitionData $derecognition,
    ): TreasuryPositionDerecognitionData;
}
