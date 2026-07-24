<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Treasury\Contracts;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionDefinitionData;

interface TreasuryPositionProvisioningContract
{
    public function provision(
        Model $principal,
        TreasuryPositionDefinitionData $definition,
    ): TreasuryPositionData;
}
