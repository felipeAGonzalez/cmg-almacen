<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_transfer_id')->constrained()->restrictOnDelete();
            $table->foreignId('source_inventory_item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->foreignId('destination_inventory_item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->decimal('requested_quantity', 15, 3);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_transfer_items');
    }
};
