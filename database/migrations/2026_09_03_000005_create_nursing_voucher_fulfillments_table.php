<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nursing_voucher_fulfillments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nursing_voucher_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplied_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('supplied_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['nursing_voucher_id', 'supplied_at'], 'nv_fulfillments_voucher_supplied_idx');
            $table->index('supplied_at');
        });

        Schema::create('nursing_voucher_fulfillment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nursing_voucher_fulfillment_id');
            $table->foreign('nursing_voucher_fulfillment_id', 'nv_fulfillment_items_fulfillment_fk')
                ->references('id')->on('nursing_voucher_fulfillments')->restrictOnDelete();
            $table->foreignId('nursing_voucher_item_id');
            $table->foreign('nursing_voucher_item_id', 'nv_fulfillment_items_voucher_item_fk')
                ->references('id')->on('nursing_voucher_items')->restrictOnDelete();
            $table->decimal('quantity', 15, 3);
            $table->timestamps();

            $table->unique(
                ['nursing_voucher_fulfillment_id', 'nursing_voucher_item_id'],
                'nursing_fulfillment_item_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nursing_voucher_fulfillment_items');
        Schema::dropIfExists('nursing_voucher_fulfillments');
    }
};
