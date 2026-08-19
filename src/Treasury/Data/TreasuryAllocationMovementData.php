<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Treasury\Data;

use Spatie\LaravelData\Data;

final class TreasuryAllocationMovementData extends Data
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $operationReference,
        public readonly string $allocationReference,
        public readonly string $counterpartyPositionReference,
        public readonly int $amountMinor,
        public readonly string $currency,
        public readonly string $idempotencyKey,
        public readonly string $externalReference,
        public readonly array $metadata = [],
    ) {}
}
