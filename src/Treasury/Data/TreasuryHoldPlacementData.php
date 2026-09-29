<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Treasury\Data;

use Spatie\LaravelData\Data;

final class TreasuryHoldPlacementData extends Data
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $operationReference,
        public readonly string $holdReference,
        public readonly string $sourcePositionReference,
        public readonly string $heldPositionReference,
        public readonly int $amountMinor,
        public readonly string $currency,
        public readonly string $idempotencyKey,
        public readonly string $externalReference,
        public readonly ?int $maximumAmountMinor = null,
        public readonly bool $replenishable = false,
        public readonly array $metadata = [],
    ) {}
}
