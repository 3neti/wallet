<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Treasury\Data;

use Spatie\LaravelData\Data;

final class TreasuryInventoryReadModelData extends Data
{
    public function __construct(
        public readonly string $walletReference,
        public readonly string $currency,
        public readonly int $walletBalanceMinor,
        public readonly int $eligibleInventoryMinor,
        public readonly int $allocatedAmountMinor,
        public readonly int $drawnAmountMinor,
        public readonly int $releasedAmountMinor,
        public readonly int $outstandingAmountMinor,
        public readonly int $usableAmountMinor,
        public readonly bool $hasTreasuryFacts,
        public readonly ?string $inventoryReference = null,
        public readonly ?string $allocationReference = null,
        public readonly array $metadata = [],
    ) {}
}
