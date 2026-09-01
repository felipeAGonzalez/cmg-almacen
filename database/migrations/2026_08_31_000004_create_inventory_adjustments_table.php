<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_batch_id')->constrained()->restrictOnDelete();
            $table->decimal('previous_quantity', 15, 3);
            $table->decimal('counted_quantity', 15, 3);
            $table->decimal('difference', 15, 3);
            $table->string('reason');
            $table->text('notes')->nullable();
            $table->foreignId('adjusted_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('adjusted_at');
            $table->timestamps();

            $table->index(['inventory_item_id', 'adjusted_at', 'id'], 'adjustments_item_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_adjustments');
    }
};
