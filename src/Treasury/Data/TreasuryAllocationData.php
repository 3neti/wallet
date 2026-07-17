<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Treasury\Data;

use Spatie\LaravelData\Data;

final class TreasuryAllocationData extends Data
{
    public function __construct(
        public readonly string $allocationReference,
        public readonly string $inventoryReference,
        public readonly int $amountMinor,
        public readonly string $currency,
        public readonly string $status,
        public readonly string $idempotencyKey,
        public readonly ?string $externalReference = null,
        public readonly array $metadata = [],
    ) {}
}
