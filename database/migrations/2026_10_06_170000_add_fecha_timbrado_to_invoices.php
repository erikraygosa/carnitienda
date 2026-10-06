<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // "fecha" = fecha de elaboración (la que captura el usuario);
    // "fecha_timbrado" = fecha del CFDI ya timbrado (atributo Fecha del XML).
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dateTime('fecha_timbrado')->nullable()->after('fecha');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('fecha_timbrado');
        });
    }
};
