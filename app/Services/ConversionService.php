<?php

namespace App\Services;

use App\Models\Quote;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ConversionService
{
    public function quoteToSale(Quote $quote, int $posRegisterId, int $warehouseId, ?int $userId = null): Sale
    {
        return DB::transaction(function () use ($quote, $posRegisterId, $warehouseId, $userId) {
            $sale = Sale::create([
                'fecha' => Carbon::now(),
                'pos_register_id' => $posRegisterId,
                'warehouse_id' => $warehouseId,
                'client_id' => $quote->client_id,
                'payment_type_id' => null,
                'tipo_venta' => 'CONTADO',
                'subtotal' => $quote->subtotal,
                'impuestos' => $quote->impuestos,
                'descuento' => $quote->descuento,
                'total' => $quote->total,
                'status' => 'ABIERTA',
                'user_id' => $userId,
                'created_by' => $userId,
                'owner_id' => $quote->owner_id,
            ]);

            foreach ($quote->items as $qi) {
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $qi->product_id,
                    'cantidad' => $qi->cantidad,
                    'precio' => $qi->precio,
                    'descuento' => $qi->descuento,
                    'impuesto' => $qi->impuesto,
                    'total' => $qi->total,
                ]);
            }

            $quote->update(['status' => 'CONVERTIDA']);

            return $sale;
        });
    }

    public function saleToInvoice(Sale $sale, string $serie = 'A', ?int $folio = null, ?int $userId = null): Invoice
    {
        return DB::transaction(function () use ($sale, $serie, $folio, $userId) {
            $inv = Invoice::create([
                'client_id' => $sale->client_id,
                'serie' => $serie,
                'folio' => $folio,
                'fecha' => now(),
                'forma_pago' => null,
                'metodo_pago' => 'PUE',
                'uso_cfdi' => null,
                'moneda' => 'MXN',
                'subtotal' => $sale->subtotal,
                'impuestos' => $sale->impuestos,
                'total' => $sale->total,
                'estatus' => 'EMITIDA',
                'version_cfdi' => '4.0',
                'created_by' => $userId,
                'owner_id' => $sale->owner_id,
            ]);

            foreach ($sale->items as $si) {
                InvoiceItem::create([
                    'invoice_id' => $inv->id,
                    'product_id' => $si->product_id,
                    'descripcion' => $si->product ? $si->product->nombre : 'Concepto',
                    'clave_prod_serv' => null,
                    'clave_unidad' => $si->product ? $si->product->unidad : 'ACT',
                    'cantidad' => $si->cantidad,
                    'precio_unitario' => $si->precio,
                    'descuento' => $si->descuento,
                    'impuesto_trasladado' => $si->impuesto,
                    'impuesto_retenido' => 0,
                    'total' => $si->total,
                ]);
            }

            $sale->update(['status' => 'FACTURADA']);

            return $inv;
        });
    }
}
