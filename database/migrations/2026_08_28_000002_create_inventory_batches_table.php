<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('entry_item_id')->unique()->constrained()->restrictOnDelete();
            $table->string('internal_lot')->unique();
            $table->string('manufacturer_lot')->nullable();
            $table->date('expiration_date')->nullable();
            $table->decimal('received_quantity', 15, 3);
            $table->decimal('available_quantity', 15, 3);
            $table->decimal('unit_cost', 15, 4);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_batches');
    }
};
