<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Treasury\Enums;

enum TreasuryCustodyMode: string
{
    case PooledInternal = 'pooled_internal';
    case DedicatedExternal = 'dedicated_external';
    case ProviderProjection = 'provider_projection';
}
