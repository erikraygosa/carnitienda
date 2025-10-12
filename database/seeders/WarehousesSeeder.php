<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WarehousesSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $rows = [
            ['codigo' => 'ALM-01', 'nombre' => 'Almacén Central', 'direccion' => 'Calle 1 #100', 'activo' => 1],
            ['codigo' => 'ALM-02', 'nombre' => 'Almacén Sucursal', 'direccion' => 'Av. 50 #200', 'activo' => 1],
        ];
        foreach ($rows as $r) {
            DB::table('warehouses')->insert(array_merge($r, ['created_at'=>$now,'updated_at'=>$now]));
        }
    }
}
