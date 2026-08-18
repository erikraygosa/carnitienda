<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Las notas de venta directas (mostrador) se amarran a la caja real del
     * sistema (cash_registers, la misma que usan Cajas/POS/cortes), no a
     * pos_register_id — ese campo apuntaba a un modelo casi sin uso que no
     * se conecta con nada del módulo de Cajas.
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('cash_register_id')->nullable()->after('pos_register_id')
                ->constrained('cash_registers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cash_register_id');
        });
    }
};
