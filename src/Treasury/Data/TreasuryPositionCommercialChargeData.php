<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Treasury\Data;

use Spatie\LaravelData\Data;

final class TreasuryPositionCommercialChargeData extends Data
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $operationReference,
        public readonly string $sourcePositionReference,
        public readonly string $destinationPositionReference,
        public readonly int $amountMinor,
        public readonly string $currency,
        public readonly string $idempotencyKey,
        public readonly string $externalReference,
        public readonly ?int $transferId = null,
        public readonly ?string $transferUuid = null,
        public readonly ?int $sourceTransactionId = null,
        public readonly ?string $sourceTransactionUuid = null,
        public readonly ?int $destinationTransactionId = null,
        public readonly ?string $destinationTransactionUuid = null,
        public readonly array $metadata = [],
    ) {}
}
