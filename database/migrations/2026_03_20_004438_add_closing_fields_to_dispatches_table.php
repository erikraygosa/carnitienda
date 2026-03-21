<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dispatches', function (Blueprint $table) {
            $table->timestamp('en_ruta_at')->nullable()->after('status');
            $table->timestamp('cerrado_at')->nullable()->after('en_ruta_at');
            $table->decimal('monto_liquidado', 12, 2)->nullable()->after('cerrado_at');
            $table->string('notas_cierre')->nullable()->after('monto_liquidado');
        });
    }

    public function down(): void
    {
        Schema::table('dispatches', function (Blueprint $table) {
            $table->dropColumn(['en_ruta_at','cerrado_at','monto_liquidado','notas_cierre']);
        });
    }
};