<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_batches', function (Blueprint $table) {
            $table->foreignId('entry_item_id')->nullable()->change();
            $table->foreignId('source_batch_id')->nullable()->after('entry_item_id')->constrained('inventory_batches')->restrictOnDelete();
            $table->unique(['inventory_item_id', 'source_batch_id'], 'inventory_batches_destination_source_unique');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_batches', function (Blueprint $table) {
            $table->dropUnique('inventory_batches_destination_source_unique');
            $table->dropConstrainedForeignId('source_batch_id');
            $table->foreignId('entry_item_id')->nullable(false)->change();
        });
    }
};
