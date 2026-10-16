<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entry_items', function (Blueprint $table): void {
            $table->decimal('vat_rate', 5, 4)->default(0.1600)->after('unit_cost');
        });

        DB::table('entry_items')
            ->whereIn('inventory_item_id', function ($query): void {
                $query->select('inventory_items.id')
                    ->from('inventory_items')
                    ->join('products', 'products.id', '=', 'inventory_items.product_id')
                    ->join('categories', 'categories.id', '=', 'products.category_id')
                    ->where('categories.name', 'Medicamentos');
            })
            ->update(['vat_rate' => 0]);
    }

    public function down(): void
    {
        Schema::table('entry_items', function (Blueprint $table): void {
            $table->dropColumn('vat_rate');
        });
    }
};
