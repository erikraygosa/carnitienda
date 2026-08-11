<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dispatches', function (Blueprint $table) {
            // Cierre operativo: traspasos + pedidos a crédito (no efectivo/contraentrega).
            $table->timestamp('traspasos_cerrado_at')->nullable()->after('cerrado_at');
            // Cierre de cobranza: pedidos efectivo/contraentrega + CxC + registro en caja.
            $table->timestamp('cobranza_cerrado_at')->nullable()->after('traspasos_cerrado_at');
        });
    }

    public function down(): void
    {
        Schema::table('dispatches', function (Blueprint $table) {
            $table->dropColumn(['traspasos_cerrado_at', 'cobranza_cerrado_at']);
        });
    }
};
