<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            // Polymorphic targets cannot have a conventional foreign key; application rules block deletion.
            $table->morphs('stockable');
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('minimum_stock', 15, 3);
            $table->decimal('maximum_stock', 15, 3);
            $table->timestamps();

            $table->unique(
                ['stockable_type', 'stockable_id', 'product_id'],
                'inventory_items_stockable_product_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
