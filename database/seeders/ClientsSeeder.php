<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ClientsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $routeCentro = DB::table('shipping_routes')->where('nombre','Ruta Centro')->value('id');
        $plGeneral   = DB::table('price_lists')->where('nombre','General')->value('id');
        $plMay       = DB::table('price_lists')->where('nombre','Mayorista')->value('id');
        $pagoContado = DB::table('payment_types')->where('clave','EFECTIVO')->value('id');
        $pagoCredito = DB::table('payment_types')->where('clave','CREDITO')->value('id');

        $rows = [
            // PF
            [
                'nombre'=>'Carlos López', 'email'=>'carlos@example.com','telefono'=>'9995550001','direccion'=>'Calle 2 #20',
                'activo'=>1,'tipo_persona'=>'PF','rfc'=>null,'razon_social'=>null,'nombre_comercial'=>null,
                'regimen_fiscal'=>null,'uso_cfdi_default'=>null,
                'shipping_route_id'=>$routeCentro,'payment_type_id'=>$pagoContado,'price_list_id'=>$plGeneral,
                'credito_limite'=>0,'credito_dias'=>0,
            ],
            // PM
            [
                'nombre'=>'Carnes Don Pancho S.A. de C.V.','email'=>'ventas@donpancho.mx','telefono'=>'9995550002','direccion'=>'Av. 60 #500',
                'activo'=>1,'tipo_persona'=>'PM','rfc'=>'CDP920101ABC','razon_social'=>'Carnes Don Pancho S.A. de C.V.','nombre_comercial'=>'Don Pancho',
                'regimen_fiscal'=>'601','uso_cfdi_default'=>'G01',
                'shipping_route_id'=>$routeCentro,'payment_type_id'=>$pagoCredito,'price_list_id'=>$plMay,
                'credito_limite'=>20000,'credito_dias'=>15,
            ],
        ];

        foreach ($rows as $r) {
            DB::table('clients')->insert(array_merge($r, ['created_at'=>$now,'updated_at'=>$now]));
        }
    }
}
