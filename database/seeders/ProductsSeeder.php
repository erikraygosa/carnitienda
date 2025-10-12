<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\Category;

class ProductsSeeder extends Seeder
{
    public function run(): void
    {
        // Busca IDs de categorías por nombre (ajusta según tu CategorySeeder)
        $catCarnes  = Category::where('nombre', 'Carnes')->first();
        $catInsumos = Category::where('nombre', 'Insumos')->first();

        // Si no existen, usa el primero disponible para no fallar
        $fallbackCat = Category::first();

        $rows = [
            // Materias primas / principales
            [
                'sku'               => 'CAR-PIER-ENT',
                'nombre'            => 'Pierna de cerdo (entera)',
                'unidad'            => 'KG',
                'es_compuesto'      => false,
                'es_subproducto'    => false,
                'precio_base'       => 120.0000,
                'costo_promedio'    => 120.0000,
                'tasa_iva'          => 0.00,
                'stock_min'         => 5,
                'maneja_inventario' => true,
                'activo'            => true,
                'barcode'           => null,
                'notas'             => null,
                'category_id'       => optional($catCarnes ?: $fallbackCat)->id,
            ],
            [
                'sku'               => 'CAR-ESP',
                'nombre'            => 'Especias mixtas',
                'unidad'            => 'KG',
                'es_compuesto'      => false,
                'es_subproducto'    => false,
                'precio_base'       => 80.0000,
                'costo_promedio'    => 80.0000,
                'tasa_iva'          => 0.00,
                'stock_min'         => 2,
                'maneja_inventario' => true,
                'activo'            => true,
                'barcode'           => null,
                'notas'             => null,
                'category_id'       => optional($catInsumos ?: $fallbackCat)->id,
            ],

            // Productos compuestos (ej. chorizo que usa carne + especias)
            [
                'sku'               => 'CAR-CHOR',
                'nombre'            => 'Chorizo casero',
                'unidad'            => 'KG',
                'es_compuesto'      => true,
                'es_subproducto'    => false,
                'precio_base'       => 160.0000,
                'costo_promedio'    => 140.0000,
                'tasa_iva'          => 0.00,
                'stock_min'         => 5,
                'maneja_inventario' => true,
                'activo'            => true,
                'barcode'           => null,
                'notas'             => null,
                'category_id'       => optional($catCarnes ?: $fallbackCat)->id,
            ],

            // Subproductos derivados
            [
                'sku'               => 'CAR-MOLI',
                'nombre'            => 'Molida de cerdo',
                'unidad'            => 'KG',
                'es_compuesto'      => false,
                'es_subproducto'    => true,
                'precio_base'       => 140.0000,
                'costo_promedio'    => 120.0000,
                'tasa_iva'          => 0.00,
                'stock_min'         => 5,
                'maneja_inventario' => true,
                'activo'            => true,
                'barcode'           => null,
                'notas'             => null,
                'category_id'       => optional($catCarnes ?: $fallbackCat)->id,
            ],

            // Otros
            [
                'sku'               => 'BOL-BOLS',
                'nombre'            => 'Bolsas plásticas',
                'unidad'            => 'PZA',
                'es_compuesto'      => false,
                'es_subproducto'    => false,
                'precio_base'       => 0.5000,
                'costo_promedio'    => 0.5000,
                'tasa_iva'          => 0.00,
                'stock_min'         => 100,
                'maneja_inventario' => true,
                'activo'            => true,
                'barcode'           => null,
                'notas'             => null,
                'category_id'       => optional($catInsumos ?: $fallbackCat)->id,
            ],
        ];

        foreach ($rows as $r) {
            Product::updateOrCreate(
                ['sku' => $r['sku']],   // clave única
                $r                       // datos a insertar/actualizar
            );
        }
    }
}
