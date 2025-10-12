<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class PricingService
{
    public static function priceFor(int $productId, int $clientId, ?int $priceListId, ?string &$source = null): float
    {
        // 1) Override por cliente
        $override = DB::table('client_price_overrides')
            ->where('client_id', $clientId)
            ->where('product_id', $productId)
            ->value('precio');
        if ($override !== null) { $source = 'override'; return (float) $override; }

        // 2) Lista del cliente
        if ($priceListId) {
            $listPrice = DB::table('price_list_items')
                ->where('price_list_id', $priceListId)
                ->where('product_id', $productId)
                ->value('precio');
            if ($listPrice !== null) { $source = 'price_list'; return (float) $listPrice; }
        }

        // 3) Precio base del producto
        $base = DB::table('products')->where('id', $productId)->value('precio_base') ?? 0;
        $source = 'base';
        return (float) $base;
    }
}
