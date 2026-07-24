<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Treasury\Enums;

enum TreasuryPositionOperationType: string
{
    case Recognition = 'recognition';
    case Allocation = 'allocation';
}
