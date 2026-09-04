<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('administration_vouchers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('cabinet_id')->constrained()->restrictOnDelete();
            $table->string('status');
            $table->dateTime('requested_at')->index();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->dateTime('rejected_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['warehouse_id', 'status'], 'admin_vouchers_warehouse_status_idx');
            $table->index(['cabinet_id', 'status'], 'admin_vouchers_cabinet_status_idx');
            $table->index('requested_by', 'admin_vouchers_requester_idx');
        });

        Schema::create('administration_voucher_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('administration_voucher_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('requested_quantity', 15, 3);
            $table->decimal('supplied_quantity', 15, 3)->default(0);
            $table->timestamps();
            $table->unique(['administration_voucher_id', 'product_id'], 'admin_voucher_product_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('administration_voucher_items');
        Schema::dropIfExists('administration_vouchers');
    }
};
