<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Treasury\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class TreasurySettlementResource extends Model
{
    protected $table = 'treasury_settlement_resources';

    protected $fillable = [
        'resource_reference',
        'resource_type',
        'currency',
        'status',
        'external_reference',
        'metadata',
    ];

    protected $attributes = [
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(TreasuryInventory::class, 'settlement_resource_id');
    }
}
