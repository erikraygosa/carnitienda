<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * sales.status es un ENUM de MySQL con lista fija — hay que agregar
     * 'COMPLETADA' (venta directa de mostrador) a esa lista o cualquier
     * insert con ese valor truena con "Data truncated for column 'status'".
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE sales MODIFY COLUMN status ENUM(
            'BORRADOR','APROBADO','ABIERTA','PREPARANDO','PROCESADA',
            'EN_RUTA','ENTREGADA','NO_ENTREGADA','CERRADA','CANCELADA','COMPLETADA'
        ) NOT NULL DEFAULT 'BORRADOR'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE sales MODIFY COLUMN status ENUM(
            'BORRADOR','APROBADO','ABIERTA','PREPARANDO','PROCESADA',
            'EN_RUTA','ENTREGADA','NO_ENTREGADA','CERRADA','CANCELADA'
        ) NOT NULL DEFAULT 'BORRADOR'");
    }
};
