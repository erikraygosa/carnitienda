<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La migración "add_unidad_to_invoice_items_table" nunca agregó realmente
     * la columna `unidad` (solo agregó valor_unitario, base, objeto_imp, etc.).
     * La columna existía en el entorno local porque se creó manualmente ahí,
     * pero nunca llegó a producción -> "Column not found: 1054 unidad" al
     * crear una factura. Esta migración la agrega de forma idempotente.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('invoice_items', 'unidad')) {
            Schema::table('invoice_items', function (Blueprint $table) {
                $table->string('unidad', 20)->nullable()->after('clave_unidad');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('invoice_items', 'unidad')) {
            Schema::table('invoice_items', function (Blueprint $table) {
                $table->dropColumn('unidad');
            });
        }
    }
};
