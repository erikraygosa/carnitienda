<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ClientPriceOverridesSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $client = DB::table('clients')->where('nombre','Carnes Don Pancho S.A. de C.V.')->value('id');
        $prod   = DB::table('products')->where('sku','CAR-MOLI')->value('id');

        DB::table('client_price_overrides')->insert([
            'client_id'=>$client,'product_id'=>$prod,'precio'=>132.50,
            'created_at'=>$now,'updated_at'=>$now
        ]);
    }
}
