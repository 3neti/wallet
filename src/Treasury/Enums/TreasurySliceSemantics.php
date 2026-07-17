<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Treasury\Enums;

enum TreasurySliceSemantics: string
{
    case OPEN = 'open';
    case FIXED = 'fixed';
    case NAMED = 'named';
}
