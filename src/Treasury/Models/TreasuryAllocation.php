<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Treasury\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class TreasuryAllocation extends Model
{
    protected $table = 'treasury_allocations';

    protected $fillable = [
        'allocation_reference',
        'registration_idempotency_key',
        'registration_hash',
        'backing_position_operation_id',
        'reserve_position_id',
        'release_position_id',
        'currency',
        'decimal_places',
        'initial_amount_minor',
        'maximum_amount_minor',
        'balance_minor',
        'replenishable',
        'version',
        'status',
        'external_reference',
        'metadata',
    ];

    protected $attributes = [
        'replenishable' => false,
        'version' => 0,
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'decimal_places' => 'integer',
            'initial_amount_minor' => 'integer',
            'maximum_amount_minor' => 'integer',
            'balance_minor' => 'integer',
            'replenishable' => 'boolean',
            'version' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function backingPositionOperation(): BelongsTo
    {
        return $this->belongsTo(TreasuryPositionOperation::class, 'backing_position_operation_id');
    }

    public function reservePosition(): BelongsTo
    {
        return $this->belongsTo(TreasuryPosition::class, 'reserve_position_id');
    }

    public function releasePosition(): BelongsTo
    {
        return $this->belongsTo(TreasuryPosition::class, 'release_position_id');
    }

    public function operations(): HasMany
    {
        return $this->hasMany(TreasuryAllocationOperation::class, 'allocation_id');
    }
}
