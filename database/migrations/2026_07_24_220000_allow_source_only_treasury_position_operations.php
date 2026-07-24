<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treasury_position_operations', function (Blueprint $table): void {
            $table->foreignId('destination_position_id')->nullable()->change();
            $table->unsignedBigInteger('destination_transaction_id')->nullable()->change();
            $table->uuid('destination_transaction_uuid')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('treasury_position_operations', function (Blueprint $table): void {
            $table->foreignId('destination_position_id')->nullable(false)->change();
            $table->unsignedBigInteger('destination_transaction_id')->nullable(false)->change();
            $table->uuid('destination_transaction_uuid')->nullable(false)->change();
        });
    }
};
