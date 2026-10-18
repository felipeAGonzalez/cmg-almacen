<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('operational_settings', 'administration_voucher_delegate_user_id')) {
            Schema::table('operational_settings', function (Blueprint $table): void {
                $table->foreignId('administration_voucher_delegate_user_id')
                    ->nullable()
                    ->after('warehouse_rest_day');
            });
        }

        if (! Schema::hasColumn('operational_settings', 'administration_voucher_delegate_day')) {
            Schema::table('operational_settings', function (Blueprint $table): void {
                $table->unsignedTinyInteger('administration_voucher_delegate_day')
                    ->nullable()
                    ->after('administration_voucher_delegate_user_id');
            });
        }

        Schema::table('operational_settings', function (Blueprint $table): void {
            $table->foreign(
                'administration_voucher_delegate_user_id',
                'ops_admin_voucher_delegate_fk',
            )->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('operational_settings', function (Blueprint $table): void {
            $table->dropForeign('ops_admin_voucher_delegate_fk');
            $table->dropColumn('administration_voucher_delegate_user_id');
            $table->dropColumn('administration_voucher_delegate_day');
        });
    }
};
