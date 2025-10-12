<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductSubproductRulesSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $main = DB::table('products')->where('sku','CAR-PIER-ENT')->value('id'); // principal
        $sub  = DB::table('products')->where('sku','CAR-MOLI')->value('id');     // subproducto

        DB::table('product_subproduct_rules')->insert([
            'main_product_id' => $main,
            'sub_product_id'  => $sub,
            'ratio'           => 1.00,   // 1 KG molida consume 1 KG pierna
            'merma_porcent'   => 2.50,   // 2.5% merma
            'activo'          => 1,
            'created_at'=>$now,'updated_at'=>$now
        ]);
    }
}
