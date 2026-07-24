<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Treasury\Enums;

enum TreasuryPositionPurpose: string
{
    case TreasuryClearing = 'treasury_clearing';
    case ClientFunds = 'client_funds';
    case PayCodeReserve = 'pay_code_reserve';
    case LegacyUnattributed = 'legacy_unattributed';

    public function label(): string
    {
        return match ($this) {
            self::TreasuryClearing => 'Treasury Clearing Position',
            self::ClientFunds => 'Client Funds Position',
            self::PayCodeReserve => 'Pay Code Reserve Position',
            self::LegacyUnattributed => 'Legacy Unattributed Position',
        };
    }
}
