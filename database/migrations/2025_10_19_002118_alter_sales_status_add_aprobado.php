<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE `sales`
            MODIFY `status` ENUM(
                'BORRADOR',
                'APROBADO',
                'ABIERTA',
                'PREPARANDO',
                'PROCESADA',
                'EN_RUTA',
                'ENTREGADA',
                'NO_ENTREGADA',
                'CERRADA',
                'CANCELADA'
            ) NOT NULL DEFAULT 'BORRADOR'
        ");
    }

    public function down(): void
    {
        // Quita APROBADO si vuelves atrás
        DB::statement("
            ALTER TABLE `sales`
            MODIFY `status` ENUM(
                'BORRADOR',
                'ABIERTA',
                'PREPARANDO',
                'PROCESADA',
                'EN_RUTA',
                'ENTREGADA',
                'NO_ENTREGADA',
                'CERRADA',
                'CANCELADA'
            ) NOT NULL DEFAULT 'BORRADOR'
        ");
    }
};
