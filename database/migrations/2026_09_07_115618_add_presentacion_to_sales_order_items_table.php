<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_order_items', function (Blueprint $table) {
            // Presentación en que se pidió la partida: KILOS, PIEZAS, CAJAS,
            // o null ("—", ninguna). Reemplaza al selector de "Cajas" en el
            // formulario de Pedidos — el num_cajas numérico (referencia para
            // Salida de Producto/etiquetas) se deja de capturar ahí, queda
            // en null para pedidos nuevos.
            $table->string('presentacion', 10)->nullable()->after('num_cajas');
        });
    }

    public function down(): void
    {
        Schema::table('sales_order_items', function (Blueprint $table) {
            $table->dropColumn('presentacion');
        });
    }
};
