<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ShippingRoutesSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $rows = [
            ['nombre' => 'Ruta Centro', 'descripcion' => 'Entregas zona centro', 'activo' => 1],
            ['nombre' => 'Ruta Norte',  'descripcion' => 'Entregas zona norte',  'activo' => 1],
            ['nombre' => 'Ruta Sur',    'descripcion' => 'Entregas zona sur',    'activo' => 1],
        ];
        foreach ($rows as $r) {
            DB::table('shipping_routes')->insert(array_merge($r, ['created_at'=>$now,'updated_at'=>$now]));
        }
    }
}
