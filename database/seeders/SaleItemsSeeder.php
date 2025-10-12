<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SaleItemsSeeder extends Seeder
{
    public function run(): void
    {
        $sale    = DB::table('sales')->first();
        $product = DB::table('products')->first();

        if (!$sale || !$product) {
            // No hay venta o producto para asociar; salimos sin error.
            return;
        }

        // Evitar duplicar el mismo item de esa venta
        $exists = DB::table('sale_items')
            ->where('sale_id', $sale->id)
            ->where('product_id', $product->id)
            ->exists();

        if ($exists) {
            return;
        }

        $qty      = 1;
        $price    = (float) ($product->precio_base ?? 0);      // unitario
        $disc     = 0.0;                                       // sin descuento
        $taxRate  = (float) ($product->tasa_iva ?? 0);         // 0–100
        $base     = round($qty * $price - $disc, 4);           // base imponible
        $tax      = round($base * ($taxRate / 100), 4);        // impuesto
        $total    = round($base + $tax, 4);

        $now = now();

        DB::table('sale_items')->insert([
            'sale_id'    => $sale->id,
            'product_id' => $product->id,
            'cantidad'   => $qty,
            'precio'     => $price,     // unitario
            'descuento'  => $disc,      // monto de descuento
            'impuesto'   => $tax,       // monto de impuesto
            'total'      => $total,     // total línea
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
