<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $rows = [
            ['nombre' => 'Carnes',      'descripcion' => 'Cortes, molidas y cárnicos', 'activo' => 1],
            ['nombre' => 'Condimentos', 'descripcion' => 'Especias y aditivos',       'activo' => 1],
            ['nombre' => 'Empaque',     'descripcion' => 'Bolsas y materiales',        'activo' => 1],
        ];

        foreach ($rows as $r) {
            DB::table('categories')->insert(array_merge($r, [
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        // (Opcional) Asignar categorías a productos existentes por SKU/prefijo
        $ids = DB::table('categories')->pluck('id','nombre');
        DB::table('products')->where('sku','like','CAR-%')->update(['category_id' => $ids['Carnes'] ?? null]);
        DB::table('products')->where('sku','like','ESP%')->update(['category_id' => $ids['Condimentos'] ?? null]);
        DB::table('products')->where('sku','like','BOL-%')->update(['category_id' => $ids['Empaque'] ?? null]);
    }
}
