<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'factuapi_id')) {
                // ID interno de Facturapi (distinto del UUID fiscal del SAT) —
                // lo exige el endpoint de cancelación/estatus como parámetro
                // de ruta. El driver ya lo capturaba al timbrar pero nunca se
                // guardaba, así que cancelar cualquier factura fallaba con
                // "El campo 'id' tiene un formato inválido" (se mandaba el
                // UUID del SAT en su lugar).
                $table->string('factuapi_id', 60)->nullable()->after('uuid');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'factuapi_id')) {
                $table->dropColumn('factuapi_id');
            }
        });
    }
};
