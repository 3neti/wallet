<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Treasury\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LBHurtado\Wallet\Treasury\Enums\TreasuryAllocationOperationType;
use LBHurtado\Wallet\Treasury\Exceptions\TreasuryImmutableOperation;

final class TreasuryAllocationOperation extends Model
{
    protected $table = 'treasury_allocation_operations';

    protected $fillable = [
        'operation_reference',
        'idempotency_key',
        'request_hash',
        'allocation_id',
        'operation_type',
        'source_position_id',
        'destination_position_id',
        'reverses_operation_id',
        'position_operation_id',
        'amount_minor',
        'currency',
        'balance_before_minor',
        'balance_after_minor',
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
            throw new TreasuryImmutableOperation('Committed Treasury Allocation operations cannot be updated.');
        });

        self::deleting(function (): never {
            throw new TreasuryImmutableOperation('Committed Treasury Allocation operations cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'operation_type' => TreasuryAllocationOperationType::class,
            'amount_minor' => 'integer',
            'balance_before_minor' => 'integer',
            'balance_after_minor' => 'integer',
            'effective_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(TreasuryAllocation::class, 'allocation_id');
    }

    public function sourcePosition(): BelongsTo
    {
        return $this->belongsTo(TreasuryPosition::class, 'source_position_id');
    }

    public function destinationPosition(): BelongsTo
    {
        return $this->belongsTo(TreasuryPosition::class, 'destination_position_id');
    }

    public function reversedOperation(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_operation_id');
    }

    public function reversals(): HasMany
    {
        return $this->hasMany(self::class, 'reverses_operation_id');
    }

    public function positionOperation(): BelongsTo
    {
        return $this->belongsTo(TreasuryPositionOperation::class, 'position_operation_id');
    }
}
