<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treasury_allocations', function (Blueprint $table): void {
            $table->id();
            $table->string('allocation_reference', 191)->unique();
            $table->string('registration_idempotency_key', 191)->unique();
            $table->char('registration_hash', 64);
            $table->foreignId('backing_position_operation_id')
                ->unique()
                ->constrained('treasury_position_operations')
                ->restrictOnDelete();
            $table->foreignId('reserve_position_id')
                ->constrained('treasury_positions')
                ->restrictOnDelete();
            $table->foreignId('release_position_id')
                ->constrained('treasury_positions')
                ->restrictOnDelete();
            $table->char('currency', 3);
            $table->unsignedTinyInteger('decimal_places');
            $table->unsignedBigInteger('initial_amount_minor');
            $table->unsignedBigInteger('maximum_amount_minor');
            $table->unsignedBigInteger('balance_minor');
            $table->boolean('replenishable')->default(false);
            $table->unsignedBigInteger('version')->default(0);
            $table->string('status', 32)->default('active');
            $table->string('external_reference', 191)->index();
            $table->json('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'currency'], 'treasury_allocations_status_currency_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_allocations');
    }
};
