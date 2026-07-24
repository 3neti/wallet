<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Treasury\Data;

use Spatie\LaravelData\Data;

final class TreasuryPositionRecognitionData extends Data
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $operationReference,
        public readonly string $destinationPositionReference,
        public readonly int $amountMinor,
        public readonly string $currency,
        public readonly string $idempotencyKey,
        public readonly string $externalReference,
        public readonly ?int $destinationTransactionId = null,
        public readonly ?string $destinationTransactionUuid = null,
        public readonly array $metadata = [],
    ) {}
}
