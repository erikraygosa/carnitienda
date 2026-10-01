<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            // Igual que en sales_orders: ruta/ronda opcionales en captura,
            // para que el panel de rutas (y la auto-asignación de despachos,
            // ver AutoDespachoService) también pueda ubicar un traspaso sin
            // tener que esperar a asignarlo a mano desde /admin/dispatches.
            $table->foreignId('shipping_route_id')->nullable()->after('to_warehouse_id')
                ->constrained('shipping_routes')->nullOnDelete();
            $table->unsignedTinyInteger('ronda')->nullable()->after('shipping_route_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shipping_route_id');
            $table->dropColumn('ronda');
        });
    }
};
