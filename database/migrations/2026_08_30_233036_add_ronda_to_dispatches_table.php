<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Ronda" = si el despacho es la primera o segunda salida del día para
     * esa ruta (algunas rutas se recorren dos veces en el mismo día).
     * Por default 1 (primera ruta) para no romper despachos existentes.
     */
    public function up(): void
    {
        Schema::table('dispatches', function (Blueprint $table) {
            $table->unsignedTinyInteger('ronda')->default(1)->after('shipping_route_id');
        });
    }

    public function down(): void
    {
        Schema::table('dispatches', function (Blueprint $table) {
            $table->dropColumn('ronda');
        });
    }
};
