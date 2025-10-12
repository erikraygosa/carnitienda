<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductBomsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $pChorizo = DB::table('products')->where('sku','CAR-CHOR')->value('id');
        $pPierna  = DB::table('products')->where('sku','CAR-PIER-ENT')->value('id');
        $pEspec   = DB::table('products')->where('sku','CAR-ESP')->value('id');

        $rows = [
            // para 1 KG de chorizo: 0.85 KG de pierna + 0.15 KG de especias
            ['parent_product_id'=>$pChorizo,'component_product_id'=>$pPierna,'cantidad'=>0.85,'activo'=>1],
            ['parent_product_id'=>$pChorizo,'component_product_id'=>$pEspec,'cantidad'=>0.15,'activo'=>1],
        ];
        foreach ($rows as $r) {
            DB::table('product_boms')->insert(array_merge($r, ['created_at'=>$now,'updated_at'=>$now]));
        }
    }
}
