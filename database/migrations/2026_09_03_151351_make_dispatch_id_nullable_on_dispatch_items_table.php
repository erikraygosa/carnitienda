<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Permite "quitar" un pedido ya surtido de un despacho sin perder sus
     * líneas surtidas — se desasigna (dispatch_id = null) en vez de
     * borrarse, para que quede libre y se pueda asignar a cualquier otro
     * despacho después sin volver a pasar por Salida de Producto.
     */
    public function up(): void
    {
        Schema::table('dispatch_items', function (Blueprint $table) {
            $table->foreignId('dispatch_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('dispatch_items', function (Blueprint $table) {
            $table->foreignId('dispatch_id')->nullable(false)->change();
        });
    }
};
