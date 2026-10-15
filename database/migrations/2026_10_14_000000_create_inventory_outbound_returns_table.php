<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_outbound_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_outbound_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('received_at');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['warehouse_id', 'received_at'], 'ior_warehouse_received_idx');
        });

        Schema::create('inventory_outbound_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_outbound_return_id');
            $table->foreign('inventory_outbound_return_id', 'iori_return_fk')->references('id')->on('inventory_outbound_returns')->restrictOnDelete();
            $table->foreignId('inventory_outbound_allocation_id');
            $table->foreign('inventory_outbound_allocation_id', 'iori_allocation_fk')->references('id')->on('inventory_outbound_allocations')->restrictOnDelete();
            $table->decimal('quantity', 15, 3);
            $table->timestamps();
            $table->unique(['inventory_outbound_return_id', 'inventory_outbound_allocation_id'], 'iori_return_allocation_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_outbound_return_items');
        Schema::dropIfExists('inventory_outbound_returns');
    }
};
