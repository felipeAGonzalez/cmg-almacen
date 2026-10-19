<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('key')->nullable()->unique()->after('id');
        });

        $this->assignKey('medications', ['Medicamentos', 'Medicamento']);
        $this->assignKey('medical_supplies', ['Insumo médico']);
        $this->assignKey('healing_material', ['Material para curación', 'Material de curación']);
        $this->assignKey('consumables', ['Consumible']);
        $this->assignKey('medical_devices', ['Dispositivo médico']);
        $this->assignKey('solutions', ['Soluciones']);
        $this->assignKey('filters', ['Filtros']);
        $this->assignKey('acids_and_bicarbonates', ['Ácidos y bicarbonatos']);
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique(['key']);
            $table->dropColumn('key');
        });
    }

    /** @param array<int, string> $names */
    private function assignKey(string $key, array $names): void
    {
        foreach ($names as $name) {
            $updated = DB::table('categories')
                ->where('name', $name)
                ->whereNull('key')
                ->limit(1)
                ->update(['key' => $key]);

            if ($updated > 0) {
                return;
            }
        }
    }
};
