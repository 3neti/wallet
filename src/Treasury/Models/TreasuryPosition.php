<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Treasury\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LBHurtado\Wallet\Treasury\Enums\TreasuryCustodyMode;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;

final class TreasuryPosition extends Model
{
    protected $table = 'treasury_positions';

    protected $fillable = [
        'settlement_resource_id',
        'position_reference',
        'registration_idempotency_key',
        'registration_hash',
        'scope_hash',
        'principal_type',
        'principal_id',
        'principal_reference',
        'mandate_reference',
        'internal_ledger_id',
        'internal_ledger_uuid',
        'provider',
        'connection_reference',
        'currency',
        'decimal_places',
        'purpose',
        'custody_mode',
        'legal_profile',
        'legal_profile_version',
        'reconciliation_reference',
        'status',
        'metadata',
    ];

    protected $attributes = [
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'decimal_places' => 'integer',
            'purpose' => TreasuryPositionPurpose::class,
            'custody_mode' => TreasuryCustodyMode::class,
            'metadata' => 'array',
        ];
    }

    public function principal(): MorphTo
    {
        return $this->morphTo();
    }

    public function settlementResource(): BelongsTo
    {
        return $this->belongsTo(TreasurySettlementResource::class, 'settlement_resource_id');
    }
}
