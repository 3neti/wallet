<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Treasury\Contracts;

use LBHurtado\Wallet\Treasury\Data\TreasuryPositionData;

interface TreasuryPositionReadModelContract
{
    public function find(string $positionReference): ?TreasuryPositionData;

    /**
     * @return list<TreasuryPositionData>
     */
    public function forPrincipal(string $principalReference): array;

    /**
     * @return list<TreasuryPositionData>
     */
    public function forConnection(
        string $provider,
        string $connectionReference,
        string $currency,
    ): array;

    public function operationExists(string $operationReference): bool;
}
