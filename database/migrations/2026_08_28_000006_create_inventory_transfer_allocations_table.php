<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_transfer_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_transfer_item_id');
            $table->foreignId('source_batch_id');
            $table->foreignId('destination_batch_id');
            $table->decimal('quantity', 15, 3);
            $table->timestamps();

            $table->foreign('inventory_transfer_item_id', 'ita_transfer_item_fk')
                ->references('id')->on('inventory_transfer_items')->restrictOnDelete();
            $table->foreign('source_batch_id', 'ita_source_batch_fk')
                ->references('id')->on('inventory_batches')->restrictOnDelete();
            $table->foreign('destination_batch_id', 'ita_destination_batch_fk')
                ->references('id')->on('inventory_batches')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_transfer_allocations');
    }
};
