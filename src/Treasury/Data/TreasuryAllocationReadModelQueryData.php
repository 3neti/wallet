<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Treasury\Data;

use Spatie\LaravelData\Data;

final class TreasuryAllocationReadModelQueryData extends Data
{
    public function __construct(
        public readonly string $allocationReference,
        public readonly string $currency,
        public readonly ?string $inventoryReference = null,
        public readonly array $metadata = [],
    ) {}
}
