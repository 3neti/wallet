<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Treasury\Data;

use Spatie\LaravelData\Data;

final class TreasuryPositionDerecognitionData extends Data
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $operationReference,
        public readonly string $sourcePositionReference,
        public readonly int $amountMinor,
        public readonly string $currency,
        public readonly string $idempotencyKey,
        public readonly string $externalReference,
        public readonly ?int $sourceTransactionId = null,
        public readonly ?string $sourceTransactionUuid = null,
        public readonly array $metadata = [],
    ) {}
}
