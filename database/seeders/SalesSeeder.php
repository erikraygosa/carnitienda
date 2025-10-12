<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SalesSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $alm1 = DB::table('warehouses')->where('codigo','ALM-01')->value('id');
        $caja = DB::table('pos_registers')->where('serie_ticket','TCC')->value('id');
        $clientCont = DB::table('clients')->where('nombre','Carlos López')->value('id');
        $clientCred = DB::table('clients')->where('nombre','Carnes Don Pancho S.A. de C.V.')->value('id');
        $pagoEfec = DB::table('payment_types')->where('clave','EFECTIVO')->value('id');
        $pagoCred = DB::table('payment_types')->where('clave','CREDITO')->value('id');

        $pChor   = DB::table('products')->where('sku','CAR-CHOR')->first();
        $pMolida = DB::table('products')->where('sku','CAR-MOLI')->first();

        // Venta de contado
        $sale1 = DB::table('sales')->insertGetId([
            'fecha'=>Carbon::now(),'pos_register_id'=>$caja,'warehouse_id'=>$alm1,'client_id'=>$clientCont,
            'payment_type_id'=>$pagoEfec,'tipo_venta'=>'CONTADO','subtotal'=>620.00,'impuestos'=>0,'descuento'=>0,'total'=>620.00,
            'status'=>'CERRADA','driver_id'=>null,'user_id'=>null,'created_at'=>$now,'updated_at'=>$now
        ]);
        DB::table('sale_items')->insert([
            ['sale_id'=>$sale1,'product_id'=>$pChor->id,'cantidad'=>2.000,'precio'=>155.00,'descuento'=>0,'impuesto'=>0,'total'=>310.00,'created_at'=>$now,'updated_at'=>$now],
            ['sale_id'=>$sale1,'product_id'=>$pMolida->id,'cantidad'=>2.000,'precio'=>155.00,'descuento'=>0,'impuesto'=>0,'total'=>310.00,'created_at'=>$now,'updated_at'=>$now],
        ]);

        // Venta a crédito (para generar CxC manualmente después con tu servicio)
        $sale2 = DB::table('sales')->insertGetId([
            'fecha'=>Carbon::now(),'pos_register_id'=>$caja,'warehouse_id'=>$alm1,'client_id'=>$clientCred,
            'payment_type_id'=>$pagoCred,'tipo_venta'=>'CREDITO','subtotal'=>3100.00,'impuestos'=>0,'descuento'=>0,'total'=>3100.00,
            'status'=>'ABIERTA','driver_id'=>null,'user_id'=>null,'created_at'=>$now,'updated_at'=>$now
        ]);
        DB::table('sale_items')->insert([
            ['sale_id'=>$sale2,'product_id'=>$pChor->id,'cantidad'=>20.000,'precio'=>155.00,'descuento'=>0,'impuesto'=>0,'total'=>3100.00,'created_at'=>$now,'updated_at'=>$now],
        ]);
    }
}
