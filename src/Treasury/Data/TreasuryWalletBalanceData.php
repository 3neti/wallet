<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Treasury\Data;

use Spatie\LaravelData\Data;

final class TreasuryWalletBalanceData extends Data
{
    public function __construct(
        public readonly string $walletReference,
        public readonly string $currency,
        public readonly int $walletBalanceMinor,
        public readonly array $metadata = [],
    ) {}
}
