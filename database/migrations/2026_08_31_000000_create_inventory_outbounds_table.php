<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_outbounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->string('reason');
            $table->text('notes')->nullable();
            $table->foreignId('processed_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('processed_at');
            $table->string('source_type');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_outbounds');
    }
};
