<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Treasury\Data;

use Spatie\LaravelData\Data;

final class TreasuryAllocationActivityReadModelData extends Data
{
    /**
     * @param  list<TreasuryAllocationActivityItemData>  $movements
     */
    public function __construct(
        public readonly bool $hasTreasuryFacts,
        public readonly array $movements,
        public readonly int $currentPage,
        public readonly int $perPage,
        public readonly int $total,
        public readonly int $lastPage,
    ) {}
}
