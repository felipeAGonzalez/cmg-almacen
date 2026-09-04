<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nursing_vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->string('source_type');
            $table->foreignId('source_cabinet_id')->nullable()->constrained('cabinets')->restrictOnDelete();
            $table->string('external_patient_id');
            $table->string('external_hospitalization_id');
            $table->string('patient_name');
            $table->string('external_room_id');
            $table->string('room_number');
            $table->string('status');
            $table->timestamp('requested_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('rejection_reason', 2000)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['warehouse_id', 'status']);
            $table->index('requested_by');
            $table->index('external_hospitalization_id');
            $table->index('requested_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nursing_vouchers');
    }
};
