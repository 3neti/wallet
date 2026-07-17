<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Treasury\Data;

use Spatie\LaravelData\Data;

final class TreasuryAllocationReadModelData extends Data
{
    /**
     * @param  list<TreasurySliceReadModelData>  $slices
     */
    public function __construct(
        public readonly string $allocationReference,
        public readonly string $currency,
        public readonly int $allocatedAmountMinor,
        public readonly int $drawnAmountMinor,
        public readonly int $releasedAmountMinor,
        public readonly int $outstandingAmountMinor,
        public readonly int $usableAmountMinor,
        public readonly int $sliceCount,
        public readonly bool $hasTreasuryFacts,
        public readonly ?string $inventoryReference = null,
        public readonly array $slices = [],
        public readonly array $metadata = [],
    ) {}
}
