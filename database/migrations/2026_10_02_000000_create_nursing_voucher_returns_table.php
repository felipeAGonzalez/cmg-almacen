<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nursing_voucher_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nursing_voucher_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('status');
            $table->timestamp('requested_at');
            $table->timestamp('received_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('notes')->nullable();
            $table->string('rejection_reason', 2000)->nullable();
            $table->timestamps();

            $table->index(['nursing_voucher_id', 'status'], 'nv_returns_voucher_status_idx');
            $table->index('requested_at');
        });

        Schema::create('nursing_voucher_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nursing_voucher_return_id');
            $table->foreign('nursing_voucher_return_id', 'nv_return_items_return_fk')
                ->references('id')->on('nursing_voucher_returns')->restrictOnDelete();
            $table->foreignId('nursing_voucher_allocation_id');
            $table->foreign('nursing_voucher_allocation_id', 'nv_return_items_allocation_fk')
                ->references('id')->on('nursing_voucher_allocations')->restrictOnDelete();
            $table->decimal('quantity', 15, 3);
            $table->timestamps();

            $table->unique(
                ['nursing_voucher_return_id', 'nursing_voucher_allocation_id'],
                'nv_return_allocation_unique',
            );
            $table->index('nursing_voucher_allocation_id', 'nv_return_items_allocation_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nursing_voucher_return_items');
        Schema::dropIfExists('nursing_voucher_returns');
    }
};
