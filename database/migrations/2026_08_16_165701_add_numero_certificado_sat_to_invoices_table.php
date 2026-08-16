<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Columna que Invoice::$fillable y PacCfdiService::stamp() ya usaban,
     * pero que nunca tuvo una migración propia (existía en local por un
     * ALTER manual anterior) — por eso producción tronaba con "Column not
     * found: numero_certificado_sat" al timbrar.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'numero_certificado_sat')) {
                $table->string('numero_certificado_sat', 60)->nullable()->after('sello_sat');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'numero_certificado_sat')) {
                $table->dropColumn('numero_certificado_sat');
            }
        });
    }
};
