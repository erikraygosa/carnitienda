<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_order_items', function (Blueprint $table) {
            // Array JSON con el peso (kg) de cada caja individual, ej. [12.5, 11.8, 13.2]
            // — el índice del array corresponde a "caja N de num_cajas". Solo se
            // usa cuando SuperAdmin tiene activo "Imprimir etiquetas por cajas";
            // si num_cajas se reduce después, el array puede quedar más largo de
            // lo necesario, no se recorta automáticamente.
            $table->json('pesos_cajas')->nullable()->after('num_cajas')
                  ->comment('Peso en kg de cada caja individual, para la etiqueta ZPL por caja');
        });
    }

    public function down(): void
    {
        Schema::table('sales_order_items', function (Blueprint $table) {
            $table->dropColumn('pesos_cajas');
        });
    }
};
