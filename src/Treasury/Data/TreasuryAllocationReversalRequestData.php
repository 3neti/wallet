<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Treasury\Data;

use Spatie\LaravelData\Data;

final class TreasuryAllocationReversalRequestData extends Data
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $operationReference,
        public readonly string $allocationReference,
        public readonly string $reversesOperationReference,
        public readonly string $currency,
        public readonly string $idempotencyKey,
        public readonly string $externalReference,
        public readonly array $metadata = [],
    ) {}
}
