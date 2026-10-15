<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_outbounds', function (Blueprint $table): void {
            $table->foreignId('source_cabinet_id')
                ->nullable()
                ->after('warehouse_id')
                ->constrained('cabinets')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_outbounds', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('source_cabinet_id');
        });
    }
};
