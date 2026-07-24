<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Treasury\Data;

use LBHurtado\Wallet\Treasury\Enums\TreasuryCustodyMode;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use Spatie\LaravelData\Data;

final class TreasuryPositionDefinitionData extends Data
{
    /**
     * @param  array<string, scalar|array<array-key, scalar|null>|null>  $metadata
     */
    public function __construct(
        public readonly string $positionReference,
        public readonly string $principalReference,
        public readonly string $mandateReference,
        public readonly string $settlementResourceReference,
        public readonly string $settlementResourceType,
        public readonly string $provider,
        public readonly string $connectionReference,
        public readonly string $currency,
        public readonly int $decimalPlaces,
        public readonly TreasuryPositionPurpose $purpose,
        public readonly TreasuryCustodyMode $custodyMode,
        public readonly string $legalProfile,
        public readonly string $legalProfileVersion,
        public readonly string $idempotencyKey,
        public readonly ?string $reconciliationReference = null,
        public readonly array $metadata = [],
    ) {}
}
