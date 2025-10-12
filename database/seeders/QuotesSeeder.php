<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class QuotesSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $client  = DB::table('clients')->where('nombre','Carnes Don Pancho S.A. de C.V.')->first();
        $plMay   = DB::table('price_lists')->where('nombre','Mayorista')->value('id');
        $pChor   = DB::table('products')->where('sku','CAR-CHOR')->first();
        $pMolida = DB::table('products')->where('sku','CAR-MOLI')->first();

        $quoteId = DB::table('quotes')->insertGetId([
            'fecha' => Carbon::now(),
            'client_id' => $client->id,
            'price_list_id' => $plMay,
            'moneda' => 'MXN',
            'subtotal' => 0, 'impuestos' => 0, 'descuento' => 0, 'total' => 0,
            'vigencia_hasta' => Carbon::now()->addDays(7)->toDateString(),
            'status' => 'ENVIADA',
            'created_by' => null, 'owner_id' => null,
            'created_at'=>$now,'updated_at'=>$now,
        ]);

        $items = [
            ['quote_id'=>$quoteId,'product_id'=>$pChor->id,'descripcion'=>$pChor->nombre,'cantidad'=>20.000,'precio'=>155.00,'descuento'=>0,'impuesto'=>0,'total'=>3100.00],
            ['quote_id'=>$quoteId,'product_id'=>$pMolida->id,'descripcion'=>$pMolida->nombre,'cantidad'=>10.000,'precio'=>138.00,'descuento'=>0,'impuesto'=>0,'total'=>1380.00],
        ];
        foreach ($items as $it) {
            DB::table('quote_items')->insert(array_merge($it, ['created_at'=>$now,'updated_at'=>$now]));
        }

        DB::table('quotes')->where('id',$quoteId)->update([
            'subtotal'=>4480.00,'total'=>4480.00,'updated_at'=>now()
        ]);
    }
}
