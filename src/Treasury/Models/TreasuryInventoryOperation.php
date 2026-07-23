<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Treasury\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LBHurtado\Wallet\Treasury\Enums\TreasuryInventoryOperationType;
use LBHurtado\Wallet\Treasury\Exceptions\TreasuryImmutableOperation;

final class TreasuryInventoryOperation extends Model
{
    protected $table = 'treasury_inventory_operations';

    protected $fillable = [
        'operation_reference',
        'idempotency_key',
        'request_hash',
        'operation_type',
        'source_inventory_id',
        'destination_inventory_id',
        'reverses_operation_id',
        'amount_minor',
        'currency',
        'status',
        'effective_at',
        'external_reference',
        'metadata',
    ];

    protected $attributes = [
        'status' => 'committed',
    ];

    protected static function booted(): void
    {
        self::updating(function (): never {
            throw new TreasuryImmutableOperation('Committed Treasury Inventory operations cannot be updated.');
        });

        self::deleting(function (): never {
            throw new TreasuryImmutableOperation('Committed Treasury Inventory operations cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'operation_type' => TreasuryInventoryOperationType::class,
            'amount_minor' => 'integer',
            'effective_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    public function sourceInventory(): BelongsTo
    {
        return $this->belongsTo(TreasuryInventory::class, 'source_inventory_id');
    }

    public function destinationInventory(): BelongsTo
    {
        return $this->belongsTo(TreasuryInventory::class, 'destination_inventory_id');
    }

    public function reversedOperation(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_operation_id');
    }

    public function reversals(): HasMany
    {
        return $this->hasMany(self::class, 'reverses_operation_id');
    }
}
