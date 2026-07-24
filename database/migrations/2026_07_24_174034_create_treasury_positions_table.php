<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treasury_positions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('settlement_resource_id')
                ->constrained('treasury_settlement_resources')
                ->restrictOnDelete();
            $table->string('position_reference', 191)->unique();
            $table->string('registration_idempotency_key', 191)->unique();
            $table->char('registration_hash', 64);
            $table->char('scope_hash', 64)->unique();
            $table->string('principal_type', 191);
            $table->string('principal_id', 64);
            $table->index(['principal_type', 'principal_id']);
            $table->string('principal_reference', 191)->index();
            $table->string('mandate_reference', 191)->index();
            $table->unsignedBigInteger('internal_ledger_id')->unique();
            $table->uuid('internal_ledger_uuid')->unique();
            $table->string('provider', 64)->index();
            $table->string('connection_reference', 191)->index();
            $table->char('currency', 3);
            $table->unsignedSmallInteger('decimal_places');
            $table->string('purpose', 64)->index();
            $table->string('custody_mode', 64);
            $table->string('legal_profile', 191);
            $table->string('legal_profile_version', 64);
            $table->string('reconciliation_reference', 191)->nullable()->index();
            $table->string('status', 32)->default('active')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_positions');
    }
};
