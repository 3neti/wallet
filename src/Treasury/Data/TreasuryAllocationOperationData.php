<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Treasury\Data;

use LBHurtado\Wallet\Treasury\Enums\TreasuryAllocationOperationType;
use Spatie\LaravelData\Data;

final class TreasuryAllocationOperationData extends Data
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $operationReference,
        public readonly string $allocationReference,
        public readonly TreasuryAllocationOperationType $operationType,
        public readonly int $amountMinor,
        public readonly string $currency,
        public readonly int $balanceBeforeMinor,
        public readonly int $balanceAfterMinor,
        public readonly string $status,
        public readonly string $idempotencyKey,
        public readonly string $externalReference,
        public readonly ?string $sourcePositionReference = null,
        public readonly ?string $destinationPositionReference = null,
        public readonly ?string $reversesOperationReference = null,
        public readonly array $metadata = [],
    ) {}
}
