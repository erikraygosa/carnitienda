<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Permite guardar varios correos del cliente en el mismo campo (ventas, cobranza, contabilidad...).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('email', 500)->nullable()->change();
        });
    }

    public function down(): void
    {
        // No se reduce de vuelta: podría truncar listas de correos ya guardadas.
    }
};
