<?php

namespace LBHurtado\Wallet\Enums;

enum WalletType: string
{
    case PLATFORM = 'platform';
    case REWARDS = 'rewards';
    case ESCROW = 'escrow';
    case COMMISSION = 'commission';

    public static function default(): self
    {
        return WalletType::PLATFORM;
    }

    public function label(): string
    {
        return match ($this) {
            self::PLATFORM => 'Platform Wallet',
            self::REWARDS => 'Rewards Wallet',
            self::ESCROW => 'Escrow Wallet',
            self::COMMISSION => 'Commission Wallet',
        };
    }

    public function defaultMeta(): array
    {
        return match ($this) {
            self::PLATFORM => ['description' => 'Main wallet for platform transactions.'],
            self::REWARDS => ['description' => 'Wallet for loyalty points and rewards.'],
            self::ESCROW => ['description' => 'Wallet for held funds.'],
            self::COMMISSION => ['description' => 'Platform earnings.'],
        };
    }
}
