<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Treasury\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionOperationType;

final class TreasuryPositionOperation extends Model
{
    protected $table = 'treasury_position_operations';

    protected $fillable = [
        'operation_reference',
        'idempotency_key',
        'request_hash',
        'operation_type',
        'source_position_id',
        'destination_position_id',
        'amount_minor',
        'currency',
        'external_reference',
        'transfer_id',
        'transfer_uuid',
        'source_transaction_id',
        'source_transaction_uuid',
        'destination_transaction_id',
        'destination_transaction_uuid',
        'status',
        'metadata',
    ];

    protected $attributes = [
        'status' => 'committed',
    ];

    protected function casts(): array
    {
        return [
            'operation_type' => TreasuryPositionOperationType::class,
            'amount_minor' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function sourcePosition(): BelongsTo
    {
        return $this->belongsTo(TreasuryPosition::class, 'source_position_id');
    }

    public function destinationPosition(): BelongsTo
    {
        return $this->belongsTo(TreasuryPosition::class, 'destination_position_id');
    }
}
