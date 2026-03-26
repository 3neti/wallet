<?php

namespace LBHurtado\Wallet\Services;

// TODO: change this to dynamic
use Bavix\Wallet\Interfaces\Wallet;
use LBHurtado\Wallet\Enums\WalletType;

class WalletProvisioningService
{
    public function createDefaultWalletsForUser(Wallet $user): void
    {
        foreach (WalletType::cases() as $type) {
            $user->getOrCreateWalletByType(
                $type->value,
                [
                    'name' => $type->label(),
                    'meta' => $type->defaultMeta(),
                ]
            );
        }
    }
}
