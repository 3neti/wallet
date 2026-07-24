<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('treasury_position_operations')) {
            return;
        }

        Schema::create('treasury_position_operations', function (Blueprint $table): void {
            $table->id();
            $table->string('operation_reference', 191)->unique();
            $table->string('idempotency_key', 191)->unique();
            $table->char('request_hash', 64);
            $table->string('operation_type', 32)->index();
            $table->foreignId('source_position_id')
                ->nullable()
                ->constrained('treasury_positions')
                ->restrictOnDelete();
            $table->foreignId('destination_position_id')
                ->constrained('treasury_positions')
                ->restrictOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('external_reference', 191)->index();
            $table->unsignedBigInteger('transfer_id')->nullable()->index();
            $table->uuid('transfer_uuid')->nullable()->index();
            $table->unsignedBigInteger('source_transaction_id')->nullable()->index();
            $table->uuid('source_transaction_uuid')->nullable()->index();
            $table->unsignedBigInteger('destination_transaction_id')->index();
            $table->uuid('destination_transaction_uuid')->index();
            $table->string('status', 32)->default('committed')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_position_operations');
    }
};
