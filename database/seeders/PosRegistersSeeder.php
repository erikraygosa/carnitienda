<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PosRegistersSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $alm1 = DB::table('warehouses')->where('codigo','ALM-01')->value('id');
        $alm2 = DB::table('warehouses')->where('codigo','ALM-02')->value('id');

        DB::table('pos_registers')->insert([
            ['warehouse_id' => $alm1, 'nombre' => 'Caja Central 1', 'serie_ticket' => 'TCC', 'folio_actual' => 1, 'activo' => 1, 'created_at'=>$now,'updated_at'=>$now],
            ['warehouse_id' => $alm2, 'nombre' => 'Caja Sucursal 1','serie_ticket' => 'TSU', 'folio_actual' => 1, 'activo' => 1, 'created_at'=>$now,'updated_at'=>$now],
        ]);
    }
}
