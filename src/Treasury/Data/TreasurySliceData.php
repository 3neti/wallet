<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Treasury\Data;

use Spatie\LaravelData\Data;

final class TreasurySliceData extends Data
{
    public function __construct(
        public readonly string $sliceReference,
        public readonly string $allocationReference,
        public readonly int $amountMinor,
        public readonly string $currency,
        public readonly string $status,
        public readonly string $idempotencyKey,
        public readonly ?string $externalReference = null,
        public readonly array $metadata = [],
    ) {}
}
