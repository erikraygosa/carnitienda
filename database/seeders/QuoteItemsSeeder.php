<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class QuoteItemsSeeder extends Seeder
{
    public function run(): void
    {
        $quote   = DB::table('quotes')->first();
        $product = DB::table('products')->first();

        if (!$quote || !$product) return;

        // Evitar duplicado por (quote_id, product_id)
        $exists = DB::table('quote_items')
            ->where('quote_id', $quote->id)
            ->where('product_id', $product->id)
            ->exists();

        if ($exists) return;

        $qty     = 1;
        $price   = (float) ($product->precio_base ?? 0);
        $disc    = 0.0;
        $taxRate = (float) ($product->tasa_iva ?? 0); // 0–100
        $base    = round($qty * $price - $disc, 4);
        $tax     = round($base * ($taxRate / 100), 4);
        $total   = round($base + $tax, 4);
        $now     = now();

        DB::table('quote_items')->insert([
            'quote_id'    => $quote->id,
            'product_id'  => $product->id,
            'descripcion' => $product->nombre ?? 'Producto', // <-- requerido por tu esquema
            'cantidad'    => $qty,
            'precio'      => $price,     // unitario
            'descuento'   => $disc,      // monto
            'impuesto'    => $tax,       // monto
            'total'       => $total,     // total línea
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);
    }
}
