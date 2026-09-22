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
        Schema::table('stock_transfer_items', function (Blueprint $table) {
            // Mismo campo/mismo motivo que sales_order_items.presentacion:
            // reemplaza el campo numérico de Cajas en la captura por un
            // selector Kilos/Piezas/Cajas, igual que en pedidos.
            $table->string('presentacion', 10)->nullable()->after('num_cajas');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_transfer_items', function (Blueprint $table) {
            $table->dropColumn('presentacion');
        });
    }
};
