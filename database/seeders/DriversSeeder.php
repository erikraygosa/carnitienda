<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DriversSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $rows = [
            ['nombre' => 'Juan Pérez',   'telefono' => '9991112233', 'licencia' => 'LIC-001', 'activo' => 1],
            ['nombre' => 'Ana García',   'telefono' => '9992223344', 'licencia' => 'LIC-002', 'activo' => 1],
        ];
        foreach ($rows as $r) {
            DB::table('drivers')->insert(array_merge($r, ['created_at'=>$now,'updated_at'=>$now]));
        }
    }
}
