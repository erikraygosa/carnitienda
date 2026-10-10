<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marca de "el inventario de este pedido ya se descontó" (en Salida de
     * producto). Evita que un pedido que vuelve a Procesado, o un doble clic en
     * "Completar surtido", descuente el inventario dos veces.
     */
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->timestamp('inventario_descontado_at')->nullable()->after('despachado_at');
        });

        // Pedidos que ya pasaron por Salida de producto (hay registro en la bitácora).
        DB::statement("
            UPDATE sales_orders so
            JOIN (
                SELECT document_id, MIN(created_at) AS cuando
                FROM document_activity_logs
                WHERE document_type = ? AND action = 'salida_de_producto'
                GROUP BY document_id
            ) l ON l.document_id = so.id
            SET so.inventario_descontado_at = l.cuando
            WHERE so.status <> 'NO_ENTREGADO'
        ", ['App\\Models\\SalesOrder']);

        // Pedidos viejos más allá de Despachado sin registro en la bitácora: también ya salieron.
        DB::statement("
            UPDATE sales_orders
            SET inventario_descontado_at = COALESCE(despachado_at, updated_at)
            WHERE inventario_descontado_at IS NULL
              AND status IN ('DESPACHADO', 'EN_RUTA', 'ENTREGADO')
        ");
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropColumn('inventario_descontado_at');
        });
    }
};
