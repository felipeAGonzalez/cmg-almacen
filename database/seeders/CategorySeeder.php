<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'medications' => ['Medicamentos', 'Productos destinados al tratamiento, prevención o control de enfermedades.'],
            'medical_supplies' => ['Insumo médico', 'Material utilizado durante procedimientos y atención clínica.'],
            'healing_material' => ['Material para curación', 'Productos utilizados en limpieza, protección, cobertura o atención de heridas.'],
            'consumables' => ['Consumible', 'Artículos de uso frecuente que se agotan con su utilización.'],
            'medical_devices' => ['Dispositivo médico', 'Equipos, instrumentos o dispositivos utilizados en atención clínica.'],
        ];

        foreach ($categories as $key => [$name, $description]) {
            $category = Category::query()
                ->where('key', $key)
                ->orWhere('name', $name)
                ->first();

            if ($category) {
                if ($category->key === null) {
                    $category->update(['key' => $key]);
                }

                continue;
            }

            Category::create(compact('key', 'name', 'description'));
        }
    }
}
