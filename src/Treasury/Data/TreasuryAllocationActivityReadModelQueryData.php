<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Treasury\Data;

use Spatie\LaravelData\Data;

final class TreasuryAllocationActivityReadModelQueryData extends Data
{
    public function __construct(
        public readonly string $allocationReference,
        public readonly string $currency,
        public readonly int $page = 1,
        public readonly int $perPage = 25,
    ) {}
}
