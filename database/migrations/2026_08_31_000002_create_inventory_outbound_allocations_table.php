<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_outbound_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_outbound_item_id');
            $table->foreignId('inventory_batch_id');
            $table->decimal('quantity', 15, 3);
            $table->timestamps();

            $table->foreign('inventory_outbound_item_id', 'ioa_outbound_item_fk')
                ->references('id')->on('inventory_outbound_items')->restrictOnDelete();
            $table->foreign('inventory_batch_id', 'ioa_inventory_batch_fk')
                ->references('id')->on('inventory_batches')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_outbound_allocations');
    }
};
