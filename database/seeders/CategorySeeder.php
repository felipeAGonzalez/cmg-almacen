<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Medicamento' => 'Productos destinados al tratamiento, prevención o control de enfermedades.',
            'Insumo médico' => 'Material utilizado durante procedimientos y atención clínica.',
            'Material de curación' => 'Productos utilizados en limpieza, protección, cobertura o atención de heridas.',
            'Consumible' => 'Artículos de uso frecuente que se agotan con su utilización.',
            'Dispositivo médico' => 'Equipos, instrumentos o dispositivos utilizados en atención clínica.',
        ];

        foreach ($categories as $name => $description) {
            Category::firstOrCreate(['name' => $name], ['description' => $description]);
        }
    }
}
