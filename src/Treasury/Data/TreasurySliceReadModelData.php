<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Treasury\Data;

use LBHurtado\Wallet\Treasury\Enums\TreasurySliceSemantics;
use Spatie\LaravelData\Data;

final class TreasurySliceReadModelData extends Data
{
    public function __construct(
        public readonly string $sliceReference,
        public readonly string $allocationReference,
        public readonly TreasurySliceSemantics $semantics,
        public readonly string $currency,
        public readonly int $allocatedAmountMinor,
        public readonly int $drawnAmountMinor,
        public readonly int $releasedAmountMinor,
        public readonly int $outstandingAmountMinor,
        public readonly int $usableAmountMinor,
        public readonly bool $hasTreasuryFacts,
        public readonly ?string $name = null,
        public readonly array $metadata = [],
    ) {}
}
