<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_transfers', function (Blueprint $table): void {
            $table->foreignId('administration_voucher_id')->nullable()->after('id');
            $table->foreign('administration_voucher_id', 'transfers_admin_voucher_fk')->references('id')->on('administration_vouchers')->restrictOnDelete();
            $table->index('administration_voucher_id', 'transfers_admin_voucher_idx');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_transfers', function (Blueprint $table): void {
            $table->dropForeign('transfers_admin_voucher_fk');
            $table->dropIndex('transfers_admin_voucher_idx');
            $table->dropColumn('administration_voucher_id');
        });
    }
};
