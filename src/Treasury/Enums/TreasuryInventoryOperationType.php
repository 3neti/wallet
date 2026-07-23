<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Treasury\Enums;

enum TreasuryInventoryOperationType: string
{
    case Recognition = 'recognition';
    case Reclassification = 'reclassification';
    case Adjustment = 'adjustment';
    case Reversal = 'reversal';
}
