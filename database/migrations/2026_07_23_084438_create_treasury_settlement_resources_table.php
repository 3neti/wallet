<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treasury_settlement_resources', function (Blueprint $table): void {
            $table->id();
            $table->string('resource_reference', 191)->unique();
            $table->string('resource_type', 64);
            $table->string('currency', 3);
            $table->string('status', 32)->default('active')->index();
            $table->string('external_reference', 191)->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_settlement_resources');
    }
};
