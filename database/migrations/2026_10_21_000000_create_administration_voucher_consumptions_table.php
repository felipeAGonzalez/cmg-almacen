<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('administration_voucher_consumptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('administration_voucher_id');
            $table->foreign('administration_voucher_id', 'av_consumptions_voucher_fk')
                ->references('id')->on('administration_vouchers')->restrictOnDelete();
            $table->string('source_type', 50);
            $table->unsignedBigInteger('source_id');
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 15, 3);
            $table->date('consumption_date');
            $table->timestamps();

            $table->unique(['source_type', 'source_id'], 'av_consumptions_source_unique');
            $table->index(['consumption_date', 'administration_voucher_id'], 'av_consumptions_date_voucher_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('administration_voucher_consumptions');
    }
};
