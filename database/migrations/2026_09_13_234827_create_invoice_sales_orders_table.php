<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Antes una factura solo podía cubrir UN pedido (invoices.sales_order_id,
     * FK simple). Para poder facturar varios pedidos juntos en una sola
     * factura (consolidada / "público en general") hace falta un
     * pedido↔factura muchos-a-muchos. invoices.sales_order_id se conserva
     * tal cual para no romper nada que ya lo use (PDF, complementos, etc.) —
     * este pivote es la fuente de verdad nueva para "¿qué pedidos cubre esta
     * factura?" y para el filtro/badge de 'Facturada' en Pedidos.
     */
    public function up(): void
    {
        Schema::create('invoice_sales_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('sales_order_id')->constrained('sales_orders')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['invoice_id', 'sales_order_id']);
        });

        // Backfill: toda factura que ya traía sales_order_id directo también
        // queda reflejada en el pivote, para que el filtro/badge de
        // 'Facturada' en Pedidos no pierda ninguna factura vieja.
        DB::table('invoices')
            ->whereNotNull('sales_order_id')
            ->select('id', 'sales_order_id')
            ->orderBy('id')
            ->chunk(200, function ($rows) {
                $now = now();
                DB::table('invoice_sales_orders')->insert(
                    $rows->map(fn ($r) => [
                        'invoice_id'     => $r->id,
                        'sales_order_id' => $r->sales_order_id,
                        'created_at'     => $now,
                        'updated_at'     => $now,
                    ])->all()
                );
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_sales_orders');
    }
};
