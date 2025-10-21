<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Ampliamos el ENUM para incluir todo el flujo nuevo
        // (BORRADOR, APROBADO, PREPARANDO, PROCESADO, DESPACHADO, EN_RUTA, ENTREGADO, NO_ENTREGADO, CANCELADO)
        DB::statement("
            ALTER TABLE `sales_orders`
            MODIFY `status` ENUM(
                'BORRADOR',
                'APROBADO',
                'PREPARANDO',
                'PROCESADO',
                'DESPACHADO',
                'EN_RUTA',
                'ENTREGADO',
                'NO_ENTREGADO',
                'CANCELADO'
            ) NOT NULL DEFAULT 'BORRADOR'
        ");
    }

    public function down(): void
    {
        // Revertimos al conjunto clásico (ajústalo si tu esquema anterior era distinto)
        DB::statement("
            ALTER TABLE `sales_orders`
            MODIFY `status` ENUM(
                'BORRADOR',
                'APROBADO',
                'PROCESADO',
                'DESPACHADO',
                'ENTREGADO',
                'CANCELADO'
            ) NOT NULL DEFAULT 'BORRADOR'
        ");
    }
};
