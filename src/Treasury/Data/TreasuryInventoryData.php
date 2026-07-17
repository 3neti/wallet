<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Treasury\Data;

use Spatie\LaravelData\Data;

final class TreasuryInventoryData extends Data
{
    public function __construct(
        public readonly string $inventoryReference,
        public readonly string $resourceType,
        public readonly string $currency,
        public readonly int $capacityMinor,
        public readonly string $status,
        public readonly string $idempotencyKey,
        public readonly ?string $externalReference = null,
        public readonly array $metadata = [],
    ) {}
}
