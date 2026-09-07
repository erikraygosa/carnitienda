<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            // Mismo campo/mismo motivo que sales_order_items.presentacion:
            // reemplaza al selector de "Cajas" (número) en el formulario de
            // Notas de venta.
            $table->string('presentacion', 10)->nullable()->after('num_cajas');
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn('presentacion');
        });
    }
};
