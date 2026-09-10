<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Igual que en sales_orders: saldo_pendiente permite abonos parciales a
     * una nota de venta a crédito (no solo liquidarla completa de un jalón),
     * y cobrado_at marca cuándo quedó en $0. Necesario para poder meter las
     * notas de venta a crédito al reporte de Liquidaciones, igual que a los
     * pedidos.
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('saldo_pendiente', 12, 2)->nullable()->after('total');
            $table->timestamp('cobrado_at')->nullable()->after('driver_settlement_at');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['saldo_pendiente', 'cobrado_at']);
        });
    }
};
