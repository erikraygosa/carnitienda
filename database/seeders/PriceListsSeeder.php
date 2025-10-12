<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PriceListsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $rows = [
            ['nombre'=>'General','moneda'=>'MXN','activo'=>1],
            ['nombre'=>'Mayorista','moneda'=>'MXN','activo'=>1],
        ];
        foreach ($rows as $r) {
            DB::table('price_lists')->insert(array_merge($r, ['created_at'=>$now,'updated_at'=>$now]));
        }
    }
}
