<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Treasury\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class TreasuryInventory extends Model
{
    protected $table = 'treasury_inventories';

    protected $fillable = [
        'settlement_resource_id',
        'inventory_reference',
        'registration_idempotency_key',
        'registration_hash',
        'currency',
        'status',
        'balance_minor',
        'version',
        'metadata',
    ];

    protected $attributes = [
        'status' => 'active',
        'balance_minor' => 0,
        'version' => 0,
    ];

    protected function casts(): array
    {
        return [
            'balance_minor' => 'integer',
            'version' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function settlementResource(): BelongsTo
    {
        return $this->belongsTo(TreasurySettlementResource::class, 'settlement_resource_id');
    }

    public function sourceOperations(): HasMany
    {
        return $this->hasMany(TreasuryInventoryOperation::class, 'source_inventory_id');
    }

    public function destinationOperations(): HasMany
    {
        return $this->hasMany(TreasuryInventoryOperation::class, 'destination_inventory_id');
    }
}
