<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Treasury\Data;

use Spatie\LaravelData\Data;

final class TreasuryInventoryPositionData extends Data
{
    public function __construct(
        public readonly string $inventoryReference,
        public readonly string $settlementResourceReference,
        public readonly string $resourceType,
        public readonly string $currency,
        public readonly string $status,
        public readonly int $balanceMinor,
        public readonly int $version,
        public readonly ?string $lastOperationReference,
        public readonly bool $hasTreasuryFacts,
        public readonly array $metadata = [],
    ) {}
}
