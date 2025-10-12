<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $rows = [
            // Materias primas / principales
            ['sku'=>'CAR-PIER-ENT','nombre'=>'Pierna de cerdo (entera)','unidad'=>'KG','es_compuesto'=>0,'es_subproducto'=>0,'precio_base'=>120.00,'stock_min'=>5,'activo'=>1],
            ['sku'=>'CAR-ESP','nombre'=>'Especias mixtas','unidad'=>'KG','es_compuesto'=>0,'es_subproducto'=>0,'precio_base'=>80.00,'stock_min'=>2,'activo'=>1],
            // Productos compuestos (ej. chorizo que usa carne + especias)
            ['sku'=>'CAR-CHOR','nombre'=>'Chorizo casero','unidad'=>'KG','es_compuesto'=>1,'es_subproducto'=>0,'precio_base'=>160.00,'stock_min'=>5,'activo'=>1],
            // Subproductos derivados (ej. molida que descuenta de la pierna entera)
            ['sku'=>'CAR-MOLI','nombre'=>'Molida de cerdo','unidad'=>'KG','es_compuesto'=>0,'es_subproducto'=>1,'precio_base'=>140.00,'stock_min'=>5,'activo'=>1],
            // Otros
            ['sku'=>'BOL-BOLS','nombre'=>'Bolsas plásticas','unidad'=>'PZA','es_compuesto'=>0,'es_subproducto'=>0,'precio_base'=>0.50,'stock_min'=>100,'activo'=>1],
        ];
        foreach ($rows as $r) {
            DB::table('products')->insert(array_merge($r, ['created_at'=>$now,'updated_at'=>$now]));
        }
    }
}
