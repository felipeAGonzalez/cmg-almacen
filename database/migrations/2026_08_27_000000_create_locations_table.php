<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('description', 1000)->nullable();
            $table->timestamps();

            $table->unique(['warehouse_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
