<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Igual que dispatches.ronda: si el pedido es de la primera o segunda
     * salida del día en su ruta (algunas rutas se recorren dos veces al
     * día). Por default 1 (primera ruta) para no romper pedidos existentes.
     */
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->unsignedTinyInteger('ronda')->default(1)->after('shipping_route_id');
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropColumn('ronda');
        });
    }
};
