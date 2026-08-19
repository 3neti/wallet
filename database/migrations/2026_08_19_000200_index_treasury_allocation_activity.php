<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treasury_allocation_operations', function (Blueprint $table): void {
            $table->index(
                ['allocation_id', 'status', 'effective_at', 'id'],
                'treasury_allocation_activity_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('treasury_allocation_operations', function (Blueprint $table): void {
            $table->dropIndex('treasury_allocation_activity_idx');
        });
    }
};
