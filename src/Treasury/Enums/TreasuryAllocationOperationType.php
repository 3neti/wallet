<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Treasury\Enums;

enum TreasuryAllocationOperationType: string
{
    case Activation = 'activation';
    case Draw = 'draw';
    case Replenishment = 'replenishment';
    case Release = 'release';
    case Reversal = 'reversal';
}
