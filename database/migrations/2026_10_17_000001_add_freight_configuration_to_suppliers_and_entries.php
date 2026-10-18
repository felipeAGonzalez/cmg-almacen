<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->boolean('charges_freight')->default(false)->after('address');
        });

        Schema::table('entries', function (Blueprint $table): void {
            $table->decimal('freight_cost', 15, 2)->default(0)->after('invoice_date');
        });
    }

    public function down(): void
    {
        Schema::table('entries', function (Blueprint $table): void {
            $table->dropColumn('freight_cost');
        });

        Schema::table('suppliers', function (Blueprint $table): void {
            $table->dropColumn('charges_freight');
        });
    }
};
