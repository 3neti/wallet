<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Treasury\Data;

use Spatie\LaravelData\Data;

final class TreasuryAllocationActivityItemData extends Data
{
    public function __construct(
        public readonly string $type,
        public readonly int $amountMinor,
        public readonly string $currency,
        public readonly int $balanceBeforeMinor,
        public readonly int $balanceAfterMinor,
        public readonly string $effectiveAt,
    ) {}
}
