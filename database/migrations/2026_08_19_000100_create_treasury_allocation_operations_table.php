<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treasury_allocation_operations', function (Blueprint $table): void {
            $table->id();
            $table->string('operation_reference', 191)->unique();
            $table->string('idempotency_key', 191)->unique();
            $table->char('request_hash', 64);
            $table->foreignId('allocation_id')
                ->constrained('treasury_allocations')
                ->restrictOnDelete();
            $table->string('operation_type', 32)->index();
            $table->foreignId('source_position_id')
                ->nullable()
                ->constrained('treasury_positions')
                ->restrictOnDelete();
            $table->foreignId('destination_position_id')
                ->nullable()
                ->constrained('treasury_positions')
                ->restrictOnDelete();
            $table->foreignId('reverses_operation_id')
                ->nullable()
                ->unique()
                ->constrained('treasury_allocation_operations')
                ->restrictOnDelete();
            $table->foreignId('position_operation_id')
                ->nullable()
                ->unique()
                ->constrained('treasury_position_operations')
                ->restrictOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->unsignedBigInteger('balance_before_minor');
            $table->unsignedBigInteger('balance_after_minor');
            $table->string('status', 32)->default('committed')->index();
            $table->timestampTz('effective_at', 6)->index();
            $table->string('external_reference', 191)->index();
            $table->json('metadata')->nullable();
            $table->timestampsTz();

            $table->index(
                ['allocation_id', 'operation_type', 'id'],
                'treasury_allocation_operations_history_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_allocation_operations');
    }
};
