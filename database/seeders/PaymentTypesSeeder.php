<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PaymentTypesSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $rows = [
            ['clave' => 'EFECTIVO',      'descripcion' => 'Pago en efectivo',          'activo' => 1],
            ['clave' => 'TRANSFERENCIA', 'descripcion' => 'Transferencia bancaria',    'activo' => 1],
            ['clave' => 'CONTRAENTREGA', 'descripcion' => 'Pago contra entrega',       'activo' => 1],
            ['clave' => 'CREDITO',       'descripcion' => 'Crédito a días pactados',   'activo' => 1],
        ];
        foreach ($rows as $r) {
            $exists = DB::table('payment_types')->where('clave', $r['clave'])->exists();
            if (!$exists) {
                DB::table('payment_types')->insert(array_merge($r, ['created_at'=>$now,'updated_at'=>$now]));
            }
        }
    }
}
