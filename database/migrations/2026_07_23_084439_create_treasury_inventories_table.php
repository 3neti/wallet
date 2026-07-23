<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treasury_inventories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('settlement_resource_id')
                ->constrained('treasury_settlement_resources')
                ->restrictOnDelete();
            $table->string('inventory_reference', 191)->unique();
            $table->string('registration_idempotency_key', 191)->unique();
            $table->char('registration_hash', 64);
            $table->string('currency', 3);
            $table->string('status', 32)->default('active')->index();
            $table->bigInteger('balance_minor')->default(0);
            $table->unsignedBigInteger('version')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_inventories');
    }
};
