<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nursing_voucher_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nursing_voucher_fulfillment_item_id');
            $table->foreign('nursing_voucher_fulfillment_item_id', 'nv_allocations_fulfillment_item_fk')
                ->references('id')->on('nursing_voucher_fulfillment_items')->restrictOnDelete();
            $table->foreignId('inventory_batch_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 15, 3);
            $table->timestamps();

            $table->index('inventory_batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nursing_voucher_allocations');
    }
};
