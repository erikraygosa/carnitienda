<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Mismo patrón que invoice_sales_orders, pero para notas de venta
     * (Sale) — necesario para poder facturar varias notas juntas en una
     * sola factura consolidada ("Facturar varios pedidos" ahora también
     * soporta Notas).
     */
    public function up(): void
    {
        Schema::create('invoice_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['invoice_id', 'sale_id']);
        });

        DB::table('invoices')
            ->whereNotNull('sale_id')
            ->select('id', 'sale_id')
            ->orderBy('id')
            ->chunk(200, function ($rows) {
                $now = now();
                DB::table('invoice_sales')->insert(
                    $rows->map(fn ($r) => [
                        'invoice_id' => $r->id,
                        'sale_id'    => $r->sale_id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all()
                );
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_sales');
    }
};
