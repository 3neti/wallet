<?php

namespace LBHurtado\Wallet\Traits;

use Bavix\Wallet\Traits\HasWallets;

trait HasPlatformWallets
{
    use HasWallets;

    public function getWalletByType(string $type): ?\Bavix\Wallet\Models\Wallet
    {
        return $this->wallets()->where('slug', $type)->first();
    }

    public function getOrCreateWalletByType(string $type, array $attributes = []): \Bavix\Wallet\Models\Wallet
    {
        return $this->wallets()->firstOrCreate(
            ['slug' => $type],
            array_merge([
                'name' => ucfirst(str_replace('-', ' ', $type)).' Wallet',
                'meta' => [],
            ], $attributes)
        );
    }
}
