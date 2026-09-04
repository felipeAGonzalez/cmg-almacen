<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nursing_voucher_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nursing_voucher_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('requested_quantity', 15, 3);
            $table->decimal('supplied_quantity', 15, 3)->default(0);
            $table->timestamps();

            $table->unique(['nursing_voucher_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nursing_voucher_items');
    }
};
