<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entries', function (Blueprint $table) {
            $table->index(['warehouse_id', 'created_at', 'id'], 'entries_kardex_context_date_idx');
        });
        Schema::table('inventory_transfers', function (Blueprint $table) {
            $table->index(['warehouse_id', 'transferred_at', 'id'], 'transfers_kardex_warehouse_date_idx');
            $table->index(['cabinet_id', 'transferred_at', 'id'], 'transfers_kardex_cabinet_date_idx');
        });
        Schema::table('inventory_outbounds', function (Blueprint $table) {
            $table->index(['warehouse_id', 'processed_at', 'id'], 'outbounds_kardex_context_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('entries', fn (Blueprint $table) => $table->dropIndex('entries_kardex_context_date_idx'));
        Schema::table('inventory_transfers', function (Blueprint $table) {
            $table->dropIndex('transfers_kardex_warehouse_date_idx');
            $table->dropIndex('transfers_kardex_cabinet_date_idx');
        });
        Schema::table('inventory_outbounds', fn (Blueprint $table) => $table->dropIndex('outbounds_kardex_context_date_idx'));
    }
};
