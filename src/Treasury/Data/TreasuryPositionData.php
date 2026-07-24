<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Treasury\Data;

use LBHurtado\Wallet\Treasury\Enums\TreasuryCustodyMode;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use Spatie\LaravelData\Data;

final class TreasuryPositionData extends Data
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $positionReference,
        public readonly string $principalReference,
        public readonly string $mandateReference,
        public readonly string $settlementResourceReference,
        public readonly string $provider,
        public readonly string $connectionReference,
        public readonly string $currency,
        public readonly int $decimalPlaces,
        public readonly TreasuryPositionPurpose $purpose,
        public readonly TreasuryCustodyMode $custodyMode,
        public readonly string $legalProfile,
        public readonly string $legalProfileVersion,
        public readonly int $balanceMinor,
        public readonly string $status,
        public readonly ?string $reconciliationReference = null,
        public readonly array $metadata = [],
    ) {}
}
