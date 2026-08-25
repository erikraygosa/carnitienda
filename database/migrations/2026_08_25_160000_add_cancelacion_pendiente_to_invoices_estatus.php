<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega 'CANCELACION_PENDIENTE' al ENUM de estatus de facturas.
     *
     * El SAT/PAC no siempre cancela un CFDI de inmediato: cuando el motivo o
     * el monto lo requieren, el receptor debe aceptar/rechazar la cancelación
     * (hasta 72h). Antes de este cambio el sistema marcaba la factura como
     * CANCELADA en cuanto Facturapi aceptaba la solicitud, aunque en el SAT
     * siguiera vigente pendiente de aceptación.
     */
    public function up(): void
    {
        if (Schema::hasColumn('invoices', 'estatus')) {
            DB::statement("
                ALTER TABLE `invoices`
                MODIFY `estatus` ENUM('BORRADOR','TIMBRADA','ENVIADA','CANCELACION_PENDIENTE','CANCELADA')
                NOT NULL DEFAULT 'BORRADOR'
            ");
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('invoices', 'estatus')) {
            // Antes de quitar el valor del ENUM, regresa cualquier factura en
            // ese estado a TIMBRADA para no dejar filas con un valor inválido.
            DB::statement("
                UPDATE `invoices` SET `estatus` = 'TIMBRADA' WHERE `estatus` = 'CANCELACION_PENDIENTE'
            ");

            DB::statement("
                ALTER TABLE `invoices`
                MODIFY `estatus` ENUM('BORRADOR','TIMBRADA','ENVIADA','CANCELADA')
                NOT NULL DEFAULT 'BORRADOR'
            ");
        }
    }
};
