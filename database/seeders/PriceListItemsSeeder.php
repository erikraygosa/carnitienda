<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PriceListItemsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $plGeneral = DB::table('price_lists')->where('nombre','General')->value('id');
        $plMay     = DB::table('price_lists')->where('nombre','Mayorista')->value('id');

        $prod = DB::table('products')->pluck('id','sku');

        $items = [
            // Lista General
            ['price_list_id'=>$plGeneral,'product_id'=>$prod['CAR-PIER-ENT'],'precio'=>120.00],
            ['price_list_id'=>$plGeneral,'product_id'=>$prod['CAR-ESP'],'precio'=>80.00],
            ['price_list_id'=>$plGeneral,'product_id'=>$prod['CAR-CHOR'],'precio'=>165.00],
            ['price_list_id'=>$plGeneral,'product_id'=>$prod['CAR-MOLI'],'precio'=>145.00],
            // Mayorista
            ['price_list_id'=>$plMay,'product_id'=>$prod['CAR-PIER-ENT'],'precio'=>110.00],
            ['price_list_id'=>$plMay,'product_id'=>$prod['CAR-ESP'],'precio'=>72.00],
            ['price_list_id'=>$plMay,'product_id'=>$prod['CAR-CHOR'],'precio'=>155.00],
            ['price_list_id'=>$plMay,'product_id'=>$prod['CAR-MOLI'],'precio'=>138.00],
        ];

        foreach ($items as $it) {
            DB::table('price_list_items')->insert(array_merge($it, ['created_at'=>$now,'updated_at'=>$now]));
        }
    }
}
