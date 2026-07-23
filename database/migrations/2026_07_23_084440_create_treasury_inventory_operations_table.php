<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treasury_inventory_operations', function (Blueprint $table): void {
            $table->id();
            $table->string('operation_reference', 191)->unique();
            $table->string('idempotency_key', 191)->unique();
            $table->char('request_hash', 64);
            $table->string('operation_type', 32)->index();
            $table->foreignId('source_inventory_id')
                ->nullable()
                ->constrained('treasury_inventories')
                ->restrictOnDelete();
            $table->foreignId('destination_inventory_id')
                ->nullable()
                ->constrained('treasury_inventories')
                ->restrictOnDelete();
            $table->foreignId('reverses_operation_id')
                ->nullable()
                ->constrained('treasury_inventory_operations')
                ->restrictOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3);
            $table->string('status', 32)->default('committed')->index();
            $table->timestampTz('effective_at')->index();
            $table->string('external_reference', 191)->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_inventory_operations');
    }
};
