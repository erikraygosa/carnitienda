<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_transfer_items', function (Blueprint $table) {
            if (!Schema::hasColumn('stock_transfer_items', 'num_cajas')) {
                $table->unsignedSmallInteger('num_cajas')->nullable()->after('qty')
                      ->comment('Número aproximado de cajas (referencia)');
            }
            // 'comentarios' ya existe desde la migración original de la tabla en algunos entornos;
            // se agrega aquí solo si de verdad falta.
            if (!Schema::hasColumn('stock_transfer_items', 'comentarios')) {
                $table->string('comentarios', 200)->nullable()->after('num_cajas');
            }
        });
    }

    public function down(): void
    {
        Schema::table('stock_transfer_items', function (Blueprint $table) {
            if (Schema::hasColumn('stock_transfer_items', 'num_cajas')) {
                $table->dropColumn('num_cajas');
            }
        });
    }
};
