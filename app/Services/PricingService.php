<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Product;

class PricingService
{
    /**
     * Determina el mejor precio para un cliente y producto:
     * override de cliente > lista del cliente > lista general (pasada en $fallbackListId) > precio_base del producto.
     */
    public function resolvePrice(?Client $client, Product $product, ?int $fallbackListId = null): float
    {
        // 1) Override por cliente
        if ($client) {
            $ov = $client->priceOverrides()->where('product_id', $product->id)->first();
            if ($ov) return (float)$ov->precio;
            // 2) Lista del cliente
            if ($client->priceList) {
                $pli = $client->priceList->items()->where('product_id', $product->id)->first();
                if ($pli) return (float)$pli->precio;
            }
        }
        // 3) Lista general de fallback
        if ($fallbackListId) {
            $pli = \App\Models\PriceListItem::where('price_list_id', $fallbackListId)
                    ->where('product_id', $product->id)->first();
            if ($pli) return (float)$pli->precio;
        }
        // 4) Precio base
        return (float)$product->precio_base;
    }
}
