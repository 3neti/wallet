<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Treasury\Enums;

enum TreasuryPositionOperationType: string
{
    case Recognition = 'recognition';
    case Allocation = 'allocation';
    case Reservation = 'reservation';
    case Release = 'release';
    case PayoutRecoveryHold = 'payout_recovery_hold';
    case PayoutRecoveryRelease = 'payout_recovery_release';
    case Derecognition = 'derecognition';
    case CommercialCharge = 'commercial_charge';
    case CommercialReversal = 'commercial_reversal';
    case PayableSettlement = 'payable_settlement';
    case InternalPayableSettlement = 'internal_payable_settlement';
    case AllocationDraw = 'allocation_draw';
    case AllocationReplenishment = 'allocation_replenishment';
    case AllocationRelease = 'allocation_release';
    case AllocationReversal = 'allocation_reversal';
}
